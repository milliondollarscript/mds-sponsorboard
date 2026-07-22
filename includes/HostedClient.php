<?php
/**
 * Optional SponsorBoard hosted service client.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Synchronizes local SponsorBoard inventory with the hosted API when enabled.
 */
class HostedClient {

    private const OPTION_ENABLED = 'mds_sponsorboard_hosted_enabled';
    private const OPTION_EVENTS_ENABLED = 'mds_sponsorboard_hosted_events_enabled';
    private const OPTION_API_URL = 'mds_sponsorboard_hosted_api_url';
    private const OPTION_API_KEY = 'mds_sponsorboard_hosted_api_key';
    private const OPTION_SITE_ID = 'mds_sponsorboard_hosted_site_id';
    private const OPTION_LAST_SYNC = 'mds_sponsorboard_hosted_last_sync';
    private const OPTION_LAST_ERROR = 'mds_sponsorboard_hosted_last_error';

    /**
     * Return current hosted-service settings for admin display and sync checks.
     *
     * @return array
     */
    public static function settings() {
        $api_key = (string) get_option(self::OPTION_API_KEY, '');

        return [
            'enabled' => '1' === (string) get_option(self::OPTION_ENABLED, '0'),
            'events_enabled' => '1' === (string) get_option(self::OPTION_EVENTS_ENABLED, '0'),
            'api_url' => untrailingslashit((string) get_option(self::OPTION_API_URL, '')),
            'api_key_set' => '' !== $api_key,
            'site_id' => sanitize_text_field((string) get_option(self::OPTION_SITE_ID, '')),
            'last_sync' => sanitize_text_field((string) get_option(self::OPTION_LAST_SYNC, '')),
            'last_error' => sanitize_text_field((string) get_option(self::OPTION_LAST_ERROR, '')),
        ];
    }

    /**
     * Save hosted-service settings from a trusted admin POST body.
     *
     * @param array $input Raw input.
     * @return array|\WP_Error Updated settings or error.
     */
    public static function save_settings(array $input) {
        $api_url = self::sanitize_api_url(wp_unslash($input['hosted_api_url'] ?? ''));
        if (!empty($input['hosted_enabled']) && '' === $api_url) {
            return new \WP_Error('mds_sponsorboard_hosted_missing_url', __('Enter the hosted SponsorBoard API URL before enabling sync.', 'mds-sponsorboard'));
        }

        update_option(self::OPTION_ENABLED, !empty($input['hosted_enabled']) ? '1' : '0', false);
        update_option(self::OPTION_EVENTS_ENABLED, !empty($input['hosted_events_enabled']) ? '1' : '0', false);
        update_option(self::OPTION_API_URL, $api_url, false);

        if (!empty($input['hosted_clear_api_key'])) {
            delete_option(self::OPTION_API_KEY);
            delete_option(self::OPTION_SITE_ID);
        } else {
            $api_key = sanitize_text_field(wp_unslash($input['hosted_api_key'] ?? ''));
            if ('' !== $api_key) {
                update_option(self::OPTION_API_KEY, $api_key, false);
            }
        }

        return self::settings();
    }

    /**
     * Whether hosted sync has enough configuration to make authenticated calls.
     *
     * @return bool
     */
    public static function configured() {
        $settings = self::settings();

        return !empty($settings['enabled']) && '' !== $settings['api_url'] && !empty($settings['api_key_set']);
    }

    /**
     * Test the hosted connection by upserting this site.
     *
     * @return array|\WP_Error
     */
    public static function test_connection() {
        return self::ensure_site(true);
    }

    /**
     * Synchronize a board row.
     *
     * @param array $board Local board.
     * @return array|bool|\WP_Error
     */
    public static function sync_board(array $board) {
        if (!self::configured()) {
            return true;
        }

        $site = self::ensure_site();
        if (is_wp_error($site)) {
            return self::record_error($site);
        }

        $board_id = absint($board['id'] ?? 0);
        if (!$board_id) {
            return true;
        }

        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        $response = self::request_json('POST', '/api/admin/sponsorboard/boards', [
            'siteId' => sanitize_text_field((string) ($site['id'] ?? '')),
            'externalId' => 'wp-board-' . $board_id,
            'slug' => sanitize_title((string) ($board['slug'] ?? ('board-' . $board_id))),
            'title' => sanitize_text_field((string) ($board['title'] ?? __('Sponsor Board', 'mds-sponsorboard'))),
            'layout' => sanitize_key((string) ($board['layout'] ?? 'grid')),
            'status' => 'active' === sanitize_key((string) ($board['status'] ?? 'draft')) ? 'active' : 'archived',
            'settings' => [
                'source' => 'wordpress',
                'localBoardId' => $board_id,
                'description' => wp_strip_all_tags((string) ($board['description'] ?? '')),
                'columns' => absint($settings['columns'] ?? 0),
                'publicUrl' => home_url('/'),
            ],
        ], true, 8);

        if (is_wp_error($response)) {
            return self::record_error($response);
        }

        $remote = is_array($response['board'] ?? null) ? $response['board'] : [];
        if (!empty($remote['id'])) {
            $settings['hosted_service'] = [
                'site_id' => sanitize_text_field((string) ($site['id'] ?? '')),
                'board_id' => sanitize_text_field((string) $remote['id']),
                'synced_at' => current_time('mysql'),
            ];
            Repository::update_board($board_id, array_merge($board, ['settings' => $settings]));
            self::record_success();
        }

        return $remote;
    }

    /**
     * Synchronize a slot row.
     *
     * @param array $slot Local slot.
     * @return array|bool|\WP_Error
     */
    public static function sync_slot(array $slot) {
        if (!self::configured()) {
            return true;
        }

        $slot_id = absint($slot['id'] ?? 0);
        $board = Repository::find_board(absint($slot['board_id'] ?? 0));
        if (!$slot_id || !$board) {
            return true;
        }

        $remote_board_id = self::remote_board_id($board);
        if (!$remote_board_id) {
            $remote_board = self::sync_board($board);
            if (is_wp_error($remote_board)) {
                return $remote_board;
            }
            $remote_board_id = sanitize_text_field((string) ($remote_board['id'] ?? ''));
        }

        if (!$remote_board_id) {
            return true;
        }

        $metadata = is_array($slot['metadata'] ?? null) ? $slot['metadata'] : [];
        $response = self::request_json('POST', '/api/admin/sponsorboard/slots', [
            'boardId' => $remote_board_id,
            'externalId' => 'wp-slot-' . $slot_id,
            'slotKey' => sanitize_title((string) ($slot['slug'] ?? ('slot-' . $slot_id))),
            'label' => sanitize_text_field((string) ($slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))),
            'status' => self::remote_slot_status((string) ($slot['status'] ?? 'available')),
            'priceAmount' => max(0, (float) ($slot['price'] ?? 0)),
            'currency' => Repository::effective_currency($slot['currency'] ?? self::default_currency()),
            'durationDays' => max(1, absint($metadata['duration_days'] ?? 30)),
            'positionConfig' => [
                'position' => absint($slot['position'] ?? 0),
            ],
            'constraints' => [
                'creativeType' => sanitize_key((string) ($slot['creative_type'] ?? 'image')),
                'dimensions' => sanitize_text_field((string) ($slot['dimensions'] ?? '')),
            ],
        ], true, 8);

        if (is_wp_error($response)) {
            return self::record_error($response);
        }

        $remote = is_array($response['slot'] ?? null) ? $response['slot'] : [];
        if (!empty($remote['id'])) {
            $metadata['hosted_service'] = [
                'board_id' => $remote_board_id,
                'slot_id' => sanitize_text_field((string) $remote['id']),
                'synced_at' => current_time('mysql'),
            ];
            Repository::update_slot($slot_id, array_merge($slot, ['metadata' => $metadata]));
            self::record_success();
        }

        return $remote;
    }

    /**
     * Synchronize a booking row.
     *
     * @param array $booking Local booking.
     * @return array|bool|\WP_Error
     */
    public static function sync_booking(array $booking) {
        if (!self::configured()) {
            return true;
        }

        $booking_id = absint($booking['id'] ?? 0);
        $board = Repository::find_board(absint($booking['board_id'] ?? 0));
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        if (!$booking_id || !$board || !$slot) {
            return true;
        }

        $remote_board_id = self::remote_board_id($board);
        if (!$remote_board_id) {
            $remote_board = self::sync_board($board);
            if (is_wp_error($remote_board)) {
                return $remote_board;
            }
            $remote_board_id = sanitize_text_field((string) ($remote_board['id'] ?? ''));
        }

        $remote_slot_id = self::remote_slot_id($slot);
        if (!$remote_slot_id) {
            $remote_slot = self::sync_slot($slot);
            if (is_wp_error($remote_slot)) {
                return $remote_slot;
            }
            $remote_slot_id = sanitize_text_field((string) ($remote_slot['id'] ?? ''));
        }

        if (!$remote_board_id || !$remote_slot_id) {
            return true;
        }

        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $response = self::request_json('POST', '/api/admin/sponsorboard/bookings', [
            'boardId' => $remote_board_id,
            'slotId' => $remote_slot_id,
            'externalId' => 'wp-booking-' . $booking_id,
            'sponsorName' => sanitize_text_field((string) ($booking['sponsor_name'] ?? '')),
            'sponsorEmail' => sanitize_email((string) ($booking['sponsor_email'] ?? '')),
            'status' => self::remote_booking_status((string) ($booking['status'] ?? 'requested')),
            'paymentStatus' => self::remote_payment_status((string) ($booking['status'] ?? 'requested'), $booking),
            'amount' => max(0, (float) ($slot['price'] ?? 0)),
            'currency' => Repository::effective_currency($slot['currency'] ?? $board['currency'] ?? self::default_currency()),
            'startsAt' => self::rfc3339((string) ($booking['starts_at'] ?? '')),
            'endsAt' => self::rfc3339((string) ($booking['ends_at'] ?? '')),
            'creative' => [
                'sponsorUrl' => esc_url_raw((string) ($booking['sponsor_url'] ?? '')),
                'creativeUrl' => esc_url_raw((string) ($creative['creative_url'] ?? '')),
                'creativeStatus' => sanitize_key((string) ($creative['creative_status'] ?? 'pending_review')),
            ],
            'settings' => [
                'source' => 'wordpress',
                'localBookingId' => $booking_id,
                'localSlotId' => absint($slot['id'] ?? 0),
                'localBoardId' => absint($board['id'] ?? 0),
            ],
        ], true, 8);

        if (is_wp_error($response)) {
            return self::record_error($response);
        }

        $remote = is_array($response['booking'] ?? null) ? $response['booking'] : [];
        if (!empty($remote['id'])) {
            $metadata['hosted_service'] = [
                'board_id' => $remote_board_id,
                'slot_id' => $remote_slot_id,
                'booking_id' => sanitize_text_field((string) $remote['id']),
                'synced_at' => current_time('mysql'),
            ];
            Repository::update_booking($booking_id, array_merge($booking, ['metadata' => $metadata]));
            self::record_success();
        }

        return $remote;
    }

    /**
     * Mirror an analytics event to the hosted aggregate API.
     *
     * @param array $event Local event payload.
     * @return bool|\WP_Error
     */
    public static function record_event(array $event) {
        $settings = self::settings();
        if (empty($settings['enabled']) || empty($settings['events_enabled']) || '' === $settings['api_url']) {
            return true;
        }

        $board = Repository::find_board(absint($event['board_id'] ?? 0));
        $slot = Repository::find_slot(absint($event['slot_id'] ?? 0));
        $booking = Repository::find_booking(absint($event['booking_id'] ?? 0));
        $remote_board_id = $board ? self::remote_board_id($board) : '';
        $remote_slot_id = $slot ? self::remote_slot_id($slot) : '';
        $remote_booking_id = $booking ? self::remote_booking_id($booking) : '';

        if (!$remote_board_id || !$remote_slot_id || !$remote_booking_id) {
            if ($booking) {
                $remote_booking = self::sync_booking($booking);
                if (is_wp_error($remote_booking)) {
                    return $remote_booking;
                }
                $remote_board_id = sanitize_text_field((string) ($remote_booking['boardId'] ?? $remote_board_id));
                $remote_slot_id = sanitize_text_field((string) ($remote_booking['slotId'] ?? $remote_slot_id));
                $remote_booking_id = sanitize_text_field((string) ($remote_booking['id'] ?? $remote_booking_id));
            }
        }

        if (!$remote_board_id) {
            return true;
        }

        $response = self::request_json('POST', '/api/sponsorboard/events', [
            'boardId' => $remote_board_id,
            'slotId' => $remote_slot_id,
            'bookingId' => $remote_booking_id,
            'eventType' => sanitize_key((string) ($event['event_type'] ?? '')),
            'count' => max(1, absint($event['event_value'] ?? 1)),
            'occurredAt' => gmdate('c'),
        ], false, 3);

        return is_wp_error($response) ? self::record_error($response) : true;
    }

    /**
     * Upsert this WordPress site in the hosted service.
     *
     * @param bool $require_config Whether to require enabled/auth configuration.
     * @return array|\WP_Error
     */
    private static function ensure_site($require_config = false) {
        $settings = self::settings();
        if ($require_config && ('' === $settings['api_url'] || empty($settings['api_key_set']))) {
            return new \WP_Error('mds_sponsorboard_hosted_not_configured', __('Enter the hosted SponsorBoard API URL and API key first.', 'mds-sponsorboard'));
        }

        if (!self::configured() && !$require_config) {
            return new \WP_Error('mds_sponsorboard_hosted_disabled', __('Hosted SponsorBoard sync is disabled.', 'mds-sponsorboard'));
        }

        $response = self::request_json('POST', '/api/admin/sponsorboard/sites', [
            'siteUrl' => home_url('/'),
            'plan' => 'wordpress',
            'settings' => [
                'pluginVersion' => defined('MDS_SPONSORBOARD_VERSION') ? MDS_SPONSORBOARD_VERSION : '',
                'wordpressVersion' => get_bloginfo('version'),
            ],
        ], true, 8);

        if (is_wp_error($response)) {
            return self::record_error($response);
        }

        $site = is_array($response['site'] ?? null) ? $response['site'] : [];
        if (!empty($site['id'])) {
            update_option(self::OPTION_SITE_ID, sanitize_text_field((string) $site['id']), false);
            self::record_success();
        }

        return $site;
    }

    private static function request_json($method, $path, array $payload = [], $requires_auth = true, $timeout = 8) {
        $settings = self::settings();
        $url = self::build_url($settings['api_url'], $path);
        if (!$url) {
            return new \WP_Error('mds_sponsorboard_hosted_invalid_url', __('The hosted SponsorBoard API URL is invalid.', 'mds-sponsorboard'));
        }

        $api_key = (string) get_option(self::OPTION_API_KEY, '');
        if ($requires_auth && '' === $api_key) {
            return new \WP_Error('mds_sponsorboard_hosted_missing_key', __('Enter a hosted SponsorBoard API key first.', 'mds-sponsorboard'));
        }

        $args = [
            'method' => strtoupper((string) $method),
            'timeout' => max(2, absint($timeout)),
            'redirection' => 0,
            'reject_unsafe_urls' => !self::is_local_service_url($url),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'MDS-SponsorBoard/' . (defined('MDS_SPONSORBOARD_VERSION') ? MDS_SPONSORBOARD_VERSION : 'dev') . ' WordPress/' . get_bloginfo('version'),
                'X-Site-URL' => home_url('/'),
            ],
        ];

        if ($requires_auth) {
            $args['headers']['Authorization'] = 'Bearer ' . $api_key;
            $args['headers']['X-API-Key'] = $api_key;
        }

        if ('GET' === $args['method'] && $payload) {
            $url = add_query_arg($payload, $url);
        } elseif ($payload) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = self::is_local_service_url($url)
            ? wp_remote_request($url, $args)
            : wp_safe_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = '' !== $body ? json_decode($body, true) : [];
        if (JSON_ERROR_NONE !== json_last_error()) {
            $decoded = [];
        }

        if ($code < 200 || $code >= 300) {
            $message = is_array($decoded) ? DataSanitizer::text($decoded['error'] ?? $decoded['message'] ?? '', 500) : '';
            if ('' === $message && '' !== $body) {
                $message = DataSanitizer::text(wp_strip_all_tags($body), 500);
            }
            /* translators: %d: HTTP response status code. */
            return new \WP_Error('mds_sponsorboard_hosted_http_error', $message ?: sprintf(__('Hosted SponsorBoard returned HTTP %d.', 'mds-sponsorboard'), $code), [
                'status' => $code,
                'body' => $decoded ?: $body,
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private static function build_url($api_url, $path) {
        $api_url = self::sanitize_api_url($api_url);
        if ('' === $api_url) {
            return '';
        }

        return untrailingslashit($api_url) . '/' . ltrim((string) $path, '/');
    }

    private static function sanitize_api_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        $parts = wp_parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (
            !in_array($scheme, ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return '';
        }

        $url = untrailingslashit(esc_url_raw($url));
        if (self::is_local_service_url($url)) {
            $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

            return in_array($environment, ['local', 'development'], true) ? $url : '';
        }

        if ('https' !== $scheme || !wp_http_validate_url($url)) {
            return '';
        }

        return $url;
    }

    /**
     * Whether the URL uses an explicitly supported local-development host.
     *
     * @param string $url URL to inspect.
     * @return bool
     */
    private static function is_local_service_url($url) {
        $parts = wp_parse_url((string) $url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true);
    }

    private static function remote_board_id(array $board) {
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        $hosted = is_array($settings['hosted_service'] ?? null) ? $settings['hosted_service'] : [];

        return sanitize_text_field((string) ($hosted['board_id'] ?? ''));
    }

    private static function remote_slot_id(array $slot) {
        $metadata = is_array($slot['metadata'] ?? null) ? $slot['metadata'] : [];
        $hosted = is_array($metadata['hosted_service'] ?? null) ? $metadata['hosted_service'] : [];

        return sanitize_text_field((string) ($hosted['slot_id'] ?? ''));
    }

    private static function remote_booking_id(array $booking) {
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $hosted = is_array($metadata['hosted_service'] ?? null) ? $metadata['hosted_service'] : [];

        return sanitize_text_field((string) ($hosted['booking_id'] ?? ''));
    }

    private static function remote_slot_status($status) {
        $status = sanitize_key((string) $status);
        if ('available' === $status) {
            return 'available';
        }

        return in_array($status, ['paused', 'archived'], true) ? 'disabled' : 'held';
    }

    private static function remote_booking_status($status) {
        $status = sanitize_key((string) $status);
        if (in_array($status, ['approved', 'active'], true)) {
            return $status;
        }
        if ('paid' === $status) {
            return 'approved';
        }
        if (in_array($status, ['expired', 'cancelled'], true)) {
            return $status;
        }
        if ('rejected' === $status) {
            return 'cancelled';
        }

        return 'pending';
    }

    private static function remote_payment_status($status, array $booking = []) {
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $was_paid = !empty($metadata['lifecycle_was_paid']) || !empty($booking['order_id']);

        return in_array(sanitize_key((string) $status), ['paid', 'approved', 'active'], true) || $was_paid ? 'paid' : 'unpaid';
    }

    private static function default_currency() {
        return Repository::default_currency();
    }

    private static function rfc3339($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $timestamp = strtotime($value . (false === strpos($value, '+') && false === strpos($value, 'Z') ? ' UTC' : ''));

        return $timestamp ? gmdate('c', $timestamp) : '';
    }

    private static function record_error($error) {
        if (is_wp_error($error)) {
            update_option(self::OPTION_LAST_ERROR, $error->get_error_message(), false);
        }

        return $error;
    }

    private static function record_success() {
        update_option(self::OPTION_LAST_SYNC, current_time('mysql'), false);
        delete_option(self::OPTION_LAST_ERROR);
    }
}
