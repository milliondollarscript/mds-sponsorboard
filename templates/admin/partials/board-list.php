<?php
/**
 * SponsorBoard board selector.
 *
 * @package MDS\Extensions\SponsorBoard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (empty($items)) : ?>
    <p><?php echo esc_html__('No sponsor boards exist yet.', 'mds-sponsorboard'); ?></p>
<?php else : ?>
    <div class="mds-sponsorboard-board-list">
        <?php foreach ($items as $item) : ?>
            <a class="mds-sponsorboard-board-link<?php echo esc_attr(!empty($item['is_selected']) ? ' is-selected' : ''); ?>" href="<?php echo esc_url($item['url'] ?? ''); ?>">
                <span><?php echo esc_html($item['title'] ?? __('Untitled board', 'mds-sponsorboard')); ?></span>
                <small>
                    <?php
                    echo esc_html(($item['status'] ?? '') . ' · ' . sprintf(
                        /* translators: %d: slot count */
                        _n('%d slot', '%d slots', absint($item['slot_count'] ?? 0), 'mds-sponsorboard'),
                        absint($item['slot_count'] ?? 0)
                    ));
                    ?>
                </small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
