<?php
/**
 * SponsorBoard admin POST handlers.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle;
use MillionDollarScript\Extensions\SponsorBoard\ExportJobManager;
use MillionDollarScript\Extensions\SponsorBoard\Notifications;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorBoardAdminRequests {

    /**
     * Save a board from admin.
     *
     * @return void
     */
    public function save_board() {
        $this->require_admin('mds_sponsorboard_save_board');
        $id = absint($_POST['board_id'] ?? 0);
        $settings = [
            'columns' => min(12, max(1, absint($_POST['columns'] ?? 3))),
            'disclosure_label' => sanitize_text_field(wp_unslash($_POST['disclosure_label'] ?? __('Sponsored', 'mds-sponsorboard'))),
            'checkout_enabled' => $this->truthy($_POST['checkout_enabled'] ?? '0') ? '1' : '0',
            'contact_email' => sanitize_email(wp_unslash($_POST['contact_email'] ?? '')),
            'terms_url' => wp_unslash($_POST['terms_url'] ?? ''),
            'privacy_url' => wp_unslash($_POST['privacy_url'] ?? ''),
            'booking_retention_days' => min(3650, max(30, absint($_POST['booking_retention_days'] ?? 365))),
            'booking_limit_per_hour' => min(100, max(1, absint($_POST['booking_limit_per_hour'] ?? 10))),
        ];
        $data = [
            'title' => wp_unslash($_POST['title'] ?? ''),
            'slug' => wp_unslash($_POST['slug'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'status' => wp_unslash($_POST['status'] ?? 'draft'),
            'layout' => wp_unslash($_POST['layout'] ?? 'grid'),
            'currency' => Repository::currency_locked() ? Repository::default_currency() : wp_unslash($_POST['currency'] ?? ''),
            'settings' => $settings,
        ];

        $board = $id ? Repository::update_board($id, $data) : null;
        if (!$id) {
            $id = Repository::create_board($data);
            $board = $id ? Repository::find_board($id) : null;
        }

        $args = ['page' => 'mds3-sponsorboard', 'tab' => 'boards', 'saved' => $board ? 'board' : '0'];
        if ($board) {
            $args['board_id'] = absint($board['id'] ?? $id);
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Save a slot from admin.
     *
     * @return void
     */
    public function save_slot() {
        $this->require_admin('mds_sponsorboard_save_slot');
        $board_id = absint($_POST['board_id'] ?? 0);
        $slot_id = absint($_POST['slot_id'] ?? 0);
        $data = [
            'board_id' => $board_id,
            'title' => wp_unslash($_POST['title'] ?? ''),
            'slug' => wp_unslash($_POST['slug'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'price' => wp_unslash($_POST['price'] ?? 0),
            'currency' => Repository::currency_locked() ? Repository::default_currency() : wp_unslash($_POST['currency'] ?? ''),
            'status' => wp_unslash($_POST['status'] ?? 'available'),
            'position' => wp_unslash($_POST['position'] ?? 0),
            'creative_type' => wp_unslash($_POST['creative_type'] ?? 'image'),
            'dimensions' => wp_unslash($_POST['dimensions'] ?? ''),
        ];

        $slot = $slot_id ? Repository::update_slot($slot_id, $data) : null;
        if (!$slot_id) {
            $slot_id = Repository::create_slot($data);
            $slot = $slot_id ? Repository::find_slot($slot_id) : null;
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-sponsorboard',
            'board_id' => $board_id,
            'tab' => 'slots',
            'saved' => $slot ? 'slot' : '0',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Save a booking status from admin.
     *
     * @return void
     */
    public function save_booking() {
        $this->require_admin('mds_sponsorboard_save_booking');
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $booking = $booking_id ? Repository::find_booking($booking_id) : null;
        if (!$booking) {
            wp_safe_redirect(add_query_arg(['page' => 'mds3-sponsorboard', 'tab' => 'bookings', 'saved' => '0'], admin_url('admin.php')));
            exit;
        }

        $status = sanitize_key(wp_unslash($_POST['status'] ?? $booking['status'] ?? 'requested'));
        if (!$this->booking_status_allowed($status)) {
            $status = sanitize_key((string) ($booking['status'] ?? 'requested'));
        }

        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $creative_status = sanitize_key(wp_unslash($_POST['creative_status'] ?? $creative['creative_status'] ?? 'pending_review'));
        if (!isset($this->creative_statuses()[$creative_status])) {
            $creative_status = 'pending_review';
        }
        $creative['creative_status'] = $creative_status;
        $creative['creative_moderated_at'] = current_time('mysql');
        $creative['creative_moderated_by'] = get_current_user_id();

        $target_slot_id = absint($_POST['slot_id'] ?? $booking['slot_id'] ?? 0);
        $starts_at = BookingLifecycle::from_site_input(wp_unslash($_POST['starts_at'] ?? BookingLifecycle::to_site_input($booking['starts_at'] ?? '')));
        $ends_at = BookingLifecycle::from_site_input(wp_unslash($_POST['ends_at'] ?? BookingLifecycle::to_site_input($booking['ends_at'] ?? '')));
        $schedule_error = is_wp_error($starts_at) ? $starts_at : (is_wp_error($ends_at) ? $ends_at : Repository::validate_booking_schedule($target_slot_id, $starts_at, $ends_at, $status, $booking_id));
        if (is_wp_error($schedule_error)) {
            $this->admin_notice('error', $schedule_error->get_error_message());
            wp_safe_redirect(add_query_arg([
                'page' => 'mds3-sponsorboard',
                'board_id' => absint($booking['board_id'] ?? 0),
                'tab' => 'bookings',
                'saved' => '0',
            ], admin_url('admin.php')));
            exit;
        }

        if ($target_slot_id !== absint($booking['slot_id'] ?? 0)) {
            $moved = Repository::move_booking($booking_id, $target_slot_id);
            if (is_wp_error($moved)) {
                $this->admin_notice('error', $moved->get_error_message());
                wp_safe_redirect(add_query_arg([
                    'page' => 'mds3-sponsorboard',
                    'board_id' => absint($booking['board_id'] ?? 0),
                    'tab' => 'bookings',
                    'saved' => '0',
                ], admin_url('admin.php')));
                exit;
            }
            $booking = $moved;
        }

        $updated = Repository::update_booking($booking_id, array_merge($booking, [
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'creative_payload' => $creative,
        ]));
        if ($updated) {
            $this->sync_slot_for_booking_status($updated);
            Notifications::booking_updated($updated);
        } else {
            $this->admin_notice('error', __('The booking could not be saved because its schedule or inventory changed. Review it and try again.', 'mds-sponsorboard'));
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-sponsorboard',
            'board_id' => absint($booking['board_id'] ?? 0),
            'tab' => 'bookings',
            'saved' => $updated ? 'booking' : '0',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Save local analytics retention settings.
     *
     * @return void
     */
    public function save_analytics_settings() {
        $this->require_admin('mds_sponsorboard_save_analytics_settings');
        Repository::save_analytics_settings($_POST);
        if (!empty($_POST['analytics_cleanup_now'])) {
            $cleanup = Repository::cleanup_old_events();
            $this->admin_notice('success', sprintf(
                /* translators: 1: rolled-up groups, 2: deleted raw rows. */
                __('Analytics cleanup finished. Rolled up %1$s groups and deleted %2$s raw events.', 'mds-sponsorboard'),
                number_format_i18n(absint($cleanup['rolled_up'] ?? 0)),
                number_format_i18n(absint($cleanup['deleted'] ?? 0))
            ));
        }

        wp_safe_redirect(add_query_arg(['page' => 'mds3-sponsorboard', 'tab' => 'overview', 'saved' => 'analytics'], admin_url('admin.php')));
        exit;
    }

    public function export_bookings() {
        $this->start_export('bookings', 'mds_sponsorboard_export_bookings');
    }

    public function export_events() {
        $this->start_export('events', 'mds_sponsorboard_export_events');
    }

    public function download_export() {
        $this->require_admin('mds_sponsorboard_download_export');
        ExportJobManager::download(wp_unslash($_GET['job_id'] ?? ''));
    }

    private function require_admin($nonce_action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage SponsorBoard.', 'mds-sponsorboard'));
        }

        check_admin_referer($nonce_action);
    }

    private function admin_notice($type, $message) {
        set_transient('mds_sponsorboard_admin_notice_' . get_current_user_id(), [
            'type' => sanitize_key((string) $type),
            'message' => sanitize_text_field((string) $message),
        ], MINUTE_IN_SECONDS);
    }

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

    private function start_export($type, $nonce_action) {
        $this->require_admin($nonce_action);

        $board_id = absint($_GET['board_id'] ?? 0);
        $job_id = sanitize_text_field(wp_unslash($_GET['job_id'] ?? ''));
        $job = ExportJobManager::start_or_continue($type, $board_id, $job_id);
        $args = [
            'page' => 'mds3-sponsorboard',
            'board_id' => $board_id,
            'tab' => 'bookings',
        ];

        if (is_wp_error($job)) {
            $this->admin_notice('error', $job->get_error_message());
            $args['saved'] = '0';
        } else {
            $args['sponsorboard_export'] = sanitize_text_field((string) ($job['id'] ?? ''));
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function sync_slot_for_booking_status(array $booking) {
        Repository::refresh_slot_status(absint($booking['slot_id'] ?? 0));
    }
}
