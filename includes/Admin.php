<?php
/**
 * SponsorBoard admin UI.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard;

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
        add_action('mds3_admin_menu', [$this, 'menu']);
        add_action('admin_head', [$this, 'hide_sidebar_menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('mds3_dashboard_menu_items', [$this, 'dashboard_menu_items']);
        add_filter('mds3_dashboard_service_cards', [$this, 'dashboard_service_cards']);
        add_action('admin_post_mds_sponsorboard_save_board', [$this, 'save_board']);
        add_action('admin_post_mds_sponsorboard_save_slot', [$this, 'save_slot']);
        add_action('admin_post_mds_sponsorboard_save_booking', [$this, 'save_booking']);
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
     * Keep the WP sidebar focused on Dashboard and Extensions.
     *
     * @return void
     */
    public function hide_sidebar_menu() {
        remove_submenu_page('mds3', 'mds3-sponsorboard');
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
     * Add SponsorBoard to the Million Dollar Script dashboard menu.
     *
     * @param array $groups Menu groups.
     * @return array
     */
    public function dashboard_menu_items(array $groups) {
        if (!isset($groups['manage']['items']) || !is_array($groups['manage']['items'])) {
            $groups['manage'] = [
                'label' => __('Manage', 'mds-sponsorboard'),
                'items' => [],
            ];
        }

        $groups['manage']['items'][] = [
            'label' => __('SponsorBoard', 'mds-sponsorboard'),
            'url' => admin_url('admin.php?page=mds3-sponsorboard'),
            'icon' => 'dashicons-megaphone',
        ];

        return $groups;
    }

    /**
     * Add an active-extension service card.
     *
     * @param array $cards Dashboard service cards.
     * @return array
     */
    public function dashboard_service_cards(array $cards) {
        array_unshift($cards, [
            'title' => __('SponsorBoard', 'mds-sponsorboard'),
            'description' => __('Sell fixed sponsor slots, featured placements, launch partners, and supporter inventory alongside your pixel grids.', 'mds-sponsorboard'),
            'url' => admin_url('admin.php?page=mds3-sponsorboard'),
            'label' => __('Manage sponsors', 'mds-sponsorboard'),
            'primary' => false,
        ]);

        return $cards;
    }
}
