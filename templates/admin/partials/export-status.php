<?php
/**
 * SponsorBoard export status panel.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

$export_status = is_array($export_status ?? null) ? $export_status : [];
$type = sanitize_key((string) ($export_status['type'] ?? ''));
$status = sanitize_key((string) ($export_status['status'] ?? ''));
$total = absint($export_status['total'] ?? 0);
$offset = min($total, absint($export_status['offset'] ?? 0));
$percent = $total > 0 ? min(100, round(($offset / $total) * 100)) : 100;
$board_id = absint($export_status['board_id'] ?? 0);
$job_id = sanitize_text_field((string) ($export_status['id'] ?? ''));
$action = 'events' === $type ? 'mds_sponsorboard_export_events' : 'mds_sponsorboard_export_bookings';
?>
<div class="mds-sponsorboard-export-status" role="status">
    <div>
        <strong><?php echo esc_html('events' === $type ? __('Events export', 'mds-sponsorboard') : __('Bookings export', 'mds-sponsorboard')); ?></strong>
        <?php if ('error' === $status) : ?>
            <p><?php echo esc_html((string) ($export_status['error'] ?? __('The export could not be loaded.', 'mds-sponsorboard'))); ?></p>
        <?php elseif ('done' === $status) : ?>
            <p><?php echo esc_html(sprintf(
                /* translators: %s: number of exported rows. */
                __('Export ready with %s rows.', 'mds-sponsorboard'),
                number_format_i18n($total)
            )); ?></p>
        <?php else : ?>
            <p><?php echo esc_html(sprintf(
                /* translators: 1: processed rows, 2: total rows. */
                __('Exported %1$s of %2$s rows. Continue until the file is ready.', 'mds-sponsorboard'),
                number_format_i18n($offset),
                number_format_i18n($total)
            )); ?></p>
        <?php endif; ?>
    </div>

    <?php if ('error' !== $status) : ?>
        <div class="mds-sponsorboard-export-progress" aria-label="<?php echo esc_attr__('Export progress', 'mds-sponsorboard'); ?>">
            <span style="width: <?php echo esc_attr((string) $percent); ?>%"></span>
        </div>
    <?php endif; ?>

    <div class="mds-sponsorboard-export-actions">
        <?php if ('done' === $status) : ?>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'mds_sponsorboard_download_export', 'job_id' => $job_id], admin_url('admin-post.php')), 'mds_sponsorboard_download_export')); ?>"><?php echo esc_html__('Download CSV', 'mds-sponsorboard'); ?></a>
        <?php elseif ('error' !== $status) : ?>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => $action, 'board_id' => $board_id, 'job_id' => $job_id], admin_url('admin-post.php')), $action)); ?>"><?php echo esc_html__('Continue export', 'mds-sponsorboard'); ?></a>
        <?php endif; ?>
    </div>
</div>
