# Luxury Motors — design notes

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

Layout: success header + confirmation number → itinerary card (pickup/drop-off, guest, total, vehicle photo) → Price Summary → Back to fleet. No hero/studio/vehicle blocks.

