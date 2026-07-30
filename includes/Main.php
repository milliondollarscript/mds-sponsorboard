<?php
/**
 * SponsorBoard runtime hooks, REST API, and frontend rendering.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates SponsorBoard extension behavior.
 */
class Main {
    use Concerns\RegistersSponsorBoardBlocks;
    use Concerns\HandlesSponsorBoardAnalytics;
    use Concerns\HandlesSponsorBookings;
    use Concerns\HandlesSponsorCreativeUploads;
    use Concerns\HandlesSponsorBoardRestApi;
    use Concerns\RendersSponsorBoardFrontend;
    use Concerns\SupportsSponsorBoardRuntime;

    private const REST_NAMESPACE = 'million-dollar-script/v1';
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
        BookingLifecycle::register();

        if (is_admin()) {
            Admin::init();
        }

        add_action('init', [$this, 'register_shortcodes']);
        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'block_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'block_canvas_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_action('template_redirect', [$this, 'maybe_render_manage_page']);
        add_action('admin_post_mds_sponsorboard_request_booking', [$this, 'handle_booking_request']);
        add_action('admin_post_nopriv_mds_sponsorboard_request_booking', [$this, 'handle_booking_request']);
        add_action('admin_post_mds_sponsorboard_start_checkout', [$this, 'handle_checkout_start']);
        add_action('admin_post_nopriv_mds_sponsorboard_start_checkout', [$this, 'handle_checkout_start']);
        add_action('admin_post_mds_sponsorboard_update_booking', [$this, 'handle_booking_update']);
        add_action('admin_post_nopriv_mds_sponsorboard_update_booking', [$this, 'handle_booking_update']);
        add_action('wp_ajax_mds_sponsorboard_track', [$this, 'handle_track_event']);
        add_action('wp_ajax_nopriv_mds_sponsorboard_track', [$this, 'handle_track_event']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('million-dollar-script/extensions/sponsorboard/payment/status', [$this, 'handle_provider_payment_status'], 10, 3);
        add_action('mds_sponsorboard_payment_status', [$this, 'handle_provider_payment_status'], 10, 3);
        add_action('million-dollar-script/payment/source/status', [$this, 'handle_core_payment_source_status'], 10, 4);
        add_action('million-dollar-script/commerce/source/activate', [$this, 'handle_subscription_term'], 10, 4);
        add_action('million-dollar-script/commerce/source/renew', [$this, 'handle_subscription_term'], 10, 4);
        add_action('mds_sponsorboard_cleanup_events', [$this, 'cleanup_analytics_events']);
        add_filter('wp_privacy_personal_data_exporters', [$this, 'privacy_exporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'privacy_erasers']);
        add_filter('million-dollar-script/extensions/sponsorboard/manage/url', [$this, 'filter_manage_url'], 10, 3);
        add_filter('mds_sponsorboard_manage_url', [$this, 'filter_manage_url'], 10, 3);
        add_filter('million-dollar-script/api/endpoint/manifest', [$this, 'api_endpoint_manifest']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'extension_onboarding_items']);

        if (!wp_next_scheduled('mds_sponsorboard_cleanup_events')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'mds_sponsorboard_cleanup_events');
        }
    }

    /**
     * Add SponsorBoard setup shortcuts and legal draft recommendations.
     *
     * @param array $items Existing onboarding items.
     * @return array
     */
    public function extension_onboarding_items(array $items) {
        $items['mds-sponsorboard'] = [
            'name' => __('SponsorBoard', 'mds-sponsorboard'),
            'summary' => __('Set up boards, slots, creative rules, disclosure text, analytics, and sponsor booking moderation.', 'mds-sponsorboard'),
            'priority' => 15,
            'actions' => [
                [
                    'label' => __('Open SponsorBoard', 'mds-sponsorboard'),
                    'url' => admin_url('admin.php?page=mds3-sponsorboard'),
                    'primary' => true,
                ],
            ],
            'legal_documents' => [
                [
                    'slug' => 'advertising-terms',
                    'title' => __('SponsorBoard Advertising Terms', 'mds-sponsorboard'),
                    'description' => __('Terms for sponsor bookings, slot availability, creative approval, renewals, refunds, reporting, and removals.', 'mds-sponsorboard'),
                    'page_slug' => 'sponsorboard-advertising-terms',
                    'content' => $this->sponsorboard_advertising_terms_content(),
                ],
                [
                    'slug' => 'privacy-and-creative-policy',
                    'title' => __('SponsorBoard Privacy and Creative Policy', 'mds-sponsorboard'),
                    'description' => __('Privacy and creative-upload language for sponsor details, analytics, manage links, and moderation records.', 'mds-sponsorboard'),
                    'page_slug' => 'sponsorboard-privacy-and-creative-policy',
                    'content' => $this->sponsorboard_privacy_policy_content(),
                ],
            ],
        ];

        return $items;
    }

    /**
     * Draft advertising terms content.
     *
     * @return string
     */
    private function sponsorboard_advertising_terms_content() {
        return '<h2>' . esc_html__('SponsorBoard Advertising Terms', 'mds-sponsorboard') . '</h2>'
            . '<p>' . esc_html__('These terms apply to sponsor bookings, advertising slots, logos, images, links, calls to action, renewal terms, and related sponsor materials displayed through SponsorBoard on {{site_name}}.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Sponsored Placement Disclosure', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('SponsorBoard entries may be paid advertisements or sponsored placements. A paid booking does not create an endorsement, partnership, performance guarantee, visitor guarantee, search ranking guarantee, or exclusivity unless a separate written agreement says so.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Creative Review and Approval', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Sponsor creative may be reviewed before publication and may be rejected, paused, edited for formatting, relabeled, or removed if it is inaccurate, unlawful, misleading, infringing, unsafe, malicious, offensive, deceptive, technically harmful, or inconsistent with site standards. Sponsors are responsible for all claims, links, images, trademarks, endorsements, regulated disclosures, accessibility text, and landing pages they provide.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Availability, Reporting, and Changes', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Slot availability, display position, reporting metrics, estimated impressions, clicks, click-through rates, and revenue summaries are administrative records and may not match third-party analytics, ad blockers, CDN logs, browser behavior, or gateway records exactly. {{site_name}} may change layouts, slot inventory, categories, pricing, disclosure labels, and display rules as business, security, legal, or operational needs change.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Fees, Renewals, Cancellations, and Removal', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Booking fees, billing cadence, placement terms, taxes, currency, renewal options, cancellations, refund windows, failed-payment handling, disputes, and chargebacks follow the checkout terms shown at purchase. A booking may be removed without refund when it violates these terms, creates legal or security risk, triggers payment risk, harms site reputation, or interferes with site operations.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Rights, License, and Contact', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Sponsors grant {{site_name}} a limited right to display submitted creative, names, logos, links, and calls to action for the booked placement and related administrative previews. Sponsors confirm they have all permissions needed for submitted materials. Questions, support requests, corrections, and takedown notices can be sent through {{contact_method}}.', 'mds-sponsorboard') . '</p>';
    }

    /**
     * Draft privacy and creative policy content.
     *
     * @return string
     */
    private function sponsorboard_privacy_policy_content() {
        return '<h2>' . esc_html__('SponsorBoard Privacy and Creative Policy', 'mds-sponsorboard') . '</h2>'
            . '<p>' . esc_html__('This policy explains how {{site_name}} handles sponsor, booking, creative, analytics, and management data submitted through SponsorBoard on {{site_url}}. It supplements the main privacy policy at {{privacy_policy_url}}.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('How Sponsor Data Is Used', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('SponsorBoard may collect sponsor names, business names, email addresses, URLs, creative files, logos, placement text, booking terms, payment references, manage links, moderation notes, impression and click events, timestamps, IP-derived abuse signals, and technical request data. Sponsor data is used to create and manage bookings, review creative, publish approved placements, process renewals, send manage links, prevent abuse, protect the site, provide reporting, handle disputes, and maintain accounting or audit records.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Public Creative and Analytics', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Approved sponsor creative, business names, links, calls to action, images, and logos may be displayed publicly. Impression and click reporting is used for administration and sponsor communication. Reports may be approximate, delayed, filtered for abuse, affected by browser privacy tools, or combined with third-party analytics when those tools are enabled.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Uploads, Manage Links, and Retention', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Creative uploads and manage links should be treated as sensitive access materials. Creative files, booking records, manage tokens, analytics records, payment references, support messages, and security logs may be retained while a booking is active and afterward for support, accounting, fraud prevention, dispute handling, legal compliance, backup recovery, and audit purposes. Sponsors can request access, correction, removal, export, or erasure through {{contact_method}}, subject to legal, accounting, fraud-prevention, security, backup, and dispute-retention obligations.', 'mds-sponsorboard') . '</p>'
            . '<h3>' . esc_html__('Third-Party Services', 'mds-sponsorboard') . '</h3>'
            . '<p>' . esc_html__('Payment gateways, hosted rendering services, CDN providers, email services, analytics providers, security tools, and hosting providers may process SponsorBoard data under their own terms and privacy policies when those services are enabled on {{site_name}}.', 'mds-sponsorboard') . '</p>';
    }
}
