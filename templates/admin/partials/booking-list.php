<?php
/**
 * SponsorBoard booking list.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($bookings)) : ?>
    <p><?php echo esc_html__('No sponsor bookings have been requested yet.', 'mds-sponsorboard'); ?></p>
    <p><a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'mds3-sponsorboard', 'board_id' => absint($board['id'] ?? 0), 'tab' => 'slots'], admin_url('admin.php'))); ?>"><?php esc_html_e('Review sponsor slots', 'mds-sponsorboard'); ?></a></p>
<?php else : ?>
    <p class="mds-sponsorboard-booking-timezone">
        <?php
        /* translators: %s: WordPress timezone name. */
        echo esc_html(sprintf(__('Booking times use the site timezone: %s. Leave both fields blank for a booking without schedule limits.', 'mds-sponsorboard'), wp_timezone()->getName()));
        ?>
    </p>
    <div class="mds-sponsorboard-bookings">
        <?php foreach ($bookings as $booking) : ?>
            <?php
            $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
            $booking_id = absint($booking['id'] ?? 0);
            $booking_heading_id = 'mds-sponsorboard-booking-' . $booking_id;
            $lifecycle_state = \MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle::state($booking);
            $lifecycle_label = \MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle::label($booking);
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mds-sponsorboard-booking-card" aria-labelledby="<?php echo esc_attr($booking_heading_id); ?>">
                <?php wp_nonce_field('mds_sponsorboard_save_booking'); ?>
                <input type="hidden" name="action" value="mds_sponsorboard_save_booking" />
                <input type="hidden" name="booking_id" value="<?php echo esc_attr((string) $booking_id); ?>" />
                <div class="mds-sponsorboard-booking-summary">
                    <div class="mds-sponsorboard-booking-identity">
                        <strong id="<?php echo esc_attr($booking_heading_id); ?>">#<?php echo esc_html((string) $booking_id); ?> <?php echo esc_html((string) ($booking['slot_title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))); ?></strong>
                        <span><?php echo esc_html((string) ($booking['sponsor_name'] ?? '')); ?> · <?php echo esc_html((string) ($booking['sponsor_email'] ?? '')); ?></span>
                        <span class="mds-sponsorboard-lifecycle mds-sponsorboard-lifecycle--<?php echo esc_attr($lifecycle_state); ?>"><?php echo esc_html($lifecycle_label); ?></span>
                    </div>
                    <?php if (!empty($booking['sponsor_url']) || !empty($creative['creative_url']) || !empty($creative['attachment_id'])) : ?>
                        <div class="mds-sponsorboard-booking-links" aria-label="<?php echo esc_attr__('Booking resources', 'mds-sponsorboard'); ?>">
                            <?php if (!empty($booking['sponsor_url'])) : ?>
                                <a href="<?php echo esc_url((string) $booking['sponsor_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Sponsor website', 'mds-sponsorboard'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($creative['creative_url'])) : ?>
                                <a href="<?php echo esc_url((string) $creative['creative_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Creative URL', 'mds-sponsorboard'); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($creative['attachment_id'])) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link(absint($creative['attachment_id'])) ?: wp_get_attachment_url(absint($creative['attachment_id']))); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Media file', 'mds-sponsorboard'); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <label class="mds-sponsorboard-field">
                    <span><?php esc_html_e('Inventory slot', 'mds-sponsorboard'); ?></span>
                    <select name="slot_id">
                        <?php foreach ($slots as $slot) : ?>
                            <?php
                            $is_current = absint($slot['id'] ?? 0) === absint($booking['slot_id'] ?? 0);
                            if (!$is_current && 'available' !== (string) ($slot['status'] ?? '')) {
                                continue;
                            }
                            ?>
                            <option value="<?php echo esc_attr((string) absint($slot['id'] ?? 0)); ?>" <?php selected($is_current); ?>>
                                <?php echo esc_html(sprintf('%1$s - %2$s', (string) ($slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard')), $is_current ? __('current', 'mds-sponsorboard') : __('available', 'mds-sponsorboard'))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small><?php esc_html_e('Choose an available slot to move this booking without changing its sponsor or payment history.', 'mds-sponsorboard'); ?></small>
                </label>
                <?php $this->select('status', __('Status', 'mds-sponsorboard'), $booking['status'] ?? 'requested', $this->booking_statuses()); ?>
                <?php $this->select('creative_status', __('Creative', 'mds-sponsorboard'), $creative['creative_status'] ?? 'pending_review', $this->creative_statuses()); ?>
                <?php $this->input('starts_at', __('Starts', 'mds-sponsorboard'), \MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle::to_site_input($booking['starts_at'] ?? ''), 'datetime-local', false, __('The approved creative stays private until this time. Blank starts immediately after approval.', 'mds-sponsorboard')); ?>
                <?php $this->input('ends_at', __('Ends', 'mds-sponsorboard'), \MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle::to_site_input($booking['ends_at'] ?? ''), 'datetime-local', false, __('The booking stops rendering and releases its slot at this time. Blank has no scheduled end.', 'mds-sponsorboard')); ?>
                <div class="mds-sponsorboard-booking-actions">
                    <?php submit_button(__('Save booking', 'mds-sponsorboard'), 'secondary', '', false); ?>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
