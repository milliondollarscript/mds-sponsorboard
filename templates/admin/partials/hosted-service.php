<?php
/**
 * SponsorBoard hosted service settings panel.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

$hosted = is_array($hosted ?? null) ? $hosted : [];
$analytics = is_array($analytics ?? null) ? $analytics : [];
?>
<section class="mds3-card mds-sponsorboard-wide mds-sponsorboard-hosted">
    <div class="mds3-card-heading">
        <div>
            <h2><?php echo esc_html__('Hosted service', 'mds-sponsorboard'); ?></h2>
            <p><?php echo esc_html__('Keep an optional hosted copy of sponsor inventory for large reporting, external clients, and service-side analytics.', 'mds-sponsorboard'); ?></p>
        </div>
        <?php if (!empty($hosted['site_id'])) : ?>
            <code><?php echo esc_html((string) $hosted['site_id']); ?></code>
        <?php endif; ?>
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-form mds-sponsorboard-hosted__form">
        <?php wp_nonce_field('mds_sponsorboard_save_hosted_settings'); ?>
        <input type="hidden" name="action" value="mds_sponsorboard_save_hosted_settings" />

        <label class="mds-sponsorboard-check">
            <input type="checkbox" name="hosted_enabled" value="1" <?php checked(!empty($hosted['enabled'])); ?> />
            <span><?php echo esc_html__('Sync boards, slots, and bookings to the hosted API', 'mds-sponsorboard'); ?><small><?php echo esc_html__('Use this only when you have a hosted SponsorBoard service connected for external dashboards or large reporting jobs.', 'mds-sponsorboard'); ?></small></span>
        </label>

        <label class="mds-sponsorboard-check">
            <input type="checkbox" name="hosted_events_enabled" value="1" <?php checked(!empty($hosted['events_enabled'])); ?> />
            <span><?php echo esc_html__('Mirror impression and click aggregates to the hosted API', 'mds-sponsorboard'); ?><small><?php echo esc_html__('Sends board, slot, booking, event type, count, and timestamp only. Raw IP addresses and user-agent strings are not sent. Local analytics still work when this is off.', 'mds-sponsorboard'); ?></small></span>
        </label>

        <label class="mds-sponsorboard-check">
            <input type="checkbox" name="analytics_rollup_enabled" value="1" <?php checked(!empty($analytics['rollup_enabled'])); ?> />
            <span><?php echo esc_html__('Roll up old raw events before cleanup', 'mds-sponsorboard'); ?><small><?php echo esc_html__('Keeps local impressions, clicks, and CTR usable after detailed raw event rows age out.', 'mds-sponsorboard'); ?></small></span>
        </label>

        <label>
            <span><?php echo esc_html__('Raw event retention', 'mds-sponsorboard'); ?></span>
            <input type="number" name="analytics_retention_days" min="0" max="3650" step="1" value="<?php echo esc_attr((string) absint($analytics['retention_days'] ?? 365)); ?>" />
            <small class="mds-sponsorboard-help"><?php echo esc_html__('Number of days to keep detailed raw impression and click rows. Use 0 to keep raw rows indefinitely.', 'mds-sponsorboard'); ?></small>
        </label>

        <label>
            <span><?php echo esc_html__('API URL', 'mds-sponsorboard'); ?></span>
            <input type="url" name="hosted_api_url" value="<?php echo esc_attr((string) ($hosted['api_url'] ?? '')); ?>" placeholder="http://localhost:3031" />
            <small class="mds-sponsorboard-help"><?php echo esc_html__('Base URL for the hosted SponsorBoard API. Leave blank to keep all SponsorBoard data local.', 'mds-sponsorboard'); ?></small>
        </label>

        <label>
            <span><?php echo esc_html__('Admin API key', 'mds-sponsorboard'); ?></span>
            <input type="password" name="hosted_api_key" value="" autocomplete="off" placeholder="<?php echo esc_attr(!empty($hosted['api_key_set']) ? __('Saved; leave blank to keep', 'mds-sponsorboard') : __('Enter key', 'mds-sponsorboard')); ?>" />
            <small class="mds-sponsorboard-help"><?php echo esc_html__('Stored locally and sent only to the configured hosted API URL.', 'mds-sponsorboard'); ?></small>
        </label>

        <?php if (!empty($hosted['api_key_set'])) : ?>
            <label class="mds-sponsorboard-check">
                <input type="checkbox" name="hosted_clear_api_key" value="1" />
                <span><?php echo esc_html__('Clear saved API key', 'mds-sponsorboard'); ?></span>
            </label>
        <?php endif; ?>

        <div class="mds-sponsorboard-hosted__meta">
            <?php if (!empty($analytics['last_cleanup'])) : ?>
                <span><?php echo esc_html(sprintf(__('Last analytics cleanup: %s', 'mds-sponsorboard'), (string) $analytics['last_cleanup'])); ?></span>
            <?php endif; ?>
            <?php if (!empty($hosted['last_sync'])) : ?>
                <span><?php echo esc_html(sprintf(__('Last sync: %s', 'mds-sponsorboard'), (string) $hosted['last_sync'])); ?></span>
            <?php endif; ?>
            <?php if (!empty($hosted['last_error'])) : ?>
                <span class="mds-sponsorboard-hosted__error"><?php echo esc_html(sprintf(__('Last error: %s', 'mds-sponsorboard'), (string) $hosted['last_error'])); ?></span>
            <?php endif; ?>
        </div>

        <div class="mds-sponsorboard-form-actions">
            <?php submit_button(__('Save hosted settings', 'mds-sponsorboard'), 'primary', '', false); ?>
            <?php submit_button(__('Save and test', 'mds-sponsorboard'), 'secondary', 'hosted_test_connection', false); ?>
            <?php submit_button(__('Save and clean up analytics', 'mds-sponsorboard'), 'secondary', 'analytics_cleanup_now', false); ?>
        </div>
    </form>
</section>
