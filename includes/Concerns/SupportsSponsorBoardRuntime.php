<?php
/**
 * Shared SponsorBoard runtime helpers used by handlers, REST, and rendering.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\Repository;

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
        Repository::refresh_slot_status(absint($booking['slot_id'] ?? 0));
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

    private function checkout_redirect($url, $fallback_url = '') {
        if (class_exists('\\MillionDollarScript\\Extensions\\Support') && method_exists('\\MillionDollarScript\\Extensions\\Support', 'external_redirect')) {
            \MillionDollarScript\Extensions\Support::external_redirect($url, $fallback_url ?: home_url('/'));
        }

        $target = esc_url_raw((string) $url, ['http', 'https']);
        $parts = wp_parse_url($target);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!$target || empty($parts['host']) || !in_array($scheme, ['http', 'https'], true)) {
            $this->redirect($fallback_url ?: home_url('/'));
        }

        wp_redirect($target, 302, 'Million Dollar Script - SponsorBoard');
        exit;
    }

    private function checkout_enabled(array $board) {
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];

        return $this->truthy($settings['checkout_enabled'] ?? '0') && class_exists('\\MillionDollarScript\\Commerce\\Payments') && 'standalone' !== \MillionDollarScript\Commerce\Payments::active_provider_id();
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
        Repository::refresh_slot_status(absint($slot['id'] ?? 0));
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

    private function rate_limited($slot_id, $context = 'request', $limit = 10) {
        $limit = min(100, max(1, absint($limit)));
        $bucket = gmdate('YmdH');
        $hash = hash_hmac('sha256', sanitize_key((string) $context) . '|' . absint($slot_id) . '|' . $bucket . '|' . $this->client_hash('ip'), wp_salt('auth'));
        $transient = 'mds_sponsorboard_rate_' . substr($hash, 0, 40);
        $count = absint(get_transient($transient));
        if ($count >= $limit) {
            return true;
        }

        set_transient($transient, $count + 1, HOUR_IN_SECONDS + MINUTE_IN_SECONDS);

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
