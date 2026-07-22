<?php
/**
 * SponsorBoard admin page panels, tables, and board management forms.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\ExportJobManager;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

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

        $board_page = Repository::boards_page(max(1, absint($_GET['board_page'] ?? 1)), 20);
        $boards = $board_page['items'];
        $selected_id = absint($_GET['board_id'] ?? 0);
        $selected = isset($_GET['board_id'])
            ? ($selected_id ? Repository::find_board($selected_id) : null)
            : ($boards[0] ?? null);
        $selected_id = absint($selected['id'] ?? 0);
        $slots = $selected_id ? Repository::slots_for_board($selected_id) : [];
        $totals = $this->totals(Repository::all_boards(1000));
        $booking_filters = [
            'status' => sanitize_key(wp_unslash($_GET['booking_status'] ?? '')),
            'search' => sanitize_text_field(wp_unslash($_GET['booking_search'] ?? '')),
        ];
        $booking_page = $selected_id
            ? Repository::bookings_page($selected_id, max(1, absint($_GET['booking_page'] ?? 1)), 20, $booking_filters)
            : ['items' => [], 'page' => 1, 'per_page' => 20, 'total' => 0, 'pages' => 1];
        $selected_report = $selected_id ? Repository::reporting_summary($selected_id) : [];
        $hosted = \MillionDollarScript\Extensions\SponsorBoard\HostedClient::settings();
        $analytics = Repository::analytics_settings();
        $export_status = ExportJobManager::current_status();
        $default_tab = $selected_id ? 'bookings' : 'overview';
        $active_tab = sanitize_key(wp_unslash($_GET['tab'] ?? $default_tab));
        $allowed_tabs = ['overview', 'boards', 'hosted'];
        if ($selected_id) {
            $allowed_tabs = array_merge($allowed_tabs, ['slots', 'bookings']);
        }
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = $default_tab;
        }

        $this->admin_template('page.php', compact('active_tab', 'analytics', 'board_page', 'boards', 'booking_filters', 'booking_page', 'export_status', 'hosted', 'selected', 'selected_id', 'selected_report', 'slots', 'totals'));
    }

    private function notices() {
        $saved = sanitize_key(wp_unslash($_GET['saved'] ?? ''));
        $activated = (bool) get_transient('mds_sponsorboard_activated');
        if (get_transient('mds_sponsorboard_activated')) {
            delete_transient('mds_sponsorboard_activated');
        }
        $hosted_notice = get_transient('mds_sponsorboard_hosted_notice_' . get_current_user_id());
        if ($hosted_notice) {
            delete_transient('mds_sponsorboard_hosted_notice_' . get_current_user_id());
        }

        $this->admin_template('partials/notices.php', compact('activated', 'hosted_notice', 'saved'));
    }

    private function metric($label, $value, $description) {
        $this->admin_template('partials/metric.php', compact('description', 'label', 'value'));
    }

    private function board_list(array $boards, $selected_id, $active_tab = 'boards') {
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
                'url' => add_query_arg(['page' => 'mds3-sponsorboard', 'board_id' => $id, 'tab' => sanitize_key((string) $active_tab)], admin_url('admin.php')),
            ];
        }

        $this->admin_template('partials/board-list.php', compact('items'));
    }

    private function board_form($board) {
        $board = is_array($board) ? $board : [];
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];

        $this->admin_template('partials/board-form.php', compact('board', 'settings'));
    }

    private function hosted_service_panel(array $hosted, array $analytics) {
        $this->admin_template('partials/hosted-service.php', compact('analytics', 'hosted'));
    }

    private function export_status_panel($export_status) {
        $export_status = is_array($export_status) ? $export_status : [];
        if (!$export_status) {
            return;
        }

        $this->admin_template('partials/export-status.php', compact('export_status'));
    }

    private function slot_create_form(array $board) {
        $position = count(Repository::slots_for_board(absint($board['id'] ?? 0))) + 1;
        $this->admin_template('partials/slot-create-form.php', compact('board', 'position'));
    }

    private function slot_table(array $board, array $slots) {
        $this->admin_template('partials/slot-list.php', compact('board', 'slots'));
    }

    private function booking_table(array $board, array $bookings, array $slots) {
        $this->admin_template('partials/booking-list.php', compact('board', 'bookings', 'slots'));
    }
}
