<?php
/**
 * SponsorBoard admin page.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
$base_url = admin_url('admin.php?page=mds3-sponsorboard');
$tabs = [];
if (!empty($selected)) {
    $tabs['bookings'] = sprintf(__('Bookings (%d)', 'mds-sponsorboard'), absint($booking_page['total'] ?? 0));
}
$tabs['overview'] = __('Overview', 'mds-sponsorboard');
$tabs['boards'] = __('Boards', 'mds-sponsorboard');
if (!empty($selected)) {
    $tabs['slots'] = sprintf(__('Slots (%d)', 'mds-sponsorboard'), count($slots ?? []));
}
$tabs['hosted'] = __('Hosted service', 'mds-sponsorboard');
?>
<div class="wrap mds3-admin mds-sponsorboard-admin">
    <div class="mds-extension-heading-row">
        <h1><?php echo esc_html__('SponsorBoard', 'mds-sponsorboard'); ?></h1>
        <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-sponsorboard')); ?>
    </div>
    <hr class="wp-header-end">
    <?php $this->notices(); ?>

    <nav class="nav-tab-wrapper wp-clearfix mds-sponsorboard-tabs" aria-label="<?php echo esc_attr__('SponsorBoard workspace', 'mds-sponsorboard'); ?>">
        <?php foreach ($tabs as $tab_key => $tab_label) : ?>
            <a class="nav-tab<?php echo $active_tab === $tab_key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tab' => $tab_key, 'board_id' => absint($selected_id ?? 0)], $base_url)); ?>" <?php echo $active_tab === $tab_key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($tab_label); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ('overview' === $active_tab) : ?>
        <section class="mds-sponsorboard-hero mds3-card">
            <div>
                <p class="mds-sponsorboard-eyebrow"><?php echo esc_html__('Monetization extension', 'mds-sponsorboard'); ?></p>
                <h2><?php echo esc_html__('Sell sponsor inventory without changing your grid.', 'mds-sponsorboard'); ?></h2>
                <p><?php echo esc_html__('Create sponsor boards, price fixed slots, expose secure API endpoints, and embed boards anywhere with a shortcode.', 'mds-sponsorboard'); ?></p>
            </div>
            <div class="mds-sponsorboard-hero-actions">
                <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['tab' => 'boards', 'board_id' => 0], $base_url)); ?>"><?php echo esc_html__('Create board', 'mds-sponsorboard'); ?></a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-api')); ?>"><?php echo esc_html__('API policies', 'mds-sponsorboard'); ?></a>
            </div>
        </section>

        <section class="mds-sponsorboard-metrics" aria-label="<?php echo esc_attr__('SponsorBoard metrics', 'mds-sponsorboard'); ?>">
            <?php
            $this->metric(__('Boards', 'mds-sponsorboard'), number_format_i18n(absint($board_page['total'] ?? count($boards ?? []))), __('Configured sponsor boards', 'mds-sponsorboard'));
            $this->metric(__('Slots', 'mds-sponsorboard'), number_format_i18n(absint($totals['slots'] ?? 0)), __('Total inventory slots', 'mds-sponsorboard'));
            $this->metric(__('Available', 'mds-sponsorboard'), number_format_i18n(absint($totals['available'] ?? 0)), __('Ready to sell', 'mds-sponsorboard'));
            $this->metric(__('Sold or Reserved', 'mds-sponsorboard'), number_format_i18n(absint($totals['sold'] ?? 0) + absint($totals['reserved'] ?? 0)), __('Committed inventory', 'mds-sponsorboard'));
            $this->metric(__('Revenue', 'mds-sponsorboard'), esc_html($this->effective_currency($selected['currency'] ?? '') . ' ' . number_format_i18n((float) ($totals['revenue'] ?? 0), 2)), __('Paid, approved, and active bookings', 'mds-sponsorboard'));
            $this->metric(__('Impressions', 'mds-sponsorboard'), number_format_i18n(absint($totals['impressions'] ?? 0)), __('Tracked approved sponsor views', 'mds-sponsorboard'));
            /* translators: %s: click-through rate percentage. */
            $this->metric(__('Clicks', 'mds-sponsorboard'), number_format_i18n(absint($totals['clicks'] ?? 0)), sprintf(__('CTR %s', 'mds-sponsorboard'), number_format_i18n((float) ($totals['ctr'] ?? 0), 2) . '%'));
            $this->metric(__('Expiring', 'mds-sponsorboard'), number_format_i18n(absint($totals['expiring'] ?? 0)), __('Active bookings ending in 30 days', 'mds-sponsorboard'));
            ?>
        </section>
    <?php elseif ('hosted' === $active_tab) : ?>
        <?php $this->hosted_service_panel($hosted ?? [], $analytics ?? []); ?>
    <?php else : ?>
        <div class="mds-sponsorboard-layout<?php echo 'bookings' === $active_tab ? ' mds-sponsorboard-layout--bookings' : ''; ?>">
            <section class="mds3-card">
                <div class="mds3-card-heading"><div><h2><?php echo esc_html__('Boards', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Choose the sponsor board you want to manage.', 'mds-sponsorboard'); ?></p></div></div>
                <?php $this->board_list($boards ?? [], $selected_id ?? 0, $active_tab); ?>
                <?php if (absint($board_page['pages'] ?? 1) > 1) : ?>
                    <nav class="mds-sponsorboard-pagination" aria-label="<?php echo esc_attr__('Sponsor board pages', 'mds-sponsorboard'); ?>">
                        <?php echo wp_kses_post(paginate_links([
                            'base' => add_query_arg(['board_page' => '%#%', 'board_id' => absint($selected_id ?? 0), 'tab' => $active_tab], $base_url),
                            'current' => absint($board_page['page'] ?? 1),
                            'total' => absint($board_page['pages'] ?? 1),
                            'type' => 'list',
                        ])); ?>
                    </nav>
                <?php endif; ?>
            </section>

            <?php if ('boards' === $active_tab) : ?>
                <section class="mds3-card">
                    <div class="mds3-card-heading">
                        <div><h2><?php echo esc_html(!empty($selected) ? __('Edit board', 'mds-sponsorboard') : __('Create board', 'mds-sponsorboard')); ?></h2><p><?php echo esc_html__('Status controls whether the shortcode renders publicly.', 'mds-sponsorboard'); ?></p></div>
                        <?php if (!empty($selected)) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['tab' => 'boards', 'board_id' => 0], $base_url)); ?>"><?php esc_html_e('New board', 'mds-sponsorboard'); ?></a><?php endif; ?>
                    </div>
                    <?php $this->board_form($selected ?? null); ?>
                </section>
            <?php elseif ('slots' === $active_tab && !empty($selected)) : ?>
                <section class="mds3-card">
                    <div class="mds3-card-heading">
                        <div><h2><?php echo esc_html__('Slots', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Price and label sponsor inventory. Available slots can be advertised, reserved, or marked sold.', 'mds-sponsorboard'); ?></p></div>
                        <?php echo wp_kses_post(\MillionDollarScript\Extensions\Admin::shortcode_copy(sprintf('[mds_sponsorboard id="%d"]', absint($selected_id ?? 0)))); ?>
                    </div>
                    <?php $this->slot_create_form($selected); ?>
                    <?php $this->slot_table($selected, $slots ?? []); ?>
                </section>
            <?php elseif ('bookings' === $active_tab && !empty($selected)) : ?>
                <section class="mds3-card">
                    <div class="mds3-card-heading">
                        <div><h2><?php echo esc_html__('Bookings', 'mds-sponsorboard'); ?></h2><p><?php echo esc_html__('Review sponsor requests, approve creatives, export reports, and keep slot availability in sync.', 'mds-sponsorboard'); ?></p></div>
                        <div class="mds-sponsorboard-export-actions">
                            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'mds_sponsorboard_export_bookings', 'board_id' => absint($selected_id ?? 0)], admin_url('admin-post.php')), 'mds_sponsorboard_export_bookings')); ?>"><?php echo esc_html__('Export bookings', 'mds-sponsorboard'); ?></a>
                            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'mds_sponsorboard_export_events', 'board_id' => absint($selected_id ?? 0)], admin_url('admin-post.php')), 'mds_sponsorboard_export_events')); ?>"><?php echo esc_html__('Export events', 'mds-sponsorboard'); ?></a>
                        </div>
                    </div>
                    <?php $this->export_status_panel($export_status ?? null); ?>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="mds-sponsorboard-booking-filters">
                        <input type="hidden" name="page" value="mds3-sponsorboard" />
                        <input type="hidden" name="board_id" value="<?php echo esc_attr((string) absint($selected_id ?? 0)); ?>" />
                        <input type="hidden" name="tab" value="bookings" />
                        <label><span><?php esc_html_e('Search bookings', 'mds-sponsorboard'); ?></span><input type="search" name="booking_search" value="<?php echo esc_attr((string) ($booking_filters['search'] ?? '')); ?>" maxlength="100" placeholder="<?php echo esc_attr__('Name, email, slot, or ID', 'mds-sponsorboard'); ?>" /></label>
                        <label><span><?php esc_html_e('Status', 'mds-sponsorboard'); ?></span><select name="booking_status"><option value=""><?php esc_html_e('All statuses', 'mds-sponsorboard'); ?></option><?php foreach ($this->booking_statuses() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($booking_filters['status'] ?? ''), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                        <button class="button" type="submit"><?php esc_html_e('Filter', 'mds-sponsorboard'); ?></button>
                        <?php if (!empty($booking_filters['search']) || !empty($booking_filters['status'])) : ?><a class="button" href="<?php echo esc_url(add_query_arg(['tab' => 'bookings', 'board_id' => absint($selected_id ?? 0)], $base_url)); ?>"><?php esc_html_e('Clear', 'mds-sponsorboard'); ?></a><?php endif; ?>
                    </form>
                    <?php $this->booking_table($selected, $booking_page['items'] ?? [], $slots ?? []); ?>
                    <?php if (absint($booking_page['pages'] ?? 1) > 1) : ?>
                        <nav class="mds-sponsorboard-pagination" aria-label="<?php echo esc_attr__('Sponsor booking pages', 'mds-sponsorboard'); ?>">
                            <?php echo wp_kses_post(paginate_links([
                                'base' => add_query_arg(['tab' => 'bookings', 'board_id' => absint($selected_id ?? 0), 'booking_status' => (string) ($booking_filters['status'] ?? ''), 'booking_search' => (string) ($booking_filters['search'] ?? ''), 'booking_page' => '%#%'], $base_url),
                                'current' => absint($booking_page['page'] ?? 1),
                                'total' => absint($booking_page['pages'] ?? 1),
                                'type' => 'list',
                            ])); ?>
                        </nav>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
