# Luxury Motors — design notes

## Visual surfaces (cards)

Cards are elevated surfaces: **radius + shadow, no outline border**. Buttons keep their borders.

Tokens in `css/tokens.css`:

- `--radius-cards` — every card-like panel (not buttons).
- `--shadow-cards` — light surfaces (white, velo, checkout).
- `--shadow-cards-dark` — dark surfaces (notte, grafite, hero, fleet).

Nested layout: a grouping wrap is its own card; inner panels (itinerary copy, receipt, fleet item, filter strip) are **independent** cards with a contrasting fill. Do not merge them into one bordered slab. Hairlines inside a card are OK; 1px chrome around the card is not. Depth is present at rest, not only on hover.

Current map:

- Checkout: `.checkout-trip`, `.checkout-main`, `.checkout-aside` (independent light cards; aside sticky)
- Confirmation: `.confirm-layout` (outer), `.confirm-itinerary-copy` + `.confirm-receipt` (inner, `--color-velo-di-luce`)
- Fleet: `.view-vehicle-fleet > .wrap`, `.banner`, `.fleet-refine`, `.card--fleet`

Agent rule: `.cursor/rules/luxury-motors-visual-surfaces.mdc`

## Checkout (`/reserve/{vehicle}`)

Inspiration (Refero, adapted — not cloned): TravelPerk “Review and pay” two-column review, TravelPerk booking overview (compact car image in the summary, not a hero), Uber Rent review (terms + total + primary action grouped).

### Layout

- Desktop: two columns (~2fr / 1fr). Main column: trip card, then customer form, then terms / marketing / **Confirm Booking**. Sidebar: **Price Summary**, sticky.
- Vehicle image: small, in the trip card. Never full-bleed.
- Pickup / drop-off: two-column pair (location + date — time).
- Mobile stack: trip → price → customer form (including consents and CTA). Do not shrink the desktop grid.

### Naming

- Sidebar heading: **Price Summary** (not “Payment Detail”).
- Footer: **Total payment**, “For N rented days”, **Daily price** = total ÷ days (effective daily, includes fees/taxes).
- Rental line uses vehicle `field_daily_price` as the **base** only (`USD 65.00 × day → $325.00`).

### Future groups

Price rows are grouped: booking, location, fee, insurance (empty), extra (empty), tax, surcharge. Insert Insurance / Additionals later without a layout rewrite.

### Legal

- Required: agree to Terms and Conditions (placeholder `/terms-and-conditions`).
- Optional, unchecked: promotional emails from **Luxury Motors**. Independent of terms.

## Confirmation (`/reserve/confirmed/{booking}`)

Not a Vehicle node and not `entity.node.canonical`. The `{booking}` parameter loads the booking entity for data only (same idea as `{vehicle}` on checkout).

Inspiration (Refero, adapted): [Onefinestay confirmation](https://refero.design/pages/37f3be25-059c-4422-8c04-6d33973bd857) — centered success copy, then a two-column itinerary (details + image); [Understory receipt](https://refero.design/pages/41572f71-5be5-47b1-86f0-aa31d37fd835) — line items then a booking-details card; [time2book](https://refero.design/pages/1d55c82f-97d0-469c-b036-433906e52d82) — “Booking confirmed” + compact total.

Layout: success header + confirmation number inside `.confirm-layout` (outer card) → independent `.confirm-itinerary-copy` card (pickup/drop-off, guest, total) beside the vehicle photo → independent `.confirm-receipt` (Price Summary) → Back to fleet. No hero/studio/vehicle blocks.

