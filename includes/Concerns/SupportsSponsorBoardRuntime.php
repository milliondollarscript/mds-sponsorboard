<?php
/**
 * Shared SponsorBoard runtime helpers used by handlers, REST, and rendering.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait SupportsSponsorBoardRuntime {

    private function booking_status_allowed($status) {
        return in_array(sanitize_key((string) $status), [
            'requested',
            'pending_payment',
            'paid',
            'approved',
            'changes_requested',
            'rejected',
            'active',
            'expired',
            'cancelled',
        ], true);
    }

    private function sync_slot_for_booking_status(array $booking) {
        $slot_id = absint($booking['slot_id'] ?? 0);
        if (!$slot_id) {
            return;
        }

        $slot = Repository::find_slot($slot_id);
        if (!$slot) {
            return;
        }

        $booking_status = sanitize_key((string) ($booking['status'] ?? 'requested'));
        if (in_array($booking_status, ['paid', 'approved', 'active'], true)) {
            $slot_status = 'sold';
        } elseif (in_array($booking_status, ['rejected', 'expired', 'cancelled'], true)) {
            $slot_status = 'available';
        } else {
            $slot_status = 'reserved';
        }

        Repository::update_slot($slot_id, array_merge($slot, ['status' => $slot_status]));
    }

    private function manage_url($booking_id, $token) {
        return add_query_arg([
            'mds_sponsorboard_manage' => absint($booking_id),
            'token' => rawurlencode((string) $token),
        ], home_url('/'));
    }

    private function redirect($url, array $args = []) {
        wp_safe_redirect(add_query_arg($args, $url ?: home_url('/')));
        exit;
    }

    private function checkout_enabled(array $board) {
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];

        return $this->truthy($settings['checkout_enabled'] ?? '0') && class_exists('\\MDS3\\Commerce\\Payments') && 'standalone' !== \MDS3\Commerce\Payments::active_provider_id();
    }

    private function release_failed_checkout(array $booking, array $slot, $checkout) {
        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $metadata['checkout_failed_at'] = current_time('mysql');
        if (is_wp_error($checkout)) {
            $metadata['checkout_error'] = $checkout->get_error_message();
        }

        Repository::update_booking(absint($booking['id'] ?? 0), array_merge($booking, [
            'status' => 'cancelled',
            'metadata' => $metadata,
        ]));
        Repository::update_slot(absint($slot['id'] ?? 0), array_merge($slot, ['status' => 'available']));
    }

    private function sanitize_metadata(array $metadata) {
        $clean = [];
        foreach ($metadata as $key => $value) {
            $key = sanitize_key((string) $key);
            if (!$key) {
                continue;
            }
            if (is_scalar($value) || null === $value) {
                $clean[$key] = sanitize_text_field((string) $value);
            }
        }

        return $clean;
    }

    private function rate_limited($slot_id, $context = 'request') {
        $key = 'mds_sponsorboard_' . sanitize_key((string) $context) . '_' . md5((string) $slot_id . '|' . $this->client_hash('ip'));
        if (get_transient($key)) {
            return true;
        }

        set_transient($key, 1, MINUTE_IN_SECONDS);

        return false;
    }

    private function client_hash($type) {
        $value = 'ua' === $type ? ($_SERVER['HTTP_USER_AGENT'] ?? '') : ($_SERVER['REMOTE_ADDR'] ?? '');

        return hash_hmac('sha256', (string) $value, wp_salt('auth'));
    }

    private function render_template($template, array $data = []) {
        $template = ltrim(str_replace('\\', '/', (string) $template), '/');
        $path = MDS_SPONSORBOARD_PATH . 'templates/' . $template;
        if (!is_readable($path)) {
            return '';
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $path;

        return (string) ob_get_clean();
    }

    private function truthy($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }
}
