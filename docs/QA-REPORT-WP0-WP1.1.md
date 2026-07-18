# QA report — WP0 and WP1.1

**Date:** 2026-07-18
**Scope:** Browser guardrails and calendar first-render lifecycle (`WPSE-BL-006`)

## Delivered behaviour

- The calendar canvas is measurable before FullCalendar performs its first layout.
- The server-rendered event fallback remains available until the first feed succeeds and remains visible after a feed failure.
- A scoped `ResizeObserver` updates FullCalendar after a hidden or resized host becomes measurable and disconnects after DOM removal.
- Browser fixtures cover empty and taxonomy-constrained calendars, date-boundary events, mixed captured time zones, event statuses and multiple instances.
- Smoke and browser suites use disposable WordPress Playground databases so interrupted runs cannot leak state.

## Senior developer review

- The runtime change is isolated to the existing calendar adapter and introduces no Elementor dependency or new public API.
- Stored event data, REST schemas, queries, permissions and shortcode attributes are unchanged.
- Progressive enhancement remains intact: server markup is still usable without JavaScript and after feed failure.
- The observer is component-scoped, reacts only to meaningful positive width changes and releases removed elements.
- Playwright is pinned, development-only, license-documented and excluded by the production release allowlist.

## Senior QA and security review

- The original defect was reproduced with a failing seven-column geometry assertion before the fix.
- Six browser journeys pass: first load/hard reload/resize, mobile initial view, delayed feed, failed feed fallback, multiple instances and hidden-host recovery.
- No new input, state-changing request, capability boundary or public data surface was added.
- Dependency audits report no known Composer advisories and no npm vulnerabilities at the configured severity.
- The packaged plugin contains the rebuilt calendar asset but excludes Playwright, fixtures and all other development files.

## Automated evidence

| Gate | Result |
|---|---|
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 173 tests / 608 assertions, Composer audit |
| `npm run qa` | Pass — build, 11 tooling tests, JS/CSS lint, npm audit |
| `composer test:integration` | Pass — real WordPress Playground smoke journey |
| `npm run test:e2e` | Pass — 6 Chromium journeys against the exact staging package |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |
| `git diff --check` | Pass |

## Residual scope

- Calendar button visual states (`WPSE-BL-001`) and taxonomy filter clarity/empty states (`WPSE-BL-002`) remain intentionally open for WP1.2 and WP1.3.
- Elementor editor-specific visual verification is still required in those control/style packages; hidden-container behaviour is covered host-independently at browser level.
- WordPress Plugin Check and the full supported-version/package matrix remain release-candidate gates rather than this development milestone's gate.
