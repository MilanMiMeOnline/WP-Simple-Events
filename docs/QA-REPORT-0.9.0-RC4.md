# 0.9.0 RC4 accessibility qualification

**Date:** 1 September 2026

**Scope:** shared visitor components, calendar/filter interaction, native event
fields, recurrence controls, no-JavaScript output and the supported editor output
boundary.

**Status:** complete. Local qualification and the complete hosted compatibility,
browser and strict Plugin Check matrix pass on the reviewed commit.

## Outcome

No unresolved WCAG A/AA automation finding, keyboard blocker, focus defect,
horizontal reflow defect or P1/P2 accessibility defect remains in the audited
plugin-owned surfaces. RC4 found and corrected two public CSS defects:

1. At 320 CSS pixels with enlarged WCAG text spacing, the FullCalendar toolbar's
   first control group could exceed the calendar component by eleven pixels.
2. Numbered event-list pagination links could expose targets smaller than the
   WCAG 2.2 24-by-24 CSS-pixel minimum.

Toolbar chunks and button groups now wrap inside the component, long titles may
wrap safely and pagination targets retain at least 24-by-24 CSS pixels. Both
changes are component-scoped, inherit theme typography and colors and preserve
saved block, Elementor, Divi, shortcode and CSS-variable contracts.

## Evidence model

The exact development-only `axe-core` 4.12.1 dependency runs WCAG 2.0, 2.1 and
2.2 A/AA rules inside plugin-owned roots. It is MPL-2.0 licensed, is never loaded
by WordPress and is absent from the production package. Automated rules are only
one layer:

- keyboard-only opening, choice removal, reset and focus restoration are asserted;
- focus indicators retain at least a two-pixel outline;
- visitor components reflow at 320 CSS pixels with line, letter, word and paragraph
  spacing overrides;
- forced-colors and reduced-motion modes retain structure, text and focus;
- category color remains decorative and retains a visible text label;
- native event fields and Gutenberg recurrence controls are audited while visible;
- no-JavaScript list, calendar and details fallbacks remain functional.

The public shortcode, Gutenberg, Elementor and Divi integrations reuse the same
production renderers covered here. Host editors own their surrounding shell;
their plugin controls and preview lifecycles retain the established focused
integration tests.

## Senior developer review

- The CSS change is limited to `.wpse-calendar` and `.wpse-events-pagination`.
- No PHP, REST, query, event visibility, persistence, saved-content or permission
  contract changes.
- Exact `axe-core` version and licence/removal cost are documented in ADR-097.
- The test-only classic-editor query flag exists only in the E2E fixture plugin,
  is excluded from every release archive and performs no mutation.
- The production package remains dependency-free at runtime beyond its existing
  local FullCalendar modules.

## Senior QA review

The first full run exposed a historical test-data assumption: fixed August 2026
events became past events on 1 September, while two Gutenberg assertions still
used the default upcoming period. The fixture and preview now explicitly request
all events, matching their presentation purpose. This correction then exposed
the real pagination target-size defect. No assertion was removed or weakened.

The final browser run starts from a fresh disposable WordPress site and passes all
35 journeys. Temporary editor events are created through authenticated WordPress
REST requests and permanently removed in `finally` cleanup. Fixture plugins and
their content exist only in the destroyed test environment.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| `composer validate --strict` | Pass |
| PHP coding standards and compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 776 tests / 3,199 assertions |
| Composer audit | Pass, zero advisories/abandoned packages |
| Frontend/Divi build and tooling contracts | Pass, 75/75 |
| ESLint and Stylelint | Pass, zero warnings |
| npm audit | Pass, zero vulnerabilities |
| Focused accessibility browser suite | Pass, 5/5 |
| Complete packaged browser matrix | Pass, 35/35 |
| Translation catalogue | Pass, current |
| WordPress 6.9 / PHP 8.2 packaged smoke | Pass |
| WordPress 7.1 / PHP 8.2 packaged smoke | Pass |
| Release verification and reproducibility | Pass, two byte-identical builds |
| Package SHA-256 | `692aee37dbca41972d20e0c57b4f0176d7eeac0565b33d1dd6de20430e0151f5` |

The staging archive still reports public version 0.7.0 because the feature-frozen
0.9.0 version bump belongs to RC8. It is not published from this checkpoint.

## Hosted acceptance evidence

[GitHub Actions run 33520990500](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33520990500)
completed successfully on reviewed commit `dedba243afe3e726d0578fb9fcfb5b09451b85fa`.
All eleven hosted jobs passed:

- translation catalogue;
- complete 35-journey browser matrix;
- WordPress 6.9 / PHP 8.2 packaged smoke;
- WordPress 7.1 / PHP 8.2 packaged smoke;
- historical upgrade matrix;
- JavaScript and CSS quality gates;
- PHP quality gates on PHP 8.2, 8.3, 8.4 and 8.5;
- reproducible release archive and strict official WordPress Plugin Check.

## Residual non-blocking risks

- Automated WCAG rules cannot prove complete conformance. A human VoiceOver or
  equivalent screen-reader spot check, browser zoom review and translated-content
  review remain appropriate during RC7 exploratory qualification.
- Host-editor shells and site themes can introduce their own accessibility
  defects outside plugin-owned markup and styling.
- FullCalendar 6.1.21 remains the frozen 1.0 integration; ADR-096 places a
  backwards-compatible FullCalendar 7 investigation after 1.0.

## Standards reviewed

- [WordPress accessibility coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/)
- [WordPress accessibility audits and testing](https://make.wordpress.org/accessibility/handbook/get-involved/audits-and-testing/)
- [Web Content Accessibility Guidelines 2.2](https://www.w3.org/TR/WCAG22/)
- [Understanding WCAG 2.2](https://www.w3.org/WAI/WCAG22/Understanding/)
