<?php
/**
 * SponsorBoard textarea field.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds-sponsorboard-field mds-sponsorboard-field-wide">
    <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
    <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" rows="4" <?php echo !empty($help_id) ? 'aria-describedby="' . esc_attr($help_id) . '"' : ''; ?>><?php echo esc_textarea((string) $value); ?></textarea>
    <?php if (!empty($help)) : ?>
        <span id="<?php echo esc_attr($help_id); ?>" class="mds-sponsorboard-help"><?php echo esc_html($help); ?></span>
    <?php endif; ?>
</p>
