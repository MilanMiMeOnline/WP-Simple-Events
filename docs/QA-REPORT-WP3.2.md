# QA report — WP3.2

**Date:** 2026-07-19
**Scope:** Event timezone visibility (`WPSE-BL-007`)

## Delivered behaviour

- Events Settings reports WordPress' authoritative site timezone and links administrators to General Settings instead of adding a competing timezone selector.
- The guidance explains that new events capture the current zone, existing events retain their saved zone and numeric offsets have no daylight-saving behaviour.
- A strictly sanitized global option controls public timezone visibility and is disabled by default.
- Enabled timed event details show the captured IANA identifier and UTC offset applicable at the event boundary. Fixed zones use `UTC±HH:MM`; ranges crossing an offset transition show both offsets.
- All-day events omit timezone context. Native details, the details shortcode and the composite Elementor Event Details widget share the same renderer and behaviour.
- Cards, calendars, feeds, REST metadata, canonical dates, UTC indexes and structured-data machine values are unchanged.

## Senior developer review

- `EventTimezoneDisplaySettings` owns the option key, strict boolean allowlist and backward-compatible default. Settings API registration is non-REST and uninstall cleanup uses the existing explicit allowlist.
- `EventDateFormatter` computes offsets from the validated event boundaries, never from the current date. Its optional argument and `EventDatePresentation` default preserve existing callers.
- The shared details renderer is the only consumer enabling the visible label, preventing the global toggle from changing card, feed, calendar or schema contracts.
- Public output escapes the complete label as text. The settings value, site-zone value and administrator link use their exact output-context escaping.
- Long IANA identifiers can wrap within the scoped event component without introducing theme-global styling.

## Senior QA and security review

- Unit coverage verifies disabled/enabled/malformed option values, IANA summer and winter offsets, European and North-American DST transitions, fixed half-hour offsets, long identifiers and all-day omission.
- Mapping coverage verifies new events capture the current WordPress fixed-offset zone while existing events retain their stored IANA zone after a site change.
- Settings coverage verifies IANA guidance, the fixed-offset DST warning and omission of the General Settings link without `manage_options`.
- Real-WordPress smoke coverage verifies the disabled default, nonce-protected persistence, enabled/disabled native public output and an unchanged offset-bearing JSON-LD start instant.
- The packaged plugin passes the same smoke journey on WordPress 6.9 and 7.0.1. Existing Chromium calendar journeys remain green.
- No database query, remote request, new dependency, editor write surface or public feed field was introduced.

## Automated evidence

| Gate | Result |
|---|---|
| Focused timezone regression suite | Pass — 28 PHP tests / 86 assertions |
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 216 tests / 732 assertions, Composer audit |
| `npm run qa` | Pass — production build, 11 tooling tests, JavaScript/CSS lint and npm audit |
| `npm run i18n:check` | Pass |
| Source WordPress integration smoke | Pass |
| `npm run test:e2e` | Pass — existing Chromium regression suite against the staging package |
| Supported WordPress packaged smoke matrix | Pass — WordPress 6.9 and 7.0.1 |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- Timezone visibility is global and presentation-only. A per-event timezone selector remains outside the agreed authoring contract.
- The setting affects the existing composite details output. Dedicated atomic Elementor/Gutenberg date-time components and their optional local overrides belong to WP4–WP6.
- Official WordPress Plugin Check remains the CI release gate; this checkout has no local `test:plugin-check` package script.
