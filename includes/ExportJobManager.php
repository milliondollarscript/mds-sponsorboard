<?php
/**
 * SponsorBoard paged CSV export jobs.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds large CSV exports in bounded admin requests.
 */
class ExportJobManager {

    private const TRANSIENT_PREFIX = 'mds_sponsorboard_export_';
    private const EXPORT_DIR = 'mds-sponsorboard-exports';

    /**
     * Start or continue an export job for the current admin user.
     *
     * @param string $type     Export type.
     * @param int    $board_id Optional board ID.
     * @param string $job_id   Existing job ID.
     * @return array|\WP_Error
     */
    public static function start_or_continue($type, $board_id = 0, $job_id = '') {
        $type = self::normalize_type($type);
        if (!$type) {
            return new \WP_Error('mds_sponsorboard_export_type', __('Unsupported SponsorBoard export type.', 'mds-sponsorboard'));
        }

        $job = $job_id ? self::get($job_id) : self::create($type, absint($board_id));
        if (is_wp_error($job)) {
            return $job;
        }

        $job = self::process($job);
        if (is_wp_error($job)) {
            return $job;
        }

        self::save($job);

        return $job;
    }

    /**
     * Return a job for the current admin user.
     *
     * @param string $job_id Job ID.
     * @return array|\WP_Error
     */
    public static function get($job_id) {
        $job_id = self::sanitize_job_id($job_id);
        if ('' === $job_id) {
            return new \WP_Error('mds_sponsorboard_export_missing', __('SponsorBoard export job was not found.', 'mds-sponsorboard'));
        }

        $job = get_transient(self::transient_key($job_id));
        if (!is_array($job) || absint($job['user_id'] ?? 0) !== get_current_user_id()) {
            return new \WP_Error('mds_sponsorboard_export_missing', __('SponsorBoard export job was not found or has expired.', 'mds-sponsorboard'));
        }

        return $job;
    }

    /**
     * Read the export job referenced by the current admin URL.
     *
     * @return array|null
     */
    public static function current_status() {
        $job_id = self::sanitize_job_id(wp_unslash($_GET['sponsorboard_export'] ?? ''));
        if ('' === $job_id) {
            return null;
        }

        $job = self::get($job_id);
        return is_wp_error($job) ? [
            'id' => $job_id,
            'status' => 'error',
            'error' => $job->get_error_message(),
        ] : $job;
    }

    /**
     * Download a completed export.
     *
     * @param string $job_id Job ID.
     * @return void
     */
    public static function download($job_id) {
        $job = self::get($job_id);
        if (is_wp_error($job)) {
            wp_die(esc_html($job->get_error_message()));
        }

        if ('done' !== (string) ($job['status'] ?? '')) {
            wp_die(esc_html__('This SponsorBoard export is not ready yet.', 'mds-sponsorboard'));
        }

        $path = (string) ($job['file_path'] ?? '');
        $dir = self::export_dir();
        $real_path = $path ? realpath($path) : false;
        $real_dir = realpath($dir);
        if (!$real_path || !$real_dir || 0 !== strpos($real_path, $real_dir) || !is_readable($real_path)) {
            wp_die(esc_html__('The SponsorBoard export file could not be read.', 'mds-sponsorboard'));
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) ($job['filename'] ?? basename($real_path))) . '"');
        header('Content-Length: ' . filesize($real_path));
        readfile($real_path);
        exit;
    }

    private static function create($type, $board_id) {
        $dir = self::export_dir();
        if (!$dir) {
            return new \WP_Error('mds_sponsorboard_export_dir', __('SponsorBoard could not create the export directory.', 'mds-sponsorboard'));
        }

        $job_id = wp_generate_uuid4();
        $total = 'bookings' === $type ? Repository::booking_export_count($board_id) : Repository::event_export_count($board_id);
        $filename = 'sponsorboard-' . $type . '-' . gmdate('Ymd-His') . '-' . substr(md5($job_id), 0, 8) . '.csv';

        return [
            'id' => $job_id,
            'type' => $type,
            'board_id' => absint($board_id),
            'user_id' => get_current_user_id(),
            'offset' => 0,
            'total' => absint($total),
            'processed' => 0,
            'status' => 'running',
            'file_path' => trailingslashit($dir) . $filename,
            'filename' => $filename,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
    }

    private static function process(array $job) {
        $columns = self::columns((string) ($job['type'] ?? ''));
        if (!$columns) {
            return new \WP_Error('mds_sponsorboard_export_columns', __('SponsorBoard export columns could not be resolved.', 'mds-sponsorboard'));
        }

        $path = (string) ($job['file_path'] ?? '');
        $offset = absint($job['offset'] ?? 0);
        $total = absint($job['total'] ?? 0);
        $batch_size = max(50, min(5000, absint(\MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorboard/export/batch/size', ['mds_sponsorboard_export_batch_size'], 500, $job))));
        $max_rows = max($batch_size, min(10000, absint(\MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorboard/export/rows/per/request', ['mds_sponsorboard_export_rows_per_request'], 2000, $job))));
        $started = microtime(true);
        $time_budget = max(1, min(10, (float) \MillionDollarScript\Core\Hooks::apply_compat('million-dollar-script/extensions/sponsorboard/export/time/budget', ['mds_sponsorboard_export_time_budget'], 4, $job)));
        $mode = $offset > 0 && file_exists($path) ? 'ab' : 'wb';
        $handle = fopen($path, $mode);
        if (false === $handle) {
            return new \WP_Error('mds_sponsorboard_export_file', __('SponsorBoard could not write the export file.', 'mds-sponsorboard'));
        }

        if ('wb' === $mode) {
            fputcsv($handle, $columns);
        }

        $processed_this_request = 0;
        while ($offset < $total && $processed_this_request < $max_rows && (microtime(true) - $started) < $time_budget) {
            $rows = 'bookings' === $job['type']
                ? Repository::booking_export_rows(absint($job['board_id'] ?? 0), $batch_size, $offset)
                : Repository::event_export_rows(absint($job['board_id'] ?? 0), $batch_size, $offset);

            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? '';
                    $line[] = is_scalar($value) ? (string) $value : wp_json_encode($value);
                }
                fputcsv($handle, $line);
            }

            $count = count($rows);
            $offset += $count;
            $processed_this_request += $count;
        }

        fclose($handle);

        $job['offset'] = $offset;
        $job['processed'] = min($total, absint($job['processed'] ?? 0) + $processed_this_request);
        $job['updated_at'] = current_time('mysql');
        $job['status'] = $offset >= $total ? 'done' : 'running';

        return $job;
    }

    private static function columns($type) {
        if ('bookings' === $type) {
            return ['id', 'board_id', 'board_title', 'slot_id', 'slot_title', 'sponsor_name', 'sponsor_email', 'sponsor_url', 'status', 'creative_status', 'order_id', 'user_id', 'starts_at', 'ends_at', 'created_at', 'updated_at'];
        }

        if ('events' === $type) {
            return ['id', 'board_id', 'slot_id', 'booking_id', 'event_type', 'event_value', 'source', 'event_date', 'created_at'];
        }

        return [];
    }

    private static function normalize_type($type) {
        $type = sanitize_key((string) $type);
        return in_array($type, ['bookings', 'events'], true) ? $type : '';
    }

    private static function save(array $job) {
        set_transient(self::transient_key((string) ($job['id'] ?? '')), $job, DAY_IN_SECONDS);
    }

    private static function transient_key($job_id) {
        return self::TRANSIENT_PREFIX . get_current_user_id() . '_' . self::sanitize_job_id($job_id);
    }

    private static function sanitize_job_id($job_id) {
        return preg_replace('/[^a-f0-9-]/', '', strtolower((string) $job_id));
    }

    private static function export_dir() {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        $dir = trailingslashit((string) $uploads['basedir']) . self::EXPORT_DIR;
        if (!wp_mkdir_p($dir)) {
            return '';
        }

        $deny = trailingslashit($dir) . '.htaccess';
        if (!file_exists($deny)) {
            file_put_contents($deny, "Deny from all\n");
        }

        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        return $dir;
    }
}
