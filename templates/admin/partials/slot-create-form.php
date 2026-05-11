<?php
/**
 * SponsorBoard slot creation form.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-slot-create">
    <?php wp_nonce_field('mds_sponsorboard_save_slot'); ?>
    <input type="hidden" name="action" value="mds_sponsorboard_save_slot" />
    <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
    <input type="hidden" name="slot_id" value="0" />
    <?php
    $this->input('title', __('Slot title', 'mds-sponsorboard'), '', 'text', true);
    $this->input('price', __('Price', 'mds-sponsorboard'), '100.00', 'number', true);
    $this->input('currency', __('Currency', 'mds-sponsorboard'), $board['currency'] ?? $this->default_currency(), 'text', true);
    $this->select('status', __('Status', 'mds-sponsorboard'), 'available', $this->slot_statuses());
    $this->input('position', __('Position', 'mds-sponsorboard'), $position ?? 1, 'number', true);
    $this->input('dimensions', __('Dimensions', 'mds-sponsorboard'), '600x300', 'text', false);
    submit_button(__('Add slot', 'mds-sponsorboard'), 'secondary', '', false);
    ?>
</form>
