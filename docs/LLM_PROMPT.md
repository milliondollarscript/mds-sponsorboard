# LLM Integration Notes

SponsorBoard is intentionally API-first so trusted LLM agents and other apps can inspect and manage sponsor inventory without scraping the WordPress admin.

## Safe Read Actions

Use an API key scoped to `sponsorboard.read` for:

- Listing sponsor boards.
- Reading board counts.
- Reading slot status, price, and dimensions.
- Reading sponsor booking requests for moderation dashboards.

## Write Actions

Use an API key scoped to `sponsorboard.write` for:

- Creating boards.
- Updating board status and settings.
- Creating slots.
- Updating slot price, status, dimensions, and placement metadata.
- Creating sponsor bookings for available slots.
- Updating sponsor booking status after human approval, payment, or fulfillment checks.

## Payments

SponsorBoard uses the Million Dollar Script core payments API. Agents should not call WooCommerce, EDD, Stripe, or any other payment system directly for SponsorBoard bookings. If paid checkout is needed, ensure the board has paid checkout enabled and let SponsorBoard start checkout through the active Million Dollar Script payment provider.

## Recommended Agent Rules

- Never publish a draft board unless the human administrator explicitly asks.
- Never mark a slot sold unless payment or an external fulfillment signal has already been verified.
- Treat the active Million Dollar Script payment provider as the source of truth for automated paid checkout.
- Prefer `reserved` for tentative sponsor commitments.
- Treat `manage_url` values from booking create responses like private edit links. Do not log or publish them.
- Keep sponsor-facing claims factual and do not imply endorsement unless the administrator provides that language.
