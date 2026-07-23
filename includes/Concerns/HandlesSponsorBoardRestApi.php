<?php
/**
 * SponsorBoard REST route registration, callbacks, authorization, and payload mapping.
 *
 * @package MillionDollarScript\Extensions\SponsorBoard
 */

namespace MillionDollarScript\Extensions\SponsorBoard\Concerns;

use MillionDollarScript\Extensions\SponsorBoard\BookingLifecycle;
use MillionDollarScript\Extensions\SponsorBoard\DataSanitizer;
use MillionDollarScript\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorBoardRestApi {

    public function register_rest_routes() {
        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/boards', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_list_boards'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.read', 'api_key_read');
                },
                'args' => [
                    'limit' => [
                        'default' => 50,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rest_create_board'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/boards/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_get_board'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.read', 'api_key_read');
                },
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'rest_update_board'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/boards/(?P<id>\d+)/slots', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_list_slots'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.read', 'api_key_read');
                },
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rest_create_slot'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/slots/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'rest_update_slot'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/boards/(?P<id>\d+)/bookings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_list_bookings'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
                'args' => [
                    'limit' => [
                        'default' => 50,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rest_create_booking'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        \MillionDollarScript\Extensions\Support::register_rest_route(self::REST_BASE . '/bookings/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_get_booking'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'rest_update_booking'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);
    }

    /**
     * Add SponsorBoard routes to the Million Dollar Script API policy manifest.
     *
     * @param array $endpoints Existing endpoints.
     * @return array
     */

    public function api_endpoint_manifest(array $endpoints) {
        $endpoints[] = ['id' => 'sponsorboard-boards-read', 'route' => '/million-dollar-script/v1/sponsorboard/boards', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-boards-write', 'route' => '/million-dollar-script/v1/sponsorboard/boards', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-board-read', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read one sponsor board.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-board-write', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slots-read', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)/slots', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slots-write', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)/slots', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slot-write', 'route' => '/million-dollar-script/v1/sponsorboard/slots/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-bookings-read', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)/bookings', 'methods' => ['GET'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Read private sponsor booking requests.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-bookings-write', 'route' => '/million-dollar-script/v1/sponsorboard/boards/(?P<id>\d+)/bookings', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor booking requests.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-booking-read', 'route' => '/million-dollar-script/v1/sponsorboard/bookings/(?P<id>\d+)', 'methods' => ['GET'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Read one private sponsor booking request.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-booking-write', 'route' => '/million-dollar-script/v1/sponsorboard/bookings/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor booking requests.', 'mds-sponsorboard')];

        return $endpoints;
    }

    /**
     * REST callback: list boards.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */

    public function rest_list_boards($request) {
        $per_page = min(100, max(1, absint($request['per_page'] ?? $request['limit'] ?? 50)));
        $page = Repository::boards_page(absint($request['page'] ?? 1), $per_page);
        $boards = array_map([$this, 'board_payload'], $page['items']);

        return rest_ensure_response(['data' => $boards, 'pagination' => $this->pagination_payload($page)]);
    }

    /**
     * REST callback: get one board.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_get_board($request) {
        $board = Repository::find_board(absint($request['id'] ?? 0));
        if (!$board) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        return rest_ensure_response(['data' => $this->board_payload($board, true)]);
    }

    /**
     * REST callback: create a board.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_create_board($request) {
        $input = $this->board_input($request);
        $validation = $this->validate_board_input($input, true);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $id = Repository::create_board($input);
        if (!$id) {
            return new \WP_Error('mds_sponsorboard_create_failed', __('Sponsor board could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $board = Repository::find_board($id);

        return new \WP_REST_Response(['data' => $this->board_payload($board, true)], 201);
    }

    /**
     * REST callback: update a board.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_update_board($request) {
        $board_id = absint($request['id'] ?? 0);
        $existing = Repository::find_board($board_id);
        if (!$existing) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $input = $this->board_input($request, $existing);
        $validation = $this->validate_board_input($input, false);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $board = Repository::update_board($board_id, $input);
        if (!$board) {
            return new \WP_Error('mds_sponsorboard_update_failed', __('Sponsor board could not be updated.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return rest_ensure_response(['data' => $this->board_payload($board, true)]);
    }

    /**
     * REST callback: list board slots.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_list_slots($request) {
        $board = Repository::find_board(absint($request['id'] ?? 0));
        if (!$board) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        return rest_ensure_response(['data' => array_map([$this, 'slot_payload'], Repository::slots_for_board((int) $board['id']))]);
    }

    /**
     * REST callback: create a slot.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_create_slot($request) {
        $board_id = absint($request['id'] ?? 0);
        if (!Repository::find_board($board_id)) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $input = $this->slot_input($request);
        $input['board_id'] = $board_id;
        $validation = $this->validate_slot_input($input, true);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $slot_id = Repository::create_slot($input);
        if (!$slot_id) {
            return new \WP_Error('mds_sponsorboard_slot_create_failed', __('Sponsor slot could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $slot = Repository::find_slot($slot_id);

        return new \WP_REST_Response(['data' => $this->slot_payload($slot)], 201);
    }

    /**
     * REST callback: update a slot.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_update_slot($request) {
        $slot_id = absint($request['id'] ?? 0);
        $existing = Repository::find_slot($slot_id);
        if (!$existing) {
            return new \WP_Error('mds_sponsorboard_slot_not_found', __('Sponsor slot not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $input = $this->slot_input($request, $existing);
        $validation = $this->validate_slot_input($input, false);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $slot = Repository::update_slot($slot_id, $input);
        if (!$slot) {
            return new \WP_Error('mds_sponsorboard_slot_update_failed', __('Sponsor slot could not be updated.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return rest_ensure_response(['data' => $this->slot_payload($slot)]);
    }

    /**
     * REST callback: list bookings for one board.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_list_bookings($request) {
        $board = Repository::find_board(absint($request['id'] ?? 0));
        if (!$board) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $per_page = min(100, max(1, absint($request['per_page'] ?? $request['limit'] ?? 50)));
        $page = Repository::bookings_page((int) $board['id'], absint($request['page'] ?? 1), $per_page, [
            'status' => $request['status'] ?? '',
            'search' => $request['search'] ?? '',
        ]);
        $bookings = array_map([$this, 'booking_payload'], $page['items']);

        return rest_ensure_response(['data' => $bookings, 'pagination' => $this->pagination_payload($page)]);
    }

    /**
     * REST callback: get one booking.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_get_booking($request) {
        $booking = Repository::find_booking(absint($request['id'] ?? 0));
        if (!$booking) {
            return new \WP_Error('mds_sponsorboard_booking_not_found', __('Sponsor booking not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        return rest_ensure_response(['data' => $this->booking_payload($booking)]);
    }

    /**
     * REST callback: create a sponsor booking.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_create_booking($request) {
        $board_id = absint($request['id'] ?? 0);
        $board = Repository::find_board($board_id);
        if (!$board) {
            return new \WP_Error('mds_sponsorboard_not_found', __('Sponsor board not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $input = $this->booking_input($request);
        $input['board_id'] = $board_id;

        $slot = Repository::find_slot(absint($input['slot_id'] ?? 0));
        if (!$slot || absint($slot['board_id'] ?? 0) !== $board_id) {
            return new \WP_Error('mds_sponsorboard_slot_not_found', __('Sponsor slot not found for this board.', 'mds-sponsorboard'), ['status' => 404]);
        }
        if ('available' !== (string) ($slot['status'] ?? '')) {
            return new \WP_Error('mds_sponsorboard_slot_unavailable', __('Sponsor slot is not available.', 'mds-sponsorboard'), ['status' => 409]);
        }

        $validation = $this->validate_booking_input($input, true);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $result = Repository::reserve_slot_and_create_booking($input);
        if (is_wp_error($result)) {
            $status = 'mds_sponsorboard_slot_unavailable' === $result->get_error_code() ? 409 : 400;
            return new \WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => $status]);
        }
        if (empty($result['booking']['id']) || empty($result['token'])) {
            return new \WP_Error('mds_sponsorboard_booking_create_failed', __('Sponsor booking could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $booking = is_array($result['booking']) ? $result['booking'] : [];

        return new \WP_REST_Response(['data' => $this->booking_payload($booking, (string) $result['token'])], 201);
    }

    /**
     * REST callback: update a sponsor booking.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_update_booking($request) {
        $booking_id = absint($request['id'] ?? 0);
        $booking = Repository::find_booking($booking_id);
        if (!$booking) {
            return new \WP_Error('mds_sponsorboard_booking_not_found', __('Sponsor booking not found.', 'mds-sponsorboard'), ['status' => 404]);
        }

        $changes = $this->booking_input($request, $booking);
        $target_slot_id = absint($changes['slot_id'] ?? $booking['slot_id'] ?? 0);
        $input = array_merge($booking, $changes);
        $validation = $this->validate_booking_input($input, false, $booking_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        if ($target_slot_id !== absint($booking['slot_id'] ?? 0)) {
            $moved = Repository::move_booking($booking_id, $target_slot_id);
            if (is_wp_error($moved)) {
                return new \WP_Error($moved->get_error_code(), $moved->get_error_message(), ['status' => 409]);
            }
            $booking = $moved;
            $input = array_merge($booking, $changes);
        }

        $updated = Repository::update_booking($booking_id, $input);
        if (!$updated) {
            return new \WP_Error('mds_sponsorboard_booking_update_failed', __('Sponsor booking could not be updated.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $this->sync_slot_for_booking_status($updated);

        return rest_ensure_response(['data' => $this->booking_payload($updated)]);
    }

    /**
     * Authorize a REST request through MDS3 API Governance when available.
     *
     * @param mixed  $request Request.
     * @param string $scope   Required scope.
     * @param string $level   Minimum level.
     * @return bool|\WP_Error
     */

    private function permission($request, $scope, $level) {
        return \MillionDollarScript\Extensions\Support::permission($request, $scope, $level);
    }

    private function board_input($request, array $existing = []) {
        $params = $this->request_params($request);
        $settings = is_array($params['settings'] ?? null) ? $params['settings'] : [];
        if (!$settings && is_array($existing['settings'] ?? null)) {
            $settings = $existing['settings'];
        }

        if (isset($params['columns'])) {
            $settings['columns'] = min(12, max(1, absint($params['columns'])));
        }
        if (isset($params['disclosure_label'])) {
            $settings['disclosure_label'] = sanitize_text_field((string) $params['disclosure_label']);
        }
        if (isset($params['checkout_enabled'])) {
            $settings['checkout_enabled'] = $this->truthy($params['checkout_enabled']) ? '1' : '0';
        }
        foreach (['contact_email', 'terms_url', 'privacy_url', 'booking_retention_days', 'booking_limit_per_hour'] as $field) {
            if (array_key_exists($field, $params)) {
                $settings[$field] = $params[$field];
            }
        }

        return [
            'title' => $params['title'] ?? ($existing['title'] ?? ''),
            'slug' => $params['slug'] ?? ($existing['slug'] ?? ''),
            'description' => $params['description'] ?? ($existing['description'] ?? ''),
            'status' => $params['status'] ?? ($existing['status'] ?? 'draft'),
            'layout' => $params['layout'] ?? ($existing['layout'] ?? 'grid'),
            'currency' => $params['currency'] ?? ($existing['currency'] ?? ''),
            'settings' => $settings,
        ];
    }

    private function slot_input($request, array $existing = []) {
        $params = $this->request_params($request);

        return [
            'board_id' => $params['board_id'] ?? ($existing['board_id'] ?? 0),
            'title' => $params['title'] ?? ($existing['title'] ?? ''),
            'slug' => $params['slug'] ?? ($existing['slug'] ?? ''),
            'description' => $params['description'] ?? ($existing['description'] ?? ''),
            'price' => $params['price'] ?? ($existing['price'] ?? 0),
            'currency' => $params['currency'] ?? ($existing['currency'] ?? ''),
            'status' => $params['status'] ?? ($existing['status'] ?? 'available'),
            'position' => $params['position'] ?? ($existing['position'] ?? 0),
            'creative_type' => $params['creative_type'] ?? ($existing['creative_type'] ?? 'image'),
            'dimensions' => $params['dimensions'] ?? ($existing['dimensions'] ?? ''),
            'metadata' => is_array($params['metadata'] ?? null) ? $params['metadata'] : (is_array($existing['metadata'] ?? null) ? $existing['metadata'] : []),
        ];
    }

    private function validate_board_input(array $input, $creating) {
        if ($creating && '' === trim((string) ($input['title'] ?? ''))) {
            return new \WP_Error('mds_sponsorboard_board_missing_title', __('Board title is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ('' !== trim((string) ($input['title'] ?? '')) && strlen((string) $input['title']) > 191) {
            return new \WP_Error('mds_sponsorboard_board_title_too_long', __('Board title must be 191 characters or fewer.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!$this->board_status_allowed((string) ($input['status'] ?? 'draft'))) {
            return new \WP_Error('mds_sponsorboard_board_invalid_status', __('Board status is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!$this->board_layout_allowed((string) ($input['layout'] ?? 'grid'))) {
            return new \WP_Error('mds_sponsorboard_board_invalid_layout', __('Board layout is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
        if (!empty($settings['contact_email']) && !is_email((string) $settings['contact_email'])) {
            return new \WP_Error('mds_sponsorboard_board_invalid_contact', __('Board contact email is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }
        foreach (['terms_url', 'privacy_url'] as $field) {
            if (!empty($settings[$field]) && '' === DataSanitizer::url($settings[$field])) {
                return new \WP_Error('mds_sponsorboard_board_invalid_url', __('Board links must use a valid HTTP or HTTPS URL.', 'mds-sponsorboard'), ['status' => 400]);
            }
        }

        return true;
    }

    private function validate_slot_input(array $input, $creating) {
        if ($creating && empty($input['board_id'])) {
            return new \WP_Error('mds_sponsorboard_slot_missing_board', __('Sponsor board is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ($creating && '' === trim((string) ($input['title'] ?? ''))) {
            return new \WP_Error('mds_sponsorboard_slot_missing_title', __('Slot title is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ('' !== trim((string) ($input['title'] ?? '')) && strlen((string) $input['title']) > 191) {
            return new \WP_Error('mds_sponsorboard_slot_title_too_long', __('Slot title must be 191 characters or fewer.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ((float) ($input['price'] ?? 0) < 0) {
            return new \WP_Error('mds_sponsorboard_slot_invalid_price', __('Slot price must be zero or greater.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!$this->slot_status_allowed((string) ($input['status'] ?? 'available'))) {
            return new \WP_Error('mds_sponsorboard_slot_invalid_status', __('Slot status is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!$this->creative_type_allowed((string) ($input['creative_type'] ?? 'image'))) {
            return new \WP_Error('mds_sponsorboard_slot_invalid_creative_type', __('Creative type is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ('' !== trim((string) ($input['dimensions'] ?? '')) && !preg_match('/^\s*\d{2,5}\s*x\s*\d{2,5}\s*$/i', (string) $input['dimensions'])) {
            return new \WP_Error('mds_sponsorboard_slot_invalid_dimensions', __('Dimensions must use a width x height format, such as 600x300.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return true;
    }

    private function booking_input($request, array $existing = []) {
        $params = $this->request_params($request);
        $input = [];

        foreach (['slot_id', 'order_id', 'user_id', 'creative_id'] as $field) {
            if (array_key_exists($field, $params)) {
                $input[$field] = absint($params[$field]);
            }
        }

        foreach (['sponsor_name', 'sponsor_email', 'sponsor_url', 'starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $params)) {
                $input[$field] = (string) $params[$field];
            }
        }

        if (array_key_exists('status', $params)) {
            $input['status'] = sanitize_key((string) $params['status']);
        } elseif (!$existing) {
            $input['status'] = 'requested';
        }

        if (is_array($params['creative_payload'] ?? null)) {
            $input['creative_payload'] = $params['creative_payload'];
        }
        if (is_array($params['metadata'] ?? null)) {
            $input['metadata'] = $params['metadata'];
        }
        if (array_key_exists('terms_accepted', $params)) {
            $input['terms_accepted'] = $this->truthy($params['terms_accepted']);
        }

        return $input;
    }

    private function validate_booking_input(array $input, $creating, $exclude_id = 0) {
        if ($creating && empty($input['slot_id'])) {
            return new \WP_Error('mds_sponsorboard_booking_missing_slot', __('Sponsor slot is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ($creating && empty($input['sponsor_name'])) {
            return new \WP_Error('mds_sponsorboard_booking_missing_name', __('Sponsor name is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ($creating && empty($input['sponsor_email'])) {
            return new \WP_Error('mds_sponsorboard_booking_missing_email', __('Sponsor email is required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!empty($input['sponsor_email']) && !is_email((string) $input['sponsor_email'])) {
            return new \WP_Error('mds_sponsorboard_booking_invalid_email', __('Sponsor email is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (strlen((string) ($input['sponsor_name'] ?? '')) > 191) {
            return new \WP_Error('mds_sponsorboard_booking_name_too_long', __('Sponsor name must be 191 characters or fewer.', 'mds-sponsorboard'), ['status' => 400]);
        }
        if (!empty($input['sponsor_url']) && '' === DataSanitizer::url($input['sponsor_url'])) {
            return new \WP_Error('mds_sponsorboard_booking_invalid_url', __('Sponsor website must use a valid HTTP or HTTPS URL.', 'mds-sponsorboard'), ['status' => 400]);
        }
        $creative = is_array($input['creative_payload'] ?? null) ? $input['creative_payload'] : [];
        if (!empty($creative['creative_url']) && '' === DataSanitizer::url($creative['creative_url'])) {
            return new \WP_Error('mds_sponsorboard_booking_invalid_creative_url', __('Creative URL must use a valid HTTP or HTTPS URL.', 'mds-sponsorboard'), ['status' => 400]);
        }
        if (strlen((string) ($creative['note'] ?? '')) > 2000) {
            return new \WP_Error('mds_sponsorboard_booking_note_too_long', __('Sponsor note must be 2,000 characters or fewer.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if ($creating && empty($input['terms_accepted'])) {
            return new \WP_Error('mds_sponsorboard_booking_consent_required', __('Sponsorship terms and privacy consent are required.', 'mds-sponsorboard'), ['status' => 400]);
        }

        if (!empty($input['status']) && !$this->booking_status_allowed((string) $input['status'])) {
            return new \WP_Error('mds_sponsorboard_booking_invalid_status', __('Sponsor booking status is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $schedule = Repository::validate_booking_schedule(
            absint($input['slot_id'] ?? 0),
            $input['starts_at'] ?? '',
            $input['ends_at'] ?? '',
            $input['status'] ?? 'requested',
            absint($exclude_id)
        );
        if (is_wp_error($schedule)) {
            $status = 'mds_sponsorboard_booking_window_conflict' === $schedule->get_error_code() ? 409 : 400;
            return new \WP_Error($schedule->get_error_code(), $schedule->get_error_message(), ['status' => $status]);
        }

        return true;
    }

    private function board_status_allowed($status) {
        return in_array(sanitize_key((string) $status), ['draft', 'active', 'paused', 'archived'], true);
    }

    private function board_layout_allowed($layout) {
        return in_array(sanitize_key((string) $layout), ['grid', 'strip', 'mosaic', 'cards'], true);
    }

    private function slot_status_allowed($status) {
        return in_array(sanitize_key((string) $status), ['available', 'reserved', 'sold', 'paused', 'archived'], true);
    }

    private function creative_type_allowed($type) {
        return in_array(sanitize_key((string) $type), ['image', 'logo', 'text', 'html'], true);
    }

    private function request_params($request) {
        $params = is_object($request) && method_exists($request, 'get_json_params') ? $request->get_json_params() : [];
        if (!is_array($params) || !$params) {
            $params = is_object($request) && method_exists($request, 'get_params') ? $request->get_params() : [];
        }

        return is_array($params) ? $params : [];
    }

    private function board_payload($board, $include_slots = false) {
        $board = is_array($board) ? $board : [];
        $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
        $payload = [
            'id' => absint($board['id'] ?? 0),
            'slug' => (string) ($board['slug'] ?? ''),
            'title' => (string) ($board['title'] ?? ''),
            'description' => (string) ($board['description'] ?? ''),
            'status' => (string) ($board['status'] ?? ''),
            'layout' => (string) ($board['layout'] ?? ''),
            'currency' => (string) ($board['currency'] ?? ''),
            'settings' => array_intersect_key($settings, array_flip(['columns', 'disclosure_label', 'checkout_enabled', 'terms_url', 'privacy_url'])),
            'counts' => Repository::board_counts(absint($board['id'] ?? 0)),
            'created_at' => (string) ($board['created_at'] ?? ''),
            'updated_at' => (string) ($board['updated_at'] ?? ''),
        ];

        if ($include_slots) {
            $payload['slots'] = array_map([$this, 'slot_payload'], Repository::slots_for_board(absint($board['id'] ?? 0)));
        }

        return $payload;
    }

    private function slot_payload($slot) {
        $slot = is_array($slot) ? $slot : [];

        return [
            'id' => absint($slot['id'] ?? 0),
            'board_id' => absint($slot['board_id'] ?? 0),
            'slug' => (string) ($slot['slug'] ?? ''),
            'title' => (string) ($slot['title'] ?? ''),
            'description' => (string) ($slot['description'] ?? ''),
            'price' => (float) ($slot['price'] ?? 0),
            'currency' => (string) ($slot['currency'] ?? ''),
            'status' => (string) ($slot['status'] ?? ''),
            'position' => absint($slot['position'] ?? 0),
            'creative_type' => (string) ($slot['creative_type'] ?? ''),
            'dimensions' => (string) ($slot['dimensions'] ?? ''),
            'created_at' => (string) ($slot['created_at'] ?? ''),
            'updated_at' => (string) ($slot['updated_at'] ?? ''),
        ];
    }

    private function booking_payload($booking, $token = '') {
        $booking = is_array($booking) ? $booking : [];
        $payload = [
            'id' => absint($booking['id'] ?? 0),
            'board_id' => absint($booking['board_id'] ?? 0),
            'slot_id' => absint($booking['slot_id'] ?? 0),
            'order_id' => absint($booking['order_id'] ?? 0),
            'user_id' => absint($booking['user_id'] ?? 0),
            'board_title' => (string) ($booking['board_title'] ?? ''),
            'slot_title' => (string) ($booking['slot_title'] ?? ''),
            'sponsor_name' => (string) ($booking['sponsor_name'] ?? ''),
            'sponsor_email' => (string) ($booking['sponsor_email'] ?? ''),
            'sponsor_url' => (string) ($booking['sponsor_url'] ?? ''),
            'status' => (string) ($booking['status'] ?? ''),
            'status_label' => $this->booking_status_label((string) ($booking['status'] ?? 'requested')),
            'starts_at' => (string) ($booking['starts_at'] ?? ''),
            'ends_at' => (string) ($booking['ends_at'] ?? ''),
            'lifecycle_state' => BookingLifecycle::state($booking),
            'lifecycle_label' => BookingLifecycle::label($booking),
            'creative_id' => absint($booking['creative_id'] ?? 0),
            'creative_payload' => is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [],
            'analytics' => Repository::booking_event_summary(absint($booking['id'] ?? 0)),
            'metadata' => is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [],
            'created_at' => (string) ($booking['created_at'] ?? ''),
            'updated_at' => (string) ($booking['updated_at'] ?? ''),
        ];

        if ($token) {
            $payload['manage_url'] = $this->manage_url(absint($booking['id'] ?? 0), $token);
        }

        return $payload;
    }

    private function pagination_payload(array $page) {
        return [
            'page' => absint($page['page'] ?? 1),
            'per_page' => absint($page['per_page'] ?? 0),
            'total' => absint($page['total'] ?? 0),
            'pages' => absint($page['pages'] ?? 1),
        ];
    }
}
