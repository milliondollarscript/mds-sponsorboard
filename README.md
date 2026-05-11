# Million Dollar Script SponsorBoard

SponsorBoard is a local-first Million Dollar Script extension for selling fixed sponsorship inventory alongside pixel grids or as a standalone monetization surface.

## Features

- Sponsor boards with draft, active, paused, and archived states.
- Priced sponsor slots with available, reserved, sold, paused, and archived states.
- Secure Million Dollar Script API endpoints under `/wp-json/mds/v3/sponsorboard`.
- API policy discovery through the core API Access screen.
- Dashboard and hidden admin-page integration.
- Native SponsorBoard block for the WordPress editor.
- Frontend shortcode rendering with `[mds_sponsorboard id="1"]`.
- Public sponsor request forms for active, available slots.
- Optional paid checkout through the active Million Dollar Script payment provider.
- Signed sponsor manage links for updating submitted details.
- Admin booking moderation that keeps slot availability in sync.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Million Dollar Script 3.0+

## Installation

1. Upload or sync the `mds-sponsorboard` folder into `wp-content/plugins`.
2. Activate **Million Dollar Script SponsorBoard** in WordPress.
3. Open Million Dollar Script, then use the dashboard SponsorBoard link.

On activation the extension creates its tables and seeds a draft starter board with example slots.

## API

Create an API key from **Million Dollar Script -> API Access** with these scopes as needed:

- `sponsorboard.read` for listing boards and slots.
- `sponsorboard.write` for creating or updating boards and slots.

Use the `X-Million-Dollar-Script-API-Key` header or a Bearer token.

Available routes:

- `GET /wp-json/mds/v3/sponsorboard/boards`
- `POST /wp-json/mds/v3/sponsorboard/boards`
- `GET /wp-json/mds/v3/sponsorboard/boards/{id}`
- `PATCH /wp-json/mds/v3/sponsorboard/boards/{id}`
- `GET /wp-json/mds/v3/sponsorboard/boards/{id}/slots`
- `POST /wp-json/mds/v3/sponsorboard/boards/{id}/slots`
- `PATCH /wp-json/mds/v3/sponsorboard/slots/{id}`
- `GET /wp-json/mds/v3/sponsorboard/boards/{id}/bookings`
- `POST /wp-json/mds/v3/sponsorboard/boards/{id}/bookings`
- `GET /wp-json/mds/v3/sponsorboard/bookings/{id}`
- `PATCH /wp-json/mds/v3/sponsorboard/bookings/{id}`

Booking create responses include a sponsor manage URL once. Existing bookings never expose the raw manage token through read or update endpoints.

## Shortcode

```text
[mds_sponsorboard id="1"]
```

Useful attributes:

- `id`: Board ID.
- `slug`: Board slug when no ID is supplied.
- `show_available`: `1` to show available slots, `0` to show only committed slots.
- `preview`: `1` lets administrators preview inactive boards.

## Block

Add the **Million Dollar Script SponsorBoard** block in the WordPress editor. The block lets editors choose a board, show or hide available slots, and preview inactive boards for logged-in administrators.

## Sponsor Requests

When a board is active, every available slot can show a compact request form. Sponsors submit their name, email, website, creative URL, and note. Million Dollar Script then reserves the slot and sends the sponsor to a signed manage link.

The sponsor manage page lets the sponsor update public-facing details without a WordPress account. Manage tokens are stored hashed, the page is marked noindex, and anonymous requests are rate limited per slot and visitor.

Administrators review requests from the SponsorBoard admin page. Status changes automatically update the linked slot:

- `requested`, `pending_payment`, and `changes_requested` keep the slot reserved.
- `paid`, `approved`, and `active` mark the slot sold.
- `rejected`, `expired`, and `cancelled` return the slot to available.

## Paid Checkout

SponsorBoard does not connect directly to WooCommerce, EDD, Stripe, or any other checkout plugin. It uses the Million Dollar Script core payments API. Install a payment provider extension, choose it in **Million Dollar Script -> Setup**, then set a SponsorBoard board's **Paid Checkout** option to **Use Million Dollar Script payment provider**.

When paid checkout is enabled, available slots show a **Buy this slot** form. SponsorBoard creates a pending booking, asks the core payments API to start checkout, and listens for core payment status callbacks. The selected provider extension handles the store or gateway-specific order.

## Changelog

### 0.1.0

- Initial SponsorBoard extension scaffold with admin UI, block and shortcode rendering, sponsor requests, optional core payment-provider checkout, signed manage links, booking moderation, database schema, and REST API.
