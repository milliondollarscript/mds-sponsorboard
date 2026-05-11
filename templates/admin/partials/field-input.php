<?php
/**
 * SponsorBoard input field.
 *
 * @package MDS\Extensions\SponsorBoard
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
</p>
<?php if (!empty($help)) : ?>
    <p class="description"><?php echo esc_html($help); ?></p>
<?php endif; ?>
