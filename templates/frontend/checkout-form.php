<?php
/**
 * SponsorBoard checkout request form template.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<details class="mds-sponsorboard__request mds-sponsorboard__checkout">
    <summary><?php esc_html_e('Buy this slot', 'mds-sponsorboard'); ?></summary>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mds_sponsorboard_start_checkout_' . absint($slot['id'] ?? 0)); ?>
        <input type="hidden" name="action" value="mds_sponsorboard_start_checkout" />
        <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
        <input type="hidden" name="slot_id" value="<?php echo esc_attr((string) absint($slot['id'] ?? 0)); ?>" />
        <label>
            <span><?php esc_html_e('Name', 'mds-sponsorboard'); ?></span>
            <input type="text" name="sponsor_name" required maxlength="160" />
        </label>
        <label>
            <span><?php esc_html_e('Email', 'mds-sponsorboard'); ?></span>
            <input type="email" name="sponsor_email" required maxlength="190" />
        </label>
        <label>
            <span><?php esc_html_e('Website', 'mds-sponsorboard'); ?></span>
            <input type="url" name="sponsor_url" placeholder="https://example.com" />
        </label>
        <label>
            <span><?php esc_html_e('Creative URL', 'mds-sponsorboard'); ?></span>
            <input type="url" name="creative_url" placeholder="https://example.com/logo.png" />
        </label>
        <label>
            <span><?php esc_html_e('Note', 'mds-sponsorboard'); ?></span>
            <textarea name="sponsor_note" rows="3"></textarea>
        </label>
        <button type="submit"><?php esc_html_e('Continue to payment', 'mds-sponsorboard'); ?></button>
    </form>
</details>
