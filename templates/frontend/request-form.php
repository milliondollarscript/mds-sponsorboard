<?php
/**
 * SponsorBoard booking request form template.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
$board_settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
$terms_url = (string) ($board_settings['terms_url'] ?? '');
$privacy_url = (string) ($board_settings['privacy_url'] ?? '');
?>
<details class="mds-sponsorboard__request">
    <summary><?php esc_html_e('Request this slot', 'mds-sponsorboard'); ?></summary>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <p class="mds-sponsorboard__form-note"><?php esc_html_e('Send your sponsor details for review. You will receive a private manage link after submitting.', 'mds-sponsorboard'); ?></p>
        <?php wp_nonce_field('mds_sponsorboard_request_booking_' . absint($slot['id'] ?? 0)); ?>
        <input type="hidden" name="action" value="mds_sponsorboard_request_booking" />
        <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
        <input type="hidden" name="slot_id" value="<?php echo esc_attr((string) absint($slot['id'] ?? 0)); ?>" />
        <label>
            <span><?php esc_html_e('Name', 'mds-sponsorboard'); ?></span>
            <input type="text" name="sponsor_name" required maxlength="191" autocomplete="name" />
        </label>
        <label>
            <span><?php esc_html_e('Email', 'mds-sponsorboard'); ?></span>
            <input type="email" name="sponsor_email" required maxlength="191" autocomplete="email" />
        </label>
        <label>
            <span><?php esc_html_e('Website', 'mds-sponsorboard'); ?></span>
            <input type="url" name="sponsor_url" maxlength="2048" placeholder="https://example.com" autocomplete="url" />
        </label>
        <label>
            <span><?php esc_html_e('Creative URL', 'mds-sponsorboard'); ?></span>
            <input type="url" name="creative_url" maxlength="2048" placeholder="https://example.com/logo.png" />
            <small><?php esc_html_e('Optional. You can update creative details from your manage link later.', 'mds-sponsorboard'); ?></small>
        </label>
        <label>
            <span><?php esc_html_e('Note', 'mds-sponsorboard'); ?></span>
            <textarea name="sponsor_note" rows="3" maxlength="2000"></textarea>
            <small><?php esc_html_e('Include timing, creative instructions, or questions for the site owner.', 'mds-sponsorboard'); ?></small>
        </label>
        <label class="mds-sponsorboard__consent">
            <input type="checkbox" name="terms_accepted" value="1" required />
            <span>
                <?php esc_html_e('I agree to the sponsorship terms and understand how my submitted information will be used.', 'mds-sponsorboard'); ?>
                <?php if ($terms_url) : ?><a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Terms', 'mds-sponsorboard'); ?></a><?php endif; ?>
                <?php if ($privacy_url) : ?><a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Privacy', 'mds-sponsorboard'); ?></a><?php endif; ?>
            </span>
        </label>
        <button type="submit"><?php esc_html_e('Send request', 'mds-sponsorboard'); ?></button>
    </form>
</details>
