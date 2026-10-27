# FullCalendar 7 — local 1.1.0-beta.1 qualification

**Date:** 15 October 2026

**Status:** Local beta prepared for owner testing. Owner testing and explicit publication
approval are pending. No release, tag, merge, push or WordPress.org upload is
authorized by this report.

## Scope and compatibility

The calendar adapter now uses FullCalendar 7.1.2 with its classic theme and local
Temporal support. Month/list views, navigation, filters, local dates, status
labels, resolved colors and documented `--wpse-*` controls retain their existing
meaning. Event persistence, recurrence, REST schemas and saved block/builder
settings are unchanged. No data migration is introduced.

New local stylesheets supply the explicit v7 CSS. Elementor declares that asset
for previews; Divi's app window receives a combined component/calendar stylesheet.
The `.fc .fc-button` targets remain available for existing Elementor typography.
The v7 view selector uses accessible tabs. Upstream DOM classes remain internal.

## Developer review

- Kept the stable feed unchanged and adapted colors at the browser boundary.
  Hex colors are allowlisted and event text is inserted as text, not HTML.
- Kept bounded, same-origin pagination and public permission rules unchanged.
- Replaced the removed `updateSize()` API with v7's native container observation;
  retained the configured desktop/mobile view switch and listener cleanup.
- Retained WordPress's existing 12/24-hour and meridiem choices instead of v7's
  condensed range formatting. All-day labels remain distinct from timed segments.
- Preserved the error fallback with a plugin-owned hideable parent: v7's skeleton
  forces its own root to display. This avoids CSS priority overrides and retains
  the existing rule against authored `!important` declarations.
- Reviewed exact package pins and MIT notices for FullCalendar, Headless Calendar,
  Temporal and Preact. No premium package, CDN or browser-global polyfill is added.
- Production package validation now requires both generated calendar and Divi
  stylesheets. Generated JS includes the shared adapter in the Divi bundle.
- Calendar JS grows from 206,339 to 248,232 bytes (gzip: 62,823 to 74,903 bytes).
  The new explicit calendar CSS is 15,546 bytes (gzip: 3,505 bytes).
  These assets remain conditional on calendar usage; the larger combined
  Divi assets are confined to its editor app window.

## QA review and evidence

The migration initially exposed failures in color-marker styling, changed tab
roles, time formatting, hidden error-state rendering and Elementor's obsolete
resize call. Those failures drove fixes; no budget, test timeout, assertion of
user-visible behavior or accessibility rule was weakened. DOM selectors were
adapted to v7's supported hooks and its semantic list/tab structure.

- Composer strict validation, lint, PHPStan and 779 tests / 3,210 assertions pass.
- Frontend tooling: 96 tests pass; JS/CSS lint and dependency audits pass with
  zero reported vulnerabilities.
- Translation catalogue regenerated and checked.
- Browser qualification covers month/list navigation, 320px layout, colors,
  keyboard focus, automated WCAG checks, URL/history filters, multiple calendars,
  hidden/replaced builder roots, fallback, time formats and Gutenberg journeys.
  All 38 browser scenarios pass against the packaged beta on WordPress 7.1.3 /
  PHP 8.2, including recovery after a failed feed request.
- Additional browser cases use the isolated Divi stylesheet across the spring
  and autumn DST boundaries in Europe/Brussels and America/Los_Angeles. Both pass;
  desktop/month and mobile/list screenshots were visually inspected.
- Real Elementor 3.35.9 and 4.2.3 inspector runs pass on WordPress 7.1.3 / PHP
  8.2.31, including the new calendar stylesheet dependency.
- Earlier disposable Playground attempts failed during startup or returned
  WordPress database-connection errors. A fresh run passed the entire unchanged
  suite; retained environment logs contain no error. No assertions or environment
  startup limits were relaxed. The intermittent environment failure was not
  reproduced in the successful run; its underlying cause remains unconfirmed.
- Packaged WordPress 6.9 and 7.1.3 smoke/integration checks pass with PHP 8.2.
  The 6.9 pass preceded the final browser-only fallback/time-format refinements;
  the final 7.1.3 pass includes them.
- Performance passes all six unchanged budgets on WordPress 7.1.3 / PHP 8.2
  with 500 series and 5,000 projected occurrence rows; the 17 prerequisite
  coverage cases also pass. The calendar feed returns 100 records in 41,901
  bytes with 21 queries and a median measured PHP duration of 161.797 ms.
  These are local test-environment observations, not hosting guarantees.
- Archive contents, shipped PHP syntax, production autoloading and checksum
  verification pass; two consecutive final builds are byte-for-byte identical.
  The final build only adds the clarified Divi third-party notice to the runtime
  package exercised by the final browser, smoke and performance runs.

Local artifact: `dist/mime-simple-events-calendar-1.1.0-beta.1.zip`.

SHA-256: `4635585ac2016c9601d549c64cd5bcc56f55699e1231144d2a0fb278b1155253`.

## Owner test handoff and release boundary

Install the beta ZIP on a test copy of the site and verify:

1. Existing calendars in the actual theme/builder, on desktop and phone: month,
   list, navigation, filters, colors, typography and event links.
2. One-off, recurring, all-day, multi-day and cancelled/postponed events, including
   the site's date/time format.
3. Edit and reload an existing calendar widget/block without resetting its saved
   options; check the published preview as well as the editor preview.

The real licensed Divi Visual Builder has not been exercised in this migration.
Its existing adapter contracts and isolated CSS/browser scenarios are covered;
the actual builder/theme presentation remains an explicit owner-test item.
The full hosted PHP matrix, strict official Plugin Check and
historical-upgrade release gates remain required before stable publication.
Passing local beta checks is not release authorization.

## Maintenance follow-up — 26 October 2026

The original qualification and checksum above describe the 15 October artifact.
See [the local maintenance report](QA-REPORT-MAINTENANCE-2026-10-26.md) for the
subsequent development dependency/PHP patches, current test evidence and rebuilt
beta artifact. The FullCalendar runtime and owner approval boundary are unchanged.
