<?php
/**
 * SponsorBoard admin UI.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin screens and actions.
 */
class Admin {
    use Concerns\HandlesSponsorBoardAdminRequests;
    use Concerns\RendersSponsorBoardAdminPage;
    use Concerns\RendersSponsorBoardAdminFields;

    /**
     * Singleton instance.
     *
     * @var Admin|null
     */
    private static $instance = null;

    /**
     * Whether hooks have been registered.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return Admin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public static function init() {
        self::instance()->initialize();
    }

    /**
     * Register hooks once.
     *
     * @return void
     */
    private function initialize() {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;
        add_action('million-dollar-script/admin/menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('million-dollar-script/dashboard/extension/cards', [$this, 'dashboard_extension_cards']);
        add_action('admin_post_mds_sponsorboard_save_board', [$this, 'save_board']);
        add_action('admin_post_mds_sponsorboard_save_slot', [$this, 'save_slot']);
        add_action('admin_post_mds_sponsorboard_save_booking', [$this, 'save_booking']);
        add_action('admin_post_mds_sponsorboard_save_hosted_settings', [$this, 'save_hosted_settings']);
        add_action('admin_post_mds_sponsorboard_export_bookings', [$this, 'export_bookings']);
        add_action('admin_post_mds_sponsorboard_export_events', [$this, 'export_events']);
        add_action('admin_post_mds_sponsorboard_download_export', [$this, 'download_export']);
    }

    /**
     * Register the extension page.
     *
     * @return void
     */
    public function menu() {
        add_submenu_page(
            'mds3',
            __('SponsorBoard', 'mds-sponsorboard'),
            __('SponsorBoard', 'mds-sponsorboard'),
            'manage_options',
            'mds3-sponsorboard',
            [$this, 'page']
        );
    }

    /**
     * Enqueue extension admin assets on the SponsorBoard page.
     *
     * @param string $hook Current page hook.
     * @return void
     */
    public function assets($hook) {
        if (false === strpos((string) $hook, 'mds3-sponsorboard')) {
            return;
        }

        wp_enqueue_style('mds-sponsorboard-admin', MDS_SPONSORBOARD_URL . 'assets/css/admin.css', [], MDS_SPONSORBOARD_VERSION);
    }

    /**
     * Add SponsorBoard quick actions to the dashboard Extensions section.
     *
     * @param array $cards Dashboard extension cards keyed by slug.
     * @return array
     */
    public function dashboard_extension_cards(array $cards) {
        $slug = 'mds-sponsorboard';
        $card = isset($cards[$slug]) && is_array($cards[$slug]) ? $cards[$slug] : [
            'slug' => $slug,
            'name' => __('SponsorBoard', 'mds-sponsorboard'),
            'description' => __('Sell fixed sponsor slots, featured placements, launch partners, and supporter inventory alongside your pixel grids.', 'mds-sponsorboard'),
            'active' => true,
            'installed' => true,
            'actions' => [],
        ];

        $card['description'] = __('Sell fixed sponsor slots, featured placements, launch partners, and supporter inventory alongside your pixel grids.', 'mds-sponsorboard');
        $card['actions'] = [
            [
                'label' => __('Manage sponsors', 'mds-sponsorboard'),
                'url' => admin_url('admin.php?page=mds3-sponsorboard'),
                'primary' => true,
                'icon' => 'dashicons-megaphone',
            ],
            [
                'label' => __('Docs', 'mds-sponsorboard'),
                'url' => add_query_arg([
                    'page' => 'mds3-docs',
                    'package' => $slug,
                    'doc' => 'usage',
                ], admin_url('admin.php')),
                'icon' => 'dashicons-book',
            ],
        ];
        $cards[$slug] = $card;

        return $cards;
    }
}
