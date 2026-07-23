<?php
/**
 * SponsorBoard local analytics settings panel.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

$analytics = is_array($analytics ?? null) ? $analytics : [];
?>
<section class="mds3-card mds-sponsorboard-wide mds-sponsorboard-analytics-settings">
    <div class="mds3-card-heading">
        <div>
            <h2><?php echo esc_html__('Analytics storage', 'mds-sponsorboard'); ?></h2>
            <p><?php echo esc_html__('Control how long this WordPress site keeps detailed SponsorBoard impression and click events.', 'mds-sponsorboard'); ?></p>
        </div>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-form mds-sponsorboard-analytics-settings__form">
        <?php wp_nonce_field('mds_sponsorboard_save_analytics_settings'); ?>
        <input type="hidden" name="action" value="mds_sponsorboard_save_analytics_settings" />

        <label class="mds-sponsorboard-check">
            <input type="checkbox" name="analytics_rollup_enabled" value="1" <?php checked(!empty($analytics['rollup_enabled'])); ?> />
            <span><?php echo esc_html__('Roll up old raw events before cleanup', 'mds-sponsorboard'); ?><small><?php echo esc_html__('Keeps local impressions, clicks, and CTR usable after detailed raw event rows age out.', 'mds-sponsorboard'); ?></small></span>
        </label>

        <label>
            <span><?php echo esc_html__('Raw event retention', 'mds-sponsorboard'); ?></span>
            <input type="number" name="analytics_retention_days" min="0" max="3650" step="1" value="<?php echo esc_attr((string) absint($analytics['retention_days'] ?? 365)); ?>" />
            <small class="mds-sponsorboard-help"><?php echo esc_html__('Number of days to keep detailed raw impression and click rows. Use 0 to keep raw rows indefinitely.', 'mds-sponsorboard'); ?></small>
        </label>

        <?php if (!empty($analytics['last_cleanup'])) : ?>
            <div class="mds-sponsorboard-analytics-settings__meta">
                <span><?php echo esc_html(sprintf(__('Last analytics cleanup: %s', 'mds-sponsorboard'), (string) $analytics['last_cleanup'])); ?></span>
            </div>
        <?php endif; ?>

        <div class="mds-sponsorboard-form-actions">
            <?php submit_button(__('Save analytics settings', 'mds-sponsorboard'), 'primary', '', false); ?>
            <?php submit_button(__('Save and clean up analytics', 'mds-sponsorboard'), 'secondary', 'analytics_cleanup_now', false); ?>
        </div>
    </form>
</section>
