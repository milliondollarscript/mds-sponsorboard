# Million Dollar Script - SponsorBoard

SponsorBoard is a local-first Million Dollar Script extension for selling fixed sponsorship inventory alongside pixel grids or as a standalone monetization surface.

## Features

- Sponsor boards with draft, active, paused, and archived states.
- Priced sponsor slots with available, reserved, sold, paused, and archived states.
- Secure Million Dollar Script API endpoints under `/wp-json/million-dollar-script/v1/sponsorboard`.
- API policy discovery through the core API Access screen.
- Dashboard and hidden admin-page integration.
- Native SponsorBoard block for the WordPress editor.
- Frontend shortcode rendering with `[mds_sponsorboard id="1"]`.
- Public sponsor request forms for active, available slots.
- Required sponsor consent with configurable sponsorship terms and privacy links.
- Optional paid checkout through the active Million Dollar Script payment provider.
- Optional sponsor-booking allowances and term management through the Subscriptions extension.
- Signed sponsor manage links for updating submitted details.
- Sponsor and board-contact email notifications through `wp_mail()`.
- Searchable, paginated admin booking moderation and safe moves between available slots.
- Atomic reservations and ownership-aware slot status recalculation.
- Optional start and end schedules with overlap prevention, automatic activation and expiry, and site-timezone admin controls.
- Token-protected image/logo creative uploads with type, size, and dimension validation.
- Creative approval controls so only approved uploaded assets render publicly.
- Aggregate impressions, clicks, CTR, revenue, active/expiring booking metrics, CSV exports, and WordPress privacy exporter/eraser callbacks.
- Configurable request limits, private booking-data retention, and paginated privacy processing.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Million Dollar Script 3.0+

## Installation

1. Upload or sync the `mds-sponsorboard` folder into `wp-content/plugins`.
2. Activate **Million Dollar Script - SponsorBoard** in WordPress.
3. Open Million Dollar Script, then use the dashboard SponsorBoard link.

On activation the extension creates its tables and seeds a draft starter board with example slots.

## API

Create an API key from **Million Dollar Script -> API Access** with these scopes as needed:

- `sponsorboard.read` for listing boards and public-safe slot inventory.
- `sponsorboard.write` for private booking reads and all create or update operations.

Use the `X-Million-Dollar-Script-API-Key` header or a Bearer token.

Available routes:

- `GET /wp-json/million-dollar-script/v1/sponsorboard/boards`
- `POST /wp-json/million-dollar-script/v1/sponsorboard/boards`
- `GET /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}`
- `PATCH /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}`
- `GET /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}/slots`
- `POST /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}/slots`
- `PATCH /wp-json/million-dollar-script/v1/sponsorboard/slots/{id}`
- `GET /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}/bookings`
- `POST /wp-json/million-dollar-script/v1/sponsorboard/boards/{id}/bookings`
- `GET /wp-json/million-dollar-script/v1/sponsorboard/bookings/{id}`
- `PATCH /wp-json/million-dollar-script/v1/sponsorboard/bookings/{id}`

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

Add the **Million Dollar Script - SponsorBoard** block in the WordPress editor. The block lets editors choose a board, show or hide available slots, and preview inactive boards for logged-in administrators.

## Sponsor Requests

When a board is active, every available slot can show a compact request form. Sponsors submit their name, email, website, creative URL, note, and required consent. Million Dollar Script reserves the slot atomically and sends the sponsor to a signed manage link. The board contact and sponsor receive direct WordPress email notifications.

The sponsor manage page lets the sponsor update public-facing details without a WordPress account. Image and logo slots also accept JPG, PNG, GIF, and WebP creative uploads. Uploads are stored in the WordPress media library, limited to the smaller of 2 MB or the site upload limit, and checked against the slot dimensions when dimensions are configured. Manage tokens are stored hashed. Manage pages are non-cacheable, noindexed, and protected by a no-referrer policy so the private token is not sent to outbound creative links. Anonymous request limits are configurable per board, visitor, and slot.

Administrators review requests from the SponsorBoard admin page. Status changes automatically update the linked slot:

- `requested`, `pending_payment`, and `changes_requested` keep the slot reserved.
- `paid`, `approved`, and `active` mark the slot sold.
- `rejected`, `expired`, and `cancelled` return the slot to available.

Slot state is calculated from every live booking. Cancelling one booking does not release a slot that still has another paid, approved, active, requested, pending-payment, or changes-requested booking. Administrators can also move a booking to another available slot on the same board without losing its sponsor, payment, moderation, or reporting history.

Administrators can set optional **Starts** and **Ends** values on a booking. The fields use the WordPress site timezone. A future approved booking keeps its inventory sold but stays private until its start; an ended booking stops rendering immediately and releases inventory. SponsorBoard rejects overlapping live windows for one slot and allows one booking to start exactly when another ends. A locked hourly lifecycle job, with a throttled request-time fallback, records due `Active` and `Expired` status transitions without relying on the job for public visibility enforcement.

Creative approval is separate from booking/payment status. Uploaded creatives start as `pending_review`; administrators can approve, reject, or request changes. Public boards render uploaded media-library creatives only after the booking is `approved` or `active` and the creative is `approved`. Remote creative URLs remain available for admin review and sponsor self-service, but are not embedded publicly.

## Paid Checkout

SponsorBoard does not connect directly to WooCommerce, EDD, Stripe, or any other checkout plugin. It uses the Million Dollar Script core payments API. Install a payment provider extension, choose it in **Million Dollar Script -> Setup**, then set a SponsorBoard board's **Paid Checkout** option to **Use Million Dollar Script payment provider**.

When paid checkout is enabled, available slots show a **Buy this slot** form. SponsorBoard creates a pending booking, asks the core payments API to start checkout, and listens for core payment status callbacks. Completed, processing, and paid callbacks preserve paid inventory; failed, cancelled, expired, denied, and refunded callbacks close the booking and release the slot only when no other live booking owns it. Unknown provider states are ignored. The selected provider extension handles the store or gateway-specific order.

If the optional Subscriptions extension grants the sponsor an eligible booking allowance for the selected slot, SponsorBoard consumes that allowance idempotently and marks the booking paid without opening a second checkout. Subscription-managed booking terms extend only when the new paid period ends later than the stored booking term. Customers without an eligible allowance continue through the configured one-time payment provider.

## Reporting and Privacy

SponsorBoard tracks approved sponsor impressions and clicks through a throttled local AJAX endpoint. It stores event totals with hashed IP and user-agent fingerprints for rate limiting; raw IP addresses are not stored. The admin dashboard shows revenue, occupancy, active and expiring bookings, impressions, clicks, and CTR. Booking and event CSV exports are available from the selected board.

Sponsor booking data is included in paginated WordPress personal data export and erasure tools. Each board controls how long private sponsor data is retained. Scheduled cleanup removes email addresses, manage tokens, and private metadata while preserving current or future approved sponsorship content until its scheduled end or another unpublishing action. An explicit personal-data erasure request also removes public sponsor identity, URL, and creative content. Both paths preserve non-personal aggregate reporting rows.

## Changelog

### 1.4.0

- Added optional subscription booking allowances and subscription-managed booking terms without changing the one-time checkout fallback.

### 1.3.1

- Removed the dormant hosted SponsorBoard pilot while preserving local sponsor operations.

### 1.2.0

- Added enforced booking schedules, lifecycle transitions and labels, overlap protection, accurate active/expiring reporting, and paid-history preservation after expiry.

### 1.1.1

- Hardened optional hosted-service transport with no redirects, safe remote URL validation, and local-development-only loopback support.

### 1.1.0

- Added atomic reservations, sponsor consent and notifications, admin pagination and booking moves, private-data retention, safer payment callbacks, and hardened private API/manage-link behavior.

### 1.0.0

- Added creative uploads, moderation, approved public rendering, analytics, reporting exports, and privacy callbacks.

### 0.1.0

- Initial SponsorBoard extension scaffold with admin UI, block and shortcode rendering, sponsor requests, optional core payment-provider checkout, signed manage links, booking moderation, database schema, and REST API.
