<?php
/**
 * SponsorBoard admin notices.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if ('board' === $saved) : ?>
    <div class="notice notice-success inline"><p><?php echo esc_html__('Sponsor board saved.', 'mds-sponsorboard'); ?></p></div>
<?php elseif ('slot' === $saved) : ?>
    <div class="notice notice-success inline"><p><?php echo esc_html__('Sponsor slot saved.', 'mds-sponsorboard'); ?></p></div>
<?php elseif ('booking' === $saved) : ?>
    <div class="notice notice-success inline"><p><?php echo esc_html__('Sponsor booking saved.', 'mds-sponsorboard'); ?></p></div>
<?php elseif ('hosted' === $saved) : ?>
    <div class="notice notice-success inline"><p><?php echo esc_html__('Hosted SponsorBoard settings saved.', 'mds-sponsorboard'); ?></p></div>
<?php elseif ('0' === $saved) : ?>
    <div class="notice notice-error inline"><p><?php echo esc_html__('SponsorBoard could not save that change. Check the required fields and try again.', 'mds-sponsorboard'); ?></p></div>
<?php endif; ?>

<?php if (!empty($hosted_notice) && is_array($hosted_notice)) : ?>
    <?php $notice_type = 'success' === sanitize_key((string) ($hosted_notice['type'] ?? '')) ? 'success' : 'error'; ?>
    <div class="notice notice-<?php echo esc_attr($notice_type); ?> inline"><p><?php echo esc_html((string) ($hosted_notice['message'] ?? '')); ?></p></div>
<?php endif; ?>

<?php if (!empty($activated)) : ?>
    <div class="notice notice-success inline"><p><?php echo esc_html__('SponsorBoard is active. A starter board and slots are ready for review.', 'mds-sponsorboard'); ?></p></div>
<?php endif; ?>
