<?php
/**
 * SponsorBoard signed sponsor manage document template.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
