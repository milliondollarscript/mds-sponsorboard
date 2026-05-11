<?php
/**
 * SponsorBoard slot list.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($slots)) : ?>
    <p><?php echo esc_html__('No slots have been added to this board yet.', 'mds-sponsorboard'); ?></p>
<?php else : ?>
    <div class="mds-sponsorboard-slot-list">
        <?php foreach ($slots as $slot) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-slot-card">
                <?php wp_nonce_field('mds_sponsorboard_save_slot'); ?>
                <input type="hidden" name="action" value="mds_sponsorboard_save_slot" />
                <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
                <input type="hidden" name="slot_id" value="<?php echo esc_attr((string) absint($slot['id'] ?? 0)); ?>" />
                <?php
                $this->input('title', __('Title', 'mds-sponsorboard'), $slot['title'] ?? '', 'text', true);
                $this->input('slug', __('Slug', 'mds-sponsorboard'), $slot['slug'] ?? '', 'text', false);
                $this->textarea('description', __('Description', 'mds-sponsorboard'), $slot['description'] ?? '');
                $this->input('price', __('Price', 'mds-sponsorboard'), $slot['price'] ?? 0, 'number', true);
                $this->input('currency', __('Currency', 'mds-sponsorboard'), $slot['currency'] ?? $this->default_currency(), 'text', true);
                $this->select('status', __('Status', 'mds-sponsorboard'), $slot['status'] ?? 'available', $this->slot_statuses());
                $this->select('creative_type', __('Creative Type', 'mds-sponsorboard'), $slot['creative_type'] ?? 'image', $this->creative_types());
                $this->input('position', __('Position', 'mds-sponsorboard'), $slot['position'] ?? 0, 'number', true);
                $this->input('dimensions', __('Dimensions', 'mds-sponsorboard'), $slot['dimensions'] ?? '', 'text', false);
                submit_button(__('Save slot', 'mds-sponsorboard'), 'secondary', '', false);
                ?>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
