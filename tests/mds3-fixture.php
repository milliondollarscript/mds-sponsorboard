<?php
/**
 * SponsorBoard runtime/security regression fixture.
 *
 * Usage:
 * wp --path=/var/www/html eval-file wp-content/plugins/mds-sponsorboard/tests/mds3-fixture.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\\MillionDollarScript\\Extensions\\SponsorBoard\\Repository') || !class_exists('\\MillionDollarScript\\Extensions\\SponsorBoard\\Main')) {
    echo wp_json_encode(['skipped' => 'sponsorboard_unavailable']) . "\n";
    return;
}

$repo = '\\MillionDollarScript\\Extensions\\SponsorBoard\\Repository';
$lifecycle = '\\MillionDollarScript\\Extensions\\SponsorBoard\\BookingLifecycle';
$main = \MillionDollarScript\Extensions\SponsorBoard\Main::instance();
$repo::maybe_install();
$failures = [];
$created_board_id = 0;
$secondary_board_id = 0;
$previous_analytics_settings = get_option('mds_sponsorboard_analytics_settings', null);
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

try {
    $previous_timezone = get_option('timezone_string', '');
    update_option('timezone_string', 'America/Toronto', false);
    $site_datetime = $lifecycle::from_site_input('2026-01-15T12:30');
    $assert('2026-01-15 17:30:00' === $site_datetime, 'Admin schedule input should convert from the WordPress timezone to UTC storage.');
    $assert('2026-01-15T12:30' === $lifecycle::to_site_input($site_datetime), 'Stored UTC schedules should render in the WordPress timezone.');
    $assert(is_wp_error($lifecycle::from_site_input('not-a-date')), 'Invalid admin schedule input should be rejected.');
    update_option('timezone_string', $previous_timezone, false);

    $lifecycle::schedule();
    $assert((bool) wp_next_scheduled($lifecycle::HOOK), 'Sponsor booking lifecycle processing should be scheduled.');

    $invalid_board = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards');
    $invalid_board->set_param('status', 'active');
    $invalid_board_result = $main->rest_create_board($invalid_board);
    $assert(is_wp_error($invalid_board_result), 'REST board creation without a title should fail.');

    $valid_board = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards');
    $valid_board->set_param('title', 'Fixture SponsorBoard ' . wp_generate_password(6, false, false));
    $valid_board->set_param('status', 'active');
    $valid_board->set_param('layout', 'grid');
    $valid_board->set_param('currency', $repo::default_currency());
    $valid_board->set_param('settings', [
        'contact_email' => 'board-owner@example.test',
        'booking_retention_days' => 30,
        'booking_limit_per_hour' => 10,
        'terms_url' => 'https://example.test/sponsor-terms',
        'privacy_url' => 'https://example.test/privacy',
    ]);
    $valid_board_result = $main->rest_create_board($valid_board);
    $assert($valid_board_result instanceof WP_REST_Response && 201 === $valid_board_result->get_status(), 'Valid REST board creation should succeed.');
    $valid_board_data = $valid_board_result instanceof WP_REST_Response ? $valid_board_result->get_data() : [];
    $created_board_id = absint($valid_board_data['data']['id'] ?? 0);
    $assert($created_board_id > 0, 'Created board ID should be returned.');

    $secondary_board_id = $repo::create_board([
        'title' => 'Fixture Secondary Board ' . wp_generate_password(6, false, false),
        'status' => 'active',
    ]);
    $secondary_slot_id = $repo::create_slot([
        'board_id' => $secondary_board_id,
        'title' => 'Secondary Slot',
        'dimensions' => '600x300',
    ]);
    $wrong_inventory = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $secondary_slot_id,
        'sponsor_name' => 'Wrong Board',
        'sponsor_email' => 'wrong-board@example.test',
        'terms_accepted' => true,
    ]);
    $assert(is_wp_error($wrong_inventory), 'Repository should reject a slot that belongs to a different board.');

    $invalid_slot = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards/' . $created_board_id . '/slots');
    $invalid_slot->set_param('id', $created_board_id);
    $invalid_slot->set_param('title', 'Broken dimensions');
    $invalid_slot->set_param('price', 10);
    $invalid_slot->set_param('dimensions', 'wide');
    $invalid_slot_result = $main->rest_create_slot($invalid_slot);
    $assert(is_wp_error($invalid_slot_result), 'REST slot creation with invalid dimensions should fail.');

    $valid_slot = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards/' . $created_board_id . '/slots');
    $valid_slot->set_param('id', $created_board_id);
    $valid_slot->set_param('title', 'Fixture Slot');
    $valid_slot->set_param('price', 12.50);
    $valid_slot->set_param('currency', $repo::default_currency());
    $valid_slot->set_param('dimensions', '600x300');
    $valid_slot_result = $main->rest_create_slot($valid_slot);
    $assert($valid_slot_result instanceof WP_REST_Response && 201 === $valid_slot_result->get_status(), 'Valid REST slot creation should succeed.');
    $valid_slot_data = $valid_slot_result instanceof WP_REST_Response ? $valid_slot_result->get_data() : [];
    $slot_id = absint($valid_slot_data['data']['id'] ?? 0);
    $assert($slot_id > 0, 'Created slot ID should be returned.');

    $missing_consent = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $slot_id,
        'sponsor_name' => 'Missing Consent',
        'sponsor_email' => 'missing-consent@example.test',
    ]);
    $assert(is_wp_error($missing_consent) && 'mds_sponsorboard_consent_required' === $missing_consent->get_error_code(), 'Repository should require explicit sponsorship consent.');

    $booking_result = $repo::reserve_slot_and_create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $slot_id,
        'sponsor_name' => 'Fixture Sponsor',
        'sponsor_email' => 'fixture@example.test',
        'sponsor_url' => 'https://example.test',
        'status' => 'requested',
        'terms_accepted' => true,
        'creative_payload' => [
            'creative_status' => 'approved',
        ],
    ]);
    $booking = is_array($booking_result) ? ($booking_result['booking'] ?? null) : null;
    $booking_id = absint($booking['id'] ?? 0);
    $assert($booking_id > 0, 'Fixture booking should be created for analytics checks.');
    $assert('reserved' === (string) (($repo::find_slot($slot_id)['status'] ?? '')), 'Atomic booking creation should reserve the slot.');

    $competing_reservation = $repo::reserve_slot_and_create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $slot_id,
        'sponsor_name' => 'Competing Sponsor',
        'sponsor_email' => 'competing@example.test',
        'terms_accepted' => true,
    ]);
    $assert(is_wp_error($competing_reservation) && 'mds_sponsorboard_slot_unavailable' === $competing_reservation->get_error_code(), 'A second request should not reserve the same slot.');

    $booking = $repo::update_booking($booking_id, array_merge($booking, [
        'status' => 'approved',
        'creative_payload' => ['creative_status' => 'approved'],
    ]));
    $repo::refresh_slot_status($slot_id);
    $assert('sold' === (string) (($repo::find_slot($slot_id)['status'] ?? '')), 'Approved bookings should mark inventory sold.');

    $repo::record_event([
        'board_id' => $created_board_id,
        'slot_id' => $slot_id,
        'booking_id' => $booking_id,
        'event_type' => 'impression',
        'event_value' => 2,
        'ip_hash' => 'local-ip-hash',
        'user_agent_hash' => 'local-ua-hash',
    ]);
    $repo::record_event([
        'board_id' => $created_board_id,
        'slot_id' => $slot_id,
        'booking_id' => $booking_id,
        'event_type' => 'click',
        'event_value' => 1,
        'ip_hash' => 'local-ip-hash',
        'user_agent_hash' => 'local-ua-hash',
    ]);

    $assert($repo::event_export_count($created_board_id) >= 2, 'Event export count should include fixture events.');
    $first_event_page = $repo::event_export_rows($created_board_id, 1, 0);
    $second_event_page = $repo::event_export_rows($created_board_id, 1, 1);
    $assert(1 === count($first_event_page), 'Event export pagination should limit the first page.');
    $assert(1 === count($second_event_page), 'Event export pagination should return the second page.');

    global $wpdb;
    $old_date = gmdate('Y-m-d H:i:s', time() - (10 * DAY_IN_SECONDS));
    $wpdb->update($repo::table('events'), ['created_at' => $old_date], ['board_id' => $created_board_id], ['%s'], ['%d']);
    $before_cleanup = $repo::reporting_summary($created_board_id);
    $repo::save_analytics_settings([
        'analytics_retention_days' => 1,
        'analytics_rollup_enabled' => '1',
    ]);
    $cleanup = $repo::cleanup_old_events(1);
    $after_cleanup = $repo::reporting_summary($created_board_id);
    $assert(absint($cleanup['deleted'] ?? 0) >= 2, 'Analytics cleanup should delete old raw events.');
    $assert(absint($before_cleanup['impressions'] ?? 0) === absint($after_cleanup['impressions'] ?? 0), 'Rollups should preserve local impression totals.');
    $assert(absint($before_cleanup['clicks'] ?? 0) === absint($after_cleanup['clicks'] ?? 0), 'Rollups should preserve local click totals.');
    $rolled_event_rows = $repo::event_export_rows($created_board_id, 5, 0);
    $assert(in_array('rollup', array_map(static function ($row) {
        return (string) ($row['source'] ?? '');
    }, $rolled_event_rows), true), 'Event export rows should include retained rollups after cleanup.');

    $main->handle_core_payment_source_status('sponsorboard', $booking_id, 'processing', ['provider' => 'fixture']);
    $booking = $repo::find_booking($booking_id);
    $assert('paid' === (string) ($booking['status'] ?? ''), 'Processing payment callbacks should preserve a paid booking.');
    $assert('sold' === (string) (($repo::find_slot($slot_id)['status'] ?? '')), 'Paid payment callbacks should keep inventory sold.');
    $main->handle_core_payment_source_status('sponsorboard', $booking_id, 'refunded', ['provider' => 'fixture']);
    $booking = $repo::find_booking($booking_id);
    $assert('cancelled' === (string) ($booking['status'] ?? ''), 'Refunded payment callbacks should cancel the booking.');
    $assert('available' === (string) (($repo::find_slot($slot_id)['status'] ?? '')), 'Refunded payment callbacks should release unowned inventory.');
    $main->handle_core_payment_source_status('sponsorboard', $booking_id, 'provider_custom_state', []);
    $assert('cancelled' === (string) (($repo::find_booking($booking_id)['status'] ?? '')), 'Unknown payment callbacks should not alter a booking.');

    $booking = $repo::update_booking($booking_id, array_merge($booking, [
        'status' => 'approved',
        'creative_payload' => ['creative_status' => 'approved'],
    ]));
    $repo::refresh_slot_status($slot_id);
    $move_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Move Destination',
        'dimensions' => '600x300',
    ]);
    $moved = $repo::move_booking($booking_id, $move_slot_id);
    $assert(!is_wp_error($moved) && $move_slot_id === absint($moved['slot_id'] ?? 0), 'Admin/API booking moves should preserve the booking and change its slot.');
    $assert('available' === (string) (($repo::find_slot($slot_id)['status'] ?? '')), 'Moving a booking should release its previous slot.');
    $assert('sold' === (string) (($repo::find_slot($move_slot_id)['status'] ?? '')), 'Moving an approved booking should mark its destination sold.');

    $invalid_move_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Invalid REST Move Destination',
        'dimensions' => '600x300',
    ]);
    $invalid_move_request = new WP_REST_Request('PATCH', '/million-dollar-script/v1/sponsorboard/bookings/' . $booking_id);
    $invalid_move_request->set_param('id', $booking_id);
    $invalid_move_request->set_param('slot_id', $invalid_move_slot_id);
    $invalid_move_request->set_param('starts_at', '2030-02-01 12:00:00');
    $invalid_move_request->set_param('ends_at', '2030-02-01 11:00:00');
    $invalid_move_result = $main->rest_update_booking($invalid_move_request);
    $assert(is_wp_error($invalid_move_result) && 'mds_sponsorboard_invalid_window' === $invalid_move_result->get_error_code(), 'REST updates should reject invalid booking windows.');
    $assert($move_slot_id === absint($repo::find_booking($booking_id)['slot_id'] ?? 0), 'A rejected REST schedule update must not move the booking.');
    $assert('available' === (string) ($repo::find_slot($invalid_move_slot_id)['status'] ?? ''), 'A rejected REST schedule update must not reserve its requested destination.');

    $overlapping_live = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $move_slot_id,
        'sponsor_name' => 'Other Live Sponsor',
        'sponsor_email' => 'other-live@example.test',
        'status' => 'approved',
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $assert(is_wp_error($overlapping_live) && 'mds_sponsorboard_booking_window_conflict' === $overlapping_live->get_error_code(), 'Overlapping live bookings should be rejected for the same slot.');
    $repo::update_booking($booking_id, array_merge($repo::find_booking($booking_id) ?: [], ['status' => 'cancelled']));
    $repo::refresh_slot_status($move_slot_id);
    $assert('available' === (string) (($repo::find_slot($move_slot_id)['status'] ?? '')), 'Inventory should become available after its live booking is closed.');

    $fixture_now = time();
    $active_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Active Schedule Slot',
        'price' => 25,
        'dimensions' => '600x300',
    ]);
    $active_result = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $active_slot_id,
        'sponsor_name' => 'Active Schedule Sponsor',
        'sponsor_email' => 'active-schedule@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $fixture_now - HOUR_IN_SECONDS),
        'ends_at' => gmdate('Y-m-d H:i:s', $fixture_now + HOUR_IN_SECONDS),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $active_booking_id = absint(is_array($active_result) ? ($active_result['booking']['id'] ?? 0) : 0);
    $assert($active_booking_id > 0 && $active_booking_id === absint($repo::public_booking_for_slot($active_slot_id)['id'] ?? 0), 'A started approved booking should render publicly.');

    $ended_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Ended Schedule Slot',
        'price' => 30,
        'dimensions' => '600x300',
    ]);
    $ended_result = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $ended_slot_id,
        'sponsor_name' => 'Ended Schedule Sponsor',
        'sponsor_email' => 'ended-schedule@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $fixture_now - (2 * HOUR_IN_SECONDS)),
        'ends_at' => gmdate('Y-m-d H:i:s', $fixture_now - HOUR_IN_SECONDS),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $ended_booking_id = absint(is_array($ended_result) ? ($ended_result['booking']['id'] ?? 0) : 0);
    $assert($ended_booking_id > 0 && null === $repo::public_booking_for_slot($ended_slot_id), 'An ended booking should never render publicly, even before its status transition runs.');
    $repo::refresh_slot_status($ended_slot_id, $fixture_now);
    $assert('available' === (string) ($repo::find_slot($ended_slot_id)['status'] ?? ''), 'Ended bookings should release their slot when inventory is refreshed.');

    $schedule_report = $repo::reporting_summary($created_board_id);
    $assert(1 === absint($schedule_report['active_bookings'] ?? 0), 'Reporting should count only bookings inside their active window.');
    $assert(1 === absint($schedule_report['expiring'] ?? 0), 'Reporting should count only currently active bookings ending within 30 days.');

    $current_transitions = $lifecycle::run(20, $fixture_now);
    $assert(1 === absint($current_transitions['activated'] ?? 0) && 1 === absint($current_transitions['expired'] ?? 0), 'Lifecycle processing should activate started bookings and expire ended bookings.');
    $assert('active' === (string) ($repo::find_booking($active_booking_id)['status'] ?? ''), 'A due approved booking should transition to active.');
    $ended_booking = $repo::find_booking($ended_booking_id) ?: [];
    $assert('expired' === (string) ($ended_booking['status'] ?? '') && !empty($ended_booking['metadata']['lifecycle_was_paid']), 'An ended paid booking should transition to expired while preserving paid history.');

    $repo::update_booking($active_booking_id, array_merge($repo::find_booking($active_booking_id) ?: [], ['status' => 'cancelled']));
    $scheduled_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Sequential Schedule Slot',
        'price' => 40,
        'dimensions' => '600x300',
    ]);
    $first_start = $fixture_now + DAY_IN_SECONDS;
    $first_end = $first_start + HOUR_IN_SECONDS;
    $second_end = $first_end + HOUR_IN_SECONDS;
    $invalid_window = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $scheduled_slot_id,
        'sponsor_name' => 'Invalid Window Sponsor',
        'sponsor_email' => 'invalid-window@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $first_end),
        'ends_at' => gmdate('Y-m-d H:i:s', $first_start),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $assert(is_wp_error($invalid_window) && 'mds_sponsorboard_invalid_window' === $invalid_window->get_error_code(), 'Booking end time must be later than its start time.');

    $first_scheduled = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $scheduled_slot_id,
        'sponsor_name' => 'First Scheduled Sponsor',
        'sponsor_email' => 'first-scheduled@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $first_start),
        'ends_at' => gmdate('Y-m-d H:i:s', $first_end),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $first_scheduled_id = absint(is_array($first_scheduled) ? ($first_scheduled['booking']['id'] ?? 0) : 0);
    $overlapping_schedule = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $scheduled_slot_id,
        'sponsor_name' => 'Overlapping Schedule Sponsor',
        'sponsor_email' => 'overlapping-schedule@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $first_start + (30 * MINUTE_IN_SECONDS)),
        'ends_at' => gmdate('Y-m-d H:i:s', $second_end),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $assert(is_wp_error($overlapping_schedule) && 'mds_sponsorboard_booking_window_conflict' === $overlapping_schedule->get_error_code(), 'Overlapping scheduled windows should be rejected.');

    $second_scheduled = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $scheduled_slot_id,
        'sponsor_name' => 'Second Scheduled Sponsor',
        'sponsor_email' => 'second-scheduled@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', $first_end),
        'ends_at' => gmdate('Y-m-d H:i:s', $second_end),
        'terms_accepted' => true,
        'creative_payload' => ['creative_status' => 'approved'],
    ]);
    $second_scheduled_id = absint(is_array($second_scheduled) ? ($second_scheduled['booking']['id'] ?? 0) : 0);
    $assert($first_scheduled_id > 0 && $second_scheduled_id > 0, 'Adjacent non-overlapping schedule windows should be allowed.');
    $assert('scheduled' === $lifecycle::state($repo::find_booking($first_scheduled_id) ?: [], $fixture_now), 'A future booking should report a scheduled lifecycle state.');
    $assert(null === $repo::public_booking_for_slot($scheduled_slot_id), 'Future scheduled bookings should not render publicly.');
    $repo::refresh_slot_status($scheduled_slot_id, $fixture_now);
    $assert('sold' === (string) ($repo::find_slot($scheduled_slot_id)['status'] ?? ''), 'Future approved bookings should keep their scheduled inventory reserved.');

    $first_start_result = $lifecycle::run(20, $first_start + MINUTE_IN_SECONDS);
    $assert(1 === absint($first_start_result['activated'] ?? 0), 'The first scheduled booking should activate after its start.');
    $boundary_result = $lifecycle::run(20, $first_end);
    $assert(1 === absint($boundary_result['activated'] ?? 0) && 1 === absint($boundary_result['expired'] ?? 0), 'Adjacent schedules should hand off deterministically at their shared boundary.');
    $final_end_result = $lifecycle::run(20, $second_end);
    $assert(1 === absint($final_end_result['expired'] ?? 0), 'The final scheduled booking should expire at its end.');
    $idempotent_result = $lifecycle::run(20, $second_end);
    $assert(0 === absint($idempotent_result['processed'] ?? 0), 'Lifecycle processing should be idempotent after due transitions are complete.');
    $assert('available' === (string) ($repo::find_slot($scheduled_slot_id)['status'] ?? ''), 'Inventory should be released after the final scheduled booking ends.');

    $assert(!class_exists('\\MillionDollarScript\\Extensions\\SponsorBoard\\HostedClient'), 'The retired hosted-service client should not be available.');
    $sanitized_board_settings = \MillionDollarScript\Extensions\SponsorBoard\DataSanitizer::board_settings([
        'columns' => 4,
        'hosted_service' => ['board_id' => 'retired-remote-board'],
    ]);
    $assert(!array_key_exists('hosted_service', $sanitized_board_settings), 'Retired hosted identifiers should not survive board settings validation.');

    $analytics_settings = $repo::save_analytics_settings([
        'analytics_retention_days' => 45,
        'analytics_rollup_enabled' => '1',
    ]);
    $assert(45 === absint($analytics_settings['retention_days'] ?? 0) && !empty($analytics_settings['rollup_enabled']), 'Local analytics retention and rollup settings should remain configurable.');

    $captured_mail = [];
    $capture_mail = static function ($return, $mail) use (&$captured_mail) {
        unset($return);
        $captured_mail[] = $mail;
        return true;
    };
    add_filter('pre_wp_mail', $capture_mail, 10, 2);
    \MillionDollarScript\Extensions\SponsorBoard\Notifications::booking_created(
        $repo::find_board($created_board_id) ?: [],
        $repo::find_slot($move_slot_id) ?: [],
        $repo::find_booking($booking_id) ?: [],
        home_url('/?fixture-manage=1')
    );
    remove_filter('pre_wp_mail', $capture_mail, 10);
    $assert(count($captured_mail) >= 2, 'Booking creation should notify both the board contact and sponsor.');

    $privacy_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Privacy Fixture Slot',
        'dimensions' => '600x300',
    ]);
    $privacy_public_booking_id = 0;
    for ($index = 0; $index < 105; $index++) {
        $privacy_booking = $repo::create_booking([
            'board_id' => $created_board_id,
            'slot_id' => $privacy_slot_id,
            'sponsor_name' => 'Privacy Sponsor ' . $index,
            'sponsor_email' => 'privacy-fixture@example.test',
            'sponsor_url' => 0 === $index ? 'https://privacy-sponsor.example.test' : '',
            'status' => 0 === $index ? 'approved' : 'cancelled',
            'terms_accepted' => true,
            'creative_payload' => 0 === $index ? [
                'creative_status' => 'approved',
                'headline' => 'Public privacy fixture',
            ] : [],
        ]);
        if (0 === $index && is_array($privacy_booking)) {
            $privacy_public_booking_id = absint($privacy_booking['booking']['id'] ?? 0);
        }
    }
    $privacy_page_one = $repo::bookings_for_email_page('privacy-fixture@example.test', 1, 100);
    $privacy_page_two = $repo::bookings_for_email_page('privacy-fixture@example.test', 2, 100);
    $assert(105 === absint($privacy_page_one['total'] ?? 0) && 100 === count($privacy_page_one['items'] ?? []) && 5 === count($privacy_page_two['items'] ?? []), 'Privacy export queries should paginate beyond 100 bookings.');
    $erase_one = $main->privacy_eraser('privacy-fixture@example.test', 1);
    $erase_two = $main->privacy_eraser('privacy-fixture@example.test', 2);
    $assert(false === (bool) ($erase_one['done'] ?? true) && true === (bool) ($erase_two['done'] ?? false), 'Privacy erasure should continue in bounded batches until all bookings are anonymized.');
    $assert(0 === absint($repo::bookings_for_email_page('privacy-fixture@example.test', 1, 100)['total'] ?? 0), 'Privacy erasure should remove the email from every matching booking.');
    $erased_public_booking = $repo::find_booking($privacy_public_booking_id);
    $assert(
        'Erased sponsor' === (string) ($erased_public_booking['sponsor_name'] ?? '')
        && '' === (string) ($erased_public_booking['sponsor_url'] ?? '')
        && [] === (array) ($erased_public_booking['creative_payload'] ?? []),
        'Explicit privacy erasure should remove approved public sponsor identity, URL, and creative content.'
    );

    $future_retention_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Future Retention Slot',
        'dimensions' => '600x300',
    ]);
    $future_retention_result = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $future_retention_slot_id,
        'sponsor_name' => 'Future Retention Sponsor',
        'sponsor_email' => 'future-retention@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', time() + (2 * DAY_IN_SECONDS)),
        'ends_at' => gmdate('Y-m-d H:i:s', time() + (3 * DAY_IN_SECONDS)),
        'terms_accepted' => true,
        'creative_payload' => [
            'creative_status' => 'approved',
            'creative_url' => 'https://example.test/future-creative.png',
        ],
    ]);
    $future_retention_id = absint(is_array($future_retention_result) ? ($future_retention_result['booking']['id'] ?? 0) : 0);

    $ended_retention_slot_id = $repo::create_slot([
        'board_id' => $created_board_id,
        'title' => 'Ended Retention Slot',
        'dimensions' => '600x300',
    ]);
    $ended_retention_result = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $ended_retention_slot_id,
        'sponsor_name' => 'Ended Retention Sponsor',
        'sponsor_email' => 'ended-retention@example.test',
        'status' => 'approved',
        'starts_at' => gmdate('Y-m-d H:i:s', time() - (2 * HOUR_IN_SECONDS)),
        'ends_at' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS),
        'terms_accepted' => true,
        'creative_payload' => [
            'creative_status' => 'approved',
            'creative_url' => 'https://example.test/ended-creative.png',
        ],
    ]);
    $ended_retention_id = absint(is_array($ended_retention_result) ? ($ended_retention_result['booking']['id'] ?? 0) : 0);

    $retention_result = $repo::create_booking([
        'board_id' => $created_board_id,
        'slot_id' => $privacy_slot_id,
        'sponsor_name' => 'Retention Sponsor',
        'sponsor_email' => 'retention-fixture@example.test',
        'status' => 'cancelled',
        'terms_accepted' => true,
    ]);
    $retention_id = absint(is_array($retention_result) ? ($retention_result['booking']['id'] ?? 0) : 0);
    $old_retention_date = gmdate('Y-m-d H:i:s', time() - (40 * DAY_IN_SECONDS));
    foreach ([$future_retention_id, $ended_retention_id, $retention_id, $ended_booking_id] as $stale_booking_id) {
        $wpdb->update($repo::table('bookings'), ['updated_at' => $old_retention_date], ['id' => $stale_booking_id], ['%s'], ['%d']);
    }
    $repo::cleanup_private_booking_data(200);
    $assert('' === (string) (($repo::find_booking($retention_id)['sponsor_email'] ?? '')), 'Board retention should anonymize expired private booking data.');
    $future_retention_booking = $repo::find_booking($future_retention_id) ?: [];
    $assert(
        '' === (string) ($future_retention_booking['sponsor_email'] ?? '')
        && 'approved' === (string) ($future_retention_booking['creative_payload']['creative_status'] ?? ''),
        'Retention cleanup should remove private contact data while preserving approved creative for a future scheduled placement.'
    );
    $ended_retention_booking = $repo::find_booking($ended_retention_id) ?: [];
    $assert(
        '' === (string) ($ended_retention_booking['sponsor_email'] ?? '')
        && [] === (array) ($ended_retention_booking['creative_payload'] ?? []),
        'Retention cleanup should remove ended creative even when its workflow status has not transitioned yet.'
    );
    $anonymized_paid_booking = $repo::find_booking($ended_booking_id) ?: [];
    $assert(
        '' === (string) ($anonymized_paid_booking['sponsor_email'] ?? '')
        && !empty($anonymized_paid_booking['metadata']['lifecycle_was_paid']),
        'Retention cleanup should preserve the non-personal paid-history marker for expired revenue reporting.'
    );

    $invalid_booking = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards/' . $created_board_id . '/bookings');
    $invalid_booking->set_param('id', $created_board_id);
    $invalid_booking->set_param('slot_id', $slot_id);
    $invalid_booking->set_param('sponsor_name', 'Fixture Sponsor');
    $invalid_booking->set_param('sponsor_email', 'not-an-email');
    $invalid_booking_result = $main->rest_create_booking($invalid_booking);
    $assert(is_wp_error($invalid_booking_result), 'REST booking creation with invalid email should fail.');

    $missing_rest_consent = new WP_REST_Request('POST', '/million-dollar-script/v1/sponsorboard/boards/' . $created_board_id . '/bookings');
    $missing_rest_consent->set_param('id', $created_board_id);
    $missing_rest_consent->set_param('slot_id', $privacy_slot_id);
    $missing_rest_consent->set_param('sponsor_name', 'REST Consent Fixture');
    $missing_rest_consent->set_param('sponsor_email', 'rest-consent@example.test');
    $missing_rest_consent_result = $main->rest_create_booking($missing_rest_consent);
    $assert(is_wp_error($missing_rest_consent_result) && 'mds_sponsorboard_booking_consent_required' === $missing_rest_consent_result->get_error_code(), 'REST booking creation should require explicit consent.');

    $board_response_request = new WP_REST_Request('GET', '/million-dollar-script/v1/sponsorboard/boards/' . $created_board_id);
    $board_response_request->set_param('id', $created_board_id);
    $board_response = $main->rest_get_board($board_response_request);
    $board_response_data = $board_response instanceof WP_REST_Response ? $board_response->get_data() : [];
    $public_settings = is_array($board_response_data['data']['settings'] ?? null) ? $board_response_data['data']['settings'] : [];
    $assert(!array_key_exists('contact_email', $public_settings), 'Read-level board payloads should omit private contact settings.');

    $manifest = $main->api_endpoint_manifest([]);
    $endpoint_ids = array_map(static function ($endpoint) {
        return (string) ($endpoint['id'] ?? '');
    }, $manifest);
    $assert(in_array('sponsorboard-boards-read', $endpoint_ids, true), 'Board read endpoint should be in the API manifest.');
    $assert(in_array('sponsorboard-bookings-write', $endpoint_ids, true), 'Booking write endpoint should be in the API manifest.');
    $booking_read_manifest = array_values(array_filter($manifest, static function ($endpoint) {
        return 'sponsorboard-bookings-read' === (string) ($endpoint['id'] ?? '');
    }));
    $assert('api_key_write' === (string) ($booking_read_manifest[0]['minimum_security_level'] ?? ''), 'Private booking reads should require write-level API authorization.');
} finally {
    if (null === $previous_analytics_settings) {
        delete_option('mds_sponsorboard_analytics_settings');
    } else {
        update_option('mds_sponsorboard_analytics_settings', $previous_analytics_settings, false);
    }
    if ($created_board_id) {
        $assert($repo::delete_board_cascade($created_board_id), 'Fixture board should be deleted with all child data.');
        $assert(!$repo::find_board($created_board_id), 'Fixture board should not remain after cleanup.');
    }
    if ($secondary_board_id) {
        $assert($repo::delete_board_cascade($secondary_board_id), 'Secondary fixture board should be deleted with all child data.');
    }
}

echo wp_json_encode([
    'status' => $failures ? 'failed' : 'passed',
    'failures' => $failures,
]) . "\n";
