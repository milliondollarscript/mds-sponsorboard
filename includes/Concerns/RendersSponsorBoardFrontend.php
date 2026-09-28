<?php
/**
 * SponsorBoard shortcode output, request forms, and sponsor manage pages.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersSponsorBoardFrontend {

    public function maybe_render_manage_page() {
        $booking_id = absint($_GET['mds_sponsorboard_manage'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        if (!$booking_id || !$token) {
            return;
        }

        $booking = Repository::find_booking_by_token($booking_id, $token);
        if (!$booking) {
            status_header(404);
            wp_die(esc_html__('This sponsor manage link is invalid or expired.', 'mds-sponsorboard'));
        }

        $this->enqueue_frontend_assets();
        nocache_headers();
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
            header('Referrer-Policy: no-referrer', true);
        }

        $this->render_manage_document($booking, $token);
        exit;
    }

    /**
     * Render a sponsor board shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */

    public function shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'slug' => '',
            'show_available' => '1',
            'preview' => '0',
        ], is_array($atts) ? $atts : [], 'mds_sponsorboard');

        $board = null;
        if (!empty($atts['id'])) {
            $board = Repository::find_board(absint($atts['id']));
        } elseif (!empty($atts['slug'])) {
            $board = Repository::find_board_by_slug((string) $atts['slug']);
        } else {
            foreach ($this->default_board_candidates() as $candidate) {
                $board = $candidate;
                break;
            }
        }

        if (!$board) {
            return '';
        }

        $preview = $this->truthy($atts['preview'] ?? '0') && current_user_can('manage_options');
        if (!$preview && 'active' !== (string) ($board['status'] ?? '')) {
            return '';
        }

        $this->enqueue_frontend_assets();

        return $this->render_board($board, [
            'show_available' => $this->truthy($atts['show_available'] ?? '1'),
            'preview' => $preview,
        ]);
    }

    private function render_board(array $board, array $options) {
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        $columns = min(12, max(1, absint($settings['columns'] ?? 3)));
        $disclosure = sanitize_text_field((string) ($settings['disclosure_label'] ?? __('Sponsored', 'mds-sponsorboard')));
        $layout = sanitize_html_class((string) ($board['layout'] ?? 'grid'));
        $layout = in_array($layout, ['grid', 'strip', 'mosaic', 'cards'], true) ? $layout : 'grid';
        $show_available = !empty($options['show_available']);
        $slots = array_filter(Repository::slots_for_board(absint($board['id'] ?? 0)), static function ($slot) use ($show_available) {
            $status = (string) ($slot['status'] ?? '');

            return 'archived' !== $status && ($show_available || 'available' !== $status);
        });

        $public_bookings = [];
        foreach ($slots as $slot) {
            $slot_id = absint($slot['id'] ?? 0);
            if ($slot_id) {
                $public_bookings[$slot_id] = Repository::public_booking_for_slot($slot_id);
            }
        }

        return $this->render_template('frontend/board.php', [
            'board' => $board,
            'columns' => $columns,
            'disclosure' => $disclosure,
            'layout' => $layout,
            'public_bookings' => $public_bookings,
            'slots' => $slots,
        ]);
    }

    private function default_board_candidates() {
        $boards = Repository::all_boards(100);
        $active = array_values(array_filter($boards, static function ($board) {
            return 'active' === (string) ($board['status'] ?? '');
        }));

        return $active ?: $boards;
    }

    private function checkout_form(array $board, array $slot) {
        return $this->render_template('frontend/checkout-form.php', [
            'board' => $board,
            'slot' => $slot,
        ]);
    }

    private function request_form(array $board, array $slot) {
        return $this->render_template('frontend/request-form.php', [
            'board' => $board,
            'slot' => $slot,
        ]);
    }

    private function render_manage_page(array $booking, $token) {
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        $slot = Repository::find_slot(absint($booking['slot_id'] ?? 0));

        return $this->render_template('frontend/manage-page.php', [
            'booking' => $booking,
            'creative' => $creative,
            'stats' => Repository::booking_event_summary(absint($booking['id'] ?? 0)),
            'slot' => is_array($slot) ? $slot : [],
            'token' => $token,
        ]);
    }

    private function render_manage_document(array $booking, $token) {
        add_filter('wp_robots', static function ($robots) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;

            return $robots;
        });

        status_header(200);
        echo $this->render_template('frontend/manage-document.php', [
            'booking' => $booking,
            'token' => $token,
        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function status_label($status) {
        $labels = [
            'available' => __('Available', 'mds-sponsorboard'),
            'reserved' => __('Reserved', 'mds-sponsorboard'),
            'sold' => __('Sold', 'mds-sponsorboard'),
            'paused' => __('Paused', 'mds-sponsorboard'),
            'archived' => __('Archived', 'mds-sponsorboard'),
        ];

        return $labels[sanitize_key((string) $status)] ?? ucfirst((string) $status);
    }

    private function booking_status_label($status) {
        $labels = [
            'requested' => __('Requested', 'mds-sponsorboard'),
            'pending_payment' => __('Pending payment', 'mds-sponsorboard'),
            'paid' => __('Paid', 'mds-sponsorboard'),
            'approved' => __('Approved', 'mds-sponsorboard'),
            'changes_requested' => __('Changes requested', 'mds-sponsorboard'),
            'rejected' => __('Rejected', 'mds-sponsorboard'),
            'active' => __('Active', 'mds-sponsorboard'),
            'expired' => __('Expired', 'mds-sponsorboard'),
            'cancelled' => __('Cancelled', 'mds-sponsorboard'),
        ];

        return $labels[sanitize_key((string) $status)] ?? ucfirst((string) $status);
    }

    private function creative_status_label($status) {
        $labels = [
            'pending_review' => __('Pending review', 'mds-sponsorboard'),
            'approved' => __('Approved', 'mds-sponsorboard'),
            'changes_requested' => __('Changes requested', 'mds-sponsorboard'),
            'rejected' => __('Rejected', 'mds-sponsorboard'),
        ];

        return $labels[sanitize_key((string) $status)] ?? __('Pending review', 'mds-sponsorboard');
    }

    private function render_public_creative(array $booking) {
        $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
        if ('approved' !== sanitize_key((string) ($creative['creative_status'] ?? ''))) {
            return '';
        }

        $attachment_id = absint($booking['creative_id'] ?? $creative['attachment_id'] ?? 0);
        $image = '';
        if ($attachment_id) {
            $image = wp_get_attachment_image($attachment_id, 'medium_large', false, [
                'class' => 'mds-sponsorboard__creative-image',
                'loading' => 'lazy',
            ]);
        }

        $name = trim((string) ($booking['sponsor_name'] ?? ''));
        $url = esc_url((string) ($booking['sponsor_url'] ?? ''));
        $label = $name ?: __('Sponsor', 'mds-sponsorboard');
        if ($url) {
            $label = sprintf(
                '<a href="%s" target="_blank" rel="nofollow sponsored noopener noreferrer" data-mds-sponsorboard-track="click">%s</a>',
                $url,
                esc_html($label)
            );
        } else {
            $label = esc_html($label);
        }

        return '<div class="mds-sponsorboard__creative">' . $image . '<span>' . $label . '</span></div>';
    }

    private function theme_mode() {
        return \MillionDollarScript\V3\Support\ThemeMode::mode();
    }

    private function effective_currency($currency = '') {
        return Repository::effective_currency($currency);
    }
}
