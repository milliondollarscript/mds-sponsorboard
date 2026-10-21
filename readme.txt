=== Million Dollar Script - SponsorBoard ===
Contributors: milliondollarscript
Tags: million dollar script, sponsorship, monetization, api
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sell fixed sponsor slots, featured placements, launch partners, and supporter inventory from Million Dollar Script.

== Description ==

SponsorBoard adds local sponsor inventory management to Million Dollar Script. Create boards, price slots, accept sponsor requests, expose secure API endpoints, and embed sponsor inventory with a shortcode.

== Installation ==

1. Upload the `mds-sponsorboard` folder into `wp-content/plugins`.
2. Activate Million Dollar Script - SponsorBoard.
3. Open Million Dollar Script and use the SponsorBoard dashboard link.

== Usage ==

Embed a board with:

`[mds_sponsorboard id="1"]`

Or add the Million Dollar Script - SponsorBoard block in the WordPress editor.

Active boards show request forms for available slots. Sponsors must accept the sponsorship terms and privacy notice before submitting. They receive signed manage links after a successful request, and administrators can search, filter, paginate, moderate, or move bookings from the SponsorBoard booking section.

Administrators can schedule approved bookings with optional start and end times in the WordPress site timezone. SponsorBoard prevents overlapping live schedules, keeps future inventory reserved, publishes approved creative only inside its date window, and releases ended inventory automatically.

Signed manage pages accept JPG, PNG, GIF, and WebP uploads for image and logo slots. Uploaded creatives are stored in the WordPress media library, checked against configured slot dimensions, and require administrator approval before they render publicly.

Boards can also enable paid checkout through the active Million Dollar Script payment provider. Install a provider extension, choose it in Million Dollar Script -> Setup, then set Paid Checkout on the board.

Create API keys in Million Dollar Script -> API Access with `sponsorboard.read` for board and slot inventory or `sponsorboard.write` for private bookings and all changes. The API supports boards, slots, and sponsor booking requests under `/wp-json/million-dollar-script/v1/sponsorboard`. The admin dashboard includes local revenue, occupancy, impression, click, CTR, booking export, event export, private-data retention, and WordPress privacy-tool support.

== Changelog ==

= 1.4.0 =
* Adds optional subscription allowances for sponsor bookings and subscription-managed booking terms.
* Preserves one-time payment-provider checkout when no eligible subscription allowance is available.

= 1.3.1 =
* Removed the dormant hosted SponsorBoard pilot while preserving local boards, bookings, analytics, and privacy workflows.

= 1.3.0 =
* Adds secure optional Advertiser Workspace integration for owned booking management, creative replacement, and checkout.

= 1.2.0 =
* Added enforced booking start and end dates, site-timezone admin controls, lifecycle status labels, overlap prevention, automatic activation and expiry, accurate active/expiring reporting, and paid-history preservation for hosted synchronization.

= 1.1.1 =
* Prevent authenticated hosted-service requests from following redirects.
* Require safe HTTPS hosted URLs outside explicitly supported local development hosts.
* Reject embedded credentials, query strings, fragments, and unsafe private-network targets in hosted-service URLs.

= 1.1.0 =
* Added atomic booking reservations, required consent, terms/privacy links, notifications, rate limits, private-data retention, paginated administration and privacy tools, safe booking moves, strict payment-state handling, hardened REST privacy, semantic notices, and private manage-page no-referrer protection.

= 1.0.0 =
* Added token-protected creative uploads for image and logo slots, creative moderation, approved public rendering, local analytics, CSV exports, reporting metrics, and privacy exporter/eraser callbacks.

= 0.1.0 =
* Initial SponsorBoard admin UI, block, shortcode, sponsor request flow, optional core payment-provider checkout, booking moderation, schema, and API.
