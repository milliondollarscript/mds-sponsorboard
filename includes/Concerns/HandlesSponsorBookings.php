<?php
/**
 * SponsorBoard booking, checkout, payment-status, and manage-link handlers.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

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

        if ($this->rate_limited($slot_id)) {
            $this->redirect($referrer, ['sponsorboard_request' => 'rate_limited']);
        }

        $name = sanitize_text_field(wp_unslash($_POST['sponsor_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['sponsor_email'] ?? ''));
        if (!$name || !$email || !is_email($email)) {
            $this->redirect($referrer, ['sponsorboard_request' => 'invalid']);
        }

        $creative_url = esc_url_raw(wp_unslash($_POST['creative_url'] ?? ''));
        $result = Repository::create_booking([
            'board_id' => absint($board['id'] ?? 0),
            'slot_id' => $slot_id,
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => wp_unslash($_POST['sponsor_url'] ?? ''),
            'status' => 'requested',
            'creative_payload' => [
                'creative_url' => $creative_url,
                'note' => sanitize_textarea_field(wp_unslash($_POST['sponsor_note'] ?? '')),
            ],
            'metadata' => [
                'request_ip_hash' => $this->client_hash('ip'),
                'request_user_agent_hash' => $this->client_hash('ua'),
            ],
        ]);

        if (!$result || empty($result['booking']['id']) || empty($result['token'])) {
            $this->redirect($referrer, ['sponsorboard_request' => 'failed']);
        }

        Repository::update_slot($slot_id, array_merge($slot, ['status' => 'reserved']));
        $this->redirect($this->manage_url(absint($result['booking']['id']), (string) $result['token']), ['created' => '1']);
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

        if ($this->rate_limited($slot_id, 'checkout')) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'rate_limited']);
        }

        $name = sanitize_text_field(wp_unslash($_POST['sponsor_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['sponsor_email'] ?? ''));
        if (!$name || !$email || !is_email($email)) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'invalid']);
        }

        if (!class_exists('\\MDS3\\Commerce\\Payments')) {
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

        $result = Repository::create_booking([
            'board_id' => absint($board['id'] ?? 0),
            'slot_id' => $slot_id,
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => $request['sponsor_url'],
            'status' => 'pending_payment',
            'creative_payload' => [
                'creative_url' => $creative_url,
                'note' => $request['sponsor_note'],
            ],
            'metadata' => [
                'payment_provider' => \MDS3\Commerce\Payments::active_provider_id(),
                'checkout_ip_hash' => $this->client_hash('ip'),
                'checkout_user_agent_hash' => $this->client_hash('ua'),
            ],
        ]);

        if (!$result || empty($result['booking']['id']) || empty($result['token'])) {
            $this->redirect($referrer, ['sponsorboard_checkout' => 'failed']);
        }

        $booking = is_array($result['booking']) ? $result['booking'] : [];
        Repository::update_slot($slot_id, array_merge($slot, ['status' => 'reserved']));

        $manage_url = $this->manage_url(absint($booking['id'] ?? 0), (string) $result['token']);
        $checkout = \MDS3\Commerce\Payments::create_checkout([
            'source' => 'sponsorboard',
            'source_id' => absint($booking['id'] ?? 0),
            'source_key' => (string) $result['token'],
            'user_id' => get_current_user_id(),
            'email' => $email,
            'currency' => strtoupper((string) ($slot['currency'] ?? $board['currency'] ?? 'USD')),
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
        $metadata['payment_provider'] = sanitize_key((string) ($checkout['provider'] ?? \MDS3\Commerce\Payments::active_provider_id()));
        $metadata['checkout_started_at'] = current_time('mysql');
        if (!empty($checkout['metadata']) && is_array($checkout['metadata'])) {
            $metadata = array_merge($metadata, $checkout['metadata']);
        }

        Repository::update_booking(absint($booking['id'] ?? 0), array_merge($booking, [
            'order_id' => absint($checkout['provider_order_id'] ?? $booking['order_id'] ?? 0),
            'status' => sanitize_key((string) ($checkout['status'] ?? 'pending_payment')),
            'metadata' => $metadata,
        ]));

        $this->redirect(esc_url_raw((string) $checkout['checkout_url']));
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

        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $creative['creative_url'] = esc_url_raw(wp_unslash($_POST['creative_url'] ?? $creative['creative_url'] ?? ''));
        $creative['note'] = sanitize_textarea_field(wp_unslash($_POST['sponsor_note'] ?? $creative['note'] ?? ''));

        Repository::update_booking($booking_id, [
            'sponsor_name' => wp_unslash($_POST['sponsor_name'] ?? $booking['sponsor_name'] ?? ''),
            'sponsor_email' => $booking['sponsor_email'] ?? '',
            'sponsor_url' => wp_unslash($_POST['sponsor_url'] ?? $booking['sponsor_url'] ?? ''),
            'status' => $booking['status'] ?? 'requested',
            'creative_payload' => $creative,
            'metadata' => $booking['metadata'] ?? [],
        ]);

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

        $this->handle_provider_payment_status(absint($source_id), 'paid' === sanitize_key((string) $status) ? 'paid' : 'cancelled', is_array($context) ? $context : []);
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

    /**
     * Render the signed sponsor manage page.
     *
     * @return void
     */
}
