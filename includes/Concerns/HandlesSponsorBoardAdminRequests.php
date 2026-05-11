<?php
/**
 * SponsorBoard admin POST handlers and save-time synchronization.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

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
        ];
        $data = [
            'title' => wp_unslash($_POST['title'] ?? ''),
            'slug' => wp_unslash($_POST['slug'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'status' => wp_unslash($_POST['status'] ?? 'draft'),
            'layout' => wp_unslash($_POST['layout'] ?? 'grid'),
            'currency' => wp_unslash($_POST['currency'] ?? ''),
            'settings' => $settings,
        ];

        $board = $id ? Repository::update_board($id, $data) : null;
        if (!$id) {
            $id = Repository::create_board($data);
            $board = $id ? Repository::find_board($id) : null;
        }

        $args = ['page' => 'mds3-sponsorboard', 'saved' => $board ? 'board' : '0'];
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
            'currency' => wp_unslash($_POST['currency'] ?? ''),
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
            wp_safe_redirect(add_query_arg(['page' => 'mds3-sponsorboard', 'saved' => '0'], admin_url('admin.php')));
            exit;
        }

        $status = sanitize_key(wp_unslash($_POST['status'] ?? $booking['status'] ?? 'requested'));
        $updated = Repository::update_booking($booking_id, array_merge($booking, ['status' => $status]));
        if ($updated) {
            $this->sync_slot_for_booking_status($updated);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'mds3-sponsorboard',
            'board_id' => absint($booking['board_id'] ?? 0),
            'saved' => $updated ? 'booking' : '0',
        ], admin_url('admin.php')));
        exit;
    }

    private function require_admin($nonce_action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage SponsorBoard.', 'mds-sponsorboard'));
        }

        check_admin_referer($nonce_action);
    }

    private function sync_slot_for_booking_status(array $booking) {
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));
        if (!$slot) {
            return;
        }

        $status = sanitize_key((string) ($booking['status'] ?? 'requested'));
        if (in_array($status, ['paid', 'approved', 'active'], true)) {
            Repository::update_slot(absint($slot['id'] ?? 0), array_merge($slot, ['status' => 'sold']));
        } elseif (in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
            Repository::update_slot(absint($slot['id'] ?? 0), array_merge($slot, ['status' => 'available']));
        } elseif (in_array($status, ['requested', 'pending_payment', 'changes_requested'], true)) {
            Repository::update_slot(absint($slot['id'] ?? 0), array_merge($slot, ['status' => 'reserved']));
        }
    }
}
