# QA evidence — 0.6.0 category and event color domain

**Date:** 2026-08-28

**Scope:** FCR-5 bounded category colors, explicit series-level event color
intent, canonical persistence and deterministic provider-neutral resolution

## Intended behaviour

Event categories may own one optional strict six-digit background color. The
native category add/edit screen validates that value before a term is written,
saves or deletes it only behind the event-taxonomy capability and a plugin nonce,
and shows normalized text plus a swatch in the category list.

Each canonical event has four explicit, mutually exclusive color modes:

- automatic, represented by absent metadata and therefore migration-free;
- force the calendar or website component fallback;
- use one currently assigned category that has a valid color;
- use one custom event background color.

The event choice applies to the complete recurring series. It participates in
WordPress revisions and the allowlisted duplicate-event workflow; occurrence
rows and sparse occurrence overrides never own color. Inactive values are removed
when the editor changes mode.

A pure resolver applies the stored mode authoritatively. Automatic resolution
uses a category color only when all valid assigned category colors reduce to one
distinct value. It never selects the first taxonomy term by incidental order.
Removed assignments, corrupt values, invalid modes and ambiguous categories use
the component fallback. Every resolved background is normalized and receives
whichever of black or white has the greater WCAG contrast ratio.

FCR-5 deliberately does not change public calendar appearance. FCR-6 will connect
these bounded values to one-off and recurring calendar records, category swatches
and legends without duplicating resolution logic.

## Red-green and compatibility evidence

- Pure color tests cover strict normalization, case normalization, invalid CSS-
  like input, WCAG luminance and black/white contrast selection.
- Resolver tests cover custom, explicit category, automatic, forced fallback,
  absent, corrupt, removed, unassigned, invalid and ambiguous sources independent
  of category order.
- Persistence tests require mutually exclusive metadata, migration-free
  automatic mode, valid assigned categories, partial REST updates and cleanup of
  stale inactive values.
- Category-controller tests require exact hooks, accessible native controls,
  nonce/capability denial, pre-write validation, normalization, deletion and safe
  list-table output.
- Registration, REST, native-save, metadata schema, revisions, duplication,
  metabox and Gutenberg synchronization regressions cover the integration seams.
- The complete existing 27-scenario browser suite proves that calendar filters,
  no-JavaScript fallbacks, timezones, recurrence, Gutenberg, Elementor dynamic
  initialization and public components remain unchanged before FCR-6 opts into
  presentation colors.

## Security, privacy, accessibility and performance review

- The category write path checks `manage_wpse_event_terms` and a plugin-owned
  nonce before mutation. Invalid enabled colors fail before WordPress writes the
  term. REST event metadata remains behind the existing post-meta authorization.
- Modes are allowlisted, object IDs are positive bounded integers and colors are
  strict normalized `#RRGGBB` values. Arbitrary CSS, alpha, markup, URLs and
  executable strings cannot cross the storage or presentation boundary.
- Output is escaped for text, attributes and the one normalized inline swatch.
  The hexadecimal value is also printed as text so color is never the only
  information carrier.
- Invalid, deleted, unassigned and tampered sources fail to the component default;
  they cannot influence event eligibility, URLs, dates, recurrence or structured
  data.
- No public endpoint, query, cookie, browser storage, telemetry, personal-data
  field, remote request or third-party asset was added. Category lookups are
  limited to terms already assigned to the event in the editor.
- Foreground contrast is derived deterministically server-side. Editors cannot
  store an inaccessible arbitrary text color.
- Composer and npm audits reported no vulnerabilities.

## Automated qualification

The completed implementation passed:

- `composer validate --strict`;
- `composer qa`: PHPCS, PHPStan over 298 files, 726 PHPUnit tests with 2,830
  assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 55 tool-contract tests,
  ESLint, Stylelint and the high-severity npm audit;
- translation-template regeneration and catalogue verification;
- the complete 27-test WordPress browser matrix;
- packaged WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2 smoke matrices;
- two byte-for-byte identical release builds with SHA-256
  `46bc576337e74216d216ed35c6c091fe28413ea8d4895cb6c1b561eac04d0d28`;
- `git diff --check`.

The host WP-CLI 2.12 process reported deprecations from its own bundled
dependencies under PHP 8.5 while verifying translations. Verification completed
successfully and none originated in the plugin package.

## Senior developer review

One small domain layer owns normalization, stored intent, source identity,
contrast and deterministic resolution without WordPress globals. WordPress
adapters own only registration, authorization, canonical persistence and native
editor markup. This keeps FCR-6 from reimplementing category precedence in the
calendar feed, recurrence projections or builder hosts.

Automatic mode removes all optional metadata, so existing events need no
migration and preserve their current appearance until FCR-6 resolves a deliberately
configured category color. Explicit category persistence verifies both current
assignment and current color validity; the resolver repeats that fail-safe check
against its bounded prepared map. REST updates that do not carry color keys leave
the prior color intent untouched.

The review found no query-per-public-item path because public integration is not
part of this slice. The native editor lists only already-assigned colored
categories. A newly assigned category must therefore be saved once before it can
be selected explicitly; this deliberate bounded-query trade-off is documented in
the administrator workflow.

## Senior QA review and residual scope

The four modes are explicit and reversible. Missing or stale selections produce a
useful warning and a safe visual fallback rather than an arbitrary color. Native
labels, fieldsets, descriptions, textual swatches and conditional controls remain
keyboard- and screen-reader compatible without requiring JavaScript for saving.

FCR-6 still needs to prove prepared metadata loading, public one-off/recurring and
exact-occurrence integration, multi-day/calendar-list treatments, filter swatches,
legend auto/show/hide logic and no-JavaScript colored fallbacks. Official strict
Plugin Check remains the hosted CI gate for the eventual FCR-7 release commit; it
is not claimed for this unversioned local roadmap slice. The package intentionally
retains version 0.5.1 until 0.6.0 release qualification.
