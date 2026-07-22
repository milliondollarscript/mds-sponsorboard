<?php
/**
 * SponsorBoard data repository and schema.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Local-first data access for SponsorBoard.
 */
class Repository {

    public const DB_VERSION = '1.2.0';
    public const DB_VERSION_OPTION = 'mds_sponsorboard_db_version';
    private const ANALYTICS_SETTINGS_OPTION = 'mds_sponsorboard_analytics_settings';

    /**
     * Install or update database tables.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $boards = self::table('boards');
        $slots = self::table('slots');
        $bookings = self::table('bookings');
        $events = self::table('events');
        $event_rollups = self::table('event_rollups');
        $audit = self::table('audit_logs');

        dbDelta("
CREATE TABLE {$boards} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(191) NOT NULL,
    title VARCHAR(191) NOT NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    layout VARCHAR(32) NOT NULL DEFAULT 'grid',
    currency VARCHAR(8) NOT NULL DEFAULT 'USD',
    settings LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY slug (slug),
    KEY status (status)
) {$charset};
CREATE TABLE {$slots} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(191) NOT NULL,
    title VARCHAR(191) NOT NULL,
    description TEXT NULL,
    price DECIMAL(20,6) NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'USD',
    status VARCHAR(32) NOT NULL DEFAULT 'available',
    position INT UNSIGNED NOT NULL DEFAULT 0,
    creative_type VARCHAR(32) NOT NULL DEFAULT 'image',
    dimensions VARCHAR(64) NOT NULL DEFAULT '',
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY board_slug (board_id,slug),
    KEY board_status (board_id,status),
    KEY position (position)
) {$charset};
CREATE TABLE {$bookings} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    slot_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    sponsor_name VARCHAR(191) NOT NULL DEFAULT '',
    sponsor_email VARCHAR(191) NOT NULL DEFAULT '',
    sponsor_url TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'requested',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    creative_id BIGINT UNSIGNED NULL,
    creative_payload LONGTEXT NULL,
    manage_token_hash VARCHAR(191) NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY board_status (board_id,status),
    KEY slot_status (slot_id,status),
    KEY lifecycle_due (status,ends_at,starts_at),
    KEY slot_window (slot_id,status,starts_at,ends_at),
    KEY order_id (order_id),
    KEY user_id (user_id),
    KEY manage_token_hash (manage_token_hash)
) {$charset};
CREATE TABLE {$events} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    slot_id BIGINT UNSIGNED NULL,
    booking_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(32) NOT NULL,
    event_value DECIMAL(20,6) NOT NULL DEFAULT 1,
    metadata LONGTEXT NULL,
    ip_hash VARCHAR(64) NULL,
    user_agent_hash VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY board_event (board_id,event_type),
    KEY slot_event (slot_id,event_type),
    KEY booking_event (booking_id,event_type),
    KEY created_at (created_at)
) {$charset};
CREATE TABLE {$event_rollups} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    slot_id BIGINT UNSIGNED NULL,
    booking_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(32) NOT NULL,
    event_date DATE NOT NULL,
    event_value DECIMAL(20,6) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY rollup_scope (board_id,slot_id,booking_id,event_type,event_date),
    KEY board_event_date (board_id,event_type,event_date),
    KEY booking_event_date (booking_id,event_type,event_date)
) {$charset};
CREATE TABLE {$audit} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    object_type VARCHAR(64) NOT NULL,
    object_id BIGINT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    message TEXT NULL,
    metadata LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY object_action (object_type,object_id,action),
    KEY actor_id (actor_id),
    KEY created_at (created_at)
) {$charset};
");

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * Install schema if needed.
     *
     * @return void
     */
    public static function maybe_install() {
        if (self::DB_VERSION !== (string) get_option(self::DB_VERSION_OPTION, '')) {
            self::install();
        }
    }

    /**
     * Create starter board and slots for first activation.
     *
     * @return int Board ID.
     */
    public static function maybe_seed() {
        if (self::all_boards(1)) {
            return 0;
        }

        $board_id = self::create_board([
            'title' => __('Main Sponsor Board', 'mds-sponsorboard'),
            'description' => __('Starter sponsorship inventory for featured partners, supporters, and launch sponsors.', 'mds-sponsorboard'),
            'status' => 'draft',
            'layout' => 'grid',
            'currency' => self::default_currency(),
            'settings' => [
                'columns' => 3,
                'disclosure_label' => __('Sponsored', 'mds-sponsorboard'),
                'checkout_enabled' => '0',
                'payment_provider' => '',
            ],
        ]);

        if ($board_id) {
            $labels = [
                __('Hero Sponsor', 'mds-sponsorboard'),
                __('Featured Sponsor', 'mds-sponsorboard'),
                __('Community Sponsor', 'mds-sponsorboard'),
                __('Launch Partner', 'mds-sponsorboard'),
                __('Supporter Slot', 'mds-sponsorboard'),
                __('Resource Sponsor', 'mds-sponsorboard'),
            ];
            foreach ($labels as $index => $label) {
                self::create_slot([
                    'board_id' => $board_id,
                    'title' => $label,
                    'price' => 100 + ($index * 25),
                    'currency' => self::default_currency(),
                    'status' => 'available',
                    'position' => $index + 1,
                    'dimensions' => '600x300',
                ]);
            }
        }

        return $board_id;
    }

    /**
     * Return table name.
     *
     * @param string $name Table suffix.
     * @return string
     */
    public static function table($name) {
        global $wpdb;

        return $wpdb->prefix . 'mds_sponsorboard_' . sanitize_key((string) $name);
    }

    /**
     * Return all boards.
     *
     * @param int $limit Maximum number of boards.
     * @return array
     */
    public static function all_boards($limit = 50) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::ident(self::table('boards')) . ' ORDER BY updated_at DESC, id DESC LIMIT %d',
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return array_map([__CLASS__, 'normalize_board'], is_array($rows) ? $rows : []);
    }

    /**
     * Return one page of boards for admin and API consumers.
     *
     * @param int $page     One-based page number.
     * @param int $per_page Rows per page.
     * @return array
     */
    public static function boards_page($page = 1, $per_page = 20) {
        global $wpdb;

        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        $total = absint($wpdb->get_var('SELECT COUNT(*) FROM ' . self::ident(self::table('boards'))));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::ident(self::table('boards')) . ' ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
                $per_page,
                ($page - 1) * $per_page
            ),
            ARRAY_A
        );

        return [
            'items' => array_map([__CLASS__, 'normalize_board'], is_array($rows) ? $rows : []),
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    /**
     * Find one board.
     *
     * @param int $id Board ID.
     * @return array|null
     */
    public static function find_board($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::ident(self::table('boards')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_board($row) : null;
    }

    /**
     * Find one board by slug.
     *
     * @param string $slug Board slug.
     * @return array|null
     */
    public static function find_board_by_slug($slug) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::ident(self::table('boards')) . ' WHERE slug = %s',
                sanitize_title((string) $slug)
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_board($row) : null;
    }

    /**
     * Create a board.
     *
     * @param array $data Board data.
     * @return int
     */
    public static function create_board(array $data) {
        global $wpdb;

        $now = self::now();
        $title = DataSanitizer::text($data['title'] ?? '', 191);
        if ('' === $title) {
            $title = __('Untitled Sponsor Board', 'mds-sponsorboard');
        }

        $slug = self::unique_slug((string) ($data['slug'] ?? $title), 'boards');

        $insert = [
            'slug' => $slug,
            'title' => $title,
            'description' => DataSanitizer::html($data['description'] ?? '', 10000),
            'status' => self::board_status($data['status'] ?? 'draft'),
            'layout' => self::layout($data['layout'] ?? 'grid'),
            'currency' => self::effective_currency($data['currency'] ?? self::default_currency()),
            'settings' => self::json(DataSanitizer::board_settings($data['settings'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(self::table('boards'), $insert, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
        if (false === $result) {
            return 0;
        }

        $id = absint($wpdb->insert_id);
        self::audit('board', $id, 'created', __('Sponsor board created.', 'mds-sponsorboard'), $insert);

        return $id;
    }

    /**
     * Update a board.
     *
     * @param int   $id Board ID.
     * @param array $data Board data.
     * @return array|null
     */
    public static function update_board($id, array $data) {
        global $wpdb;

        $id = absint($id);
        $board = $id ? self::find_board($id) : null;
        if (!$board) {
            return null;
        }

        $existing_settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        $update = [
            'title' => DataSanitizer::text($data['title'] ?? $board['title'] ?? __('Untitled Sponsor Board', 'mds-sponsorboard'), 191),
            'description' => DataSanitizer::html($data['description'] ?? $board['description'] ?? '', 10000),
            'status' => self::board_status($data['status'] ?? $board['status'] ?? 'draft'),
            'layout' => self::layout($data['layout'] ?? $board['layout'] ?? 'grid'),
            'currency' => self::effective_currency($data['currency'] ?? $board['currency'] ?? self::default_currency()),
            'settings' => self::json(DataSanitizer::board_settings($data['settings'] ?? [], $existing_settings)),
            'updated_at' => self::now(),
        ];

        if (!empty($data['slug'])) {
            $update['slug'] = self::unique_slug((string) $data['slug'], 'boards', $id);
        }

        $formats = array_fill(0, count($update), '%s');
        $result = $wpdb->update(self::table('boards'), $update, ['id' => $id], $formats, ['%d']);
        if (false === $result) {
            return null;
        }

        self::audit('board', $id, 'updated', __('Sponsor board updated.', 'mds-sponsorboard'), $update);

        return self::find_board($id);
    }

    /**
     * Return slots for a board.
     *
     * @param int $board_id Board ID.
     * @return array
     */
    public static function slots_for_board($board_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . self::ident(self::table('slots')) . ' WHERE board_id = %d ORDER BY position ASC, id ASC',
                absint($board_id)
            ),
            ARRAY_A
        );

        return array_map([__CLASS__, 'normalize_slot'], is_array($rows) ? $rows : []);
    }

    /**
     * Find one slot.
     *
     * @param int $id Slot ID.
     * @return array|null
     */
    public static function find_slot($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::ident(self::table('slots')) . ' WHERE id = %d', absint($id)),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_slot($row) : null;
    }

    /**
     * Create a sponsor slot.
     *
     * @param array $data Slot data.
     * @return int
     */
    public static function create_slot(array $data) {
        global $wpdb;

        $board_id = absint($data['board_id'] ?? 0);
        if (!$board_id || !self::find_board($board_id)) {
            return 0;
        }

        $now = self::now();
        $title = DataSanitizer::text($data['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'), 191);
        $dimensions = DataSanitizer::text($data['dimensions'] ?? '', 64);
        if ('' !== $dimensions && !preg_match('/^\d{1,5}x\d{1,5}$/', $dimensions)) {
            return 0;
        }
        $slug = self::unique_slot_slug($board_id, (string) ($data['slug'] ?? $title));
        $insert = [
            'board_id' => $board_id,
            'slug' => $slug,
            'title' => $title,
            'description' => DataSanitizer::html($data['description'] ?? '', 10000),
            'price' => DataSanitizer::money($data['price'] ?? 0),
            'currency' => self::effective_currency($data['currency'] ?? self::default_currency()),
            'status' => self::slot_status($data['status'] ?? 'available'),
            'position' => absint($data['position'] ?? 0),
            'creative_type' => self::creative_type($data['creative_type'] ?? 'image'),
            'dimensions' => $dimensions,
            'metadata' => self::json(DataSanitizer::metadata($data['metadata'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(
            self::table('slots'),
            $insert,
            ['%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $result) {
            return 0;
        }

        $id = absint($wpdb->insert_id);
        self::audit('slot', $id, 'created', __('Sponsor slot created.', 'mds-sponsorboard'), $insert);

        return $id;
    }

    /**
     * Update a sponsor slot.
     *
     * @param int   $id   Slot ID.
     * @param array $data Slot data.
     * @return array|null
     */
    public static function update_slot($id, array $data) {
        global $wpdb;

        $id = absint($id);
        $slot = $id ? self::find_slot($id) : null;
        if (!$slot) {
            return null;
        }

        $board_id = absint($data['board_id'] ?? $slot['board_id']);
        if (!$board_id || !self::find_board($board_id)) {
            return null;
        }

        $dimensions = DataSanitizer::text($data['dimensions'] ?? $slot['dimensions'] ?? '', 64);
        if ('' !== $dimensions && !preg_match('/^\d{1,5}x\d{1,5}$/', $dimensions)) {
            return null;
        }

        $update = [
            'board_id' => $board_id,
            'title' => DataSanitizer::text($data['title'] ?? $slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'), 191),
            'description' => DataSanitizer::html($data['description'] ?? $slot['description'] ?? '', 10000),
            'price' => DataSanitizer::money($data['price'] ?? $slot['price'] ?? 0),
            'currency' => self::effective_currency($data['currency'] ?? $slot['currency'] ?? self::default_currency()),
            'status' => self::slot_status($data['status'] ?? $slot['status'] ?? 'available'),
            'position' => absint($data['position'] ?? $slot['position'] ?? 0),
            'creative_type' => self::creative_type($data['creative_type'] ?? $slot['creative_type'] ?? 'image'),
            'dimensions' => $dimensions,
            'metadata' => self::json(DataSanitizer::metadata($data['metadata'] ?? $slot['metadata'] ?? [])),
            'updated_at' => self::now(),
        ];

        if (!empty($data['slug'])) {
            $update['slug'] = self::unique_slot_slug($board_id, (string) $data['slug'], $id);
        }

        $formats = [];
        foreach ($update as $key => $value) {
            unset($value);
            $formats[] = in_array($key, ['board_id', 'position'], true) ? '%d' : ('price' === $key ? '%f' : '%s');
        }

        $result = $wpdb->update(self::table('slots'), $update, ['id' => $id], $formats, ['%d']);
        if (false === $result) {
            return null;
        }

        self::audit('slot', $id, 'updated', __('Sponsor slot updated.', 'mds-sponsorboard'), $update);

        return self::find_slot($id);
    }

    /**
     * Board summary counts.
     *
     * @param int $board_id Board ID.
     * @return array
     */
    public static function board_counts($board_id) {
        global $wpdb;

        $board_id = absint($board_id);
        $slot_counts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT status, COUNT(*) AS total FROM ' . self::ident(self::table('slots')) . ' WHERE board_id = %d GROUP BY status',
                $board_id
            ),
            ARRAY_A
        );
        $booking_counts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT status, COUNT(*) AS total FROM ' . self::ident(self::table('bookings')) . ' WHERE board_id = %d GROUP BY status',
                $board_id
            ),
            ARRAY_A
        );

        return [
            'slots' => self::count_map($slot_counts),
            'bookings' => self::count_map($booking_counts),
        ];
    }

    /**
     * Create a sponsor booking request.
     *
     * @param array $data Booking data.
     * @return array|\WP_Error Booking row and raw manage token.
     */
    public static function create_booking(array $data) {
        global $wpdb;

        $board_id = absint($data['board_id'] ?? 0);
        $slot_id = absint($data['slot_id'] ?? 0);
        $board = $board_id ? self::find_board($board_id) : null;
        $slot = $slot_id ? self::find_slot($slot_id) : null;
        if (!$board || !$slot || absint($slot['board_id'] ?? 0) !== $board_id) {
            return new \WP_Error('mds_sponsorboard_invalid_inventory', __('The selected sponsor slot does not belong to this board.', 'mds-sponsorboard'));
        }

        $name = DataSanitizer::text($data['sponsor_name'] ?? '', 191);
        $email = sanitize_email((string) ($data['sponsor_email'] ?? ''));
        if ('' === $name || !is_email($email)) {
            return new \WP_Error('mds_sponsorboard_invalid_sponsor', __('Enter a sponsor name and a valid email address.', 'mds-sponsorboard'));
        }

        if (empty($data['terms_accepted'])) {
            return new \WP_Error('mds_sponsorboard_consent_required', __('Accept the sponsorship terms and privacy notice before continuing.', 'mds-sponsorboard'));
        }

        $token = self::manage_token();
        $now = self::now();
        $creative_payload = DataSanitizer::creative($data['creative_payload'] ?? []);
        $metadata = DataSanitizer::metadata($data['metadata'] ?? []);
        $metadata['terms_accepted_at'] = $now;
        $metadata['private_data_updated_at'] = $now;
        $status = self::booking_status($data['status'] ?? 'requested');
        $schedule = self::validate_booking_schedule($slot_id, $data['starts_at'] ?? '', $data['ends_at'] ?? '', $status);
        if (is_wp_error($schedule)) {
            return $schedule;
        }
        $starts_at = self::datetime_or_null($data['starts_at'] ?? null);
        $ends_at = self::datetime_or_null($data['ends_at'] ?? null);
        if (in_array($status, ['paid', 'approved', 'active'], true)) {
            $metadata['lifecycle_was_paid'] = true;
        }

        $insert = [
            'board_id' => $board_id,
            'slot_id' => $slot_id,
            'order_id' => absint($data['order_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? get_current_user_id()),
            'sponsor_name' => $name,
            'sponsor_email' => $email,
            'sponsor_url' => DataSanitizer::url($data['sponsor_url'] ?? ''),
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'creative_id' => absint($data['creative_id'] ?? 0),
            'creative_payload' => self::json($creative_payload),
            'manage_token_hash' => self::hash_manage_token($token),
            'metadata' => self::json($metadata),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(
            self::table('bookings'),
            $insert,
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $result) {
            return new \WP_Error('mds_sponsorboard_booking_insert_failed', __('The sponsor booking could not be saved.', 'mds-sponsorboard'));
        }

        $id = absint($wpdb->insert_id);
        self::audit('booking', $id, 'created', __('Sponsor booking requested.', 'mds-sponsorboard'), [
            'board_id' => $board_id,
            'slot_id' => $slot_id,
            'status' => $insert['status'],
        ]);

        return [
            'booking' => self::find_booking($id),
            'token' => $token,
        ];
    }

    /**
     * Atomically reserve an available slot and create its booking.
     *
     * @param array $data Booking data.
     * @return array|\WP_Error
     */
    public static function reserve_slot_and_create_booking(array $data) {
        global $wpdb;

        $board_id = absint($data['board_id'] ?? 0);
        $slot_id = absint($data['slot_id'] ?? 0);
        if (!$board_id || !$slot_id) {
            return new \WP_Error('mds_sponsorboard_invalid_inventory', __('Choose an available sponsor slot.', 'mds-sponsorboard'));
        }

        $wpdb->query('START TRANSACTION');
        $reserved = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::ident(self::table('slots')) . ' SET status = %s, updated_at = %s WHERE id = %d AND board_id = %d AND status = %s',
                'reserved',
                self::now(),
                $slot_id,
                $board_id,
                'available'
            )
        );
        if (1 !== $reserved) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('mds_sponsorboard_slot_unavailable', __('That sponsor slot was just reserved. Choose another available slot.', 'mds-sponsorboard'));
        }

        $result = self::create_booking($data);
        if (is_wp_error($result)) {
            $wpdb->query('ROLLBACK');
            return $result;
        }

        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('mds_sponsorboard_booking_commit_failed', __('The sponsor booking could not be completed. Please try again.', 'mds-sponsorboard'));
        }

        return $result;
    }

    /**
     * Find one booking.
     *
     * @param int $id Booking ID.
     * @return array|null
     */
    public static function find_booking($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.id = %d',
                absint($id)
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_booking($row) : null;
    }

    /**
     * Find a booking by signed manage token.
     *
     * @param int    $id    Booking ID.
     * @param string $token Raw manage token.
     * @return array|null
     */
    public static function find_booking_by_token($id, $token) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.id = %d AND b.manage_token_hash = %s',
                absint($id),
                self::hash_manage_token($token)
            ),
            ARRAY_A
        );

        return is_array($row) ? self::normalize_booking($row) : null;
    }

    /**
     * Return bookings for a board.
     *
     * @param int $board_id Board ID.
     * @param int $limit    Maximum rows.
     * @return array
     */
    public static function bookings_for_board($board_id, $limit = 50) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.board_id = %d ORDER BY b.updated_at DESC, b.id DESC LIMIT %d',
                absint($board_id),
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []);
    }

    /**
     * Return a filtered page of bookings for one board.
     *
     * @param int   $board_id Board ID.
     * @param int   $page     One-based page number.
     * @param int   $per_page Rows per page.
     * @param array $args     Optional status and search filters.
     * @return array
     */
    public static function bookings_page($board_id, $page = 1, $per_page = 20, array $args = []) {
        global $wpdb;

        $board_id = absint($board_id);
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        $where = ['b.board_id = %d'];
        $values = [$board_id];
        $status = sanitize_key((string) ($args['status'] ?? ''));
        if ($status && in_array($status, ['requested', 'pending_payment', 'paid', 'approved', 'changes_requested', 'rejected', 'active', 'expired', 'cancelled'], true)) {
            $where[] = 'b.status = %s';
            $values[] = $status;
        }
        $search = DataSanitizer::text($args['search'] ?? '', 100);
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(b.sponsor_name LIKE %s OR b.sponsor_email LIKE %s OR slot.title LIKE %s OR CAST(b.id AS CHAR) = %s)';
            array_push($values, $like, $like, $like, $search);
        }

        $from = ' FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE ' . implode(' AND ', $where);
        $total = absint($wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, $values)));
        $query_values = array_merge($values, [$per_page, ($page - 1) * $per_page]);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions' . $from . ' ORDER BY b.updated_at DESC, b.id DESC LIMIT %d OFFSET %d',
                $query_values
            ),
            ARRAY_A
        );

        return [
            'items' => array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []),
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    /**
     * Update a booking.
     *
     * @param int   $id   Booking ID.
     * @param array $data Booking data.
     * @return array|null
     */
    public static function update_booking($id, array $data) {
        global $wpdb;

        $id = absint($id);
        $booking = $id ? self::find_booking($id) : null;
        if (!$booking) {
            return null;
        }

        $creative_payload = is_array($data['creative_payload'] ?? null)
            ? $data['creative_payload']
            : (is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : []);
        $creative_payload = DataSanitizer::creative($creative_payload);
        $metadata = is_array($data['metadata'] ?? null)
            ? $data['metadata']
            : (is_array($booking['metadata'] ?? null) ? $booking['metadata'] : []);
        $metadata = DataSanitizer::metadata($metadata);

        $sponsor_name = DataSanitizer::text($data['sponsor_name'] ?? $booking['sponsor_name'] ?? '', 191);
        $sponsor_email = sanitize_email((string) ($data['sponsor_email'] ?? $booking['sponsor_email'] ?? ''));
        $sponsor_url = DataSanitizer::url($data['sponsor_url'] ?? $booking['sponsor_url'] ?? '');
        if ($sponsor_name !== (string) ($booking['sponsor_name'] ?? '') || $sponsor_email !== (string) ($booking['sponsor_email'] ?? '') || $sponsor_url !== (string) ($booking['sponsor_url'] ?? '') || $creative_payload !== ($booking['creative_payload'] ?? [])) {
            $metadata['private_data_updated_at'] = self::now();
        }

        $status = self::booking_status($data['status'] ?? $booking['status'] ?? 'requested');
        $starts_at = self::datetime_or_null($data['starts_at'] ?? $booking['starts_at'] ?? null);
        $ends_at = self::datetime_or_null($data['ends_at'] ?? $booking['ends_at'] ?? null);
        $schedule = self::validate_booking_schedule(absint($booking['slot_id'] ?? 0), $starts_at, $ends_at, $status, $id);
        if (is_wp_error($schedule)) {
            return null;
        }
        if (in_array($status, ['paid', 'approved', 'active'], true)) {
            $metadata['lifecycle_was_paid'] = true;
        }

        $update = [
            'order_id' => absint($data['order_id'] ?? $booking['order_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? $booking['user_id'] ?? 0),
            'sponsor_name' => $sponsor_name,
            'sponsor_email' => $sponsor_email,
            'sponsor_url' => $sponsor_url,
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'creative_id' => absint($data['creative_id'] ?? $booking['creative_id'] ?? 0),
            'creative_payload' => self::json($creative_payload),
            'metadata' => self::json($metadata),
            'updated_at' => self::now(),
        ];

        $result = $wpdb->update(
            self::table('bookings'),
            $update,
            ['id' => $id],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );
        if (false === $result) {
            return null;
        }

        self::audit('booking', $id, 'updated', __('Sponsor booking updated.', 'mds-sponsorboard'), [
            'status' => $update['status'],
        ]);

        return self::find_booking($id);
    }

    /**
     * Recalculate slot state from every live booking without releasing owned inventory.
     *
     * @param int $slot_id Slot ID.
     * @return array|null
     */
    public static function refresh_slot_status($slot_id, $now = null) {
        global $wpdb;

        $slot = self::find_slot($slot_id);
        if (!$slot || in_array((string) ($slot['status'] ?? ''), ['paused', 'archived'], true)) {
            return $slot;
        }

        $now = null === $now ? time() : absint($now);
        $statuses = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT status FROM ' . self::ident(self::table('bookings')) . ' WHERE slot_id = %d AND status IN ("requested","pending_payment","paid","approved","changes_requested","active") AND (ends_at IS NULL OR ends_at = "" OR ends_at > %s)',
                absint($slot_id),
                gmdate('Y-m-d H:i:s', $now)
            )
        );
        $statuses = array_map('sanitize_key', is_array($statuses) ? $statuses : []);
        if (array_intersect($statuses, ['paid', 'approved', 'active'])) {
            $status = 'sold';
        } elseif ($statuses) {
            $status = 'reserved';
        } else {
            $status = 'available';
        }

        if ($status === (string) ($slot['status'] ?? '')) {
            return $slot;
        }

        $wpdb->update(
            self::table('slots'),
            ['status' => $status, 'updated_at' => self::now()],
            ['id' => absint($slot_id)],
            ['%s', '%s'],
            ['%d']
        );
        self::audit('slot', absint($slot_id), 'status_refreshed', __('Sponsor slot status recalculated from its bookings.', 'mds-sponsorboard'), ['status' => $status]);

        return self::find_slot($slot_id);
    }

    /**
     * Validate schedule ordering and prevent overlapping live ownership.
     *
     * @return true|\WP_Error
     */
    public static function validate_booking_schedule($slot_id, $starts_at, $ends_at, $status, $exclude_id = 0) {
        global $wpdb;

        $raw_start = trim((string) $starts_at);
        $raw_end = trim((string) $ends_at);
        $start = self::datetime_or_null($raw_start);
        $end = self::datetime_or_null($raw_end);
        if ('' !== $raw_start && null === $start) {
            return new \WP_Error('mds_sponsorboard_invalid_start', __('Enter a valid booking start date and time.', 'mds-sponsorboard'));
        }
        if ('' !== $raw_end && null === $end) {
            return new \WP_Error('mds_sponsorboard_invalid_end', __('Enter a valid booking end date and time.', 'mds-sponsorboard'));
        }

        $window = BookingLifecycle::validate_window($start, $end);
        if (is_wp_error($window)) {
            return $window;
        }

        $status = self::booking_status($status);
        if (!in_array($status, ['requested', 'pending_payment', 'paid', 'approved', 'changes_requested', 'active'], true)) {
            return true;
        }

        $where = [
            'slot_id = %d',
            'status IN ("requested","pending_payment","paid","approved","changes_requested","active")',
        ];
        $values = [absint($slot_id)];
        if ($exclude_id) {
            $where[] = 'id != %d';
            $values[] = absint($exclude_id);
        }
        if ($end) {
            $where[] = '(starts_at IS NULL OR starts_at = "" OR starts_at < %s)';
            $values[] = $end;
        }
        if ($start) {
            $where[] = '(ends_at IS NULL OR ends_at = "" OR ends_at > %s)';
            $values[] = $start;
        }

        $conflict = absint($wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::ident(self::table('bookings')) . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT 1',
            $values
        )));
        if ($conflict) {
            return new \WP_Error(
                'mds_sponsorboard_booking_window_conflict',
                __('This booking overlaps another live booking for the same sponsor slot.', 'mds-sponsorboard'),
                ['conflicting_booking_id' => $conflict]
            );
        }

        return true;
    }

    /**
     * Return due schedule transitions in a bounded deterministic order.
     *
     * @return array
     */
    public static function lifecycle_due_bookings($limit = 100, $now = null) {
        global $wpdb;

        $limit = min(200, max(1, absint($limit)));
        $now = null === $now ? time() : absint($now);
        $utc = gmdate('Y-m-d H:i:s', $now);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.status IN ("requested","pending_payment","paid","approved","changes_requested","active") AND ((b.ends_at IS NOT NULL AND b.ends_at != "" AND b.ends_at <= %s) OR (b.status = "approved" AND b.starts_at IS NOT NULL AND b.starts_at != "" AND b.starts_at <= %s AND (b.ends_at IS NULL OR b.ends_at = "" OR b.ends_at > %s) AND b.creative_payload LIKE %s)) ORDER BY COALESCE(b.ends_at,b.starts_at), b.id ASC LIMIT %d',
                $utc,
                $utc,
                $utc,
                '%"creative_status":"approved"%',
                $limit
            ),
            ARRAY_A
        );

        return array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []);
    }

    /**
     * Move a booking to another available slot on the same board.
     *
     * @param int $booking_id    Booking ID.
     * @param int $target_slot_id Target slot ID.
     * @return array|\WP_Error
     */
    public static function move_booking($booking_id, $target_slot_id) {
        global $wpdb;

        $booking = self::find_booking($booking_id);
        $target = self::find_slot($target_slot_id);
        if (!$booking || !$target || absint($target['board_id'] ?? 0) !== absint($booking['board_id'] ?? 0)) {
            return new \WP_Error('mds_sponsorboard_invalid_move', __('Choose a slot from the same sponsor board.', 'mds-sponsorboard'));
        }

        $old_slot_id = absint($booking['slot_id'] ?? 0);
        $target_slot_id = absint($target_slot_id);
        if ($old_slot_id === $target_slot_id) {
            return $booking;
        }

        $wpdb->query('START TRANSACTION');
        $reserved = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::ident(self::table('slots')) . ' SET status = %s, updated_at = %s WHERE id = %d AND board_id = %d AND status = %s',
                'reserved',
                self::now(),
                $target_slot_id,
                absint($booking['board_id'] ?? 0),
                'available'
            )
        );
        if (1 !== $reserved) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('mds_sponsorboard_move_unavailable', __('The destination slot is no longer available.', 'mds-sponsorboard'));
        }

        $updated = $wpdb->update(
            self::table('bookings'),
            ['slot_id' => $target_slot_id, 'updated_at' => self::now()],
            ['id' => absint($booking_id)],
            ['%d', '%s'],
            ['%d']
        );
        if (false === $updated || false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('mds_sponsorboard_move_failed', __('The sponsor booking could not be moved.', 'mds-sponsorboard'));
        }

        self::refresh_slot_status($old_slot_id);
        self::refresh_slot_status($target_slot_id);
        self::audit('booking', absint($booking_id), 'moved', __('Sponsor booking moved to another slot.', 'mds-sponsorboard'), [
            'from_slot_id' => $old_slot_id,
            'to_slot_id' => $target_slot_id,
        ]);

        return self::find_booking($booking_id);
    }

    /**
     * Recent bookings across boards.
     *
     * @param int $limit Maximum rows.
     * @return array
     */
    public static function recent_bookings($limit = 20) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id ORDER BY b.updated_at DESC, b.id DESC LIMIT %d',
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return the approved booking that may render publicly for a slot.
     *
     * @param int $slot_id Slot ID.
     * @return array|null
     */
    public static function public_booking_for_slot($slot_id) {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.slot_id = %d AND b.status IN ("approved","active") AND (b.starts_at IS NULL OR b.starts_at = "" OR b.starts_at <= %s) AND (b.ends_at IS NULL OR b.ends_at = "" OR b.ends_at > %s) ORDER BY COALESCE(b.starts_at,"1000-01-01 00:00:00") DESC, b.updated_at DESC, b.id DESC LIMIT 1',
                absint($slot_id),
                $now,
                $now
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $booking = self::normalize_booking($row);
        return BookingLifecycle::is_public($booking) ? $booking : null;
    }

    /**
     * Record an aggregate analytics event.
     *
     * @param array $data Event data.
     * @return int Event ID.
     */
    public static function record_event(array $data) {
        global $wpdb;

        $result = $wpdb->insert(self::table('events'), [
            'board_id' => absint($data['board_id'] ?? 0),
            'slot_id' => absint($data['slot_id'] ?? 0),
            'booking_id' => absint($data['booking_id'] ?? 0),
            'event_type' => sanitize_key((string) ($data['event_type'] ?? '')),
            'event_value' => max(0, (float) ($data['event_value'] ?? 1)),
            'metadata' => self::json($data['metadata'] ?? []),
            'ip_hash' => sanitize_text_field((string) ($data['ip_hash'] ?? '')),
            'user_agent_hash' => sanitize_text_field((string) ($data['user_agent_hash'] ?? '')),
            'created_at' => self::now(),
        ], ['%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s']);

        return false === $result ? 0 : absint($wpdb->insert_id);
    }

    /**
     * Return reporting totals for all boards or one board.
     *
     * @param int $board_id Optional board ID.
     * @return array
     */
    public static function reporting_summary($board_id = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $events = self::event_totals($board_id);
        $impressions = absint($events['impression'] ?? 0);
        $clicks = absint($events['click'] ?? 0);

        $board_where = '';
        $board_args = [];
        if ($board_id) {
            $board_where = ' AND b.board_id = %d';
            $board_args[] = $board_id;
        }

        $now = gmdate('Y-m-d H:i:s');
        $cutoff = gmdate('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS));
        $revenue_where = ' WHERE (b.status IN ("paid","approved","active") OR (b.status = "expired" AND b.metadata LIKE %s))' . $board_where;
        $revenue_args = array_merge(['%"lifecycle_was_paid":true%'], $board_args);
        $active_where = ' WHERE b.status IN ("paid","approved","active") AND (b.starts_at IS NULL OR b.starts_at = "" OR b.starts_at <= %s) AND (b.ends_at IS NULL OR b.ends_at = "" OR b.ends_at > %s)' . $board_where;
        $active_args = array_merge([$now, $now], $board_args);
        $expiring_where = $active_where . ' AND b.ends_at IS NOT NULL AND b.ends_at != "" AND b.ends_at <= %s';
        $expiring_args = array_merge($active_args, [$cutoff]);

        $revenue_sql = 'SELECT COALESCE(SUM(slot.price), 0) FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id' . $revenue_where;
        $active_sql = 'SELECT COUNT(*) FROM ' . self::ident(self::table('bookings')) . ' b' . $active_where;
        $expiring_sql = 'SELECT COUNT(*) FROM ' . self::ident(self::table('bookings')) . ' b' . $expiring_where;

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
            'revenue' => (float) $wpdb->get_var($wpdb->prepare($revenue_sql, $revenue_args)),
            'active_bookings' => absint($wpdb->get_var($wpdb->prepare($active_sql, $active_args))),
            'expiring' => absint($wpdb->get_var($wpdb->prepare($expiring_sql, $expiring_args))),
        ];
    }

    /**
     * Return public-facing event totals for one booking.
     *
     * @param int $booking_id Booking ID.
     * @return array
     */
    public static function booking_event_summary($booking_id) {
        $events = self::event_totals(0, absint($booking_id));
        $impressions = absint($events['impression'] ?? 0);
        $clicks = absint($events['click'] ?? 0);

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $impressions > 0 ? ($clicks / $impressions) * 100 : 0,
        ];
    }

    /**
     * Return aggregate event totals from raw rows and retained rollups.
     *
     * @param int $board_id   Optional board ID.
     * @param int $booking_id Optional booking ID.
     * @return array
     */
    public static function event_totals($board_id = 0, $booking_id = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $booking_id = absint($booking_id);
        $totals = [];

        foreach (['events', 'event_rollups'] as $table) {
            $where = [];
            $args = [];
            if ($board_id) {
                $where[] = 'board_id = %d';
                $args[] = $board_id;
            }
            if ($booking_id) {
                $where[] = 'booking_id = %d';
                $args[] = $booking_id;
            }
            $sql = 'SELECT event_type, COALESCE(SUM(event_value), 0) AS total FROM ' . self::ident(self::table($table));
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' GROUP BY event_type';

            $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
            foreach (is_array($rows) ? $rows : [] as $row) {
                $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
                if ('' === $event_type) {
                    continue;
                }
                $totals[$event_type] = (float) ($totals[$event_type] ?? 0) + (float) ($row['total'] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * Count CSV-safe booking export rows.
     *
     * @param int $board_id Optional board ID.
     * @return int
     */
    public static function booking_export_count($board_id = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $sql = 'SELECT COUNT(*) FROM ' . self::ident(self::table('bookings')) . ' b';
        if ($board_id) {
            $sql .= ' WHERE b.board_id = %d';
            return absint($wpdb->get_var($wpdb->prepare($sql, $board_id)));
        }

        return absint($wpdb->get_var($sql));
    }

    /**
     * Return CSV-safe booking export rows.
     *
     * @param int $board_id Optional board ID.
     * @param int $limit    Maximum rows.
     * @param int $offset   Offset for paged exports.
     * @return array
     */
    public static function booking_export_rows($board_id = 0, $limit = 1000, $offset = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $limit = max(1, min(5000, absint($limit)));
        $offset = max(0, absint($offset));
        $sql = 'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id';
        $args = [];
        if ($board_id) {
            $sql .= ' WHERE b.board_id = %d';
            $args[] = $board_id;
        }
        $sql .= ' ORDER BY b.updated_at DESC, b.id DESC LIMIT %d OFFSET %d';
        $args[] = $limit;
        $args[] = $offset;
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $export = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $booking = self::normalize_booking($row);
            $creative = is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [];
            $export[] = [
                'id' => absint($booking['id'] ?? 0),
                'board_id' => absint($booking['board_id'] ?? 0),
                'board_title' => (string) ($booking['board_title'] ?? ''),
                'slot_id' => absint($booking['slot_id'] ?? 0),
                'slot_title' => (string) ($booking['slot_title'] ?? ''),
                'sponsor_name' => (string) ($booking['sponsor_name'] ?? ''),
                'sponsor_email' => (string) ($booking['sponsor_email'] ?? ''),
                'sponsor_url' => (string) ($booking['sponsor_url'] ?? ''),
                'status' => (string) ($booking['status'] ?? ''),
                'creative_status' => (string) ($creative['creative_status'] ?? 'pending_review'),
                'order_id' => absint($booking['order_id'] ?? 0),
                'user_id' => absint($booking['user_id'] ?? 0),
                'starts_at' => (string) ($booking['starts_at'] ?? ''),
                'ends_at' => (string) ($booking['ends_at'] ?? ''),
                'created_at' => (string) ($booking['created_at'] ?? ''),
                'updated_at' => (string) ($booking['updated_at'] ?? ''),
            ];
        }

        return $export;
    }

    /**
     * Count CSV-safe analytics export rows, including retained rollups.
     *
     * @param int $board_id Optional board ID.
     * @return int
     */
    public static function event_export_count($board_id = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $total = 0;
        foreach (['events', 'event_rollups'] as $table) {
            $sql = 'SELECT COUNT(*) FROM ' . self::ident(self::table($table));
            if ($board_id) {
                $sql .= ' WHERE board_id = %d';
                $total += absint($wpdb->get_var($wpdb->prepare($sql, $board_id)));
            } else {
                $total += absint($wpdb->get_var($sql));
            }
        }

        return $total;
    }

    /**
     * Return CSV-safe event export rows.
     *
     * @param int $board_id Optional board ID.
     * @param int $limit    Maximum rows.
     * @param int $offset   Offset for paged exports.
     * @return array
     */
    public static function event_export_rows($board_id = 0, $limit = 1000, $offset = 0) {
        global $wpdb;

        $board_id = absint($board_id);
        $limit = max(1, min(5000, absint($limit)));
        $offset = max(0, absint($offset));
        $raw_where = '';
        $rollup_where = '';
        $args = [];
        if ($board_id) {
            $raw_where = ' WHERE board_id = %d';
            $rollup_where = ' WHERE board_id = %d';
            $args[] = $board_id;
            $args[] = $board_id;
        }
        $args[] = $limit;
        $args[] = $offset;
        $sql = 'SELECT * FROM ('
            . 'SELECT id, board_id, slot_id, booking_id, event_type, event_value, created_at, "raw" AS source, "" AS event_date FROM ' . self::ident(self::table('events')) . $raw_where
            . ' UNION ALL '
            . 'SELECT id, board_id, slot_id, booking_id, event_type, event_value, CONCAT(event_date, " 00:00:00") AS created_at, "rollup" AS source, event_date FROM ' . self::ident(self::table('event_rollups')) . $rollup_where
            . ') sponsorboard_events ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return analytics retention settings.
     *
     * @return array
     */
    public static function analytics_settings() {
        $settings = get_option(self::ANALYTICS_SETTINGS_OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $retention_days = absint($settings['retention_days'] ?? 365);

        return [
            'retention_days' => min(3650, $retention_days),
            'rollup_enabled' => array_key_exists('rollup_enabled', $settings) ? '1' === (string) $settings['rollup_enabled'] : true,
            'last_cleanup' => sanitize_text_field((string) ($settings['last_cleanup'] ?? '')),
            'last_cleanup_summary' => is_array($settings['last_cleanup_summary'] ?? null) ? $settings['last_cleanup_summary'] : [],
        ];
    }

    /**
     * Save analytics retention settings from a trusted admin POST body.
     *
     * @param array $input Raw input.
     * @return array
     */
    public static function save_analytics_settings(array $input) {
        $current = self::analytics_settings();
        $settings = [
            'retention_days' => min(3650, max(0, absint(wp_unslash($input['analytics_retention_days'] ?? $current['retention_days'])))),
            'rollup_enabled' => !empty($input['analytics_rollup_enabled']) ? '1' : '0',
            'last_cleanup' => (string) ($current['last_cleanup'] ?? ''),
            'last_cleanup_summary' => is_array($current['last_cleanup_summary'] ?? null) ? $current['last_cleanup_summary'] : [],
        ];

        update_option(self::ANALYTICS_SETTINGS_OPTION, $settings, false);

        return self::analytics_settings();
    }

    /**
     * Roll up and delete raw analytics rows older than the configured retention period.
     *
     * @param int|null $retention_days Optional retention override.
     * @return array
     */
    public static function cleanup_old_events($retention_days = null) {
        global $wpdb;

        $settings = self::analytics_settings();
        $retention_days = null === $retention_days ? absint($settings['retention_days'] ?? 365) : absint($retention_days);
        if ($retention_days <= 0) {
            return [
                'rolled_up' => 0,
                'deleted' => 0,
                'cutoff' => '',
                'skipped' => 'retention_disabled',
            ];
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));
        $rolled_up = 0;

        if (!empty($settings['rollup_enabled'])) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT board_id, COALESCE(slot_id, 0) AS slot_id, COALESCE(booking_id, 0) AS booking_id, event_type, DATE(created_at) AS event_date, COALESCE(SUM(event_value), 0) AS event_value FROM ' . self::ident(self::table('events')) . ' WHERE created_at < %s GROUP BY board_id, slot_id, booking_id, event_type, DATE(created_at)',
                    $cutoff
                ),
                ARRAY_A
            );

            foreach (is_array($rows) ? $rows : [] as $row) {
                $event_type = sanitize_key((string) ($row['event_type'] ?? ''));
                $event_date = sanitize_text_field((string) ($row['event_date'] ?? ''));
                if ('' === $event_type || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
                    continue;
                }

                $result = $wpdb->query(
                    $wpdb->prepare(
                        'INSERT INTO ' . self::ident(self::table('event_rollups')) . ' (board_id, slot_id, booking_id, event_type, event_date, event_value, created_at, updated_at) VALUES (%d, %d, %d, %s, %s, %f, %s, %s) ON DUPLICATE KEY UPDATE event_value = event_value + VALUES(event_value), updated_at = VALUES(updated_at)',
                        absint($row['board_id'] ?? 0),
                        absint($row['slot_id'] ?? 0),
                        absint($row['booking_id'] ?? 0),
                        $event_type,
                        $event_date,
                        max(0, (float) ($row['event_value'] ?? 0)),
                        self::now(),
                        self::now()
                    )
                );
                if (false !== $result) {
                    $rolled_up++;
                }
            }
        }

        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM ' . self::ident(self::table('events')) . ' WHERE created_at < %s', $cutoff));
        $summary = [
            'rolled_up' => absint($rolled_up),
            'deleted' => false === $deleted ? 0 : absint($deleted),
            'cutoff' => $cutoff,
        ];
        $settings['last_cleanup'] = self::now();
        $settings['last_cleanup_summary'] = $summary;
        update_option(self::ANALYTICS_SETTINGS_OPTION, $settings, false);

        return $summary;
    }

    /**
     * Return bookings associated with an email address for privacy export.
     *
     * @param string $email Email address.
     * @return array
     */
    public static function bookings_for_email($email) {
        $page = self::bookings_for_email_page($email, 1, 500);

        return $page['items'];
    }

    /**
     * Return a bounded page of privacy-export bookings for an email address.
     *
     * @param string $email    Email address.
     * @param int    $page     One-based page number.
     * @param int    $per_page Rows per page.
     * @return array
     */
    public static function bookings_for_email_page($email, $page = 1, $per_page = 100) {
        global $wpdb;

        $email = sanitize_email((string) $email);
        $page = max(1, absint($page));
        $per_page = min(500, max(1, absint($per_page)));
        if (!$email) {
            return ['items' => [], 'page' => $page, 'per_page' => $per_page, 'total' => 0, 'pages' => 1];
        }

        $total = absint($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::ident(self::table('bookings')) . ' WHERE sponsor_email = %s', $email)));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.sponsor_email = %s ORDER BY b.updated_at DESC, b.id DESC LIMIT %d OFFSET %d',
                $email,
                $per_page,
                ($page - 1) * $per_page
            ),
            ARRAY_A
        );

        return [
            'items' => array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []),
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    /**
     * Return SponsorBoard bookings for a verified workspace principal.
     *
     * @param int    $user_id  Verified WordPress user ID.
     * @param string $email    Verified email address.
     * @param int    $page     One-based page number.
     * @param int    $per_page Rows per page.
     * @param array  $args     Optional view and search filters.
     * @return array
     */
    public static function workspace_bookings_page($user_id, $email, $page = 1, $per_page = 20, array $args = []) {
        global $wpdb;

        $user_id = absint($user_id);
        $email = sanitize_email((string) $email);
        $page = max(1, absint($page));
        $per_page = min(100, max(1, absint($per_page)));
        if (!$user_id && !$email) {
            return ['items' => [], 'page' => $page, 'per_page' => $per_page, 'total' => 0, 'pages' => 1];
        }

        $owner = [];
        $values = [];
        if ($user_id) {
            $owner[] = 'b.user_id = %d';
            $values[] = $user_id;
        }
        if ($email) {
            $owner[] = 'LOWER(b.sponsor_email) = LOWER(%s)';
            $values[] = $email;
        }
        $where = ['(' . implode(' OR ', $owner) . ')'];
        $view = sanitize_key((string) ($args['view'] ?? ''));
        $views = [
            'active' => ['paid', 'approved', 'active'],
            'pending' => ['requested', 'pending_payment', 'changes_requested'],
            'history' => ['rejected', 'expired', 'cancelled'],
        ];
        if (!empty($views[$view])) {
            $where[] = 'b.status IN (' . implode(',', array_fill(0, count($views[$view]), '%s')) . ')';
            $values = array_merge($values, $views[$view]);
        } elseif ('expiring' === $view) {
            $where[] = 'b.status IN (%s, %s, %s)';
            array_push($values, 'paid', 'approved', 'active');
            $where[] = 'b.ends_at IS NOT NULL AND b.ends_at > %s AND b.ends_at <= %s';
            array_push($values, self::now(), gmdate('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS)));
        } elseif ('action' === $view) {
            $where[] = 'b.status IN (%s, %s, %s)';
            array_push($values, 'requested', 'pending_payment', 'changes_requested');
        }

        $search = DataSanitizer::text($args['search'] ?? '', 100);
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(b.sponsor_name LIKE %s OR board.title LIKE %s OR slot.title LIKE %s OR CAST(b.id AS CHAR) = %s)';
            array_push($values, $like, $like, $like, $search);
        }

        $from = ' FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE ' . implode(' AND ', $where);
        $total = absint($wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $from, $values)));
        $offset = array_key_exists('offset', $args) ? max(0, absint($args['offset'])) : (($page - 1) * $per_page);
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT b.*, board.title AS board_title, slot.title AS slot_title, slot.creative_type AS slot_creative_type, slot.dimensions AS slot_dimensions, slot.price AS slot_price, slot.currency AS slot_currency' . $from . ' ORDER BY b.updated_at DESC, b.id DESC LIMIT %d OFFSET %d',
            array_merge($values, [$per_page, $offset])
        ), ARRAY_A);

        return [
            'items' => array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []),
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }

    public static function workspace_booking($booking_id, $user_id, $email) {
        $booking = self::find_booking($booking_id);
        if (!$booking) {
            return null;
        }
        $user_match = absint($user_id) && absint($booking['user_id'] ?? 0) === absint($user_id);
        $email = sanitize_email((string) $email);
        $email_match = $email && hash_equals(strtolower($email), strtolower(sanitize_email((string) ($booking['sponsor_email'] ?? ''))));
        return $user_match || $email_match ? $booking : null;
    }

    public static function workspace_booking_history($booking_id, $limit = 50) {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT action, message, created_at FROM ' . self::ident(self::table('audit_logs')) . ' WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT %d',
            'booking',
            absint($booking_id),
            min(100, max(1, absint($limit)))
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Anonymize bookings for a privacy erasure request while preserving reporting rows.
     *
     * @param string $email Email address.
     * @param int    $limit Maximum rows per privacy request.
     * @return int Number of affected rows.
     */
    public static function anonymize_bookings_for_email($email, $limit = 100) {
        global $wpdb;

        $email = sanitize_email((string) $email);
        if (!$email) {
            return 0;
        }

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT id FROM ' . self::ident(self::table('bookings')) . ' WHERE sponsor_email = %s ORDER BY id ASC LIMIT %d',
                $email,
                min(500, max(1, absint($limit)))
            )
        );
        $removed = 0;
        foreach (array_map('absint', is_array($ids) ? $ids : []) as $id) {
            $removed += self::anonymize_booking($id, false) ? 1 : 0;
        }

        return $removed;
    }

    /**
     * Remove expired private booking data according to each board's policy.
     *
     * @param int $limit Maximum rows in one cron run.
     * @return int Number of anonymized bookings.
     */
    public static function cleanup_private_booking_data($limit = 200) {
        global $wpdb;

        $remaining = min(1000, max(1, absint($limit)));
        $removed = 0;
        $page = 1;
        do {
            $boards = self::boards_page($page, 100);
            foreach ($boards['items'] as $board) {
                if ($remaining < 1) {
                    break 2;
                }
                $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
                $days = min(3650, max(30, absint($settings['booking_retention_days'] ?? 365)));
                $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
                $ids = $wpdb->get_col(
                    $wpdb->prepare(
                        'SELECT id FROM ' . self::ident(self::table('bookings')) . ' WHERE board_id = %d AND sponsor_email != %s AND updated_at < %s ORDER BY id ASC LIMIT %d',
                        absint($board['id'] ?? 0),
                        '',
                        $cutoff,
                        $remaining
                    )
                );
                foreach (array_map('absint', is_array($ids) ? $ids : []) as $id) {
                    if (self::anonymize_booking($id, true)) {
                        $removed++;
                        $remaining--;
                    }
                }
            }
            $page++;
        } while ($page <= absint($boards['pages'] ?? 1) && $remaining > 0);

        return $removed;
    }

    /**
     * Delete a board and every extension-owned row beneath it.
     *
     * Intended for explicit admin deletion and deterministic test cleanup.
     *
     * @param int $board_id Board ID.
     * @return bool
     */
    public static function delete_board_cascade($board_id) {
        global $wpdb;

        $board_id = absint($board_id);
        if (!$board_id || !self::find_board($board_id)) {
            return false;
        }

        $slot_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::ident(self::table('slots')) . ' WHERE board_id = %d', $board_id)));
        $booking_ids = array_map('absint', $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::ident(self::table('bookings')) . ' WHERE board_id = %d', $board_id)));
        $wpdb->query('START TRANSACTION');
        $operations = [
            $wpdb->delete(self::table('events'), ['board_id' => $board_id], ['%d']),
            $wpdb->delete(self::table('event_rollups'), ['board_id' => $board_id], ['%d']),
            self::delete_audit_rows('booking', $booking_ids),
            self::delete_audit_rows('slot', $slot_ids),
            self::delete_audit_rows('board', [$board_id]),
            $wpdb->delete(self::table('bookings'), ['board_id' => $board_id], ['%d']),
            $wpdb->delete(self::table('slots'), ['board_id' => $board_id], ['%d']),
            $wpdb->delete(self::table('boards'), ['id' => $board_id], ['%d']),
        ];
        if (in_array(false, $operations, true) || false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        return true;
    }

    /**
     * Write an audit entry.
     *
     * @param string $object_type Object type.
     * @param int    $object_id   Object ID.
     * @param string $action      Action.
     * @param string $message     Message.
     * @param array  $metadata    Metadata.
     * @return void
     */
    public static function audit($object_type, $object_id, $action, $message = '', array $metadata = []) {
        global $wpdb;

        $wpdb->insert(self::table('audit_logs'), [
            'actor_id' => get_current_user_id(),
            'object_type' => sanitize_key((string) $object_type),
            'object_id' => absint($object_id),
            'action' => sanitize_key((string) $action),
            'message' => sanitize_textarea_field((string) $message),
            'metadata' => self::json($metadata),
            'created_at' => self::now(),
        ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s']);
    }

    private static function anonymize_booking($booking_id, $preserve_public = true) {
        global $wpdb;

        $booking = self::find_booking($booking_id);
        if (!$booking || '' === (string) ($booking['sponsor_email'] ?? '')) {
            return false;
        }

        $public = $preserve_public && BookingLifecycle::retains_approved_creative($booking);
        $existing_metadata = is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [];
        $metadata = [
            'privacy_erased_at' => self::now(),
            'public_sponsorship_retained' => $public,
        ];
        if (!empty($existing_metadata['lifecycle_was_paid'])) {
            $metadata['lifecycle_was_paid'] = true;
        }
        $data = [
            'sponsor_email' => '',
            'manage_token_hash' => '',
            'metadata' => self::json($metadata),
            'updated_at' => self::now(),
        ];
        if (!$public) {
            $data['sponsor_name'] = __('Erased sponsor', 'mds-sponsorboard');
            $data['sponsor_url'] = '';
            $data['creative_id'] = 0;
            $data['creative_payload'] = self::json([]);
        }

        $formats = [];
        foreach (array_keys($data) as $field) {
            $formats[] = 'creative_id' === $field ? '%d' : '%s';
        }
        $result = $wpdb->update(self::table('bookings'), $data, ['id' => absint($booking_id)], $formats, ['%d']);
        if (false !== $result) {
            self::audit('booking', absint($booking_id), 'privacy_anonymized', __('Private sponsor booking data anonymized.', 'mds-sponsorboard'));
        }

        return false !== $result;
    }

    private static function delete_audit_rows($object_type, array $ids) {
        global $wpdb;

        $ids = array_values(array_filter(array_map('absint', $ids)));
        if (!$ids) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . self::ident(self::table('audit_logs')) . ' WHERE object_type = %s AND object_id IN (' . $placeholders . ')',
                array_merge([sanitize_key((string) $object_type)], $ids)
            )
        );
    }

    private static function normalize_board(array $row) {
        $row['id'] = absint($row['id'] ?? 0);
        $row['settings'] = self::decode_json($row['settings'] ?? '');

        return $row;
    }

    private static function normalize_slot(array $row) {
        $row['id'] = absint($row['id'] ?? 0);
        $row['board_id'] = absint($row['board_id'] ?? 0);
        $row['price'] = (float) ($row['price'] ?? 0);
        $row['metadata'] = self::decode_json($row['metadata'] ?? '');

        return $row;
    }

    private static function normalize_booking(array $row) {
        $row['id'] = absint($row['id'] ?? 0);
        $row['board_id'] = absint($row['board_id'] ?? 0);
        $row['slot_id'] = absint($row['slot_id'] ?? 0);
        $row['order_id'] = absint($row['order_id'] ?? 0);
        $row['user_id'] = absint($row['user_id'] ?? 0);
        $row['creative_id'] = absint($row['creative_id'] ?? 0);
        $row['creative_payload'] = self::decode_json($row['creative_payload'] ?? '');
        $row['metadata'] = self::decode_json($row['metadata'] ?? '');
        unset($row['manage_token_hash']);

        return $row;
    }

    private static function count_map($rows) {
        $counts = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $counts[sanitize_key((string) ($row['status'] ?? ''))] = absint($row['total'] ?? 0);
        }

        return $counts;
    }

    private static function unique_slug($value, $table, $exclude_id = 0) {
        global $wpdb;

        $base = sanitize_title((string) $value);
        $base = $base ?: 'sponsor-board';
        $slug = $base;
        $suffix = 2;

        do {
            $sql = 'SELECT id FROM ' . self::ident(self::table($table)) . ' WHERE slug = %s';
            $args = [$slug];
            if ($exclude_id) {
                $sql .= ' AND id != %d';
                $args[] = absint($exclude_id);
            }
            $exists = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        } while ($suffix < 1000);

        return $base . '-' . wp_generate_password(6, false, false);
    }

    private static function unique_slot_slug($board_id, $value, $exclude_id = 0) {
        global $wpdb;

        $base = sanitize_title((string) $value);
        $base = $base ?: 'slot';
        $slug = $base;
        $suffix = 2;

        do {
            $sql = 'SELECT id FROM ' . self::ident(self::table('slots')) . ' WHERE board_id = %d AND slug = %s';
            $args = [absint($board_id), $slug];
            if ($exclude_id) {
                $sql .= ' AND id != %d';
                $args[] = absint($exclude_id);
            }
            $exists = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
            if (!$exists) {
                return $slug;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        } while ($suffix < 1000);

        return $base . '-' . wp_generate_password(6, false, false);
    }

    private static function ident($identifier) {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
    }

    private static function board_status($status) {
        $status = sanitize_key((string) $status);

        return in_array($status, ['draft', 'active', 'paused', 'archived'], true) ? $status : 'draft';
    }

    private static function slot_status($status) {
        $status = sanitize_key((string) $status);

        return in_array($status, ['available', 'reserved', 'sold', 'paused', 'archived'], true) ? $status : 'available';
    }

    private static function booking_status($status) {
        $status = sanitize_key((string) $status);

        return in_array($status, ['requested', 'pending_payment', 'paid', 'approved', 'changes_requested', 'rejected', 'active', 'expired', 'cancelled'], true) ? $status : 'requested';
    }

    private static function layout($layout) {
        $layout = sanitize_key((string) $layout);

        return in_array($layout, ['grid', 'strip', 'mosaic', 'cards'], true) ? $layout : 'grid';
    }

    private static function creative_type($type) {
        $type = sanitize_key((string) $type);

        return in_array($type, ['image', 'logo', 'text', 'html'], true) ? $type : 'image';
    }

    private static function currency($currency) {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency') && method_exists('\\MillionDollarScript\\Commerce\\Currency', 'normalize_code')) {
            return \MillionDollarScript\Commerce\Currency::normalize_code($currency, self::default_currency(), 8);
        }

        $currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $currency));

        return $currency ? substr($currency, 0, 8) : self::default_currency();
    }

    public static function default_currency() {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency')) {
            return (string) \MillionDollarScript\Commerce\Currency::current_code();
        }

        return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
    }

    public static function currency_locked() {
        if (!class_exists('\\MillionDollarScript\\Commerce\\Currency')) {
            return false;
        }

        return \MillionDollarScript\Commerce\Currency::provider_locks_currency();
    }

    public static function effective_currency($currency = '') {
        if (class_exists('\\MillionDollarScript\\Commerce\\Currency') && method_exists('\\MillionDollarScript\\Commerce\\Currency', 'effective_code')) {
            return \MillionDollarScript\Commerce\Currency::effective_code($currency, null, 8);
        }

        if (self::currency_locked()) {
            return self::default_currency();
        }

        return self::currency($currency ?: self::default_currency());
    }

    private static function json($value) {
        return wp_json_encode(is_array($value) ? $value : []);
    }

    private static function decode_json($value) {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function datetime_or_null($value) {
        $value = trim((string) $value);
        if ('' === $value) {
            return null;
        }

        $timestamp = strtotime($value . (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value) ? '' : ' UTC'));

        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function manage_token() {
        return wp_generate_password(32, false, false);
    }

    private static function hash_manage_token($token) {
        return hash_hmac('sha256', (string) $token, wp_salt('auth'));
    }

    private static function now() {
        return current_time('mysql');
    }
}
