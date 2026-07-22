<?php
/**
 * SponsorBoard admin form fields, labels, option sets, and totals.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait RendersSponsorBoardAdminFields {

    private function input($name, $label, $value, $type = 'text', $required = false, $help = '', $readonly = false) {
        $id = $this->field_id($name);
        $help_id = $help ? $id . '-help' : '';
        $attrs = [
            'name="' . esc_attr($name) . '"',
            'id="' . esc_attr($id) . '"',
            'type="' . esc_attr($type) . '"',
            'value="' . esc_attr((string) $value) . '"',
        ];
        if ($required) {
            $attrs[] = 'required';
        }
        if ($readonly) {
            $attrs[] = 'readonly';
        }
        if ($help_id) {
            $attrs[] = 'aria-describedby="' . esc_attr($help_id) . '"';
        }
        if ('number' === $type) {
            $integer_fields = ['columns', 'position', 'booking_limit_per_hour', 'booking_retention_days'];
            $attrs[] = in_array($name, $integer_fields, true) ? 'step="1"' : 'step="0.01"';
            $attrs[] = in_array($name, ['columns', 'booking_limit_per_hour'], true) ? 'min="1"' : ('booking_retention_days' === $name ? 'min="30"' : 'min="0"');
            if ('columns' === $name) {
                $attrs[] = 'max="12"';
            } elseif ('booking_limit_per_hour' === $name) {
                $attrs[] = 'max="100"';
            } elseif ('booking_retention_days' === $name) {
                $attrs[] = 'max="3650"';
            } elseif ('position' === $name) {
                $attrs[] = 'max="1000000"';
            } else {
                $attrs[] = 'max="999999999"';
            }
        }

        $this->admin_template('partials/field-input.php', compact('attrs', 'help', 'help_id', 'id', 'label'));
    }

    private function textarea($name, $label, $value, $help = '') {
        $id = $this->field_id($name);
        $help_id = $help ? $id . '-help' : '';
        $this->admin_template('partials/field-textarea.php', compact('help', 'help_id', 'id', 'label', 'name', 'value'));
    }

    private function select($name, $label, $value, array $options, $help = '') {
        $id = $this->field_id($name);
        $help_id = $help ? $id . '-help' : '';
        $this->admin_template('partials/field-select.php', compact('help', 'help_id', 'id', 'label', 'name', 'options', 'value'));
    }

    private function admin_template($template, array $data = []) {
        $template = ltrim(str_replace('\\', '/', (string) $template), '/');
        if (!$template || false !== strpos($template, '..')) {
            return;
        }

        $path = MDS_SPONSORBOARD_PATH . 'templates/admin/' . $template;
        if (!is_readable($path)) {
            return;
        }

        extract($data, EXTR_SKIP);
        include $path;
    }

    private function field_id($name) {
        static $index = 0;

        $index++;

        return 'mds-sponsorboard-' . sanitize_html_class((string) $name) . '-' . $index;
    }

    private function totals(array $boards) {
        $totals = ['slots' => 0, 'available' => 0, 'reserved' => 0, 'sold' => 0];
        foreach ($boards as $board) {
            $counts = Repository::board_counts(absint($board['id'] ?? 0));
            foreach ($counts['slots'] ?? [] as $status => $count) {
                $count = absint($count);
                $totals['slots'] += $count;
                if (isset($totals[$status])) {
                    $totals[$status] += $count;
                }
            }
        }

        return array_merge($totals, Repository::reporting_summary());
    }

    private function board_statuses() {
        return [
            'draft' => __('Draft', 'mds-sponsorboard'),
            'active' => __('Active', 'mds-sponsorboard'),
            'paused' => __('Paused', 'mds-sponsorboard'),
            'archived' => __('Archived', 'mds-sponsorboard'),
        ];
    }

    private function slot_statuses() {
        return [
            'available' => __('Available', 'mds-sponsorboard'),
            'reserved' => __('Reserved', 'mds-sponsorboard'),
            'sold' => __('Sold', 'mds-sponsorboard'),
            'paused' => __('Paused', 'mds-sponsorboard'),
            'archived' => __('Archived', 'mds-sponsorboard'),
        ];
    }

    private function booking_statuses() {
        return [
            'requested' => __('Requested', 'mds-sponsorboard'),
            'pending_payment' => __('Pending payment', 'mds-sponsorboard'),
            'paid' => __('Paid', 'mds-sponsorboard'),
            'approved' => __('Approved', 'mds-sponsorboard'),
            'changes_requested' => __('Changes requested', 'mds-sponsorboard'),
            'rejected' => __('Rejected', 'mds-sponsorboard'),
            'active' => __('Active', 'mds-sponsorboard'),
            'expired' => __('Expired', 'mds-sponsorboard'),
            'cancelled' => __('Cancelled', 'mds-sponsorboard'),
        ];
    }

    private function creative_statuses() {
        return [
            'pending_review' => __('Pending review', 'mds-sponsorboard'),
            'approved' => __('Approved', 'mds-sponsorboard'),
            'changes_requested' => __('Changes requested', 'mds-sponsorboard'),
            'rejected' => __('Rejected', 'mds-sponsorboard'),
        ];
    }

    private function layouts() {
        return [
            'grid' => __('Grid', 'mds-sponsorboard'),
            'strip' => __('Strip', 'mds-sponsorboard'),
            'mosaic' => __('Mosaic', 'mds-sponsorboard'),
            'cards' => __('Cards', 'mds-sponsorboard'),
        ];
    }

    private function creative_types() {
        return [
            'image' => __('Image', 'mds-sponsorboard'),
            'logo' => __('Logo', 'mds-sponsorboard'),
            'text' => __('Text', 'mds-sponsorboard'),
            'html' => __('HTML', 'mds-sponsorboard'),
        ];
    }

    private function status_label($status) {
        $labels = array_merge($this->board_statuses(), $this->slot_statuses());

        return $labels[sanitize_key((string) $status)] ?? ucfirst((string) $status);
    }

    private function default_currency() {
        return Repository::default_currency();
    }

    private function currency_locked() {
        return Repository::currency_locked();
    }

    private function effective_currency($currency = '') {
        return Repository::effective_currency($currency);
    }

    private function truthy($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }
}
