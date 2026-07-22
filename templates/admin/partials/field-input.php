<?php
/**
 * SponsorBoard input field.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p class="mds-sponsorboard-field">
    <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
    <?php
    // Attribute fragments are built and escaped before this template is included.
    ?>
    <input <?php echo implode(' ', $attrs ?? []); ?> />
    <?php if (!empty($help)) : ?>
        <span id="<?php echo esc_attr($help_id); ?>" class="mds-sponsorboard-help"><?php echo esc_html($help); ?></span>
    <?php endif; ?>
</p>
