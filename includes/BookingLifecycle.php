<?php
/**
 * Sponsor booking schedule state and due transitions.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingLifecycle {
    public const HOOK = 'mds_sponsorboard_process_lifecycle';

    private const LOCK_OPTION = 'mds_sponsorboard_lifecycle_lock';
    private const THROTTLE_TRANSIENT = 'mds_sponsorboard_lifecycle_throttle';
    private const OPEN_STATUSES = ['requested', 'pending_payment', 'paid', 'approved', 'changes_requested', 'active'];
    private const PAID_STATUSES = ['paid', 'approved', 'active'];

    public static function register(): void {
        add_action(self::HOOK, [self::class, 'run']);
        add_action('init', [self::class, 'schedule'], 20);
        add_action('init', [self::class, 'maybe_process'], 99);
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK);
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::HOOK);
        delete_transient(self::THROTTLE_TRANSIENT);
        delete_option(self::LOCK_OPTION);
    }

    public static function maybe_process(): void {
        if (get_transient(self::THROTTLE_TRANSIENT)) {
            return;
        }

        set_transient(self::THROTTLE_TRANSIENT, '1', 5 * MINUTE_IN_SECONDS);
        self::run(50);
    }

    /**
     * Process a bounded set of due starts and ends.
     *
     * @param int      $limit Maximum bookings to process.
     * @param int|null $now   Optional Unix timestamp for deterministic tests.
     * @return array{activated:int,expired:int,processed:int,locked:bool}
     */
    public static function run($limit = 100, $now = null): array {
        $limit = min(200, max(1, absint($limit)));
        $now = null === $now ? time() : absint($now);
        $token = wp_generate_uuid4();
        if (!self::acquire_lock($token, $now)) {
            return ['activated' => 0, 'expired' => 0, 'processed' => 0, 'locked' => true];
        }

        $result = ['activated' => 0, 'expired' => 0, 'processed' => 0, 'locked' => false];
        try {
            $bookings = Repository::lifecycle_due_bookings($limit, $now);
            foreach ($bookings as $booking) {
                $status = sanitize_key((string) ($booking['status'] ?? ''));
                $state = self::state($booking, $now);
                $next_status = '';
                $audit_action = '';
                $audit_message = '';

                if ('expired' === $state && in_array($status, self::OPEN_STATUSES, true)) {
                    $next_status = 'expired';
                    $audit_action = 'lifecycle_expired';
                    $audit_message = __('Sponsor booking reached its scheduled end.', 'mds-sponsorboard');
                } elseif ('active' === $state && 'approved' === $status && self::creative_is_approved($booking)) {
                    $next_status = 'active';
                    $audit_action = 'lifecycle_activated';
                    $audit_message = __('Sponsor booking reached its scheduled start.', 'mds-sponsorboard');
                }

                if ('' === $next_status) {
                    continue;
                }

                $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
                $metadata['lifecycle_previous_status'] = $status;
                if (in_array($status, self::PAID_STATUSES, true) || !empty($metadata['lifecycle_was_paid']) || !empty($booking['order_id'])) {
                    $metadata['lifecycle_was_paid'] = true;
                }
                $metadata['lifecycle_' . ('active' === $next_status ? 'activated' : 'expired') . '_at'] = gmdate('Y-m-d H:i:s', $now);

                $updated = Repository::update_booking(absint($booking['id'] ?? 0), array_merge($booking, [
                    'status' => $next_status,
                    'metadata' => $metadata,
                ]));
                if (!is_array($updated)) {
                    continue;
                }

                Repository::refresh_slot_status(absint($updated['slot_id'] ?? 0), $now);
                Repository::audit('booking', absint($updated['id'] ?? 0), $audit_action, $audit_message, [
                    'previous_status' => $status,
                    'status' => $next_status,
                ]);
                Notifications::booking_updated($updated);
                HostedClient::sync_booking($updated);
                $result['processed']++;
                $result['active' === $next_status ? 'activated' : 'expired']++;
            }
        } finally {
            self::release_lock($token);
        }

        return $result;
    }

    public static function state(array $booking, $now = null): string {
        $now = null === $now ? time() : absint($now);
        $status = sanitize_key((string) ($booking['status'] ?? 'requested'));
        if (in_array($status, ['rejected', 'cancelled', 'expired'], true)) {
            return $status;
        }

        $ends_at = self::timestamp((string) ($booking['ends_at'] ?? ''));
        if ($ends_at && $ends_at <= $now) {
            return 'expired';
        }

        $starts_at = self::timestamp((string) ($booking['starts_at'] ?? ''));
        if ($starts_at && $starts_at > $now) {
            return 'scheduled';
        }

        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        if (!empty($metadata['campaign_scheduler_hidden']) && in_array($status, ['approved', 'active'], true)) {
            return 'paused';
        }

        if (in_array($status, ['approved', 'active'], true) && self::creative_is_approved($booking)) {
            return 'active';
        }

        return $status;
    }

    public static function is_public(array $booking, $now = null): bool {
        return 'active' === self::state($booking, $now)
            && self::retains_approved_creative($booking, $now);
    }

    /**
     * Whether approved creative should remain available for a current or future placement.
     */
    public static function retains_approved_creative(array $booking, $now = null): bool {
        $now = null === $now ? time() : absint($now);
        $status = sanitize_key((string) ($booking['status'] ?? ''));
        $ends_at = self::timestamp((string) ($booking['ends_at'] ?? ''));

        return in_array($status, ['approved', 'active'], true)
            && self::creative_is_approved($booking)
            && (!$ends_at || $ends_at > $now);
    }

    public static function label(array $booking, $now = null): string {
        $labels = [
            'scheduled' => __('Scheduled', 'mds-sponsorboard'),
            'active' => __('Active now', 'mds-sponsorboard'),
            'paused' => __('Paused by Campaign Scheduler', 'mds-sponsorboard'),
            'expired' => __('Ended', 'mds-sponsorboard'),
            'requested' => __('Requested', 'mds-sponsorboard'),
            'pending_payment' => __('Pending payment', 'mds-sponsorboard'),
            'paid' => __('Paid - awaiting approval', 'mds-sponsorboard'),
            'approved' => __('Approved - awaiting creative', 'mds-sponsorboard'),
            'changes_requested' => __('Changes requested', 'mds-sponsorboard'),
            'rejected' => __('Rejected', 'mds-sponsorboard'),
            'cancelled' => __('Cancelled', 'mds-sponsorboard'),
        ];
        $state = self::state($booking, $now);

        return $labels[$state] ?? ucfirst(str_replace('_', ' ', $state));
    }

    /**
     * Validate a pair of normalized UTC SQL datetimes.
     *
     * @return true|\WP_Error
     */
    public static function validate_window($starts_at, $ends_at) {
        $start = self::timestamp((string) $starts_at);
        $end = self::timestamp((string) $ends_at);
        if ('' !== trim((string) $starts_at) && !$start) {
            return new \WP_Error('mds_sponsorboard_invalid_start', __('Enter a valid booking start date and time.', 'mds-sponsorboard'));
        }
        if ('' !== trim((string) $ends_at) && !$end) {
            return new \WP_Error('mds_sponsorboard_invalid_end', __('Enter a valid booking end date and time.', 'mds-sponsorboard'));
        }
        if ($start && $end && $end <= $start) {
            return new \WP_Error('mds_sponsorboard_invalid_window', __('The booking end must be later than its start.', 'mds-sponsorboard'));
        }

        return true;
    }

    /**
     * Convert a datetime-local value in the WordPress timezone to UTC storage.
     *
     * @return string|\WP_Error
     */
    public static function from_site_input($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            return new \WP_Error('mds_sponsorboard_invalid_datetime', __('Enter a valid date and time.', 'mds-sponsorboard'));
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function to_site_input($value): string {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if (!$date) {
            return '';
        }

        return $date->setTimezone(wp_timezone())->format('Y-m-d\TH:i');
    }

    private static function creative_is_approved(array $booking): bool {
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];

        return 'approved' === sanitize_key((string) ($creative['creative_status'] ?? ''));
    }

    private static function timestamp($value): int {
        $value = trim((string) $value);
        if ('' === $value) {
            return 0;
        }

        $timestamp = strtotime($value . (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value) ? '' : ' UTC'));

        return $timestamp ? (int) $timestamp : 0;
    }

    private static function acquire_lock($token, $now): bool {
        $lock = ['token' => (string) $token, 'expires' => absint($now) + 5 * MINUTE_IN_SECONDS];
        if (add_option(self::LOCK_OPTION, $lock, '', false)) {
            return true;
        }

        $existing = get_option(self::LOCK_OPTION, []);
        if (is_array($existing) && absint($existing['expires'] ?? 0) >= absint($now)) {
            return false;
        }

        delete_option(self::LOCK_OPTION);

        return add_option(self::LOCK_OPTION, $lock, '', false);
    }

    private static function release_lock($token): void {
        $lock = get_option(self::LOCK_OPTION, []);
        if (is_array($lock) && hash_equals((string) ($lock['token'] ?? ''), (string) $token)) {
            delete_option(self::LOCK_OPTION);
        }
    }
}
