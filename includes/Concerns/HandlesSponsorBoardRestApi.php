<?php
/**
 * SponsorBoard REST route registration, callbacks, authorization, and payload mapping.
 *
 * @package MDS\Extensions\SponsorBoard
 */

namespace MDS\Extensions\SponsorBoard\Concerns;

use MDS\Extensions\SponsorBoard\Repository;

if (!defined('ABSPATH')) {
    exit;
}

trait HandlesSponsorBoardRestApi {

    public function register_rest_routes() {
        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/boards', [
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

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/boards/(?P<id>\d+)', [
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

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/boards/(?P<id>\d+)/slots', [
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

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/slots/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'rest_update_slot'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/boards/(?P<id>\d+)/bookings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_list_bookings'],
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
                'callback' => [$this, 'rest_create_booking'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.write', 'api_key_write');
                },
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, self::REST_BASE . '/bookings/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'rest_get_booking'],
                'permission_callback' => function ($request) {
                    return $this->permission($request, 'sponsorboard.read', 'api_key_read');
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
        $endpoints[] = ['id' => 'sponsorboard-boards-read', 'route' => '/mds/v3/sponsorboard/boards', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-boards-write', 'route' => '/mds/v3/sponsorboard/boards', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-board-read', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read one sponsor board.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-board-write', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor boards.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slots-read', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)/slots', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slots-write', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)/slots', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-slot-write', 'route' => '/mds/v3/sponsorboard/slots/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor board slots.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-bookings-read', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)/bookings', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read sponsor booking requests.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-bookings-write', 'route' => '/mds/v3/sponsorboard/boards/(?P<id>\d+)/bookings', 'methods' => ['POST'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Create sponsor booking requests.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-booking-read', 'route' => '/mds/v3/sponsorboard/bookings/(?P<id>\d+)', 'methods' => ['GET'], 'scope' => 'sponsorboard.read', 'minimum_security_level' => 'api_key_read', 'description' => __('Read one sponsor booking request.', 'mds-sponsorboard')];
        $endpoints[] = ['id' => 'sponsorboard-booking-write', 'route' => '/mds/v3/sponsorboard/bookings/(?P<id>\d+)', 'methods' => ['PUT', 'PATCH'], 'scope' => 'sponsorboard.write', 'minimum_security_level' => 'api_key_write', 'description' => __('Update sponsor booking requests.', 'mds-sponsorboard')];

        return $endpoints;
    }

    /**
     * REST callback: list boards.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */

    public function rest_list_boards($request) {
        $limit = min(100, max(1, absint($request['limit'] ?? 50)));
        $boards = array_map([$this, 'board_payload'], Repository::all_boards($limit));

        return rest_ensure_response(['data' => $boards]);
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
        $id = Repository::create_board($this->board_input($request));
        if (!$id) {
            return new \WP_Error('mds_sponsorboard_create_failed', __('Sponsor board could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return new \WP_REST_Response(['data' => $this->board_payload(Repository::find_board($id), true)], 201);
    }

    /**
     * REST callback: update a board.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_update_board($request) {
        $board = Repository::update_board(absint($request['id'] ?? 0), $this->board_input($request));
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
        $slot_id = Repository::create_slot($input);
        if (!$slot_id) {
            return new \WP_Error('mds_sponsorboard_slot_create_failed', __('Sponsor slot could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return new \WP_REST_Response(['data' => $this->slot_payload(Repository::find_slot($slot_id))], 201);
    }

    /**
     * REST callback: update a slot.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response|\WP_Error
     */

    public function rest_update_slot($request) {
        $slot = Repository::update_slot(absint($request['id'] ?? 0), $this->slot_input($request));
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

        $limit = min(100, max(1, absint($request['limit'] ?? 50)));
        $bookings = array_map([$this, 'booking_payload'], Repository::bookings_for_board((int) $board['id'], $limit));

        return rest_ensure_response(['data' => $bookings]);
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

        $result = Repository::create_booking($input);
        if (!$result || empty($result['booking']['id']) || empty($result['token'])) {
            return new \WP_Error('mds_sponsorboard_booking_create_failed', __('Sponsor booking could not be created.', 'mds-sponsorboard'), ['status' => 400]);
        }

        $booking = is_array($result['booking']) ? $result['booking'] : [];
        $this->sync_slot_for_booking_status($booking);

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

        $input = array_merge($booking, $this->booking_input($request, $booking));
        $validation = $this->validate_booking_input($input, false);
        if (is_wp_error($validation)) {
            return $validation;
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
        if (class_exists('\\MDS3\\Rest\\ApiGovernance')) {
            return (new \MDS3\Rest\ApiGovernance())->authorize($request, $scope, $level);
        }

        return current_user_can('manage_options');
    }

    private function board_input($request) {
        $params = $this->request_params($request);
        $settings = is_array($params['settings'] ?? null) ? $params['settings'] : [];

        if (isset($params['columns'])) {
            $settings['columns'] = min(12, max(1, absint($params['columns'])));
        }
        if (isset($params['disclosure_label'])) {
            $settings['disclosure_label'] = sanitize_text_field((string) $params['disclosure_label']);
        }
        if (isset($params['checkout_enabled'])) {
            $settings['checkout_enabled'] = $this->truthy($params['checkout_enabled']) ? '1' : '0';
        }

        return [
            'title' => $params['title'] ?? '',
            'slug' => $params['slug'] ?? '',
            'description' => $params['description'] ?? '',
            'status' => $params['status'] ?? 'draft',
            'layout' => $params['layout'] ?? 'grid',
            'currency' => $params['currency'] ?? '',
            'settings' => $settings,
        ];
    }

    private function slot_input($request) {
        $params = $this->request_params($request);

        return [
            'board_id' => $params['board_id'] ?? 0,
            'title' => $params['title'] ?? '',
            'slug' => $params['slug'] ?? '',
            'description' => $params['description'] ?? '',
            'price' => $params['price'] ?? 0,
            'currency' => $params['currency'] ?? '',
            'status' => $params['status'] ?? 'available',
            'position' => $params['position'] ?? 0,
            'creative_type' => $params['creative_type'] ?? 'image',
            'dimensions' => $params['dimensions'] ?? '',
            'metadata' => is_array($params['metadata'] ?? null) ? $params['metadata'] : [],
        ];
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

        return $input;
    }

    private function validate_booking_input(array $input, $creating) {
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

        if (!empty($input['status']) && !$this->booking_status_allowed((string) $input['status'])) {
            return new \WP_Error('mds_sponsorboard_booking_invalid_status', __('Sponsor booking status is invalid.', 'mds-sponsorboard'), ['status' => 400]);
        }

        return true;
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
            'settings' => $settings,
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
            'metadata' => is_array($slot['metadata'] ?? null) ? $slot['metadata'] : [],
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
            'creative_id' => absint($booking['creative_id'] ?? 0),
            'creative_payload' => is_array($booking['creative_payload'] ?? null) ? $booking['creative_payload'] : [],
            'metadata' => is_array($booking['metadata'] ?? null) ? $booking['metadata'] : [],
            'created_at' => (string) ($booking['created_at'] ?? ''),
            'updated_at' => (string) ($booking['updated_at'] ?? ''),
        ];

        if ($token) {
            $payload['manage_url'] = $this->manage_url(absint($booking['id'] ?? 0), $token);
        }

        return $payload;
    }
}
