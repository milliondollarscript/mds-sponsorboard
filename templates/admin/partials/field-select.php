<?php
/**
 * SponsorBoard select field.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds-sponsorboard-field">
    <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
    <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" <?php echo !empty($help_id) ? 'aria-describedby="' . esc_attr($help_id) . '"' : ''; ?>>
        <?php foreach (($options ?? []) as $key => $option_label) : ?>
            <option value="<?php echo esc_attr((string) $key); ?>" <?php selected((string) $key, (string) $value); ?>>
                <?php echo esc_html((string) $option_label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($help)) : ?>
        <span id="<?php echo esc_attr($help_id); ?>" class="mds-sponsorboard-help"><?php echo esc_html($help); ?></span>
    <?php endif; ?>
</p>
