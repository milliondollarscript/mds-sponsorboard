<?php
/**
 * SponsorBoard admin page panels, tables, and board management forms.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersSponsorBoardAdminPage {

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage SponsorBoard.', 'mds-sponsorboard'));
        }

        Repository::maybe_install();

        $boards = Repository::all_boards(100);
        $selected_id = absint($_GET['board_id'] ?? 0);
        $selected = $selected_id ? Repository::find_board($selected_id) : ($boards[0] ?? null);
        $selected_id = absint($selected['id'] ?? 0);
        $slots = $selected_id ? Repository::slots_for_board($selected_id) : [];
        $totals = $this->totals($boards);

        $this->admin_template('page.php', compact('boards', 'selected', 'selected_id', 'slots', 'totals'));
    }

    private function notices() {
        $saved = sanitize_key(wp_unslash($_GET['saved'] ?? ''));
        $activated = (bool) get_transient('mds_sponsorboard_activated');
        if (get_transient('mds_sponsorboard_activated')) {
            delete_transient('mds_sponsorboard_activated');
        }

        $this->admin_template('partials/notices.php', compact('activated', 'saved'));
    }

    private function metric($label, $value, $description) {
        $this->admin_template('partials/metric.php', compact('description', 'label', 'value'));
    }

    private function board_list(array $boards, $selected_id) {
        $items = [];
        foreach ($boards as $board) {
            $id = absint($board['id'] ?? 0);
            $counts = Repository::board_counts($id);
            $slots = array_sum(array_map('absint', $counts['slots'] ?? []));
            $items[] = [
                'is_selected' => $id === absint($selected_id),
                'slot_count' => $slots,
                'status' => $this->status_label((string) ($board['status'] ?? 'draft')),
                'title' => (string) ($board['title'] ?? __('Untitled board', 'mds-sponsorboard')),
                'url' => add_query_arg(['page' => 'mds3-sponsorboard', 'board_id' => $id], admin_url('admin.php')),
            ];
        }

        $this->admin_template('partials/board-list.php', compact('items'));
    }

    private function board_form($board) {
        $board = is_array($board) ? $board : [];
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];

        $this->admin_template('partials/board-form.php', compact('board', 'settings'));
    }

    private function slot_create_form(array $board) {
        $position = count(Repository::slots_for_board(absint($board['id'] ?? 0))) + 1;
        $this->admin_template('partials/slot-create-form.php', compact('board', 'position'));
    }

    private function slot_table(array $board, array $slots) {
        $this->admin_template('partials/slot-list.php', compact('board', 'slots'));
    }

    private function booking_table(array $board, array $bookings) {
        unset($board);
        $this->admin_template('partials/booking-list.php', compact('bookings'));
    }
}
