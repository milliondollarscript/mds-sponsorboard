<?php
/**
 * SponsorBoard signed sponsor manage page template.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<main class="mds-sponsorboard-manage mds3-theme-<?php echo esc_attr($this->theme_mode()); ?> wp-dark-mode-ignore">
    <section class="mds-sponsorboard-manage__panel">
        <?php if (!empty($_GET['created'])) : ?>
            <div class="mds-sponsorboard-notice"><?php esc_html_e('Your sponsor request was received. Save this manage link so you can update your details.', 'mds-sponsorboard'); ?></div>
        <?php elseif (!empty($_GET['updated'])) : ?>
            <div class="mds-sponsorboard-notice"><?php esc_html_e('Your sponsor details were updated.', 'mds-sponsorboard'); ?></div>
        <?php endif; ?>
        <p class="mds-sponsorboard-manage__eyebrow"><?php esc_html_e('Sponsor request', 'mds-sponsorboard'); ?></p>
        <h1><?php echo esc_html((string) ($booking['slot_title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))); ?></h1>
        <p><?php echo esc_html((string) ($booking['board_title'] ?? __('SponsorBoard', 'mds-sponsorboard'))); ?></p>
        <dl>
            <div><dt><?php esc_html_e('Status', 'mds-sponsorboard'); ?></dt><dd><?php echo esc_html($this->booking_status_label((string) ($booking['status'] ?? 'requested'))); ?></dd></div>
            <div><dt><?php esc_html_e('Request ID', 'mds-sponsorboard'); ?></dt><dd>#<?php echo esc_html((string) absint($booking['id'] ?? 0)); ?></dd></div>
        </dl>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds_sponsorboard_update_booking_' . absint($booking['id'] ?? 0)); ?>
            <input type="hidden" name="action" value="mds_sponsorboard_update_booking" />
            <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) absint($booking['id'] ?? 0)); ?>" />
            <input type="hidden" name="token" value="<?php echo esc_attr((string) $token); ?>" />
            <label><span><?php esc_html_e('Name', 'mds-sponsorboard'); ?></span><input type="text" name="sponsor_name" required value="<?php echo esc_attr((string) ($booking['sponsor_name'] ?? '')); ?>" /></label>
            <label><span><?php esc_html_e('Website', 'mds-sponsorboard'); ?></span><input type="url" name="sponsor_url" value="<?php echo esc_attr((string) ($booking['sponsor_url'] ?? '')); ?>" /></label>
            <label><span><?php esc_html_e('Creative URL', 'mds-sponsorboard'); ?></span><input type="url" name="creative_url" value="<?php echo esc_attr((string) ($creative['creative_url'] ?? '')); ?>" /></label>
            <label><span><?php esc_html_e('Note', 'mds-sponsorboard'); ?></span><textarea name="sponsor_note" rows="4"><?php echo esc_textarea((string) ($creative['note'] ?? '')); ?></textarea></label>
            <button type="submit"><?php esc_html_e('Update details', 'mds-sponsorboard'); ?></button>
        </form>
    </section>
</main>
