# SponsorBoard Usage

SponsorBoard gives Million Dollar Script sites a second monetization surface: fixed sponsor inventory that can be sold next to a grid, on a campaign page, or on any WordPress page.

## Practical Examples

- Sell a fixed set of logo positions beside a pixel grid without changing the grid's block inventory.
- Offer header, sidebar, newsletter, event, or campaign sponsorship slots with different dimensions, prices, and schedules.
- Let a sponsor reserve a slot, manage its creative through a signed link, and complete provider checkout without requiring a WordPress account.

### Example workflow: sell scheduled event sponsor slots

1. Open SponsorBoard from the Million Dollar Script dashboard, create an active event board, and add logo slots for the available sponsorship levels.
2. Configure the board's terms, privacy links, notification address, and Million Dollar Script payment-provider checkout.
3. Add the **SponsorBoard** block to the event page. A sponsor requests a slot, completes checkout, and uploads a correctly sized creative from the signed manage page.
4. Under **Bookings**, verify payment, review the creative, set its start and end dates, and approve it. The slot is shown only during its eligible display window and remains reserved while its live booking owns it.

## Admin Workflow

1. Activate Million Dollar Script - SponsorBoard.
2. Open **Million Dollar Script -> Dashboard**.
3. Choose **Manage sponsors** from the SponsorBoard card in the dashboard Extensions section.
4. Review the starter board or create a new one.
5. Add sponsor slots with prices, statuses, and dimensions.
6. Set the board status to **Active** when it should render publicly.
7. Optional: set **Paid Checkout** to **Use Million Dollar Script payment provider** after a payment provider extension is active.
8. Review incoming sponsor requests and paid bookings in the **Bookings** section.

Each board also controls:

- The email address that receives new-booking notifications. The site administrator email is used when this is blank.
- Optional HTTP(S) sponsorship terms and privacy pages linked beside the required consent checkbox.
- The number of booking or checkout attempts allowed per visitor and slot each hour.
- How many days private sponsor contact and draft creative data is retained.

When a board exists, the admin workspace opens on **Bookings** so sponsor requests are visible first. The workspace also provides **Overview**, **Boards**, and **Slots** tabs. Board creation and configuration stay under **Boards**, inventory stays under **Slots**, and local analytics storage controls appear on **Overview**. Tab URLs preserve the selected board, filters, and pagination so administrators can return to a specific workflow directly.

## Block

Add the **SponsorBoard** block from the Million Dollar Script block category in the WordPress editor. The block controls:

- Board selection.
- Whether available slots should be shown.
- Whether inactive boards can be previewed for logged-in administrators.

## Shortcode

Use the shortcode on any page or post:

```text
[mds_sponsorboard id="1"]
```

Attributes:

- `id`: Numeric board ID.
- `slug`: Board slug if you prefer stable names.
- `show_available`: `1` shows available slots. `0` hides them.
- `preview`: `1` lets administrators preview inactive boards.

## Sponsor Request Flow

Active boards display request forms for available slots. A sponsor can submit:

- Sponsor name.
- Sponsor email.
- Sponsor website.
- Creative URL.
- A short note for the administrator.
- Consent to the sponsorship terms and use of the submitted information.

After submission, the slot becomes reserved and the sponsor receives a signed manage link. SponsorBoard also emails the board contact and sponsor through WordPress. The manage page lets the sponsor update their name, website, creative URL, uploaded creative, and note without creating a WordPress account.

Image and logo slots accept JPG, PNG, GIF, and WebP uploads from the signed manage page. SponsorBoard stores uploaded creatives in the WordPress media library, limits uploads to the smaller of 2 MB or the site upload limit, and rejects images larger than the configured slot dimensions when dimensions are set. Text and HTML slot types keep upload controls hidden; use the note and URL fields for those formats.

Administrators moderate requests in the **Bookings** section of the selected SponsorBoard page. Booking status controls slot availability:

- `Requested`, `Pending payment`, and `Changes requested` reserve the slot.
- `Paid`, `Approved`, and `Active` mark the slot sold.
- `Rejected`, `Expired`, and `Cancelled` return the slot to available.

The booking list can be searched by sponsor, email, slot, or booking ID and filtered by status. Large lists are paginated. To move a booking, choose another available slot from **Inventory slot** and save the booking. Moves are limited to the same board and preserve the booking's sponsor, payment, creative, and reporting history.

Slot status is derived from every live booking attached to that slot. Closing one booking cannot release inventory that is still owned by another requested, pending-payment, paid, approved, changes-requested, or active booking.

### Booking schedules

Use **Starts** and **Ends** on a booking to control its display window. Admin fields use the WordPress site timezone shown above the booking list:

- Leave **Starts** blank to make approved creative eligible to display immediately.
- Leave **Ends** blank when the booking has no scheduled end.
- A future approved booking reserves its slot but stays private until the start time.
- An ended booking stops rendering immediately and its slot becomes available when no later booking owns it.
- Live booking windows for one slot cannot overlap. Adjacent schedules are allowed, so one booking can start at the exact time another ends.

SponsorBoard checks the date window on every public lookup, so delayed cron processing cannot expose future or expired creative. A bounded hourly lifecycle job, with a throttled request-time fallback, records due `Active` and `Expired` statuses, refreshes slot state, and sends booking updates. Repeated runs are safe and do not repeat completed transitions.

Creative status controls public creative rendering:

- `Pending review` keeps the creative private.
- `Approved` allows an uploaded media-library creative to render publicly when the booking is also `Approved` or `Active`.
- `Changes requested` and `Rejected` keep the creative private until the sponsor replaces it or an administrator approves it.

Remote creative URLs remain visible on the manage page and admin booking cards, but public boards do not embed them as images. This avoids third-party load failures and keeps public rendering limited to reviewed local media.

The public manage link is token-protected, non-cacheable, noindexed, and protected by a no-referrer policy. Treat it like a private edit link and do not publish or log it.

When the premium Advertiser Workspace extension is active, verified sponsors can also find owned SponsorBoard bookings in the shared advertiser workspace. SponsorBoard remains the source of truth for booking status, moderation, schedules, creative data, and checkout. The workspace adapter never exposes the signed manage token or private bookings owned by another user or email address.

Authorized sponsors can update their public name, website, note, and uploaded creative from the shared workspace. Updates return the creative to pending review. Checkout uses the booking's saved provider when available and otherwise uses the currently active Million Dollar Script payment provider.

## Paid Checkout Flow

SponsorBoard uses the Million Dollar Script core payments API instead of talking to a shop plugin directly. This means WooCommerce, EDD, direct gateways, or future payment services can be swapped at the core payment-provider level without changing SponsorBoard.

To enable paid checkout:

1. Install and activate a payment provider extension, such as **Million Dollar Script - WooCommerce Checkout**.
2. Open **Million Dollar Script -> Setup** and choose that provider in **Payment Provider**.
3. Open the SponsorBoard board and set **Paid Checkout** to **Use Million Dollar Script payment provider**.

Available slots then show **Buy this slot**. The sponsor enters the same sponsor details and consent, SponsorBoard atomically creates a pending booking, and the active provider starts payment. Completed, processing, and paid callbacks move the booking to `Paid`. Failed, cancelled, expired, denied, and refunded callbacks close the booking and release inventory only when no other live booking owns the slot. Unknown payment states are ignored.

When the optional Subscriptions extension is enabled, an eligible sponsor can use a configured booking allowance for the selected slot. SponsorBoard consumes the allowance with the booking ID as its idempotency key, marks the booking paid, and opens the normal signed manage page without starting a second checkout. If no eligible allowance exists, the configured one-time payment-provider flow remains unchanged.

A subscription configured to manage the booking term can extend `Ends` after a paid cycle. SponsorBoard only moves the end date forward and keeps the subscription reference in booking metadata; retries cannot shorten the term or create a replacement booking.

## Analytics, Exports, and Privacy

Public boards track approved sponsor impressions and clicks with a throttled local AJAX endpoint. SponsorBoard stores hashed IP and user-agent fingerprints only for rate limiting and abuse control; raw IP addresses are not stored.

The SponsorBoard admin dashboard shows:

- Revenue from paid booking history, including scheduled bookings that later expired.
- Occupancy through available, reserved, and sold inventory counts.
- Active and expiring bookings.
- Impressions, clicks, and CTR.

The selected board's booking section includes CSV exports for bookings and events. Large exports are generated in batches and then downloaded from an administrator-only endpoint. Booking exports include sponsor contact details and creative status. Event exports include event IDs, board/slot/booking IDs, event type, event value, source, rollup date when applicable, and timestamp without IP or user-agent hashes.

Raw event retention controls how long detailed local impression and click rows are kept. When rollups are enabled, old raw rows are aggregated by board, slot, booking, event type, and day before cleanup so local reporting remains usable after detailed rows are removed.

SponsorBoard registers with WordPress personal data export and erasure tools. Export and erasure callbacks process records in bounded pages, including sites with more than 100 matching bookings.

Each board's **Private data retention** setting controls when private sponsor details are anonymized. Scheduled cleanup removes sponsor email, the manage token, and private metadata. For a current or future approved sponsorship, scheduled cleanup preserves the public sponsor name, public URL, and approved creative until the scheduled end or another unpublishing action. Draft, rejected, cancelled, ended, and otherwise non-public bookings have their personal and creative details removed. An explicit WordPress personal-data erasure request also removes public sponsor identity, URL, and creative content. Non-personal aggregate reporting rows are retained in both cases.

## API

SponsorBoard routes are registered under the core Million Dollar Script API namespace:

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

Use the API Access screen to create keys with:

- `sponsorboard.read` for public-safe boards and slots.
- `sponsorboard.write` for private booking reads and every create or update operation.

The same screen can raise endpoint security levels or disable endpoints.

Board and booking list endpoints accept `page` and `per_page`; booking lists also accept `status` and `search`. Responses include pagination metadata. Private booking endpoints are not LLM-safe read actions and require write-level authorization because they contain sponsor contact and creative data.

Creating a booking through the API requires `terms_accepted: true`, atomically reserves an available slot, and returns a sponsor manage URL once. Later read/update responses do not expose raw manage tokens. Updating `slot_id` moves the booking only when the destination is an available slot on the same board.

Booking create and update payloads may include `starts_at` and `ends_at`. Use UTC values with an explicit `Z` or numeric offset; values without an offset are treated as UTC. Booking responses include normalized UTC `starts_at` and `ends_at` values plus `lifecycle_state` and `lifecycle_label`. Invalid, reversed, or overlapping windows are rejected before any inventory move or booking update is applied.
