<?php
/**
 * SponsorBoard textarea field.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds-sponsorboard-field mds-sponsorboard-field-wide">
    <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
    <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" rows="4"><?php echo esc_textarea((string) $value); ?></textarea>
</p>
