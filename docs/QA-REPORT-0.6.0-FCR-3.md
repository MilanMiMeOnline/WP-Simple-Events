# QA evidence — 0.6.0 progressive filter interaction

**Date:** 2026-08-27

**Scope:** FCR-3 component-responsive disclosure, deterministic calendar
history, bounded option search and dynamic builder initialization

## Intended behaviour

The complete server-rendered GET filter remains visible and operable without
JavaScript. With enhancement available, a filter component at 599 CSS pixels or
less uses one collapsed trigger with its selected-taxonomy count. Wider
components keep the panel visible. Escape closes an open compact panel and
returns focus to the trigger.

Category and tag groups with more than 10 choices gain a local search field.
Checked choices stay visible while searching and the full server-rendered list
remains available without JavaScript. Calendar submissions remain namespaced and
shareable, add a normal browser-history entry and restore the matching controls
and bounded feed when visitors use Back or Forward. Dynamic Elementor and Divi
previews initialize through the same filter module.

Active-filter chips deliberately remain server-authoritative GET links. This
keeps removal consistent across list, calendar and archive hosts and prevents a
client-only label from becoming stale after a taxonomy change.

## Red-green evidence

- Focused unit tests first failed because no shared disclosure renderer or
  filter-script dependency existed. They now require escaped unique panel IDs,
  no-JavaScript-visible contents, normalized counts and the public Elementor
  dependencies.
- The first compact browser regression exposed that grid and flex declarations
  overrode the HTML `hidden` state for both the panel and searched options.
  Explicit component-scoped hidden selectors fixed both failures; the complete
  three-journey disclosure/history/instance-isolation group then passed.
- Browser-history assertions cover apply, a second changed selection, Back,
  Forward, reload and restore-default transitions without state leaking to
  another calendar.
- A dedicated touch context with reduced-motion preference, a 560-pixel builder
  column and 200% component text passes without horizontal panel overflow.

## Security, privacy and performance review

- The enhancement adds no endpoint, capability, mutation request, cookie,
  browser storage, analytics, remote asset or third-party request.
- Search only toggles the `hidden` property of already escaped server-rendered
  options. It never creates a query value or changes checkbox state.
- History restoration reads only the current calendar's configured namespaced
  keys. Unknown URL values cannot select a checkbox and the existing maximum-20
  server boundary remains authoritative.
- The public filter bundle is dependency-free and approximately 3.4 KB minified.
  It is enqueued only for filter-capable views and dynamically rendered
  list/calendar hosts.
- Resize observation is per component, idempotent and disconnects after its form
  leaves the document. No polling or global DOM mutation observer was added.

## Automated qualification

The completed implementation passed:

- `composer validate --strict`;
- `composer qa`: PHPCS, PHPStan, 695 PHPUnit tests with 2,640 assertions and the
  Composer advisory audit;
- `npm run qa`: deterministic builds, 54 tool-contract tests, ESLint, Stylelint
  and the high-severity dependency audit;
- the complete existing 24-test WordPress browser matrix plus focused touch,
  reduced-motion, enlarged-text and no-JavaScript journeys;
- the packaged WordPress smoke test;
- two byte-for-byte identical release builds with SHA-256
  `34c7f20d02832e05c408a45623fdfae6b21a94ea732935797d16bb0017d04794`;
- translation-template regeneration and `git diff --check`.

No high or critical dependency vulnerability was reported.

## Senior developer review

One small disclosure renderer owns the no-JavaScript markup boundary and one
dependency-free module owns all progressive filter behaviour. Lists, calendars,
the native archive, Elementor and Divi retain their existing public identities
and shared PHP renderers. Calendar history reuses the established namespaced URL
and selected-value functions rather than creating a second state model.

The implementation does not alter event eligibility, recurrence, taxonomy
queries or stored data. Component width rather than viewport width is the correct
boundary for builder columns, and provider-specific preview hooks only invoke the
same idempotent initializer.

## Senior QA review and residual scope

Keyboard, touch, Escape/focus return, multiple instances, Back/Forward, reload,
selected-option preservation, enlarged text, narrow columns, reduced motion and
the no-JavaScript baseline have executable coverage. Result loading, empty and
error announcements continue through the existing live calendar status.

FCR-3 intentionally does not add builder-facing design controls or event/category
colors. Those remain FCR-4 and FCR-5/6 respectively. Physical assistive-
technology and device sampling remains part of the final FCR-7 release
qualification rather than a substitute for these automated contracts.
