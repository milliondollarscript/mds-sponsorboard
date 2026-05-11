# SponsorBoard Usage

SponsorBoard gives Million Dollar Script sites a second monetization surface: fixed sponsor inventory that can be sold next to a grid, on a campaign page, or on any WordPress page.

## Admin Workflow

1. Activate Million Dollar Script SponsorBoard.
2. Open **Million Dollar Script -> Dashboard**.
3. Choose **SponsorBoard** from the dashboard menu.
4. Review the starter board or create a new one.
5. Add sponsor slots with prices, statuses, and dimensions.
6. Set the board status to **Active** when it should render publicly.
7. Optional: set **Paid Checkout** to **Use Million Dollar Script payment provider** after a payment provider extension is active.
8. Review incoming sponsor requests and paid bookings in the **Bookings** section.

## Block

Add the **Million Dollar Script SponsorBoard** block in the WordPress editor. The block controls:

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

After submission, the slot becomes reserved and the sponsor receives a signed manage link. The manage page lets the sponsor update their name, website, creative URL, and note without creating a WordPress account.

Administrators moderate requests from **SponsorBoard -> Bookings** on the selected board page. Booking status controls slot availability:

- `Requested`, `Pending payment`, and `Changes requested` reserve the slot.
- `Paid`, `Approved`, and `Active` mark the slot sold.
- `Rejected`, `Expired`, and `Cancelled` return the slot to available.

The public manage link is token-protected, noindexed, and should be treated like a private edit link.

## Paid Checkout Flow

SponsorBoard uses the Million Dollar Script core payments API instead of talking to a shop plugin directly. This means WooCommerce, EDD, direct gateways, or future payment services can be swapped at the core payment-provider level without changing SponsorBoard.

To enable paid checkout:

1. Install and activate a payment provider extension, such as **Million Dollar Script WooCommerce Checkout**.
2. Open **Million Dollar Script -> Setup** and choose that provider in **Payment Provider**.
3. Open the SponsorBoard board and set **Paid Checkout** to **Use Million Dollar Script payment provider**.

Available slots then show **Buy this slot**. The sponsor enters the same sponsor details, SponsorBoard creates a pending booking, and the active provider starts payment. Payment callbacks from the core API move paid bookings to `Paid` and cancelled payments to `Cancelled`.

## API

SponsorBoard routes are registered under the core Million Dollar Script API namespace:

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

Use the API Access screen to create keys with:

- `sponsorboard.read`
- `sponsorboard.write`

The same screen can raise endpoint security levels or disable endpoints.

Booking endpoints let trusted apps and LLM agents review and moderate requests without scraping admin pages. Creating a booking through the API returns a sponsor manage URL once. Later read/update responses do not expose raw manage tokens.
