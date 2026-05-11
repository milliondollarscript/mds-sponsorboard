<?php
/**
 * SponsorBoard public board template.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

$slots = is_array($slots ?? null) ? $slots : [];
?>
<section class="mds-sponsorboard mds-sponsorboard--<?php echo esc_attr($layout); ?> mds3-theme-<?php echo esc_attr($this->theme_mode()); ?> wp-dark-mode-ignore" style="--mds-sponsorboard-columns: <?php echo esc_attr((string) $columns); ?>">
    <header class="mds-sponsorboard__header">
        <span><?php echo esc_html($disclosure); ?></span>
        <h2><?php echo esc_html((string) ($board['title'] ?? __('Sponsor Board', 'mds-sponsorboard'))); ?></h2>
        <?php if (!empty($board['description'])) : ?>
            <p><?php echo wp_kses_post((string) $board['description']); ?></p>
        <?php endif; ?>
    </header>
    <div class="mds-sponsorboard__slots">
        <?php foreach ($slots as $slot) : ?>
            <?php $status = sanitize_html_class((string) ($slot['status'] ?? 'available')); ?>
            <article class="mds-sponsorboard__slot mds-sponsorboard__slot--<?php echo esc_attr($status); ?>">
                <span class="mds-sponsorboard__status"><?php echo esc_html($this->status_label((string) ($slot['status'] ?? 'available'))); ?></span>
                <h3><?php echo esc_html((string) ($slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))); ?></h3>
                <?php if (!empty($slot['description'])) : ?>
                    <p><?php echo wp_kses_post((string) $slot['description']); ?></p>
                <?php endif; ?>
                <strong><?php echo esc_html(strtoupper((string) ($slot['currency'] ?? 'USD')) . ' ' . number_format_i18n((float) ($slot['price'] ?? 0), 2)); ?></strong>
                <?php if ('available' === (string) ($slot['status'] ?? '') && 'active' === (string) ($board['status'] ?? '')) : ?>
                    <?php if ($this->checkout_enabled($board)) : ?>
                        <?php echo $this->checkout_form($board, $slot); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                    <?php echo $this->request_form($board, $slot); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
