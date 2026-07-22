<?php
/**
 * SponsorBoard metric card.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
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
