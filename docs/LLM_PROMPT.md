# LLM Integration Notes

SponsorBoard is intentionally API-first so trusted LLM agents and other apps can inspect and manage sponsor inventory without scraping the WordPress admin.

## Safe Read Actions

Use an API key scoped to `sponsorboard.read` for:

- Listing sponsor boards.
- Reading board counts.
- Reading slot status, price, and dimensions.

Board and slot read payloads exclude private board contact details, hosted-service identifiers, and booking data.

## Write Actions

Use an API key scoped to `sponsorboard.write` for:

- Creating boards.
- Updating board status and settings.
- Creating slots.
- Updating slot price, status, dimensions, and placement metadata.
- Creating sponsor bookings for available slots.
- Reading private sponsor booking requests for moderation dashboards.
- Updating sponsor booking status after human approval, payment, or fulfillment checks.
- Setting or changing optional UTC booking start and end times.
- Moving a booking to an available slot on the same board.

## Payments

SponsorBoard uses the Million Dollar Script core payments API. Agents should not call WooCommerce, EDD, Stripe, or any other payment system directly for SponsorBoard bookings. If paid checkout is needed, ensure the board has paid checkout enabled and let SponsorBoard start checkout through the active Million Dollar Script payment provider.

## Recommended Agent Rules

- Never publish a draft board unless the human administrator explicitly asks.
- Never mark a slot sold unless payment or an external fulfillment signal has already been verified.
- Supply `terms_accepted: true` only after the sponsor has explicitly accepted the terms and privacy notice.
- Treat the active Million Dollar Script payment provider as the source of truth for automated paid checkout.
- Prefer `reserved` for tentative sponsor commitments.
- Treat `manage_url` values from booking create responses like private edit links. Do not log or publish them.
- Use an explicit `Z` or numeric timezone offset for `starts_at` and `ends_at`. Treat `lifecycle_state` as the effective display state and the stored `status` as the workflow state.
- Do not create or update overlapping live windows for the same slot. Adjacent windows may share an end/start boundary.
- Do not request or expose private booking data with a read-only key. Booking reads require `sponsorboard.write` and write-level API authorization.
- Keep sponsor-facing claims factual and do not imply endorsement unless the administrator provides that language.
