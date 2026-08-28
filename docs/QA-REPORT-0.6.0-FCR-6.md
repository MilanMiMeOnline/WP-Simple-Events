# QA evidence — 0.6.0 calendar color integration

**Date:** 2026-08-28

**Scope:** FCR-6 prepared event/category colors, one-off and recurring calendar
records, no-JavaScript/list accents, filter swatches, category legends and
shortcode/Gutenberg/Elementor/Divi setting parity

## Intended behaviour

One bounded collection prepares presentation colors for the canonical event IDs
already present in a list or calendar response. It deduplicates those IDs, primes
post, event-category relationship and term metadata caches, and delegates every
precedence decision to the FCR-5 resolver. It deliberately does not query events,
change eligibility or select a component fallback.

Only explicit event/category resolutions enter calendar feed records through
FullCalendar's standard background, border and text color fields. The final
formatter accepts only normalized six-digit hexadecimal values and derives the
black/white foreground again before output. Missing, corrupt, ambiguous and
forced-component values omit these feed fields so the consuming shortcode or
builder retains ownership of its existing fallback.

Every projected occurrence uses its canonical series event ID. Sparse occurrence
content overrides, exact routes, all-day records and multi-day segments therefore
share one series color without adding occurrence-owned presentation storage.
Server-rendered cards use that same resolved value as an accent; interactive list
rows apply an equivalent scoped accent after validating the feed color again.

Visible category choices may show decorative swatches beside their text. A
calendar legend has three allowlisted states:

- Auto hides a duplicate legend when visible category filters explain colors;
- Show forces the bounded text-backed legend;
- Hide suppresses it.

The setting is available as the `legend` shortcode attribute and through the
Event Calendar controls in Gutenberg, Elementor and Divi. Existing saved
components default to Auto and unchanged events retain their pre-0.6 component
appearance until an editor assigns category/event color data.

## Red-green and compatibility evidence

- Collection tests prove canonical ID deduplication, one cache-prime call per
  cache family, automatic category resolution and the deliberate omission of a
  component fallback from prepared feed data.
- Formatter/feed tests prove standard color fields, normalized contrast,
  one-off/occurrence parity and fail-closed output for invalid presentation.
- Renderer tests prove a safe scoped CSS custom property, visible event text and
  no unvalidated inline style path.
- Legend tests prove Auto duplicate avoidance, forced rendering, bounded colored
  terms, visible category names and decorative swatches.
- Shortcode and host-adapter tests prove `auto`, `show` and `hide` normalization
  and parity across Gutenberg, Elementor and Divi.
- The JavaScript contract test proves list-view accents accept exact hexadecimal
  values only and never replace visible event content.
- The Gutenberg end-to-end regression caught a misplaced legend control in the
  List inspector; the control was moved to Calendar and the same browser journey
  now passes.

## Security, privacy, accessibility and performance review

- Stored metadata, block attributes, widget settings, shortcode values and feed
  data remain untrusted at every adapter boundary. Enumerated modes are
  allowlisted; IDs are positive/bounded; colors require exact `#RRGGBB` syntax.
- Public output escapes text, attributes and URLs for context. The sole inline
  event-color declaration is assembled only after strict normalization and uses
  a fixed custom-property name. No arbitrary CSS, alpha, URL or markup can enter
  it.
- The collection does not broaden public visibility. Feed/list queries continue
  to own published/password-free eligibility, while the color layer receives
  only the already selected canonical IDs.
- Cache priming is performed once for at most 500 deduplicated event IDs and at
  most the resulting assigned category IDs. Recurring rows share one canonical
  lookup. No database query is added inside the occurrence-formatting loop.
- Swatches are decorative and adjacent to visible category names. Event titles,
  times, category names and exceptional statuses remain present, so color is
  never the only information carrier. Derived foregrounds use the higher of
  black/white WCAG contrast.
- The legend is a labelled semantic aside/list and omits uncolored categories.
  Auto suppresses redundant content without hiding the visible filter labels
  that explain the colors.
- No cookie, browser storage, telemetry, visitor identifier, remote service,
  remote asset or personal-data field was introduced. Dependency audits reported
  no vulnerabilities.

## Automated qualification

The completed implementation passed:

- `composer validate --strict`;
- `composer qa`: PHPCS, PHPStan over the production/tests surface, 731 PHPUnit
  tests with 2,852 assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 56 tool-contract tests,
  ESLint, Stylelint and the high-severity npm audit;
- translation-template regeneration and catalogue verification;
- the complete WordPress browser suite, plus the focused Gutenberg legend
  placement regression after its red-green correction;
- the exact staged package on WordPress 6.9/PHP 8.2 smoke qualification and the
  complete current-WordPress package in the browser matrix;
- release-package syntax, autoloader, content and two byte-for-byte identical
  builds with SHA-256
  `410d38841accdecf27d0847de332d845230f64f9f65e685cc4953da3580bf169`;
- `git diff --check`.

Final FCR-7 qualification will rerun the complete packaged WordPress 6.9/current,
PHP 8.2-current, official Plugin Check, builder-host and release publication
matrix from the eventual 0.6.0 version commit. This FCR-6 slice intentionally
retains plugin version 0.5.1.

## Senior developer review

The new collection is an adapter around the existing pure resolver, not a second
precedence implementation. Calendar feed, shortcode fallback and native lists
receive presentation values explicitly; none reads color metadata in its item
formatter. Canonical event keys keep recurrence storage and sparse overrides out
of the color domain. Component fallbacks remain host-owned, preventing a REST
record from accidentally freezing Elementor, Divi or theme styling.

The public formatter repeats strict normalization at its last trust boundary.
This is intentional defence in depth rather than competing domain logic. Stable
FullCalendar fields and one scoped custom property avoid custom client-side data
protocols. Constructors retain defaults and new adapter parameters are appended,
preserving existing integrations and unit-test seams.

## Senior QA review and residual scope

The implementation covers zero/one/ambiguous categories, custom and forced
fallback modes, one-off and recurring records, no-JavaScript output, list/month
presentation, visible filters and all three legend choices. The editor placement
failure demonstrates that the browser gate tests discoverability rather than
only serialized attributes.

FCR-7 still owns real-host visual qualification for Elementor and Divi, classic/
hybrid/block-theme screenshots, keyboard/screen-reader/reflow exploratory checks,
realistic query-count budgets, corrupt upgrade fixtures, official Plugin Check
and the complete supported compatibility/reproducibility matrix. No FCR-6 defect
or undocumented public contract remains open before that release phase.
