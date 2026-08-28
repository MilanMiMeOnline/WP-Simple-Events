# QA evidence — 0.6.0 builder filter parity

**Date:** 2026-08-28

**Scope:** FCR-4 bounded filter content/style controls and shared shortcode,
Gutenberg, Elementor and Divi presentation contracts

## Intended behaviour

Event lists and calendars keep one semantic, no-JavaScript-capable GET filter
form. Authors can independently show or hide the category and tag groups,
choose an automatic, horizontal or stacked layout, choose automatic, initially
open or initially closed disclosure, and show or hide active chips and the
visual result status. Bounded labels can be customized without changing control
semantics or accessible relationships.

Gutenberg, Elementor and Divi expose the same component-scoped style surface for
the container, panel, trigger, fields, option spacing, checkboxes, chips,
actions and result status. The hosts map to shared CSS custom properties and the
same PHP renderers. Existing 0.5.x content receives the original defaults until
an author explicitly chooses a new value. Shortcodes remain theme-inheriting and
do not accept arbitrary CSS.

When one taxonomy group is intentionally hidden, its configured query constraint
continues to restrict results but visitor URL values cannot change it. This keeps
presentation choices separate from public eligibility rules.

## Red-green and compatibility evidence

- New pure normalization tests require allowlisted presentation choices,
  Unicode-safe bounded labels, strict six-digit colors and bounded pixel values.
- Attribute and rendering regressions require hidden groups to remain fixed
  query constraints, custom labels to escape correctly, disclosure state to be
  deterministic and optional chips/result text to disappear without changing
  event eligibility.
- Gutenberg, Elementor and Divi contract tests require every host to map the same
  settings to the same renderer and CSS variables. Existing provider setting IDs
  remain available for saved-content compatibility.
- A dedicated browser fixture exercises stacked layout, forced-closed and
  forced-open disclosure, hidden tag controls, hidden chips, hidden visual
  results and custom labels at component width.
- The complete 27-scenario browser matrix covers no-JavaScript submission,
  responsive disclosure, multiple independent instances, browser history,
  calendar failure states, Gutenberg serialization and dynamic Elementor
  initialization.

The browser harness itself exposed two infrastructure boundaries during final
qualification. `wp-env` can announce a Playground before both mounted plugins
finish activation, so fixture seeding now uses one bounded readiness retry.
Playwright's full clock emulation also removed a Navigation Timing entry required
by WordPress 7.1; the harness now fixes only `Date`, leaving the native
performance API intact. Browser Back/Forward assertions separately prove URL/UI
state because WordPress 7.1 may restore a history entry in a new document and
therefore reset an unrelated calendar month.

## Security, privacy and performance review

- FCR-4 adds no production endpoint, privileged action, database mutation,
  cookie, browser storage, analytics, remote asset or third-party request.
- Every choice is normalized through an allowlist. Labels are plain text capped
  at 80 Unicode characters. Colors must be normalized six-digit hex values and
  numeric dimensions are integers clamped to documented ranges.
- Output is escaped at its HTML, attribute or inline-style boundary. Builder
  values can only populate known CSS custom properties; arbitrary declarations,
  selectors, URLs and executable values are not accepted.
- Hidden taxonomy controls ignore visitor URL values while retaining their
  author-configured fixed constraints. Public queries remain bounded and still
  expose only eligible published events.
- The shared filter bundle remains dependency-free and approximately 3.5 KB
  minified. No additional frontend query, observer or global DOM scan was added.
- Composer and npm audits reported no security vulnerabilities. The
  unauthenticated fixture seed action exists only in the isolated E2E fixture
  plugin under `tests/` and is excluded from the production archive.

## Automated qualification

The completed implementation passed:

- `composer validate --strict`;
- `composer qa`: PHPCS, PHPStan over 290 files, 703 PHPUnit tests with 2,703
  assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 54 tool-contract tests,
  ESLint, Stylelint and the high-severity npm audit;
- the complete 27-test WordPress browser matrix;
- packaged WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2 smoke matrices;
- translation-template regeneration and catalogue verification;
- two byte-for-byte identical release builds with SHA-256
  `07084974c07e97c7a70913073b38ce78069cec023eef1eceffefa7ab805e9d46`;
- `git diff --check`.

The host WP-CLI 2.12 process reported deprecations from its own bundled
dependencies under PHP 8.5 while regenerating translations. Generation and
verification completed successfully; none originated in the plugin package.

## Senior developer review

One immutable presentation value object and one strict style mapper own the
provider-neutral boundaries. The shortcode renderers remain the canonical
frontend path; Gutenberg, Elementor and Divi translate only their bounded host
settings. This prevents markup drift and keeps optional integrations inert when
their host is absent.

The review caught one inheritance error before qualification: Elementor placed
status custom properties on the filter form while the status node is its
sibling. Variables are now scoped to the widget wrapper, while direct borders
remain targeted at their exact elements. No stored event schema, recurrence
model, REST eligibility rule or taxonomy query changed.

## Senior QA review and residual scope

Keyboard disclosure, Escape/focus return, touch sizing, 200% component text,
narrow/wide containers, reduced motion, hidden groups, custom labels, independent
instances, Back/Forward, no-JavaScript forms and empty/loading/error states have
executable coverage. Existing saved-content defaults and optional-host absence
are contract tested.

FCR-4 intentionally does not add category/event colors or calendar legends;
those remain FCR-5 and FCR-6. Final real-editor visual sampling in Gutenberg,
Elementor and Divi, physical assistive-technology sampling and official Plugin
Check against the eventual 0.6.0 package remain part of FCR-7 release
qualification. The current archive still carries the unreleased 0.5.1 version
by design; no public release is being made from this roadmap slice.
