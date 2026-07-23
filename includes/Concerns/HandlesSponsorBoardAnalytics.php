<?php
/**
 * SponsorBoard analytics, public event tracking, and privacy callbacks.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\DataSanitizer;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorBoardAnalytics {

    public function handle_track_event() {
        check_ajax_referer('mds_sponsorboard_track', 'nonce');

        $event_type = sanitize_key(wp_unslash($_POST['event_type'] ?? ''));
        if (!in_array($event_type, ['impression', 'click'], true)) {
            wp_send_json_error(['message' => __('Unsupported SponsorBoard event.', 'mds-sponsorboard')], 400);
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);
        $slot_id = absint($_POST['slot_id'] ?? 0);
        $board_id = absint($_POST['board_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Missing SponsorBoard booking target.', 'mds-sponsorboard')], 400);
        }

        $booking = Repository::find_booking($booking_id);
        if (!$booking) {
            wp_send_json_success(['tracked' => false]);
        }
        $slot_id = absint($booking['slot_id'] ?? 0);
        $board_id = absint($booking['board_id'] ?? 0);
        $public_booking = Repository::public_booking_for_slot($slot_id);
        if (!$public_booking || absint($public_booking['id'] ?? 0) !== $booking_id) {
            wp_send_json_success(['tracked' => false]);
        }

        if (!$board_id || !$slot_id) {
            wp_send_json_error(['message' => __('Missing SponsorBoard event target.', 'mds-sponsorboard')], 400);
        }

        $fingerprint = implode('|', [
            $event_type,
            $board_id,
            $slot_id,
            $booking_id,
            $this->client_hash('ip'),
            $this->client_hash('ua'),
        ]);
        $window = 'impression' === $event_type ? HOUR_IN_SECONDS : MINUTE_IN_SECONDS;
        $key = 'mds_sponsorboard_event_' . substr(hash_hmac('sha256', $fingerprint, wp_salt('auth')), 0, 40);
        if (get_transient($key)) {
            wp_send_json_success(['tracked' => false]);
        }
        set_transient($key, 1, (int) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorboard/event/rate/limit/seconds', ['mds_sponsorboard_event_rate_limit_seconds'], $window, $event_type));

        $event = [
            'board_id' => $board_id,
            'slot_id' => $slot_id,
            'booking_id' => $booking_id,
            'event_type' => $event_type,
            'event_value' => 1,
            'metadata' => [
                'source' => 'frontend',
                'path' => DataSanitizer::text(wp_unslash($_POST['path'] ?? ''), 500),
            ],
            'ip_hash' => $this->client_hash('ip'),
            'user_agent_hash' => $this->client_hash('ua'),
        ];

        Repository::record_event($event);

        wp_send_json_success(['tracked' => true]);
    }

    public function privacy_exporters(array $exporters) {
        $exporters['mds-sponsorboard'] = [
            'exporter_friendly_name' => __('Million Dollar Script - SponsorBoard bookings', 'mds-sponsorboard'),
            'callback' => [$this, 'privacy_exporter'],
        ];

        return $exporters;
    }

    public function privacy_erasers(array $erasers) {
        $erasers['mds-sponsorboard'] = [
            'eraser_friendly_name' => __('Million Dollar Script - SponsorBoard bookings', 'mds-sponsorboard'),
            'callback' => [$this, 'privacy_eraser'],
        ];

        return $erasers;
    }

    public function privacy_exporter($email_address, $page = 1) {
        $items = [];
        $booking_page = Repository::bookings_for_email_page((string) $email_address, max(1, absint($page)), 100);
        foreach ($booking_page['items'] as $booking) {
                $data = [
                    ['name' => __('Booking ID', 'mds-sponsorboard'), 'value' => '#' . absint($booking['id'] ?? 0)],
                    ['name' => __('Board', 'mds-sponsorboard'), 'value' => (string) ($booking['board_title'] ?? '')],
                    ['name' => __('Slot', 'mds-sponsorboard'), 'value' => (string) ($booking['slot_title'] ?? '')],
                    ['name' => __('Sponsor name', 'mds-sponsorboard'), 'value' => (string) ($booking['sponsor_name'] ?? '')],
                    ['name' => __('Sponsor email', 'mds-sponsorboard'), 'value' => (string) ($booking['sponsor_email'] ?? '')],
                    ['name' => __('Sponsor URL', 'mds-sponsorboard'), 'value' => (string) ($booking['sponsor_url'] ?? '')],
                    ['name' => __('Status', 'mds-sponsorboard'), 'value' => (string) ($booking['status'] ?? '')],
                    ['name' => __('Created', 'mds-sponsorboard'), 'value' => (string) ($booking['created_at'] ?? '')],
                    ['name' => __('Updated', 'mds-sponsorboard'), 'value' => (string) ($booking['updated_at'] ?? '')],
                ];

                $items[] = [
                    'group_id' => 'mds-sponsorboard',
                    'group_label' => __('SponsorBoard bookings', 'mds-sponsorboard'),
                    'item_id' => 'mds-sponsorboard-booking-' . absint($booking['id'] ?? 0),
                    'data' => $data,
                ];
        }

        return [
            'data' => $items,
            'done' => absint($booking_page['page'] ?? 1) >= absint($booking_page['pages'] ?? 1),
        ];
    }

    public function privacy_eraser($email_address, $page = 1) {
        unset($page);
        $removed = Repository::anonymize_bookings_for_email((string) $email_address, 100);

        return [
            'items_removed' => $removed > 0,
            'items_retained' => false,
            'messages' => [],
            'done' => $removed < 100,
        ];
    }

    public function cleanup_analytics_events() {
        Repository::cleanup_old_events();
        Repository::cleanup_private_booking_data();
    }
}
