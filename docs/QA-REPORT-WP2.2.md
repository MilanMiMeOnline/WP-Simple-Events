# QA report — WP2.2

**Date:** 2026-07-19
**Scope:** WordPress-aligned 12/24-hour calendar presentation (`WPSE-BL-004`)

## Delivered behaviour

- Calendar month and list views now inherit the active WordPress `time_format`, matching native event details and cards.
- `H/G` force a 24-hour cycle and `h/g` force a 12-hour cycle; `H/h` retain leading-zero intent and `i` controls minute display.
- Lowercase and uppercase meridiem tokens retain their presentation intent without hard-coded English labels.
- Empty or malformed formats receive a safe `H:i` calendar fallback.
- The event editor explains that native time controls may look browser-specific while saving the same canonical 24-hour value.
- No plugin-global or Elementor-specific override was introduced.

## Senior developer review

- Time presentation is isolated in `CalendarTimeFormat`; it returns a fixed allowlisted option shape and never receives event values.
- PHP escaped tokens are skipped, so literal `H/g/i/a` characters cannot accidentally select an hour cycle.
- FullCalendar receives explicit `h23` rather than generic `hour12: false`, avoiding the locale-dependent `24:xx` representation of midnight.
- Server-rendered output remains on WordPress `wp_date()` and therefore keeps WordPress localisation behaviour.
- Storage, REST/feed values, captured wall time, UTC indexes, structured data and event queries are untouched.

## Senior QA and security review

- Unit tests cover `H:i`, `G:i`, `h:i A`, `g:i a`, formats without minutes, escaped tokens and malformed fallback values.
- Native formatter regressions cover midnight and noon in both 24-hour and 12-hour WordPress formats.
- Chromium journeys inspect the server-supplied calendar contract and visible list output for `00:30–01:30`, `12:05–22:05`, `12:30 am–1:30 am` and `12:05 pm–10:05 pm`.
- The browser fixtures use a single explicit WordPress origin to prevent artificial `localhost`/`127.0.0.1` REST CORS races.
- No new visitor input, persistence path, capability, nonce, query or public-data surface was added.

## Automated evidence

| Gate | Result |
|---|---|
| Focused PHP unit suite | Pass — 16 tests / 27 assertions |
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 198 tests / 675 assertions, Composer audit |
| `npm run qa` | Pass — production build, 11 tooling tests, JavaScript/CSS lint and npm audit |
| `npm run i18n:check` | Pass |
| `npm run test:e2e` | Pass — 12 Chromium journeys against WordPress 7.0.1 and the exact staging package |
| Supported WordPress smoke matrix | Pass — WordPress 6.9 and 7.0.1 |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- Public timezone-label visibility remains WP3.2 and is independent from 12/24-hour notation.
- Optional presentation overrides are deferred to the future atomic date/time component; inheritance remains the stable default.
- Official WordPress Plugin Check remains the CI release gate and was not duplicated in this local work-package run.
