<?php
/**
 * Minimal WordPress compatibility layer for SponsorBoard unit tests.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('__')) {
    function __($text) {
        return (string) $text;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value)) ?? '');
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value) {
        return preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $value) ?? '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($value) {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?? '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        $email = filter_var((string) $value, FILTER_SANITIZE_EMAIL);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value, $protocols = null) {
        $url = trim((string) $value);
        $parts = parse_url($url);
        $allowed = is_array($protocols) ? $protocols : ['http', 'https'];
        if (!is_array($parts) || empty($parts['scheme']) || !in_array(strtolower((string) $parts['scheme']), $allowed, true)) {
            return '';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url((string) $url);
    }
}

require_once dirname(__DIR__) . '/includes/DataSanitizer.php';
