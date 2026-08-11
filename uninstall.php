<?php
/** SponsorBoard uninstall cleanup. */
if (!defined('WP_UNINSTALL_PLUGIN') || !class_exists('\\MillionDollarScript\\Extensions\\CleanupPolicy')) { exit; }
$cleaned = \MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-sponsorboard', [
    'tables' => ['mds_sponsorboard_boards', 'mds_sponsorboard_slots', 'mds_sponsorboard_bookings', 'mds_sponsorboard_events', 'mds_sponsorboard_event_rollups', 'mds_sponsorboard_audit_logs'],
    'option_prefixes' => ['mds_sponsorboard_'],
    'transient_prefixes' => ['mds_sponsorboard_'],
    'cron_hooks' => ['mds_sponsorboard_cleanup_events', 'mds_sponsorboard_process_lifecycle'],
]);
if ($cleaned) {
    $uploads = wp_upload_dir(null, false);
    $export_dir = trailingslashit((string) ($uploads['basedir'] ?? '')) . 'mds-sponsorboard-exports';
    if (is_dir($export_dir) && 0 === strpos(wp_normalize_path($export_dir), wp_normalize_path(trailingslashit((string) ($uploads['basedir'] ?? ''))))) {
        foreach ((array) glob($export_dir . '/*') as $file) { if (is_file($file)) { wp_delete_file($file); } }
        @rmdir($export_dir);
    }
}
