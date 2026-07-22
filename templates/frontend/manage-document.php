<?php
/**
 * SponsorBoard signed sponsor manage document template.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
$document_title = sprintf(
    /* translators: 1: sponsor slot title, 2: site title. */
    __('Manage %1$s - %2$s', 'mds-sponsorboard'),
    (string) ($booking['slot_title'] ?? __('Sponsor Slot', 'mds-sponsorboard')),
    wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex,nofollow,noarchive" />
    <meta name="referrer" content="no-referrer" />
    <title><?php echo esc_html($document_title); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('mds-sponsorboard-manage-screen'); ?>>
    <?php
    wp_body_open();
    echo $this->render_manage_page($booking, $token); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    wp_footer();
    ?>
</body>
</html>
