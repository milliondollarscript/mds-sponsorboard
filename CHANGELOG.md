# Changelog

## 1.3.0

- Added a customer-safe public contract for owner-scoped SponsorBoard booking lists, details, creative updates, image replacement, history, and checkout.
- Added optional Advertiser Workspace integration without exposing signed manage tokens or duplicating SponsorBoard payment and lifecycle state.
- Reused SponsorBoard upload validation for both signed booking management and verified advertiser workspace updates.
- Made workspace checkout fall back to the active Million Dollar Script payment provider when a booking has not saved a provider yet.

## 1.2.0

- Added optional booking start and end controls using the configured WordPress site timezone.
- Enforced booking date windows in public rendering even when background processing is delayed.
- Added bounded, locked, idempotent lifecycle processing to activate due approved bookings, expire ended bookings, notify participants, refresh inventory, and synchronize hosted records.
- Prevented overlapping live booking windows for the same slot while allowing adjacent schedules to hand off at a shared boundary.
- Added lifecycle labels to booking administration and exposed effective lifecycle state through the REST API.
- Corrected active and expiring reporting so future and already-ended bookings are excluded, while preserving historical paid revenue and hosted payment state after expiry.
- Aligned private-data cleanup with booking windows so future approved creative is retained and ended creative is removed even when lifecycle processing is delayed.
- Validated REST schedule updates before inventory moves so rejected updates cannot leave partial changes.

## 1.1.1

- Prevent authenticated hosted-service requests from following redirects that could expose credentials to another origin.
- Use WordPress safe HTTP validation for remote hosted services and allow plain HTTP loopback URLs only in local or development environments.
- Reject service URLs containing embedded credentials, query strings, fragments, or unsafe private-network targets.

## 1.1.0

- Open configured SponsorBoard workspaces on Bookings while retaining Overview for global metrics and first-board setup.
- Organized administration into directly addressable Overview, Boards, Slots, Bookings, and Hosted service tabs while preserving board, filter, and pagination context.
- Restored the explicit new-board route so **Create board** opens a blank form instead of reselecting an existing board.
- Added atomic slot reservations so concurrent requests cannot book the same available inventory.
- Added required sponsorship consent, configurable terms and privacy links, contact notifications, per-slot hourly request limits, and private booking-data retention.
- Added searchable, paginated board and booking administration plus safe booking moves between available slots on the same board.
- Corrected payment callback handling for paid, processing, completed, pending, failed, cancelled, expired, denied, and refunded provider states.
- Hardened slot state recalculation so closing one booking cannot release inventory still owned by another live booking.
- Paginated privacy exports and erasure, retained approved public sponsorship content during private-data cleanup, and added complete cascade deletion for extension-owned board data.
- Restricted private booking API reads to write-level authorization, removed sensitive board and slot metadata from read payloads, and removed booking reads from LLM-safe actions.
- Added bounded persisted-data validation, HTTP(S)-only public URLs, invalid analytics-target rejection, accessible semantic notices, responsive admin filters, and private manage-page no-referrer protection.

## 1.0.0

- Added token-protected sponsor creative uploads for image and logo slots with media-library storage, file type checks, size limits, and slot dimension validation.
- Added creative moderation status, admin preview links, public rendering for approved uploaded creatives, and sponsor manage-page performance stats.
- Added aggregate impression/click tracking, CTR and revenue reporting, booking/event CSV exports, and WordPress privacy exporter/eraser callbacks.
- Hardened public creative rendering so remote creative URLs are retained for review but not embedded as public images.

## 0.1.0

- Initial SponsorBoard extension with local schema, admin UI, shortcode output, dashboard integration, sponsor request forms, signed manage links, booking moderation, booking REST endpoints, responsive frontend polish, and secure REST API routes.
