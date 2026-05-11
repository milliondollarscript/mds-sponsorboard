<?php
/**
 * SponsorBoard board form.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form id="mds-sponsorboard-board-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-form">
    <?php wp_nonce_field('mds_sponsorboard_save_board'); ?>
    <input type="hidden" name="action" value="mds_sponsorboard_save_board" />
    <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
    <?php
    $this->input('title', __('Title', 'mds-sponsorboard'), $board['title'] ?? '', 'text', true);
    $this->input('slug', __('Slug', 'mds-sponsorboard'), $board['slug'] ?? '', 'text', false, __('Leave blank to generate from the title.', 'mds-sponsorboard'));
    $this->select('status', __('Status', 'mds-sponsorboard'), $board['status'] ?? 'draft', $this->board_statuses());
    $this->select('layout', __('Layout', 'mds-sponsorboard'), $board['layout'] ?? 'grid', $this->layouts());
    $this->input('currency', __('Currency', 'mds-sponsorboard'), $board['currency'] ?? $this->default_currency(), 'text', true);
    $this->input('columns', __('Columns', 'mds-sponsorboard'), $settings['columns'] ?? 3, 'number', true);
    $this->input('disclosure_label', __('Disclosure Label', 'mds-sponsorboard'), $settings['disclosure_label'] ?? __('Sponsored', 'mds-sponsorboard'), 'text', true);
    $this->select('checkout_enabled', __('Paid Checkout', 'mds-sponsorboard'), $settings['checkout_enabled'] ?? '0', [
        '0' => __('Requests only', 'mds-sponsorboard'),
        '1' => __('Use Million Dollar Script payment provider', 'mds-sponsorboard'),
    ]);
    $this->textarea('description', __('Description', 'mds-sponsorboard'), $board['description'] ?? '');
    submit_button(empty($board['id']) ? __('Create board', 'mds-sponsorboard') : __('Save board', 'mds-sponsorboard'), 'primary', '', false);
    ?>
</form>
