<?php
/**
 * SponsorBoard signed sponsor manage page template.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<main class="mds-sponsorboard-manage mds3-theme-<?php echo esc_attr($this->theme_mode()); ?> wp-dark-mode-ignore">
    <section class="mds-sponsorboard-manage__panel">
        <?php if (!empty($_GET['created'])) : ?>
            <div class="mds-sponsorboard-notice mds-sponsorboard-notice--success" role="status"><?php esc_html_e('Your sponsor request was received. Save this manage link so you can update your details.', 'mds-sponsorboard'); ?></div>
        <?php elseif (!empty($_GET['updated'])) : ?>
            <div class="mds-sponsorboard-notice mds-sponsorboard-notice--success" role="status"><?php esc_html_e('Your sponsor details were updated.', 'mds-sponsorboard'); ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['creative_error'])) : ?>
            <div class="mds-sponsorboard-notice mds-sponsorboard-notice--error" role="alert"><?php esc_html_e('The creative upload could not be saved. Check the file type, size, and slot dimensions, then try again.', 'mds-sponsorboard'); ?></div>
        <?php endif; ?>
        <p class="mds-sponsorboard-manage__eyebrow"><?php esc_html_e('Sponsor request', 'mds-sponsorboard'); ?></p>
        <h1><?php echo esc_html((string) ($booking['slot_title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))); ?></h1>
        <p><?php echo esc_html((string) ($booking['board_title'] ?? __('SponsorBoard', 'mds-sponsorboard'))); ?></p>
        <p class="mds-sponsorboard-manage__summary"><?php esc_html_e('Use this private page to keep sponsor details current. Uploaded or linked creative may need approval before it appears publicly.', 'mds-sponsorboard'); ?></p>
        <dl>
            <div><dt><?php esc_html_e('Status', 'mds-sponsorboard'); ?></dt><dd><?php echo esc_html($this->booking_status_label((string) ($booking['status'] ?? 'requested'))); ?></dd></div>
            <div><dt><?php esc_html_e('Creative', 'mds-sponsorboard'); ?></dt><dd><?php echo esc_html($this->creative_status_label((string) ($creative['creative_status'] ?? 'pending_review'))); ?></dd></div>
            <div><dt><?php esc_html_e('Request ID', 'mds-sponsorboard'); ?></dt><dd>#<?php echo esc_html((string) absint($booking['id'] ?? 0)); ?></dd></div>
            <div><dt><?php esc_html_e('Views', 'mds-sponsorboard'); ?></dt><dd><?php echo esc_html(number_format_i18n(absint($stats['impressions'] ?? 0))); ?></dd></div>
            <div><dt><?php esc_html_e('Clicks', 'mds-sponsorboard'); ?></dt><dd><?php echo esc_html(number_format_i18n(absint($stats['clicks'] ?? 0))); ?></dd></div>
        </dl>
        <?php if (!empty($creative['creative_url'])) : ?>
            <div class="mds-sponsorboard-manage__creative">
                <?php if (!empty($creative['attachment_id'])) : ?>
                    <?php echo wp_get_attachment_image(absint($creative['attachment_id']), 'medium_large', false, ['class' => 'mds-sponsorboard-manage__creative-image']); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url((string) $creative['creative_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View current creative', 'mds-sponsorboard'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mds_sponsorboard_update_booking_' . absint($booking['id'] ?? 0)); ?>
            <input type="hidden" name="action" value="mds_sponsorboard_update_booking" />
            <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) absint($booking['id'] ?? 0)); ?>" />
            <input type="hidden" name="token" value="<?php echo esc_attr((string) $token); ?>" />
            <label><span><?php esc_html_e('Name', 'mds-sponsorboard'); ?></span><input type="text" name="sponsor_name" required maxlength="191" autocomplete="name" value="<?php echo esc_attr((string) ($booking['sponsor_name'] ?? '')); ?>" /></label>
            <label><span><?php esc_html_e('Website', 'mds-sponsorboard'); ?></span><input type="url" name="sponsor_url" maxlength="2048" autocomplete="url" value="<?php echo esc_attr((string) ($booking['sponsor_url'] ?? '')); ?>" /></label>
            <label><span><?php esc_html_e('Creative URL', 'mds-sponsorboard'); ?></span><input type="url" name="creative_url" maxlength="2048" value="<?php echo esc_attr((string) ($creative['creative_url'] ?? '')); ?>" /></label>
            <?php if ($this->creative_upload_allowed($slot ?? [])) : ?>
                <label>
                    <span><?php esc_html_e('Upload creative', 'mds-sponsorboard'); ?></span>
                    <input type="file" name="creative_file" accept="image/jpeg,image/png,image/gif,image/webp" />
                    <small><?php esc_html_e('JPG, PNG, GIF, or WebP. The site may also enforce size and dimension limits for this slot.', 'mds-sponsorboard'); ?></small>
                </label>
            <?php endif; ?>
            <label><span><?php esc_html_e('Note', 'mds-sponsorboard'); ?></span><textarea name="sponsor_note" rows="4" maxlength="2000"><?php echo esc_textarea((string) ($creative['note'] ?? '')); ?></textarea></label>
            <button type="submit"><?php esc_html_e('Update details', 'mds-sponsorboard'); ?></button>
        </form>
    </section>
</main>
