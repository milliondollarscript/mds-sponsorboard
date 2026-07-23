<?php
/**
 * SponsorBoard booking, checkout, payment-status, and manage-link handlers.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\Notifications;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorBookings {

    public function handle_booking_request() {
        $slot_id = absint($_POST['slot_id'] ?? 0);
        check_admin_referer('mds_sponsorboard_request_booking_' . $slot_id);

        $slot = Repository::find_slot($slot_id);
        $board = $slot ? Repository::find_board(absint($slot['board_id'] ?? 0)) : null;
        $referrer = esc_url_raw(wp_unslash($_POST['_wp_http_referer'] ?? wp_get_referer() ?: home_url('/')));
        if (!$slot || !$board || ('active' !== (string) ($board['status'] ?? '') && !current_user_can('manage_options'))) {
            $this->redirect($referrer, ['sponsorboard_request' => 'invalid']);
        }

        if ('available' !== (string) ($slot['status'] ?? '')) {
            $this->redirect($referrer, ['sponsorboard_request' => 'unavailable']);
        }

        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        if ($this->rate_limited($slot_id, 'request', absint($settings['booking_limit_per_hour'] ?? 10))) {
            $this->redirect($referrer, ['sponsorboard_request' => 'rate_limited']);
        }

        $name = sanitize_text_field(wp_unslash($_POST['sponsor_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['sponsor_email'] ?? ''));
        if (!$name || !$email || !is_email($email)) {
            $this->redirect($referrer, ['sponsorboard_request' => 'invalid']);
        }

        $creative_url = esc_url_raw(wp_unslash($_POST['creative_url'] ?? ''));
        $result = Repository::reserve_slot_and_create_booking([
            'board_id' => absint($board['id'] ?? 0),
            'slot_id' => $slot_id,
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => wp_unslash($_POST['sponsor_url'] ?? ''),
            'status' => 'requested',
            'terms_accepted' => !empty($_POST['terms_accepted']),
            'creative_payload' => [
                'creative_url' => $creative_url,
                'note' => sanitize_textarea_field(wp_unslash($_POST['sponsor_note'] ?? '')),
            ],
            'metadata' => [
                'request_ip_hash' => $this->client_hash('ip'),
                'request_user_agent_hash' => $this->client_hash('ua'),
            ],
        ]);

        if (is_wp_error($result) || empty($result['booking']['id']) || empty($result['token'])) {
            $code = is_wp_error($result) && 'mds_sponsorboard_slot_unavailable' === $result->get_error_code() ? 'unavailable' : 'failed';
            $this->redirect($referrer, ['sponsorboard_request' => $code]);
        }

        $manage_url = $this->manage_url(absint($result['booking']['id']), (string) $result['token']);
        Notifications::booking_created($board, $slot, $result['booking'], $manage_url);
        $this->redirect($manage_url, ['created' => '1']);
    }

    /**
     * Handle a checkout request through the configured payment provider.
     *
     * @return void
     */

    public function handle_checkout_start() {
        $slot_id = absint($_POST['slot_id'] ?? 0);
        check_admin_referer('mds_sponsorboard_start_checkout_' . $slot_id);

        $slot = Repository::find_slot($slot_id);
        $board = $slot ? Repository::find_board(absint($slot['board_id'] ?? 0)) : null;
        $referrer = esc_url_raw(wp_unslash($_POST['_wp_http_referer'] ?? wp_get_referer() ?: home_url('/')));
        if (!$slot || !$board || ('active' !== (string) ($board['status'] ?? '') && !current_user_can('manage_options'))) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'invalid']);
        }

        if (!$this->checkout_enabled($board)) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'disabled']);
        }

        if ('available' !== (string) ($slot['status'] ?? '')) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'unavailable']);
        }

        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        if ($this->rate_limited($slot_id, 'checkout', absint($settings['booking_limit_per_hour'] ?? 10))) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'rate_limited']);
        }

        $name = sanitize_text_field(wp_unslash($_POST['sponsor_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['sponsor_email'] ?? ''));
        if (!$name || !$email || !is_email($email)) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'invalid']);
        }

        if (!class_exists('\\MillionDollarScript\\Commerce\\Payments')) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'provider_unavailable']);
        }

        $creative_url = esc_url_raw(wp_unslash($_POST['creative_url'] ?? ''));
        $request = [
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => esc_url_raw(wp_unslash($_POST['sponsor_url'] ?? '')),
            'creative_url' => $creative_url,
            'sponsor_note' => sanitize_textarea_field(wp_unslash($_POST['sponsor_note'] ?? '')),
        ];

        $result = Repository::reserve_slot_and_create_booking([
            'board_id' => absint($board['id'] ?? 0),
            'slot_id' => $slot_id,
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => $request['sponsor_url'],
            'status' => 'pending_payment',
            'terms_accepted' => !empty($_POST['terms_accepted']),
            'creative_payload' => [
                'creative_url' => $creative_url,
                'note' => $request['sponsor_note'],
            ],
            'metadata' => [
                'payment_provider' => \MillionDollarScript\Commerce\Payments::active_provider_id(),
                'checkout_ip_hash' => $this->client_hash('ip'),
                'checkout_user_agent_hash' => $this->client_hash('ua'),
            ],
        ]);

        if (is_wp_error($result) || empty($result['booking']['id']) || empty($result['token'])) {
            $code = is_wp_error($result) && 'mds_sponsorboard_slot_unavailable' === $result->get_error_code() ? 'unavailable' : 'failed';
            $this->redirect($referrer, ['sponsorboard_checkout' => $code]);
        }

        $booking = is_array($result['booking']) ? $result['booking'] : [];

        $manage_url = $this->manage_url(absint($booking['id'] ?? 0), (string) $result['token']);
        Notifications::booking_created($board, $slot, $booking, $manage_url);
        $checkout = \MillionDollarScript\Commerce\Payments::create_checkout([
            'source' => 'sponsorboard',
            'source_id' => absint($booking['id'] ?? 0),
            'source_key' => (string) $result['token'],
            'user_id' => get_current_user_id(),
            'email' => $email,
            'currency' => Repository::effective_currency($slot['currency'] ?? $board['currency'] ?? $this->default_currency()),
            'subtotal' => (float) ($slot['price'] ?? 0),
            'total' => (float) ($slot['price'] ?? 0),
            'items' => [[
                'name' => sprintf(
                    /* translators: 1: board title, 2: slot title */
                    __('%1$s - %2$s', 'mds-sponsorboard'),
                    (string) ($board['title'] ?? __('SponsorBoard', 'mds-sponsorboard')),
                    (string) ($slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))
                ),
                'amount' => (float) ($slot['price'] ?? 0),
                'quantity' => 1,
                'metadata' => [
                    'sponsorboard_board_id' => absint($board['id'] ?? 0),
                    'sponsorboard_slot_id' => $slot_id,
                    'sponsorboard_booking_id' => absint($booking['id'] ?? 0),
                ],
            ]],
            'manage_url' => $manage_url,
            'metadata' => [
                'sponsorboard_board_id' => absint($board['id'] ?? 0),
                'sponsorboard_slot_id' => $slot_id,
                'sponsorboard_booking_id' => absint($booking['id'] ?? 0),
            ],
        ], [
            'default_payload' => [
                'provider' => 'standalone',
                'checkout_url' => $manage_url,
                'after_upload_url' => $manage_url,
            ],
        ]);

        if (is_wp_error($checkout) || !is_array($checkout) || empty($checkout['checkout_url'])) {
            $this->release_failed_checkout($booking, $slot, $checkout);
            $this->redirect($referrer, ['sponsorboard_checkout' => 'failed']);
        }

        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $metadata['payment_provider'] = sanitize_key((string) ($checkout['provider'] ?? \MillionDollarScript\Commerce\Payments::active_provider_id()));
        $metadata['checkout_started_at'] = current_time('mysql');
        if (!empty($checkout['metadata']) && is_array($checkout['metadata'])) {
            $metadata = array_merge($metadata, $checkout['metadata']);
        }

        $updated = Repository::update_booking(absint($booking['id'] ?? 0), array_merge($booking, [
            'order_id' => absint($checkout['provider_order_id'] ?? $booking['order_id'] ?? 0),
            'status' => $this->provider_booking_status((string) ($checkout['status'] ?? 'pending_payment')) ?: 'pending_payment',
            'metadata' => $metadata,
        ]));
        $this->checkout_redirect((string) $checkout['checkout_url'], $referrer);
    }

    /**
     * Handle sponsor self-service updates.
     *
     * @return void
     */

    public function handle_booking_update() {
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        check_admin_referer('mds_sponsorboard_update_booking_' . $booking_id);

        $booking = Repository::find_booking_by_token($booking_id, $token);
        if (!$booking) {
            wp_die(esc_html__('This sponsor manage link is invalid or expired.', 'mds-sponsorboard'));
        }

        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $previous_creative_url = (string) ($creative['creative_url'] ?? '');
        $previous_note = (string) ($creative['note'] ?? '');
        $creative['creative_url'] = esc_url_raw(wp_unslash($_POST['creative_url'] ?? $creative['creative_url'] ?? ''));
        $creative['note'] = sanitize_textarea_field(wp_unslash($_POST['sponsor_note'] ?? $creative['note'] ?? ''));
        if ($previous_creative_url !== $creative['creative_url'] || $previous_note !== $creative['note'] || !empty($_FILES['creative_file']['name'])) {
            $creative['creative_status'] = 'pending_review';
        }

        $creative_id = absint($booking['creative_id'] ?? $creative['attachment_id'] ?? 0);
        $upload = $this->creative_upload_result($booking, is_array($slot) ? $slot : []);
        if (is_wp_error($upload)) {
            $this->redirect($this->manage_url($booking_id, $token), [
                'creative_error' => $this->upload_error_code($upload) ?: 'upload_failed',
            ]);
        }

        if (is_array($upload)) {
            $creative = array_merge($creative, $upload);
            $creative_id = absint($upload['attachment_id'] ?? 0);
        }

        $updated = Repository::update_booking($booking_id, [
            'sponsor_name' => wp_unslash($_POST['sponsor_name'] ?? $booking['sponsor_name'] ?? ''),
            'sponsor_email' => $booking['sponsor_email'] ?? '',
            'sponsor_url' => wp_unslash($_POST['sponsor_url'] ?? $booking['sponsor_url'] ?? ''),
            'status' => $booking['status'] ?? 'requested',
            'creative_id' => $creative_id,
            'creative_payload' => $creative,
            'metadata' => $booking['metadata'] ?? [],
        ]);
        if ($updated) {
            Notifications::booking_updated($updated);
        }

        $this->redirect($this->manage_url($booking_id, $token), ['updated' => '1']);
    }

    /**
     * Update a booking after a payment provider reports a status change.
     *
     * @param int    $booking_id Booking ID.
     * @param string $status     Booking status.
     * @param array  $context    Provider metadata.
     * @return void
     */

    public function handle_provider_payment_status($booking_id, $status, $context = []) {
        $booking = Repository::find_booking(absint($booking_id));
        if (!$booking || !$this->booking_status_allowed((string) $status)) {
            return;
        }

        $metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $context = is_array($context) ? $context : [];
        $metadata['payment_status_updated_at'] = current_time('mysql');
        $metadata['payment_status_context'] = $this->sanitize_metadata($context);

        $updated = Repository::update_booking(absint($booking['id'] ?? 0), array_merge($booking, [
            'status' => sanitize_key((string) $status),
            'metadata' => $metadata,
        ]));

        if ($updated) {
            $this->sync_slot_for_booking_status($updated);
        }
    }

    /**
     * Receive core payment API status changes.
     *
     * @param string $source    Payment source.
     * @param int    $source_id Source ID.
     * @param string $status    Payment status.
     * @param array  $context   Provider context.
     * @return void
     */

    public function handle_core_payment_source_status($source, $source_id, $status, $context = []) {
        if ('sponsorboard' !== sanitize_key((string) $source)) {
            return;
        }

        $booking_status = $this->provider_booking_status($status);
        if ('' === $booking_status) {
            return;
        }

        $this->handle_provider_payment_status(absint($source_id), $booking_status, is_array($context) ? $context : []);
    }

    /**
     * Provide signed manage URLs to payment provider extensions.
     *
     * @param string $url        Existing URL.
     * @param int    $booking_id Booking ID.
     * @param string $token      Raw manage token.
     * @return string
     */

    public function filter_manage_url($url, $booking_id, $token) {
        if ($url) {
            return (string) $url;
        }

        return $this->manage_url(absint($booking_id), (string) $token);
    }

    private function default_currency() {
        return Repository::default_currency();
    }

    private function provider_booking_status($status) {
        $status = str_replace('-', '_', sanitize_key((string) $status));
        if (in_array($status, ['paid', 'completed', 'processing'], true)) {
            return 'paid';
        }
        if (in_array($status, ['requested'], true)) {
            return 'requested';
        }
        if (in_array($status, ['pending', 'pending_payment', 'on_hold', 'created'], true)) {
            return 'pending_payment';
        }
        if (in_array($status, ['approved', 'changes_requested', 'rejected', 'active', 'expired', 'cancelled'], true)) {
            return $status;
        }
        if (in_array($status, ['failed', 'canceled', 'refunded', 'denied'], true)) {
            return 'cancelled';
        }

        return '';
    }

    /**
     * Render the signed sponsor manage page.
     *
     * @return void
     */
}
