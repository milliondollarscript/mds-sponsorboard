<?php
/**
 * SponsorBoard slot list.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
$currency_locked = $this->currency_locked();
$currency_help = $currency_locked ? __('The active payment provider controls currency for this site.', 'mds-sponsorboard') : '';
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
                $this->input('title', __('Title', 'mds-sponsorboard'), $slot['title'] ?? '', 'text', true, __('Public label for this sponsorship position.', 'mds-sponsorboard'));
                $this->input('slug', __('Slug', 'mds-sponsorboard'), $slot['slug'] ?? '', 'text', false, __('Stable identifier used by API clients. Leave unchanged once integrations depend on it.', 'mds-sponsorboard'));
                $this->textarea('description', __('Description', 'mds-sponsorboard'), $slot['description'] ?? '', __('Optional public copy shown inside this slot before it is sold.', 'mds-sponsorboard'));
                $this->input('price', __('Price', 'mds-sponsorboard'), $slot['price'] ?? 0, 'number', true, __('Amount charged for this slot when paid checkout is enabled.', 'mds-sponsorboard'));
                $this->input('currency', __('Currency', 'mds-sponsorboard'), $this->effective_currency($slot['currency'] ?? $board['currency'] ?? ''), 'text', true, $currency_help ?: __('Defaults to the board currency unless the active payment provider controls currency.', 'mds-sponsorboard'), $currency_locked);
                $this->select('status', __('Status', 'mds-sponsorboard'), $slot['status'] ?? 'available', $this->slot_statuses(), __('Available slots can accept public requests. Reserved and sold slots are shown as unavailable.', 'mds-sponsorboard'));
                $this->select('creative_type', __('Creative type', 'mds-sponsorboard'), $slot['creative_type'] ?? 'image', $this->creative_types(), __('Image and logo slots allow sponsor uploads from manage links. Text and HTML slots require admin review before display.', 'mds-sponsorboard'));
                $this->input('position', __('Position', 'mds-sponsorboard'), $slot['position'] ?? 0, 'number', true, __('Lower numbers appear first in the public board.', 'mds-sponsorboard'));
                $this->input('dimensions', __('Dimensions', 'mds-sponsorboard'), $slot['dimensions'] ?? '', 'text', false, __('Optional maximum creative size in pixels, such as 600x300.', 'mds-sponsorboard'));
                submit_button(__('Save slot', 'mds-sponsorboard'), 'secondary', '', false);
                ?>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
