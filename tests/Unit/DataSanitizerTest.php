<?php

use MillionDollarScript\Extensions\SponsorBoard\DataSanitizer;
use PHPUnit\Framework\TestCase;

final class DataSanitizerTest extends TestCase {

    public function test_text_is_sanitized_and_bounded(): void {
        self::assertSame('Hello worl', DataSanitizer::text(" <b>Hello</b>\nworld ", 10));
    }

    public function test_url_accepts_http_and_https_only(): void {
        self::assertSame('https://example.test/sponsor', DataSanitizer::url(' https://example.test/sponsor '));
        self::assertSame('', DataSanitizer::url('javascript:alert(1)'));
        self::assertSame('', DataSanitizer::url('/relative/path'));
    }

    public function test_money_is_rounded_and_clamped(): void {
        self::assertSame(12.345679, DataSanitizer::money('12.3456789'));
        self::assertSame(0, DataSanitizer::money('-5'));
        self::assertSame(999999999999.999999, DataSanitizer::money(PHP_FLOAT_MAX));
    }

    public function test_metadata_limits_depth_and_sanitizes_keys(): void {
        $metadata = DataSanitizer::metadata([
            'Display Name' => '<b>Sponsor</b>',
            'nested' => ['allowed' => true, 'too_deep' => ['value' => 'discarded']],
        ], 2);

        self::assertSame('Sponsor', $metadata['displayname']);
        self::assertTrue($metadata['nested']['allowed']);
        self::assertSame([], $metadata['nested']['too_deep']);
    }

    public function test_board_settings_apply_safe_bounds(): void {
        $settings = DataSanitizer::board_settings([
            'columns' => 99,
            'checkout_enabled' => 'yes',
            'contact_email' => 'owner@example.test',
            'booking_retention_days' => 1,
            'booking_limit_per_hour' => 500,
        ]);

        self::assertSame(12, $settings['columns']);
        self::assertSame('1', $settings['checkout_enabled']);
        self::assertSame('owner@example.test', $settings['contact_email']);
        self::assertSame(30, $settings['booking_retention_days']);
        self::assertSame(100, $settings['booking_limit_per_hour']);
    }
}
