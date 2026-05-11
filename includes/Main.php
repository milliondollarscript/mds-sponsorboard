<?php
/**
 * SponsorBoard runtime hooks, REST API, and frontend rendering.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates SponsorBoard extension behavior.
 */
class Main {
    use Concerns\RegistersSponsorBoardBlocks;
    use Concerns\HandlesSponsorBookings;
    use Concerns\HandlesSponsorBoardRestApi;
    use Concerns\RendersSponsorBoardFrontend;
    use Concerns\SupportsSponsorBoardRuntime;

    private const REST_NAMESPACE = 'mds/v3';
    private const REST_BASE = '/sponsorboard';

    /**
     * Singleton instance.
     *
     * @var Main|null
     */
    private static $instance = null;

    /**
     * Whether hooks are already registered.
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Get singleton instance.
     *
     * @return Main
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize hooks.
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
        Repository::maybe_install();

        if (is_admin()) {
            Admin::init();
        }

        add_action('init', [$this, 'register_shortcodes']);
        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'block_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('template_redirect', [$this, 'maybe_render_manage_page']);
        add_action('admin_post_mds_sponsorboard_request_booking', [$this, 'handle_booking_request']);
        add_action('admin_post_nopriv_mds_sponsorboard_request_booking', [$this, 'handle_booking_request']);
        add_action('admin_post_mds_sponsorboard_start_checkout', [$this, 'handle_checkout_start']);
        add_action('admin_post_nopriv_mds_sponsorboard_start_checkout', [$this, 'handle_checkout_start']);
        add_action('admin_post_mds_sponsorboard_update_booking', [$this, 'handle_booking_update']);
        add_action('admin_post_nopriv_mds_sponsorboard_update_booking', [$this, 'handle_booking_update']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('mds_sponsorboard_payment_status', [$this, 'handle_provider_payment_status'], 10, 3);
        add_action('mds3_payment_source_status', [$this, 'handle_core_payment_source_status'], 10, 4);
        add_filter('mds_sponsorboard_manage_url', [$this, 'filter_manage_url'], 10, 3);
        add_filter('mds3_api_endpoint_manifest', [$this, 'api_endpoint_manifest']);
    }
}
