# QA report — WP2.1

**Date:** 2026-07-19  
**Scope:** Captured wall-time calendar contract and cross-timezone date placement (`WPSE-BL-003`)

## Delivered behaviour

- Calendar month and list views preserve each event's saved local date and clock time instead of converting it to the visitor browser zone.
- Timed feed records expose floating local `start`/`end` values for UI placement and retain the captured timezone plus offset-bearing machine instants as explicit metadata.
- All-day events remain date-only and convert their stored inclusive end to FullCalendar's exclusive end.
- Calendar REST windows are day-aligned wall-time ranges. Bounded local metadata queries keep mixed timezones and events at `-14:00`/`+14:00` inside the correct visible window with truthful pagination.
- Existing UTC storage, chronological list behaviour, active/past classification and structured data remain unchanged.

## Senior developer review

- ADR-024 records the product and transport contract before the runtime change.
- The formatter revalidates canonical local values, timezone and derived UTC indexes together; inconsistent stored records are omitted rather than partially trusted.
- Calendar queries use WordPress meta-query APIs with two bounded `CHAR` comparisons and retain existing public status, password, taxonomy and pagination restrictions.
- A single global calendar timezone was deliberately avoided because it cannot represent mixed captured event zones.
- FullCalendar's local mode is explicit and receives floating values; no browser timezone is used to choose an event's visible day.

## Senior QA and security review

- Pure tests cover positive, negative and fractional fixed offsets, both supported `±14:00` extremes, near-midnight events, genuine overnight and multi-day events, all-day exclusive ends and European/North American DST transitions.
- Window tests reject missing timezones, non-midnight boundaries, invalid dates, empty/reversed ranges, ranges above 400 days and offsets outside the supported bounds.
- Controlled Chromium contexts reproduce the original UTC-to-Brussels risk and repeat the journey in `America/Los_Angeles`.
- Browser assertions verify local dates in month view, list-view grouping, explicit feed metadata, extreme-offset inclusion and parity with native single-event machine boundaries.
- During list-view parity QA, the custom status renderer was found to replace FullCalendar's semantic event link with a span. List events now retain a real focusable link, protected by the same browser journey.
- The public response adds no private content, address, capability or write surface. Request bounds, result limits and permission rules remain intact.

## Automated evidence

| Gate | Result |
|---|---|
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 190 tests / 665 assertions, Composer audit |
| `npm run qa` | Pass — production build, 11 tooling tests, JavaScript/CSS lint and npm audit |
| `npm run i18n:check` | Pass |
| `npm run test:e2e` | Pass — 10 Chromium journeys against WordPress 7.0.1 and the exact staging package |
| Supported WordPress smoke matrix | Pass — WordPress 6.9 and 7.0.1 |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- WP2.2 will align FullCalendar's visible 12/24-hour notation with the WordPress `time_format` option. This package intentionally does not add a duplicate time-format setting.
- Displaying a timezone label on public event pages remains WP3.2; the feed metadata added here supplies its authoritative source without enabling presentation prematurely.
- Official WordPress Plugin Check remains the CI release gate and was not duplicated in this local package run.
