<?php
/**
 * SponsorBoard board form.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
$currency_locked = $this->currency_locked();
$currency_help = $currency_locked ? __('The active payment provider controls currency for this site.', 'mds-sponsorboard') : '';
?>
<form id="mds-sponsorboard-board-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-form">
    <?php wp_nonce_field('mds_sponsorboard_save_board'); ?>
    <input type="hidden" name="action" value="mds_sponsorboard_save_board" />
    <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($board['id'] ?? 0)); ?>" />
    <?php
    $this->input('title', __('Title', 'mds-sponsorboard'), $board['title'] ?? '', 'text', true, __('Shown above the embedded sponsor inventory and in admin reports.', 'mds-sponsorboard'));
    $this->input('slug', __('Slug', 'mds-sponsorboard'), $board['slug'] ?? '', 'text', false, __('Leave blank to generate from the title.', 'mds-sponsorboard'));
    $this->select('status', __('Status', 'mds-sponsorboard'), $board['status'] ?? 'draft', $this->board_statuses(), __('Only active boards render publicly. Draft and paused boards remain available to admins for setup and review.', 'mds-sponsorboard'));
    $this->select('layout', __('Layout', 'mds-sponsorboard'), $board['layout'] ?? 'grid', $this->layouts(), __('Choose how slots are arranged on the public board. Strip scrolls horizontally on small screens.', 'mds-sponsorboard'));
    $this->input('currency', __('Currency', 'mds-sponsorboard'), $this->effective_currency($board['currency'] ?? ''), 'text', true, $currency_help ?: __('Used for public price labels and checkout requests when a payment provider does not lock currency.', 'mds-sponsorboard'), $currency_locked);
    $this->input('columns', __('Columns', 'mds-sponsorboard'), $settings['columns'] ?? 3, 'number', true, __('Desktop columns for grid and mosaic layouts. Mobile layouts collapse automatically.', 'mds-sponsorboard'));
    $this->input('disclosure_label', __('Disclosure label', 'mds-sponsorboard'), $settings['disclosure_label'] ?? __('Sponsored', 'mds-sponsorboard'), 'text', true, __('Short label displayed beside paid or approved sponsor inventory.', 'mds-sponsorboard'));
    $this->select('checkout_enabled', __('Paid Checkout', 'mds-sponsorboard'), $settings['checkout_enabled'] ?? '0', [
        '0' => __('Requests only', 'mds-sponsorboard'),
        '1' => __('Use Million Dollar Script payment provider', 'mds-sponsorboard'),
    ], __('Use requests only for manual review, or send sponsors through the active MDS payment provider before approval.', 'mds-sponsorboard'));
    $this->input('contact_email', __('Board contact email', 'mds-sponsorboard'), $settings['contact_email'] ?? '', 'email', false, __('Receives new booking notifications. Leave blank to use the site administrator email.', 'mds-sponsorboard'));
    $this->input('terms_url', __('Sponsorship terms URL', 'mds-sponsorboard'), $settings['terms_url'] ?? '', 'url', false, __('Optional HTTP(S) page linked beside the required consent checkbox.', 'mds-sponsorboard'));
    $this->input('privacy_url', __('Privacy notice URL', 'mds-sponsorboard'), $settings['privacy_url'] ?? '', 'url', false, __('Optional HTTP(S) privacy page linked beside the required consent checkbox.', 'mds-sponsorboard'));
    $this->input('booking_limit_per_hour', __('Requests per hour', 'mds-sponsorboard'), $settings['booking_limit_per_hour'] ?? 10, 'number', true, __('Maximum booking or checkout attempts allowed per visitor and slot each hour.', 'mds-sponsorboard'));
    $this->input('booking_retention_days', __('Private data retention', 'mds-sponsorboard'), $settings['booking_retention_days'] ?? 365, 'number', true, __('Days to retain private sponsor contact and draft creative data. Public approved sponsorship content remains until it is unpublished.', 'mds-sponsorboard'));
    $this->textarea('description', __('Description', 'mds-sponsorboard'), $board['description'] ?? '', __('Optional public introduction shown above this board.', 'mds-sponsorboard'));
    submit_button(empty($board['id']) ? __('Create board', 'mds-sponsorboard') : __('Save board', 'mds-sponsorboard'), 'primary', '', false);
    ?>
</form>
