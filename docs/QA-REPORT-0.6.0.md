# QA report — 0.6.0 filters and calendar discoverability

**Status:** locally qualified release candidate; hosted release CI and final
authenticated builder-editor sampling remain mandatory before publication

**Reviewed:** 28 August 2026

**Candidate:** MiMe Simple Events and Calendar 0.6.0

**Release SHA-256:**
`9e68110570c5e0bacf51cb6995973cf653d15eb9f6728e7a98f1ed4064b3a258`

## Scope

This report closes the local FCR-7 release-qualification work for the Phase 5
filter and color release. It aggregates the red-green evidence in FCR-0 through
FCR-6 and qualifies one exact 0.6.0 staging tree and archive.

The candidate adds:

- translated plain-text event taxonomy archive headings;
- semantic category/tag checkbox filters;
- removable active choices plus scoped clear and restore-default actions;
- component-responsive disclosure and bounded option search;
- shared Gutenberg, Elementor and Divi content/design controls;
- optional category colors and deterministic event color choices;
- cache-primed one-off and recurring-series calendar colors;
- decorative filter swatches and an Auto/Show/Hide text-backed legend.

The release does not add filter dimensions, counts, arbitrary CSS, occurrence-
specific colors, maps, ticketing, telemetry or remote services. Existing events
and saved components retain their previous appearance until an editor assigns
color data or chooses a new presentation setting.

## Exact candidate and upgrade evidence

The public plugin header, `WPSE_VERSION`, WordPress.org stable tag, npm package
metadata and translation catalogue all identify version 0.6.0. The changelog and
readme describe the same implemented scope.

Two consecutive production builds created byte-for-byte identical archives. The
release verifier reopened the archive and checked its single rooted directory,
allowlisted production content, PHP syntax, authoritative Composer autoloader,
required licences and checksum binding. The final archive contains 356 files and
no tests, development tools, Node packages, environment files, repository data
or licensed Elementor/Divi source.

The exact `.release/mime-simple-events-calendar` staging tree replaced version
0.5.0 on the disposable `simpleevents.local` site without changing its WordPress
database. Existing one-off and recurring fixtures, exact occurrence routes,
cards, pagination, calendar and theme shell remained available after the upgrade.

## Automated qualification

The completed candidate passed:

- `composer validate --strict`;
- `composer qa`: WordPress coding standards, PHPStan over 301 files, 731 PHPUnit
  tests with 2,852 assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 56 tooling-contract tests,
  ESLint, Stylelint and npm audit with zero vulnerabilities;
- the production-only npm advisory audit with zero vulnerabilities;
- translation-template regeneration and catalogue verification;
- the complete packaged browser matrix: 27/27 Playwright journeys on WordPress
  7.1, including filters, touch/reduced motion/enlarged text, multiple calendars,
  failed/delayed feeds, Elementor lifecycle, Gutenberg, no-JavaScript and
  recurrence editing;
- the exact staged package on WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2;
- five packaged theme-shell fixtures covering classic, hybrid, full block and
  explicit PHP/block template overrides;
- archive content verification and two identical release builds with SHA-256
  `9e68110570c5e0bacf51cb6995973cf653d15eb9f6728e7a98f1ed4064b3a258`;
- `git diff --check` and focused release-tree secret/development-file inspection.

The host WP-CLI 2.12 process emitted deprecations from its own bundled packages
under PHP 8.5 during catalogue generation. Generation and verification passed;
none of those messages originated in the plugin or its production archive.

## Exploratory public UX evidence

The upgraded shortcode QA page was inspected at its normal component width and
at a 390-by-844 mobile viewport:

- legacy modifier-key multiple selects became labelled native checkboxes;
- the desktop form had no horizontal overflow and inherited the Divi theme type;
- the mobile component collapsed to one `Filters` disclosure and switched its
  calendar to the configured list view;
- selecting `QA Music` produced one namespaced GET value, three matching recurring
  occurrences, a visible active-filter navigation and a `Filters (1)` count;
- the active category was removable independently and both `Clear categories`
  and `Clear all` led to a bounded URL that preserved the configured period;
- clearing returned `scrollWidth === clientWidth` at 390 pixels;
- Escape from a control inside the open panel collapsed it and returned focus to
  the trigger;
- two filter/calendar instances remained independently named and paginated;
- cancelled and postponed fixtures retained visible status text;
- no browser warning or error was recorded during the upgrade journey.

This journey changed only page URL state. It created no event, category, tag,
page, template, user, cookie or persistent filter preference.

## Security and privacy review

- Request values remain instance-namespaced, bounded and allowlisted before they
  can influence public queries. Unknown, oversized and malformed inputs fail to
  broaden event eligibility.
- Public collection and calendar paths continue to require published,
  password-free canonical events. Draft, private and password-protected event
  metadata remains excluded.
- Term and event color storage uses exact capabilities, nonces, assigned-term
  checks and strict six-digit hexadecimal values. Deleted, corrupt or ambiguous
  sources fall back instead of choosing an incidental term.
- User-facing text, URLs, attributes and the single fixed event-color custom
  property are escaped for their output contexts. Labels and builder settings do
  not become markup or arbitrary CSS.
- Color preparation receives only the canonical IDs already selected by the
  bounded query layer and primes post, relationship and term metadata once. No
  query occurs inside the occurrence-formatting loop.
- The release adds no public write endpoint, visitor cookie, browser storage,
  telemetry, remote asset, external service call or new personal-data field.
- Composer and npm reported no known vulnerability; no secret, credential,
  licence key or licensed builder source is present in the production archive.

## Accessibility and presentation review

- Taxonomy groups use fieldsets, legends, native checkboxes and visible labels;
  apply, clear, restore and disclosure controls retain native link/button
  behaviour and visible focus.
- Active choices are exposed as a named navigation, removable links have complete
  accessible names and the compact trigger announces its selected count.
- Result/loading/empty/error changes retain live status semantics. The complete
  GET form and visible results remain usable when JavaScript is disabled.
- Component-width disclosure, touch targets, enlarged text, narrow reflow and
  reduced-motion behaviour have executable browser coverage.
- Swatches are decorative beside visible category names. Event title, time,
  status and category text remain present; color is never the only carrier.
- Black or white foreground is derived from the higher WCAG contrast against the
  normalized background. Ambiguous category colors use the established component
  fallback instead of creating misleading visual meaning.

## Builder and compatibility evidence

The frontend markup and queries remain provider-neutral. Gutenberg, Elementor
and Divi adapters map bounded saved attributes to the same immutable presentation
options and canonical shortcode renderers. Host absence remains inert.

FCR-4 contract coverage verifies saved 0.5.x defaults, conditional controls,
shared custom properties and shortcode/Gutenberg/Elementor/Divi parity. The
complete Gutenberg registration and preview path passed in the packaged browser
suite. Earlier licensed-host reports retain the qualified Elementor Pro 4.2.2/
core 4.2.3 and Divi 5.11.1 template, source, role, responsive, preset and cleanup
evidence on the same shared presentation boundary.

The public page labelled `WPSE Elementor QA Lab` on the disposable site was
correctly rejected as final Elementor evidence: inspection showed that its old
calendar HTML was embedded in a Divi Text module, not rendered by an active
Elementor widget. No claim in this report relies on that static fixture.

## Senior developer review

The release keeps one filter view model, one bounded URL-state builder, one pure
color resolver and one prepared presentation collection. Builders translate
settings but do not fork frontend markup, query rules or color precedence.
Recurrence uses the canonical event ID, so sparse occurrence content cannot
silently create a second color-storage model.

The version-only release edits do not change runtime logic. Public documentation
matches the implemented feature set and clearly preserves the plugin's local,
privacy-first scope. No compatibility identifier, shortcode attribute, block
name, Elementor widget name, Divi module name or established CSS class was
renamed.

## Senior QA review and remaining release gates

No local correctness, security, privacy, accessibility, performance or archive
blocker was found. The candidate is accepted for the hosted release pipeline,
not yet for public upload.

Two gates remain deliberately unclaimed:

1. The pinned official `wordpress/plugin-check-action` must pass in strict mode
   against this exact staging tree, together with hosted PHP 8.2, 8.3, 8.4 and
   8.5 jobs. The repository intentionally has no divergent local Plugin Check
   substitute.
2. One authenticated no-save sampling pass must reopen the current Event List and
   Event Calendar controls in Elementor Free/Pro and Divi 5 after the 0.6.0
   upgrade. It must confirm the new filter/color controls are discoverable and
   that existing saved widgets/modules still preview without mutation. No theme
   template assignment or production data may be saved for this check.

Publication, GitHub release and WordPress.org SVN upload remain conditional on
both items. After the release commit is pushed, the CI archive and checksum must
match this locally qualified artifact before distribution.
