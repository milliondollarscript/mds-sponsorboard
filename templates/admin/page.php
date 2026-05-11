<?php
/**
 * SponsorBoard admin page.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mds3-admin mds-sponsorboard-admin">
    <h1><?php echo esc_html__('SponsorBoard', 'mds-sponsorboard'); ?></h1>
    <?php $this->notices(); ?>

    <section class="mds-sponsorboard-hero mds3-card">
        <div>
            <p class="mds-sponsorboard-eyebrow"><?php echo esc_html__('Monetization extension', 'mds-sponsorboard'); ?></p>
            <h2><?php echo esc_html__('Sell sponsor inventory without changing your grid.', 'mds-sponsorboard'); ?></h2>
            <p><?php echo esc_html__('Create sponsor boards, price fixed slots, expose secure API endpoints, and embed boards anywhere with a shortcode.', 'mds-sponsorboard'); ?></p>
        </div>
        <div class="mds-sponsorboard-hero-actions">
            <a class="button button-primary" href="#mds-sponsorboard-board-form"><?php echo esc_html__('Create board', 'mds-sponsorboard'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-api')); ?>"><?php echo esc_html__('API policies', 'mds-sponsorboard'); ?></a>
        </div>
    </section>

    <section class="mds-sponsorboard-metrics" aria-label="<?php echo esc_attr__('SponsorBoard metrics', 'mds-sponsorboard'); ?>">
        <?php
        $this->metric(__('Boards', 'mds-sponsorboard'), number_format_i18n(count($boards ?? [])), __('Configured sponsor boards', 'mds-sponsorboard'));
        $this->metric(__('Slots', 'mds-sponsorboard'), number_format_i18n(absint($totals['slots'] ?? 0)), __('Total inventory slots', 'mds-sponsorboard'));
        $this->metric(__('Available', 'mds-sponsorboard'), number_format_i18n(absint($totals['available'] ?? 0)), __('Ready to sell', 'mds-sponsorboard'));
        $this->metric(__('Sold or Reserved', 'mds-sponsorboard'), number_format_i18n(absint($totals['sold'] ?? 0) + absint($totals['reserved'] ?? 0)), __('Committed inventory', 'mds-sponsorboard'));
        ?>
    </section>

    <div class="mds-sponsorboard-layout">
        <section class="mds3-card">
            <div class="mds3-card-heading"><div><h2><?php echo esc_html__('Boards', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Each board can be embedded separately and managed through the API.', 'mds-sponsorboard'); ?></p></div></div>
            <?php $this->board_list($boards ?? [], $selected_id ?? 0); ?>
        </section>

        <section class="mds3-card">
            <div class="mds3-card-heading"><div><h2><?php echo esc_html(!empty($selected) ? __('Edit board', 'mds-sponsorboard') : __('Create board', 'mds-sponsorboard')); ?></h2><p><?php echo esc_html__('Status controls whether the shortcode renders publicly.', 'mds-sponsorboard'); ?></p></div></div>
            <?php $this->board_form($selected ?? null); ?>
        </section>

        <?php if (!empty($selected)) : ?>
            <section class="mds3-card mds-sponsorboard-wide">
                <div class="mds3-card-heading"><div><h2><?php echo esc_html__('Slots', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Price and label sponsor inventory. Available slots can be advertised, reserved, or marked sold.', 'mds-sponsorboard'); ?></p></div><code>[mds_sponsorboard id="<?php echo esc_html((string) absint($selected_id ?? 0)); ?>"]</code></div>
                <?php $this->slot_create_form($selected); ?>
                <?php $this->slot_table($selected, $slots ?? []); ?>
            </section>

            <section class="mds3-card mds-sponsorboard-wide">
                <div class="mds3-card-heading"><div><h2><?php echo esc_html__('Bookings', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Review sponsor requests, update moderation status, and keep slot availability in sync.', 'mds-sponsorboard'); ?></p></div></div>
                <?php $this->booking_table($selected, \MDS\Extensions\SponsorBoard\Repository::bookings_for_board(absint($selected_id ?? 0), 50)); ?>
            </section>
        <?php endif; ?>
    </div>
</div>
