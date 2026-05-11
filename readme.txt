=== Million Dollar Script SponsorBoard ===
Contributors: milliondollarscript
Tags: million dollar script, sponsorship, monetization, api
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sell fixed sponsor slots, featured placements, launch partners, and supporter inventory from Million Dollar Script.

== Description ==

SponsorBoard adds local sponsor inventory management to Million Dollar Script. Create boards, price slots, accept sponsor requests, expose secure API endpoints, and embed sponsor inventory with a shortcode.

== Installation ==

1. Upload the `mds-sponsorboard` folder into `wp-content/plugins`.
2. Activate Million Dollar Script SponsorBoard.
3. Open Million Dollar Script and use the SponsorBoard dashboard link.

== Usage ==

Embed a board with:

`[mds_sponsorboard id="1"]`

Or add the Million Dollar Script SponsorBoard block in the WordPress editor.

Active boards show request forms for available slots. Sponsors receive signed manage links after submitting a request, and administrators can review requests from the SponsorBoard booking section.

Boards can also enable paid checkout through the active Million Dollar Script payment provider. Install a provider extension, choose it in Million Dollar Script -> Setup, then set Paid Checkout on the board.

Create API keys in Million Dollar Script -> API Access with `sponsorboard.read` or `sponsorboard.write` scopes. The API supports boards, slots, and sponsor booking requests under `/wp-json/mds/v3/sponsorboard`.

== Changelog ==

= 0.1.0 =
* Initial SponsorBoard admin UI, block, shortcode, sponsor request flow, optional core payment-provider checkout, booking moderation, schema, and API.
