# Test strategy

## Objectives

Testing must detect incorrect event visibility, date ordering, permission leaks and regressions before release. Confidence comes from several focused layers rather than a large number of shallow tests.

## Layers

### Static gates

PHP syntax and style, PHPStan, JavaScript/style linting, strict package validation, dependency audits and WordPress Plugin Check run automatically. New warnings are treated as failures.

The release contract additionally rejects unexpected archive roots, paths, file types, hidden/development files and missing runtime assets. The verifier checks the SHA-256 record, symbolic links, every shipped PHP file and the production Composer autoloader. Two consecutive builds must be byte-for-byte identical.

The security contract inventories every custom REST permission callback, confines
direct database access to the reviewed occurrence adapters, rejects unsafe PHP
execution/deserialization/network primitives and rejects visitor storage or
tracking in authored browser code. It also verifies that the only runtime remote
URL builders are the explicit calendar-provider actions and schema vocabulary.

### Unit tests

Fast tests cover logic that does not require a running WordPress installation: date ranges, display status, validation, query criteria, formatter behaviour and migrations. Boundary-value and data-provider tests are preferred.

### WordPress integration tests

These verify registrations, capabilities, metadata authorization, persistence, queries, REST responses, shortcode output, templates and cache invalidation against real WordPress. Each test creates and cleans up its own data.

### End-to-end tests

The Playground smoke journey covers activation, creating, editing, publishing, filtering and opening events; admin actions and forged nonces; REST validation and visibility; settings and maintenance; list, details and calendar shortcodes; archive routing; structured data; and graceful native behaviour without Elementor or WooCommerce. Release candidates run the packaged staging directory on WordPress 6.9 and 7.1 with PHP 8.2. The PHP quality matrix additionally covers every supported minor from 8.2 through 8.5.

A separate Playwright suite exercises browser-only layout, responsive, keyboard and interaction behaviour against a disposable Playground site. It uses the exact pinned `@playwright/test` development dependency and a separately installed Chromium build. Assertions target stable component semantics and geometry rather than theme-wide screenshots. Playwright and its browser binaries are never shipped in the plugin archive.

The accessibility browser layer uses exact development-only `axe-core` rules for
WCAG 2.0, 2.1 and 2.2 A/AA regressions inside plugin-owned component roots. It
also exercises keyboard-only filter disclosure and focus restoration, visible
focus, 320 CSS-pixel reflow with WCAG text-spacing overrides, forced-colors and
reduced-motion presentation. Native event fields are audited in WordPress' classic
metabox host and the recurrence panel in Gutenberg; public renderers cover the
shared shortcode, Gutenberg, Elementor and Divi output. Automated results never
replace the manual screen-reader, zoom, language, theme and host-editor checks in
the release checklist. `axe-core`, its source and browser tooling are excluded
from production packages.

The calendar contract is protected by seven deterministic journeys: first-load/reload/resized seven-column geometry and controls, the configured mobile list view, readable normal/hover/pressed/focus/selected/disabled button states including forced-colors, a delayed REST feed, a failed feed with retained fallback, two independent calendar instances and recovery after an initially hidden host becomes visible. The disposable Playground runtime and database are removed before and after every suite to prevent state leakage between runs.

The historical upgrade matrix downloads every public canonical package from
0.2.3 onward, verifies its pinned SHA-256 value and upgrades representative
content in a disposable WordPress 6.9/PHP 8.3 site. It proves canonical data and
saved builder content remain intact, current derived storage becomes complete,
scheduled work is unique, a missing occurrence table self-repairs, deactivation
and reactivation retain data, default uninstall removes only executable jobs and
explicit destructive uninstall respects its allowlist. The exact scope and
manual pre-canonical handoff are normative in `UPGRADE-CONTRACT.md`.

### Performance regressions

The performance suite builds the exact release staging package and installs it
with a test-only fixture plugin in a fresh WordPress 7.1/PHP 8.2 Playground. Its
500 public series, 5,000 recurring occurrence rows, 20 categories and 40 tags
exercise maximum-size occurrence, list, calendar and builder pages plus the
largest supported recurrence generation horizon. The fixture validates its own
publication, generation, coverage and public-query state before timing begins.

Hard gates cover query counts, result bounds and serialized payload size; median
PHP time over five requests is a deliberately broad WebAssembly-runtime backstop.
The occurrence repository must remain exactly two queries. Shared collection
presentation primes canonical post, metadata and taxonomy caches once per bounded
page so list, archive and calendar output cannot regress to query-per-item growth.
The normative dataset, budgets and interpretation rules are in
`PERFORMANCE-BUDGETS.md`. The fixture and all generated content are excluded from
the release archive and destroyed after every run.

### Manual exploratory QA

Manual checks focus on UX, theme compatibility, responsive layouts, localization, accessibility and failure recovery. They complement rather than replace automated regression coverage.

## Required scenario matrix

- Upcoming, ongoing and past events.
- Timed, all-day, same-day and multi-day events.
- Start/end equality and invalid reversed ranges.
- Site time zones with positive and negative offsets and daylight-saving transitions.
- Draft, private, password-protected, trashed and published events.
- Users with no access, edit-own, edit-others and administrator capabilities.
- No matching events, one event, pagination boundaries and high-volume bounded queries.
- Missing optional dependencies and supported versions of WordPress/PHP.
- Elementor absent, Elementor 3.35.x and the currently tested Elementor 4.x release.
- Invalid, missing, duplicated and forged input.

## Release evidence

A release candidate needs green CI, dependency audit, strict Plugin Check against the staging package, clean install/activation/deactivation tests, current translations, a reproducible verified archive, upgrade testing when applicable, supported-version matrix results, bounded performance budgets and a completed QA checklist. Known limitations must be explicit.
