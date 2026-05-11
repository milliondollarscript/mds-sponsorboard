<?php
/**
 * SponsorBoard data repository and schema.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Local-first data access for SponsorBoard.
 */
class Repository {

    public const DB_VERSION = '0.1.0';
    public const DB_VERSION_OPTION = 'mds_sponsorboard_db_version';

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
        $title = sanitize_text_field((string) ($data['title'] ?? ''));
        if ('' === $title) {
            $title = __('Untitled Sponsor Board', 'mds-sponsorboard');
        }

        $slug = self::unique_slug((string) ($data['slug'] ?? $title), 'boards');
        $insert = [
            'slug' => $slug,
            'title' => $title,
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'status' => self::board_status($data['status'] ?? 'draft'),
            'layout' => self::layout($data['layout'] ?? 'grid'),
            'currency' => self::currency($data['currency'] ?? self::default_currency()),
            'settings' => self::json($data['settings'] ?? []),
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
        if (!$id || !self::find_board($id)) {
            return null;
        }

        $update = [
            'title' => sanitize_text_field((string) ($data['title'] ?? __('Untitled Sponsor Board', 'mds-sponsorboard'))),
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'status' => self::board_status($data['status'] ?? 'draft'),
            'layout' => self::layout($data['layout'] ?? 'grid'),
            'currency' => self::currency($data['currency'] ?? self::default_currency()),
            'settings' => self::json($data['settings'] ?? []),
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
        if (!$board_id) {
            return 0;
        }

        $now = self::now();
        $title = sanitize_text_field((string) ($data['title'] ?? __('Sponsor Slot', 'mds-sponsorboard')));
        $slug = self::unique_slot_slug($board_id, (string) ($data['slug'] ?? $title));
        $insert = [
            'board_id' => $board_id,
            'slug' => $slug,
            'title' => $title,
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'price' => max(0, (float) ($data['price'] ?? 0)),
            'currency' => self::currency($data['currency'] ?? self::default_currency()),
            'status' => self::slot_status($data['status'] ?? 'available'),
            'position' => absint($data['position'] ?? 0),
            'creative_type' => self::creative_type($data['creative_type'] ?? 'image'),
            'dimensions' => sanitize_text_field((string) ($data['dimensions'] ?? '')),
            'metadata' => self::json($data['metadata'] ?? []),
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
        if (!$board_id) {
            return null;
        }

        $update = [
            'board_id' => $board_id,
            'title' => sanitize_text_field((string) ($data['title'] ?? $slot['title'] ?? __('Sponsor Slot', 'mds-sponsorboard'))),
            'description' => wp_kses_post((string) ($data['description'] ?? $slot['description'] ?? '')),
            'price' => max(0, (float) ($data['price'] ?? $slot['price'] ?? 0)),
            'currency' => self::currency($data['currency'] ?? $slot['currency'] ?? self::default_currency()),
            'status' => self::slot_status($data['status'] ?? $slot['status'] ?? 'available'),
            'position' => absint($data['position'] ?? $slot['position'] ?? 0),
            'creative_type' => self::creative_type($data['creative_type'] ?? $slot['creative_type'] ?? 'image'),
            'dimensions' => sanitize_text_field((string) ($data['dimensions'] ?? $slot['dimensions'] ?? '')),
            'metadata' => self::json($data['metadata'] ?? $slot['metadata'] ?? []),
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
     * @return array|null Booking row and raw manage token.
     */
    public static function create_booking(array $data) {
        global $wpdb;

        $board_id = absint($data['board_id'] ?? 0);
        $slot_id = absint($data['slot_id'] ?? 0);
        if (!$board_id || !$slot_id) {
            return null;
        }

        $token = self::manage_token();
        $now = self::now();
        $insert = [
            'board_id' => $board_id,
            'slot_id' => $slot_id,
            'order_id' => absint($data['order_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? get_current_user_id()),
            'sponsor_name' => sanitize_text_field((string) ($data['sponsor_name'] ?? '')),
            'sponsor_email' => sanitize_email((string) ($data['sponsor_email'] ?? '')),
            'sponsor_url' => esc_url_raw((string) ($data['sponsor_url'] ?? '')),
            'status' => self::booking_status($data['status'] ?? 'requested'),
            'starts_at' => self::datetime_or_null($data['starts_at'] ?? null),
            'ends_at' => self::datetime_or_null($data['ends_at'] ?? null),
            'creative_id' => absint($data['creative_id'] ?? 0),
            'creative_payload' => self::json($data['creative_payload'] ?? []),
            'manage_token_hash' => self::hash_manage_token($token),
            'metadata' => self::json($data['metadata'] ?? []),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert(
            self::table('bookings'),
            $insert,
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (false === $result) {
            return null;
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
     * Find one booking.
     *
     * @param int $id Booking ID.
     * @return array|null
     */
    public static function find_booking($id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.id = %d',
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
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.id = %d AND b.manage_token_hash = %s',
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
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id WHERE b.board_id = %d ORDER BY b.updated_at DESC, b.id DESC LIMIT %d',
                absint($board_id),
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return array_map([__CLASS__, 'normalize_booking'], is_array($rows) ? $rows : []);
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
        $metadata = is_array($data['metadata'] ?? null)
            ? $data['metadata']
            : (is_array($booking['metadata'] ?? null) ? $booking['metadata'] : []);

        $update = [
            'order_id' => absint($data['order_id'] ?? $booking['order_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? $booking['user_id'] ?? 0),
            'sponsor_name' => sanitize_text_field((string) ($data['sponsor_name'] ?? $booking['sponsor_name'] ?? '')),
            'sponsor_email' => sanitize_email((string) ($data['sponsor_email'] ?? $booking['sponsor_email'] ?? '')),
            'sponsor_url' => esc_url_raw((string) ($data['sponsor_url'] ?? $booking['sponsor_url'] ?? '')),
            'status' => self::booking_status($data['status'] ?? $booking['status'] ?? 'requested'),
            'starts_at' => self::datetime_or_null($data['starts_at'] ?? $booking['starts_at'] ?? null),
            'ends_at' => self::datetime_or_null($data['ends_at'] ?? $booking['ends_at'] ?? null),
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
     * Recent bookings across boards.
     *
     * @param int $limit Maximum rows.
     * @return array
     */
    public static function recent_bookings($limit = 20) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT b.*, board.title AS board_title, slot.title AS slot_title FROM ' . self::ident(self::table('bookings')) . ' b LEFT JOIN ' . self::ident(self::table('boards')) . ' board ON board.id = b.board_id LEFT JOIN ' . self::ident(self::table('slots')) . ' slot ON slot.id = b.slot_id ORDER BY b.updated_at DESC, b.id DESC LIMIT %d',
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
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
        $currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) $currency));

        return $currency ? substr($currency, 0, 8) : 'USD';
    }

    private static function default_currency() {
        if (class_exists('\\MDS3\\Commerce\\Currency')) {
            return (string) \MDS3\Commerce\Currency::code();
        }

        return function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
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

        $timestamp = strtotime($value);

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
