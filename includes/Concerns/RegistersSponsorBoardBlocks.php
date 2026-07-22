<?php
/**
 * SponsorBoard shortcode, block, and asset registration.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait RegistersSponsorBoardBlocks {

    public function register_shortcodes() {
        add_shortcode('mds_sponsorboard', [$this, 'shortcode']);
    }

    public function frontend_assets() {
        wp_register_style('mds-sponsorboard', MDS_SPONSORBOARD_URL . 'assets/css/style.css', [], MDS_SPONSORBOARD_VERSION);
        wp_register_script('mds-sponsorboard', MDS_SPONSORBOARD_URL . 'assets/js/script.js', [], MDS_SPONSORBOARD_VERSION, true);
        \MillionDollarScript\Extensions\Support::add_browser_config('mds-sponsorboard', 'sponsorBoard', 'config', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mds_sponsorboard_track'),
        ]);
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
            'title' => __('SponsorBoard', 'mds-sponsorboard'),
            'category' => 'million-dollar-script',
            'icon' => 'megaphone',
            'description' => __('Display a SponsorBoard from Million Dollar Script.', 'mds-sponsorboard'),
            'keywords' => [
                __('MDS', 'mds-sponsorboard'),
                __('Million Dollar Script', 'mds-sponsorboard'),
                __('SponsorBoard', 'mds-sponsorboard'),
                __('sponsors', 'mds-sponsorboard'),
            ],
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
        $script_path = MDS_SPONSORBOARD_PATH . 'assets/js/blocks.js';

        wp_enqueue_script(
            'mds-sponsorboard-blocks',
            MDS_SPONSORBOARD_URL . 'assets/js/blocks.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n'],
            $this->block_asset_version($script_path),
            true
        );
        $this->enqueue_block_editor_styles();
        \MillionDollarScript\Extensions\Support::add_browser_config('mds-sponsorboard-blocks', 'sponsorBoard', 'blocks', [
            'boards' => $this->block_boards(),
            'blockApiVersion' => $this->block_api_version(),
            'blockCategory' => 'million-dollar-script',
        ]);
    }

    public function block_canvas_assets() {
        if (!is_admin()) {
            return;
        }

        $this->enqueue_block_editor_styles();
    }

    private function enqueue_block_editor_styles() {
        $style_path = MDS_SPONSORBOARD_PATH . 'assets/css/blocks.css';

        wp_enqueue_style('mds-sponsorboard-blocks', MDS_SPONSORBOARD_URL . 'assets/css/blocks.css', [], $this->block_asset_version($style_path));
    }

    private function block_asset_version($path) {
        $mtime = file_exists((string) $path) ? filemtime((string) $path) : false;

        return $mtime ? MDS_SPONSORBOARD_VERSION . '.' . $mtime : MDS_SPONSORBOARD_VERSION;
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

    private function enqueue_frontend_assets() {
        if (!wp_style_is('mds-sponsorboard', 'registered')) {
            wp_register_style('mds-sponsorboard', MDS_SPONSORBOARD_URL . 'assets/css/style.css', [], MDS_SPONSORBOARD_VERSION);
        }
        if (!wp_script_is('mds-sponsorboard', 'registered')) {
            wp_register_script('mds-sponsorboard', MDS_SPONSORBOARD_URL . 'assets/js/script.js', [], MDS_SPONSORBOARD_VERSION, true);
            \MillionDollarScript\Extensions\Support::add_browser_config('mds-sponsorboard', 'sponsorBoard', 'config', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mds_sponsorboard_track'),
            ]);
        }

        wp_enqueue_style('mds-sponsorboard');
        wp_enqueue_script('mds-sponsorboard');
    }
}
