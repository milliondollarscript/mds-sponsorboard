<?php
/**
 * SponsorBoard shortcode, block, and asset registration.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait RegistersSponsorBoardBlocks {

    public function register_shortcodes() {
        add_shortcode('mds_sponsorboard', [$this, 'shortcode']);
    }

    /**
     * Enqueue public assets only when WordPress asks for them.
     *
     * @return void
     */

    public function frontend_assets() {
        wp_enqueue_style('mds-sponsorboard', MDS_SPONSORBOARD_URL . 'assets/css/style.css', [], MDS_SPONSORBOARD_VERSION);
    }

    /**
     * Register the dynamic SponsorBoard block.
     *
     * @return void
     */

    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('mds/sponsorboard', [
            'api_version' => $this->block_api_version(),
            'title' => __('Million Dollar Script SponsorBoard', 'mds-sponsorboard'),
            'category' => 'widgets',
            'icon' => 'megaphone',
            'description' => __('Display a SponsorBoard from Million Dollar Script.', 'mds-sponsorboard'),
            'attributes' => [
                'id' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'showAvailable' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'preview' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    /**
     * Enqueue block editor assets.
     *
     * @return void
     */

    public function block_editor_assets() {
        wp_enqueue_script(
            'mds-sponsorboard-blocks',
            MDS_SPONSORBOARD_URL . 'assets/js/blocks.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n'],
            MDS_SPONSORBOARD_VERSION,
            true
        );
        wp_enqueue_style('mds-sponsorboard-blocks', MDS_SPONSORBOARD_URL . 'assets/css/blocks.css', [], MDS_SPONSORBOARD_VERSION);
        wp_localize_script('mds-sponsorboard-blocks', 'MDSSponsorBoardBlocks', [
            'boards' => $this->block_boards(),
            'blockApiVersion' => $this->block_api_version(),
        ]);
    }

    /**
     * Render the dynamic block.
     *
     * @param array $attributes Block attributes.
     * @return string
     */

    public function render_block(array $attributes) {
        $shortcode = sprintf(
            '[mds_sponsorboard id="%d" show_available="%s" preview="%s"]',
            absint($attributes['id'] ?? 0),
            !empty($attributes['showAvailable']) ? '1' : '0',
            !empty($attributes['preview']) ? '1' : '0'
        );

        return do_shortcode($shortcode);
    }

    /**
     * Handle a public sponsor booking request.
     *
     * @return void
     */

    private function block_boards() {
        $boards = [];
        foreach (Repository::all_boards(100) as $board) {
            $counts = Repository::board_counts(absint($board['id'] ?? 0));
            $slot_counts = is_array($counts['slots'] ?? null) ? $counts['slots'] : [];
            $boards[] = [
                'id' => absint($board['id'] ?? 0),
                'title' => (string) ($board['title'] ?? __('Untitled Sponsor Board', 'mds-sponsorboard')),
                'status' => (string) ($board['status'] ?? 'draft'),
                'layout' => (string) ($board['layout'] ?? 'grid'),
                'slots' => array_sum(array_map('absint', $slot_counts)),
                'available' => absint($slot_counts['available'] ?? 0),
                'sold' => absint($slot_counts['sold'] ?? 0),
                'reserved' => absint($slot_counts['reserved'] ?? 0),
            ];
        }

        return $boards;
    }

    private function block_api_version() {
        $wp_version = function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';

        return version_compare($wp_version, '6.3', '>=') ? 3 : 2;
    }
}
