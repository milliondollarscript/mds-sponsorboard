<?php
/**
 * SponsorBoard input normalization and bounded data rules.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralize persisted-data limits for HTML, REST, and background workflows.
 */
class DataSanitizer {

    public static function text($value, $limit = 191) {
        return self::limit(sanitize_text_field((string) $value), $limit);
    }

    public static function textarea($value, $limit = 2000) {
        return self::limit(sanitize_textarea_field((string) $value), $limit);
    }

    public static function html($value, $limit = 10000) {
        return self::limit(wp_kses_post((string) $value), $limit);
    }

    public static function url($value) {
        $url = esc_url_raw(trim((string) $value), ['http', 'https']);
        if ('' === $url) {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return '';
        }

        return self::limit($url, 2048);
    }

    public static function money($value) {
        return min(999999999999.999999, max(0, round((float) $value, 6)));
    }

    public static function board_settings($settings, array $existing = []) {
        $settings = is_array($settings) ? $settings : [];
        $merged = array_merge($existing, $settings);
        $clean = [
            'columns' => min(12, max(1, absint($merged['columns'] ?? 3))),
            'disclosure_label' => self::text($merged['disclosure_label'] ?? __('Sponsored', 'mds-sponsorboard'), 80),
            'checkout_enabled' => self::truthy($merged['checkout_enabled'] ?? '0') ? '1' : '0',
            'contact_email' => sanitize_email((string) ($merged['contact_email'] ?? '')),
            'terms_url' => self::url($merged['terms_url'] ?? ''),
            'privacy_url' => self::url($merged['privacy_url'] ?? ''),
            'booking_retention_days' => min(3650, max(30, absint($merged['booking_retention_days'] ?? 365))),
            'booking_limit_per_hour' => min(100, max(1, absint($merged['booking_limit_per_hour'] ?? 10))),
        ];

        if (!empty($merged['hosted_service']) && is_array($merged['hosted_service'])) {
            $clean['hosted_service'] = self::metadata($merged['hosted_service'], 2);
        }

        return $clean;
    }

    public static function creative($payload) {
        $payload = is_array($payload) ? $payload : [];
        $status = sanitize_key((string) ($payload['creative_status'] ?? 'pending_review'));
        if (!in_array($status, ['pending_review', 'approved', 'changes_requested', 'rejected'], true)) {
            $status = 'pending_review';
        }

        $clean = [
            'creative_status' => $status,
            'creative_url' => self::url($payload['creative_url'] ?? ''),
            'note' => self::textarea($payload['note'] ?? '', 2000),
        ];

        $attachment_url = self::url($payload['attachment_url'] ?? '');
        if ($attachment_url) {
            $clean['attachment_url'] = $attachment_url;
        }
        foreach (['attachment_id', 'width', 'height', 'bytes', 'creative_moderated_by'] as $field) {
            if (isset($payload[$field])) {
                $clean[$field] = absint($payload[$field]);
            }
        }
        foreach (['mime_type', 'filename', 'creative_moderated_at'] as $field) {
            if (isset($payload[$field])) {
                $clean[$field] = self::text($payload[$field], 191);
            }
        }

        return array_filter($clean, static function ($value) {
            return '' !== $value && null !== $value;
        });
    }

    public static function metadata($metadata, $depth = 3) {
        if (!is_array($metadata) || $depth < 1) {
            return [];
        }

        $clean = [];
        foreach (array_slice($metadata, 0, 100, true) as $key => $value) {
            $key = self::text(sanitize_key((string) $key), 64);
            if ('' === $key) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = self::metadata($value, $depth - 1);
            } elseif (is_bool($value)) {
                $clean[$key] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $clean[$key] = $value;
            } elseif (null === $value) {
                $clean[$key] = null;
            } else {
                $clean[$key] = self::text($value, 500);
            }
        }

        return $clean;
    }

    private static function limit($value, $limit) {
        $limit = max(1, absint($limit));
        if (function_exists('mb_substr')) {
            return mb_substr((string) $value, 0, $limit);
        }

        return substr((string) $value, 0, $limit);
    }

    private static function truthy($value) {
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }
}
