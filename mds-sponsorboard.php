<?php
/**
 * Plugin Name: Million Dollar Script SponsorBoard
 * Plugin URI: https://milliondollarscript.com/extensions/sponsorboard
 * Description: Sell and manage sponsor boards, slots, bookings, and creatives from Million Dollar Script.
 * Version: 0.1.0
 * Author: Million Dollar Script
 * Author URI: https://milliondollarscript.com
 * Text Domain: mds-sponsorboard
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: million-dollar-script
 *
 * Requires MDS: 3.0.0
 * Requires License: No
 * MDS Provides: inventory.sponsorboard sponsorship.inventory
 * MDS Requires: platform.core
 * MDS Recommends: mds-woocommerce mds-grid
 * MDS Requires Service: no
 * MDS Setup Category: monetization
 * MDS Minimum Security Level: api_key_read
 * MDS LLM Safe Actions: read_boards read_slots read_bookings
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MDS_SPONSORBOARD_VERSION')) {
    define('MDS_SPONSORBOARD_VERSION', '0.1.0');
}

if (!defined('MDS_SPONSORBOARD_FILE')) {
    define('MDS_SPONSORBOARD_FILE', __FILE__);
}

if (!defined('MDS_SPONSORBOARD_PATH')) {
    define('MDS_SPONSORBOARD_PATH', plugin_dir_path(__FILE__));
}

if (!defined('MDS_SPONSORBOARD_URL')) {
    define('MDS_SPONSORBOARD_URL', plugin_dir_url(__FILE__));
}

if (!defined('MDS_SPONSORBOARD_BASENAME')) {
    define('MDS_SPONSORBOARD_BASENAME', plugin_basename(__FILE__));
}

/**
 * Check whether Million Dollar Script core is available.
 *
 * @return bool
 */
function mds_sponsorboard_check_mds_active() {
    return (class_exists('\\MDS\\Core\\Plugin') && function_exists('mds_initialize'))
        || (class_exists('\\MDS3\\Plugin') && defined('MDS3_VERSION'));
}

/**
 * Show a dependency notice when core is unavailable.
 *
 * @return void
 */
function mds_sponsorboard_missing_mds_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('Million Dollar Script SponsorBoard', 'mds-sponsorboard') . '</strong>: ';
    echo esc_html__('This extension requires the Million Dollar Script plugin to be installed and activated.', 'mds-sponsorboard');
    echo ' <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Manage Plugins', 'mds-sponsorboard') . '</a>';
    echo '</p></div>';
}

/**
 * Load extension classes.
 *
 * @return void
 */
function mds_sponsorboard_load_files() {
    require_once MDS_SPONSORBOARD_PATH . 'includes/Repository.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/HandlesSponsorBoardAdminRequests.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/RendersSponsorBoardAdminPage.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/RendersSponsorBoardAdminFields.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Admin.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/RegistersSponsorBoardBlocks.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/HandlesSponsorBookings.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/HandlesSponsorBoardRestApi.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/RendersSponsorBoardFrontend.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Concerns/SupportsSponsorBoardRuntime.php';
    require_once MDS_SPONSORBOARD_PATH . 'includes/Main.php';
}

/**
 * Initialize the extension.
 *
 * @return void
 */
function mds_sponsorboard_init() {
    if (!mds_sponsorboard_check_mds_active()) {
        add_action('admin_notices', __NAMESPACE__ . '\\mds_sponsorboard_missing_mds_notice');
        return;
    }

    mds_sponsorboard_load_files();
    Main::init();
}

/**
 * Register extension metadata with older Million Dollar Script runtimes.
 *
 * @return void
 */
function mds_sponsorboard_register_extension() {
    if (!mds_sponsorboard_check_mds_active()) {
        return;
    }

    mds_sponsorboard_load_files();

    if (class_exists('\\MDS\\Core\\Extensions\\Manager')) {
        \MDS\Core\Extensions\Manager::register([
            'id' => 'mds-sponsorboard',
            'name' => 'SponsorBoard',
            'description' => 'Sell and manage sponsor inventory without a hosted service dependency.',
            'version' => MDS_SPONSORBOARD_VERSION,
            'author' => 'Million Dollar Script',
            'url' => 'https://milliondollarscript.com/extensions/sponsorboard',
            'main_file' => __FILE__,
            'premium' => false,
            'license_required' => false,
            'capabilities' => [
                'admin_pages' => true,
                'sponsor_inventory' => true,
                'frontend_assets' => true,
                'api' => true,
            ],
            'init_callback' => ['\\MDS\\Extensions\\SponsorBoard\\Main', 'init'],
        ]);
    }
}

/**
 * Activation hook.
 *
 * @return void
 */
function mds_sponsorboard_activate() {
    if (!mds_sponsorboard_check_mds_active()) {
        deactivate_plugins(MDS_SPONSORBOARD_BASENAME);
        wp_die(
            esc_html__('Million Dollar Script SponsorBoard requires the Million Dollar Script plugin to be installed and activated.', 'mds-sponsorboard'),
            esc_html__('Plugin Activation Error', 'mds-sponsorboard'),
            ['back_link' => true]
        );
    }

    mds_sponsorboard_load_files();
    Repository::install();
    Repository::maybe_seed();
    set_transient('mds_sponsorboard_activated', true, 30);
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function mds_sponsorboard_deactivate() {
    delete_transient('mds_sponsorboard_activated');
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\mds_sponsorboard_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\mds_sponsorboard_deactivate');

add_action('plugins_loaded', __NAMESPACE__ . '\\mds_sponsorboard_init', 20);
add_action('mds_register_extensions', __NAMESPACE__ . '\\mds_sponsorboard_register_extension');
