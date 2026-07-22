<?php
/**
 * SponsorBoard booking email notifications.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

final class Notifications {

    public static function booking_created(array $board, array $slot, array $booking, $manage_url) {
        $site_name = self::site_name();
        $admin_email = sanitize_email((string) (($board['settings']['contact_email'] ?? '') ?: get_option('admin_email')));
        $sponsor_email = sanitize_email((string) ($booking['sponsor_email'] ?? ''));

        if (is_email($admin_email)) {
            wp_mail(
                $admin_email,
                sprintf(__('[%1$s] Sponsor booking awaiting review: %2$s', 'mds-sponsorboard'), $site_name, (string) ($slot['title'] ?? '')),
                sprintf(__('A sponsor booking was submitted for "%1$s" on "%2$s". Sponsor: %3$s <%4$s>. Review it at %5$s.', 'mds-sponsorboard'), (string) ($slot['title'] ?? ''), (string) ($board['title'] ?? ''), (string) ($booking['sponsor_name'] ?? ''), $sponsor_email, admin_url('admin.php?page=mds3-sponsorboard&board_id=' . absint($board['id'] ?? 0)))
            );
        }

        if (is_email($sponsor_email)) {
            wp_mail(
                $sponsor_email,
                sprintf(__('[%s] Sponsor booking received', 'mds-sponsorboard'), $site_name),
                sprintf(__('We received your booking for "%1$s" on "%2$s". Keep this private manage link to update your sponsor details: %3$s', 'mds-sponsorboard'), (string) ($slot['title'] ?? ''), (string) ($board['title'] ?? ''), esc_url_raw((string) $manage_url))
            );
        }
    }

    public static function booking_updated(array $booking) {
        $email = sanitize_email((string) ($booking['sponsor_email'] ?? ''));
        if (!is_email($email)) {
            return;
        }

        wp_mail(
            $email,
            sprintf(__('[%s] Sponsor booking updated', 'mds-sponsorboard'), self::site_name()),
            sprintf(__('Your booking for "%1$s" is now %2$s and its creative review is %3$s.', 'mds-sponsorboard'), (string) ($booking['slot_title'] ?? ''), ucwords(str_replace('_', ' ', (string) ($booking['status'] ?? 'updated'))), ucwords(str_replace('_', ' ', (string) ($booking['creative_payload']['creative_status'] ?? 'pending_review'))))
        );
    }

    private static function site_name() {
        return wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
    }
}
