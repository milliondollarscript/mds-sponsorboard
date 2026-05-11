<?php
/**
 * SponsorBoard booking list.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($bookings)) : ?>
    <p><?php echo esc_html__('No sponsor bookings have been requested yet.', 'mds-sponsorboard'); ?></p>
<?php else : ?>
    <div class="mds-sponsorboard-bookings">
        <?php foreach ($bookings as $booking) : ?>
            <?php $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : []; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-booking-card">
                <?php wp_nonce_field('mds_sponsorboard_save_booking'); ?>
                <input type="hidden" name="action" value="mds_sponsorboard_save_booking" />
                <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) absint($booking['id'] ?? 0)); ?>" />
                <div>
                    <strong>#<?php echo esc_html((string) absint($booking['id'] ?? 0)); ?> <?php echo esc_html((string) ($booking['slot_title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))); ?></strong>
                    <span><?php echo esc_html((string) ($booking['sponsor_name'] ?? '')); ?> · <?php echo esc_html((string) ($booking['sponsor_email'] ?? '')); ?></span>
                </div>
                <?php if (!empty($booking['sponsor_url'])) : ?>
                    <a href="<?php echo esc_url((string) $booking['sponsor_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Sponsor website', 'mds-sponsorboard'); ?></a>
                <?php endif; ?>
                <?php if (!empty($creative['creative_url'])) : ?>
                    <a href="<?php echo esc_url((string) $creative['creative_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Creative URL', 'mds-sponsorboard'); ?></a>
                <?php endif; ?>
                <?php $this->select('status', __('Status', 'mds-sponsorboard'), $booking['status'] ?? 'requested', $this->booking_statuses()); ?>
                <?php submit_button(__('Save booking', 'mds-sponsorboard'), 'secondary', '', false); ?>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
