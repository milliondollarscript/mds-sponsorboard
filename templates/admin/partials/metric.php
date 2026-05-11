<?php
/**
 * SponsorBoard metric card.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<article>
    <span><?php echo esc_html($label); ?></span>
    <strong><?php echo esc_html($value); ?></strong>
    <small><?php echo esc_html($description); ?></small>
</article>
