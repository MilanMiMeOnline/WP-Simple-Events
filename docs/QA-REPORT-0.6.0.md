# QA report — 0.6.0 filters and calendar discoverability

**Status:** fully qualified for publication

**Reviewed:** 28 August 2026

**Candidate:** MiMe Simple Events and Calendar 0.6.0

**Release SHA-256:**
`0b9660f2f017ced171183dffc99f9262b071c15b2454cce68be8d9eada4cb032`

## Scope

This report closes the FCR-7 release-qualification work for the Phase 5
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
- decorative resolved-color dots for compact timed month rows;
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
- `npm run qa`: deterministic production builds, 57 tooling-contract tests,
  ESLint, Stylelint and npm audit with zero vulnerabilities;
- the production-only npm advisory audit with zero vulnerabilities;
- translation-template regeneration and catalogue verification;
- the complete packaged browser matrix: 28/28 Playwright journeys on WordPress
  7.1, including filters, touch/reduced motion/enlarged text, multiple calendars,
  failed/delayed feeds, Elementor lifecycle, Gutenberg, no-JavaScript and
  recurrence editing;
- the exact staged package on WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2;
- five packaged theme-shell fixtures covering classic, hybrid, full block and
  explicit PHP/block template overrides;
- archive content verification and two identical release builds with SHA-256
  `0b9660f2f017ced171183dffc99f9262b071c15b2454cce68be8d9eada4cb032`;
- `git diff --check` and focused release-tree secret/development-file inspection.

Hosted GitHub Actions run
[`33176384315`](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33176384315)
independently passed on release commit
`8c57414f3b9b758b0ec51cdf4de9a20885b48838`. Its ten successful jobs covered:

- strict Composer and plugin QA on PHP 8.2, 8.3, 8.4 and 8.5;
- JavaScript/CSS/build contracts and translation-catalogue verification;
- the complete browser-regression suite;
- exact-package smoke tests on WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2;
- the pinned official WordPress Plugin Check action, whose exported report was
  `Success: Checks complete. No errors found.`;
- release archive creation and upload.

The hosted archive SHA-256 was
`0b9660f2f017ced171183dffc99f9262b071c15b2454cce68be8d9eada4cb032`,
byte-for-byte identical to both local release builds recorded above. The exported
official Plugin Check report states `Success: Checks complete. No errors found.`

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

The final authenticated builder sampling used the exact 0.6.0 plugin on the
disposable `simpleevents.local` site:

- Divi 5 loaded the existing Event Calendar and Event List/Grid modules and their
  saved previews without mutation. Both modules exposed the new visitor-filter
  content controls and the matching filter, color, spacing, radius, checkbox and
  result-status design controls. No Divi save action was used.
- Elementor Free 4.2.3 was activated temporarily. Its widget library exposed the
  complete MiMe event set. Event Calendar and Event List/Grid were placed on a
  disposable draft canvas; both rendered real event data and exposed the same
  filter layout, initial-panel, label, chip, result-status and design controls.
  No Publish or Save action was used.
- Elementor autosaved the disposable draft despite the editor being closed
  without an explicit save. That expected host behaviour was detected by the
  cleanup check; the draft was then permanently deleted. Elementor was restored
  to inactive, Divi remained the active theme and MiMe 0.6.0 remained active.

No test event, taxonomy term, page, template, widget, Divi module or builder
assignment survives the sampling pass. Elementor Pro requires no separate 0.6.0
runtime claim: Pro uses the same plugin widget registration and controls, while
the earlier licensed-host evidence already covers that shared boundary. This
release adds no Pro-only integration path.

## Senior developer review

The release keeps one filter view model, one bounded URL-state builder, one pure
color resolver and one prepared presentation collection. Builders translate
settings but do not fork frontend markup, query rules or color precedence.
Recurrence uses the canonical event ID, so sparse occurrence content cannot
silently create a second color-storage model.

The follow-up copies only a strictly validated six-digit hexadecimal feed color
to one fixed component-scoped custom property. A CSS pseudo-element renders that
value as a decorative dot on FullCalendar's compact timed month rows; fallback,
solid block and list treatments remain independent. Public documentation matches
the implemented feature set and clearly preserves the plugin's local,
privacy-first scope. No compatibility identifier, shortcode attribute, block
name, Elementor widget name, Divi module name or established CSS class was
renamed.

## Senior QA review and release decision

No correctness, security, privacy, accessibility, performance, compatibility,
Plugin Check or archive blocker was found. The decorative marker regression
passed against a real FullCalendar month row, and all repository, browser,
packaged WordPress and hosted gates passed against the exact candidate. The
package identified by the SHA-256 above is accepted for GitHub release and
WordPress.org SVN publication.
