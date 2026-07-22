<?php
/**
 * SponsorBoard slot creation form.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
$currency_locked = $this->currency_locked();
$currency_help = $currency_locked ? __('The active payment provider controls currency for this site.', 'mds-sponsorboard') : '';
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-slot-create">
    <?php wp_nonce_field('mds_sponsorboard_save_slot'); ?>
    <input type="hidden" name="action" value="mds_sponsorboard_save_slot" />
    <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
    <input type="hidden" name="slot_id" value="0" />
    <?php
    $this->input('title', __('Slot title', 'mds-sponsorboard'), '', 'text', true, __('Public label for this sponsorship position.', 'mds-sponsorboard'));
    $this->input('price', __('Price', 'mds-sponsorboard'), '100.00', 'number', true, __('Amount charged for this slot when paid checkout is enabled.', 'mds-sponsorboard'));
    $this->input('currency', __('Currency', 'mds-sponsorboard'), $this->effective_currency($board['currency'] ?? ''), 'text', true, $currency_help ?: __('Defaults to the board currency unless the active payment provider controls currency.', 'mds-sponsorboard'), $currency_locked);
    $this->select('status', __('Status', 'mds-sponsorboard'), 'available', $this->slot_statuses(), __('Available slots can accept public requests. Reserved and sold slots are shown as unavailable.', 'mds-sponsorboard'));
    $this->input('position', __('Position', 'mds-sponsorboard'), $position ?? 1, 'number', true, __('Lower numbers appear first in the public board.', 'mds-sponsorboard'));
    $this->input('dimensions', __('Dimensions', 'mds-sponsorboard'), '600x300', 'text', false, __('Optional maximum creative size in pixels, such as 600x300.', 'mds-sponsorboard'));
    submit_button(__('Add slot', 'mds-sponsorboard'), 'secondary', '', false);
    ?>
</form>
