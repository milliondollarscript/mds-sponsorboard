<?php

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

final class PublicApi {
    /** @return array{items:array,total:int,limit:int,offset:int} */
    public static function scheduler_query(array $args = []): array {
        $limit = min(100, max(1, absint($args['limit'] ?? 20)));
        $offset = max(0, absint($args['offset'] ?? 0));
        $search = strtolower(sanitize_text_field((string) ($args['search'] ?? '')));
        $status = sanitize_key((string) ($args['status'] ?? ''));
        $items = [];
        foreach (Repository::recent_bookings(100) as $booking) {
            if ($status && $status !== sanitize_key((string) ($booking['status'] ?? ''))) {
                continue;
            }
            $haystack = strtolower(implode(' ', [
                (string) ($booking['id'] ?? ''),
                (string) ($booking['board_title'] ?? ''),
                (string) ($booking['slot_title'] ?? ''),
                (string) ($booking['sponsor_name'] ?? ''),
            ]));
            if ($search && false === strpos($haystack, $search)) {
                continue;
            }
            $items[] = self::scheduler_payload($booking);
        }
        $total = count($items);

        return [
            'items' => array_slice($items, $offset, $limit),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return array|\WP_Error|null */
    public static function scheduler_find($booking_id) {
        $booking = Repository::find_booking(absint($booking_id));

        return $booking ? self::scheduler_payload($booking) : null;
    }

    /** @return true|\WP_Error */
    public static function scheduler_preflight($booking_id, $activate = true) {
        $booking = Repository::find_booking(absint($booking_id));
        if (!$booking) {
            return new \WP_Error('mds_sponsorboard_schedule_booking_missing', __('The sponsor booking could not be found.', 'mds-sponsorboard'));
        }
        if (!$activate) {
            return true;
        }
        if (!in_array(sanitize_key((string) ($booking['status'] ?? '')), ['approved', 'active'], true)) {
            return new \WP_Error('mds_sponsorboard_schedule_booking_unapproved', __('The sponsor booking must be paid and approved before it can be scheduled.', 'mds-sponsorboard'));
        }
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        if ('approved' !== sanitize_key((string) ($creative['creative_status'] ?? ''))) {
            return new \WP_Error('mds_sponsorboard_schedule_creative_unapproved', __('The sponsor creative must be approved before it can be scheduled.', 'mds-sponsorboard'));
        }
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        if (!$slot || !in_array(sanitize_key((string) ($slot['status'] ?? '')), ['reserved', 'sold'], true)) {
            return new \WP_Error('mds_sponsorboard_schedule_slot_inactive', __('The sponsor booking no longer owns an active slot reservation.', 'mds-sponsorboard'));
        }

        return true;
    }

    /** @return true|\WP_Error */
    public static function scheduler_validate_window($booking_id, array $window) {
        $booking = Repository::find_booking(absint($booking_id));
        if (!$booking) {
            return new \WP_Error('mds_sponsorboard_schedule_booking_missing', __('The sponsor booking could not be found.', 'mds-sponsorboard'));
        }
        $campaign_start = strtotime((string) ($window['starts_at_utc'] ?? '') . ' UTC');
        $campaign_end = strtotime((string) ($window['ends_at_utc'] ?? '') . ' UTC');
        $booking_start = !empty($booking['starts_at']) ? strtotime((string) $booking['starts_at'] . ' UTC') : 0;
        $booking_end = !empty($booking['ends_at']) ? strtotime((string) $booking['ends_at'] . ' UTC') : 0;
        if (!$campaign_start || !$campaign_end) {
            return new \WP_Error('mds_sponsorboard_schedule_window_invalid', __('The campaign window is invalid.', 'mds-sponsorboard'));
        }
        if (($booking_start && $campaign_start < $booking_start) || ($booking_end && $campaign_end > $booking_end)) {
            return new \WP_Error('mds_sponsorboard_schedule_window_outside_booking', __('The campaign must stay within the sponsor booking start and end dates.', 'mds-sponsorboard'));
        }

        return true;
    }

    /** @return array|\WP_Error */
    public static function scheduler_set_visible($booking_id, $visible, array $context = []) {
        $preflight = self::scheduler_preflight($booking_id, (bool) $visible);
        if (is_wp_error($preflight)) {
            return $preflight;
        }
        $booking = Repository::find_booking(absint($booking_id));
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        if ($visible) {
            unset($metadata['campaign_scheduler_hidden']);
            $metadata['campaign_scheduler_activated_at'] = current_time('mysql', true);
        } else {
            $metadata['campaign_scheduler_hidden'] = true;
            $metadata['campaign_scheduler_hidden_at'] = current_time('mysql', true);
        }
        $metadata['campaign_scheduler_campaign_id'] = absint($context['campaign_id'] ?? 0);
        $updated = Repository::update_booking(absint($booking_id), array_merge($booking, [
            'status' => $visible ? 'active' : sanitize_key((string) ($booking['status'] ?? 'approved')),
            'metadata' => $metadata,
        ]));
        if (!$updated) {
            return new \WP_Error('mds_sponsorboard_schedule_update_failed', __('The sponsor booking visibility could not be updated.', 'mds-sponsorboard'));
        }
        Repository::refresh_slot_status(absint($updated['slot_id'] ?? 0));
        Repository::audit('booking', absint($updated['id'] ?? 0), $visible ? 'campaign_scheduler_activated' : 'campaign_scheduler_hidden', $visible
            ? __('Campaign Scheduler made this booking visible.', 'mds-sponsorboard')
            : __('Campaign Scheduler hid this booking.', 'mds-sponsorboard'), [
                'campaign_id' => absint($context['campaign_id'] ?? 0),
            ]);

        return self::scheduler_payload($updated);
    }

    /** @return array|\WP_Error */
    public static function query_for_principal(array $principal, array $args = []) {
        $owner = self::owner($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }
        $limit = min(100, max(1, absint($args['limit'] ?? 20)));
        $offset = max(0, absint($args['offset'] ?? 0));
        $page = (int) floor($offset / $limit) + 1;
        $result = Repository::workspace_bookings_page($owner['user_id'], $owner['email'], $page, $limit, [
            'view' => sanitize_key((string) ($args['view'] ?? '')),
            'search' => sanitize_text_field((string) ($args['search'] ?? '')),
            'offset' => $offset,
        ]);
        return [
            'items' => array_map([__CLASS__, 'summary'], $result['items']),
            'total' => absint($result['total'] ?? 0),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /** @return array|\WP_Error|null */
    public static function find_for_principal($booking_id, array $principal) {
        $owner = self::owner($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }
        $booking = Repository::workspace_booking($booking_id, $owner['user_id'], $owner['email']);
        if (!$booking) {
            return null;
        }
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        $payload = self::summary(array_merge($booking, [
            'slot_price' => (float) ($slot['price'] ?? 0),
            'slot_currency' => (string) ($slot['currency'] ?? ''),
        ]));
        $payload['placements'] = [[
            'id' => absint($booking['id'] ?? 0),
            'image_url' => esc_url_raw((string) ($booking['creative_payload']['creative_url'] ?? '')),
            'link_url' => esc_url_raw((string) ($booking['sponsor_url'] ?? '')),
            'alt_text' => sanitize_text_field((string) ($booking['sponsor_name'] ?? '')),
            'popup_text' => sanitize_textarea_field((string) ($booking['creative_payload']['note'] ?? '')),
            'fit_mode' => 'contain',
            'status' => sanitize_key((string) ($booking['creative_payload']['creative_status'] ?? $booking['status'] ?? '')),
        ]];
        $payload['history'] = array_map(static function(array $event): array {
            return [
                'type' => 'sponsorboard',
                'action' => sanitize_key((string) ($event['action'] ?? '')),
                'message' => sanitize_textarea_field((string) ($event['message'] ?? '')),
                'created_at' => sanitize_text_field((string) ($event['created_at'] ?? '')),
            ];
        }, Repository::workspace_booking_history($booking_id));
        $payload['renewal'] = ['eligible' => false];
        return $payload;
    }

    /** @return array|\WP_Error */
    public static function update_for_principal($booking_id, array $principal, array $data) {
        $booking = self::owned_booking($booking_id, $principal);
        if (is_wp_error($booking)) {
            return $booking;
        }
        if (in_array(sanitize_key((string) ($booking['status'] ?? '')), ['expired', 'cancelled', 'rejected'], true)) {
            return new \WP_Error('mds_sponsorboard_booking_closed', __('This sponsor booking can no longer be changed.', 'mds-sponsorboard'));
        }
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $creative['creative_url'] = esc_url_raw((string) ($data['creative_url'] ?? $creative['creative_url'] ?? ''));
        $creative['note'] = sanitize_textarea_field((string) ($data['note'] ?? $creative['note'] ?? ''));
        $creative['creative_status'] = 'pending_review';
        $updated = Repository::update_booking($booking_id, array_merge($booking, [
            'sponsor_name' => DataSanitizer::text($data['sponsor_name'] ?? $booking['sponsor_name'] ?? '', 191),
            'sponsor_email' => $booking['sponsor_email'] ?? '',
            'sponsor_url' => DataSanitizer::url($data['sponsor_url'] ?? $booking['sponsor_url'] ?? ''),
            'creative_payload' => $creative,
        ]));
        if (!$updated) {
            return new \WP_Error('mds_sponsorboard_booking_update_failed', __('Sponsor details could not be saved.', 'mds-sponsorboard'));
        }
        HostedClient::sync_booking($updated);
        Notifications::booking_updated($updated);
        return self::find_for_principal($booking_id, $principal);
    }

    /** @return array|\WP_Error */
    public static function replace_image_for_principal($booking_id, array $principal, array $file) {
        $booking = self::owned_booking($booking_id, $principal);
        if (is_wp_error($booking)) {
            return $booking;
        }
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        if (!$slot) {
            return new \WP_Error('mds_sponsorboard_slot_missing', __('Sponsor slot could not be found.', 'mds-sponsorboard'));
        }
        $upload = (new CreativeUploadService())->upload($file, $booking, $slot);
        if (is_wp_error($upload)) {
            return $upload;
        }
        if (!$upload) {
            return new \WP_Error('mds_sponsorboard_upload_required', __('Choose a creative image to upload.', 'mds-sponsorboard'));
        }
        $creative = array_merge(is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [], $upload);
        $updated = Repository::update_booking($booking_id, array_merge($booking, [
            'creative_id' => absint($upload['attachment_id'] ?? 0),
            'creative_payload' => $creative,
        ]));
        if (!$updated) {
            wp_delete_attachment(absint($upload['attachment_id'] ?? 0), true);
            return new \WP_Error('mds_sponsorboard_booking_update_failed', __('Sponsor creative could not be saved.', 'mds-sponsorboard'));
        }
        HostedClient::sync_booking($updated);
        Notifications::booking_updated($updated);
        return self::find_for_principal($booking_id, $principal);
    }

    /** @return array|\WP_Error */
    public static function start_checkout_for_principal($booking_id, array $principal) {
        $booking = self::owned_booking($booking_id, $principal);
        if (is_wp_error($booking)) {
            return $booking;
        }
        if (!in_array(sanitize_key((string) ($booking['status'] ?? '')), ['requested', 'pending_payment', 'changes_requested'], true)) {
            return new \WP_Error('mds_sponsorboard_checkout_unavailable', __('Payment is not available for this booking.', 'mds-sponsorboard'));
        }
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        if (!$slot || (float) ($slot['price'] ?? 0) <= 0) {
            return new \WP_Error('mds_sponsorboard_checkout_unavailable', __('Payment is not available for this booking.', 'mds-sponsorboard'));
        }
        $provider = sanitize_key((string) ($booking['metadata']['payment_provider'] ?? ''));
        if (!$provider) {
            $provider = \MillionDollarScript\Commerce\Payments::active_provider_id();
        }
        $manage_url = (string) apply_filters('million-dollar-script/extensions/sponsorboard/customer/workspace/url', '', $booking);
        $checkout = \MillionDollarScript\Commerce\Payments::create_checkout([
            'source' => 'sponsorboard',
            'source_id' => absint($booking_id),
            'payment_provider' => $provider,
            'existing_provider_order_id' => sanitize_text_field((string) ($booking['order_id'] ?? '')),
            'user_id' => absint($booking['user_id'] ?? 0),
            'email' => sanitize_email((string) ($booking['sponsor_email'] ?? '')),
            'currency' => (string) ($slot['currency'] ?? Repository::default_currency()),
            'subtotal' => (float) ($slot['price'] ?? 0),
            'total' => (float) ($slot['price'] ?? 0),
            'items' => [[
                'name' => sanitize_text_field((string) ($booking['slot_title'] ?? __('Sponsor booking', 'mds-sponsorboard'))),
                'amount' => (float) ($slot['price'] ?? 0),
                'quantity' => 1,
            ]],
            'manage_url' => esc_url_raw($manage_url),
        ]);
        if (is_wp_error($checkout)) {
            return $checkout;
        }
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $metadata['payment_provider'] = sanitize_key((string) ($checkout['provider'] ?? ''));
        $metadata['checkout_started_at'] = current_time('mysql', true);
        Repository::update_booking($booking_id, array_merge($booking, [
            'order_id' => absint($checkout['provider_order_id'] ?? $booking['order_id'] ?? 0),
            'status' => 'pending_payment',
            'metadata' => $metadata,
        ]));
        return [
            'provider' => sanitize_key((string) ($checkout['provider'] ?? '')),
            'checkout_url' => esc_url_raw((string) ($checkout['checkout_url'] ?? '')),
            'after_upload_url' => esc_url_raw((string) ($checkout['after_upload_url'] ?? '')),
        ];
    }

    private static function summary(array $booking): array {
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $status = sanitize_key((string) ($booking['status'] ?? ''));
        $action = in_array($status, ['requested', 'pending_payment', 'changes_requested'], true)
            ? ('pending_payment' === $status ? 'payment' : 'review')
            : (empty($creative['creative_url']) ? 'creative' : '');
        return [
            'id' => absint($booking['id'] ?? 0),
            'resource_ref' => 'sponsorboard:' . absint($booking['id'] ?? 0),
            'resource_type' => 'sponsor-booking',
            'resource_label' => __('Sponsor booking', 'mds-sponsorboard'),
            'title' => trim((string) ($booking['board_title'] ?? '') . ' - ' . (string) ($booking['slot_title'] ?? ''), ' -'),
            'status' => $status,
            'currency' => sanitize_text_field((string) ($booking['slot_currency'] ?? '')),
            'total' => (float) ($booking['slot_price'] ?? 0),
            'commerce_provider' => sanitize_key((string) ($booking['metadata']['payment_provider'] ?? 'standalone')) ?: 'standalone',
            'upload_count' => empty($creative['creative_url']) ? 0 : 1,
            'active_placement_count' => 'active' === $status ? 1 : 0,
            'starts_at' => sanitize_text_field((string) ($booking['starts_at'] ?? '')),
            'expires_at' => sanitize_text_field((string) ($booking['ends_at'] ?? '')),
            'created_at' => sanitize_text_field((string) ($booking['created_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($booking['updated_at'] ?? '')),
            'action_required' => $action,
        ];
    }

    private static function scheduler_payload(array $booking): array {
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];

        return [
            'id' => absint($booking['id'] ?? 0),
            'resource_ref' => 'sponsorboard:' . absint($booking['id'] ?? 0),
            'title' => trim((string) ($booking['board_title'] ?? '') . ' - ' . (string) ($booking['slot_title'] ?? ''), ' -'),
            'status' => BookingLifecycle::state($booking),
            'currency' => sanitize_text_field((string) ($slot['currency'] ?? Repository::default_currency())),
            'owner_user_id' => absint($booking['user_id'] ?? 0),
            'owner_email' => sanitize_email((string) ($booking['sponsor_email'] ?? '')),
            'image_url' => esc_url_raw((string) ($creative['creative_url'] ?? '')),
            'starts_at' => sanitize_text_field((string) ($booking['starts_at'] ?? '')),
            'ends_at' => sanitize_text_field((string) ($booking['ends_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($booking['updated_at'] ?? '')),
        ];
    }

    /** @return array|\WP_Error */
    private static function owner(array $principal) {
        $owner = [
            'user_id' => absint($principal['user_id'] ?? 0),
            'email' => sanitize_email((string) ($principal['email'] ?? '')),
        ];
        return $owner['user_id'] || $owner['email']
            ? $owner
            : new \WP_Error('mds_sponsorboard_principal_required', __('Verified sponsor access is required.', 'mds-sponsorboard'));
    }

    /** @return array|\WP_Error */
    private static function owned_booking($booking_id, array $principal) {
        $owner = self::owner($principal);
        if (is_wp_error($owner)) {
            return $owner;
        }
        $booking = Repository::workspace_booking($booking_id, $owner['user_id'], $owner['email']);
        return $booking ?: new \WP_Error('mds_sponsorboard_booking_not_found', __('Sponsor booking could not be found.', 'mds-sponsorboard'));
    }
}
