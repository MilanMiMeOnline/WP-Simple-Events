# QA report — 0.3.0 WP3 shared presentation control system

**Date:** 18 August 2026
**Scope:** roadmap UX-003, UX-004, UX-005 and the presentation subset of UX-008
**Release state:** unreleased development increment

## Intended behaviour

Elementor users must be able to solve routine card, image, filter, pagination,
details and calendar styling needs inside the widget editor. New controls must be
opt-in so an update does not overwrite theme presentation on existing pages. Event
grids must respond to the width of the component rather than only the browser
viewport, while browsers without container-query support retain a safe fallback.

## Risk review

- **Backward compatibility:** existing widget names, saved control identifiers,
  shortcode attributes, block names, HTML classes and native visual defaults are
  unchanged. New controls have no Elementor default value and therefore emit no
  inline override on existing widgets.
- **Theme ownership:** optional backgrounds, spacing and borders use undefined
  component variables until a builder value is selected. Typography still
  inherits from the active theme unless an existing typography control is used.
- **Specificity:** all selectors remain scoped below `.wpse-*` component roots.
  Container-query selectors intentionally repeat the component class once so they
  can override Elementor's generated column rule without `!important`.
- **Responsive behaviour:** modern browsers use the event component's inline
  width. An explicit `@supports not (container-type: inline-size)` branch retains
  the established viewport breakpoints for older browsers.
- **Security/privacy:** this increment adds presentation metadata only. It adds no
  endpoint, write action, public field, remote resource, cookie or personal-data
  processing and does not alter event visibility or query bounds.
- **Accessibility:** native links and buttons retain semantic markup and visible
  focus styles. New color controls include normal and interaction states rather
  than removing the existing focus treatment.

## Implemented control surface

- Event List / Grid: card background, border, radius and shadow; independent row
  and column gaps; content padding; image ratio; filter panel, fields and button;
  pagination panel, spacing, border and links.
- Event Calendar: component background and padding; today cell, list hover and
  event colors; normal and hover toolbar-button states; button border and radius;
  responsive mobile toolbar spacing.
- Event Details: component spacing; summary panel background, padding, border,
  radius and shadow; image ratio and radius; external-action background, text,
  padding, border and radius.
- Atomic widgets: Featured Image width, ratio, border and radius; External Event
  Action background, hover colors, padding, border and radius.

All of these controls target the shared frontend components. No Elementor-only
renderer or duplicate event-data path was introduced.

## Regression evidence

Unit and tooling coverage freezes:

1. the new Elementor control identifiers, responsive control types and selector
   mappings;
2. the absence of saved defaults on opt-in controls;
3. conditional filter and pagination sections and exact scoped targets;
4. shared Featured Image and External Action style-extension hooks;
5. consumption of every new CSS custom property;
6. plugin-scoped selectors, container-width breakpoints and the legacy fallback;
7. the deliberate higher-specificity container selector and absence of
   `!important`.

## Manual host-editor verification

The development package was installed on the disposable `simpleevents.local`
site with WordPress 7.0.4, PHP 8.3.30, Elementor Free 4.2.2 and Twenty
Twenty-Five 1.5.

- Event List / Grid exposed the new card and pagination groups. The filter styling
  group correctly remained hidden while visitor filters were disabled.
- Event Calendar exposed the separated component and interaction controls.
- Changing the Event List image ratio to `1 / 1` updated the preview and emitted
  the expected component variable. The page was closed without publishing.
- No browser-console errors occurred.
- At a 625-pixel component width, the first implementation incorrectly retained
  three columns because Elementor's generated selector won the cascade. The
  regression contract was strengthened and the selector corrected. The same live
  page then measured two columns at 625 pixels and one column at 420 pixels, while
  the browser viewport remained independently controllable.

No event, page or template was created. No Elementor change was saved. The local
site may retain the installed development plugin package, but it contains no test
content from this round. The production site was not changed.

## Automated checks

| Check | Result |
|---|---|
| `composer validate --strict` | Pass |
| `composer qa` | Pass — 280 tests, 1,134 assertions; PHPStan clean; no Composer advisories |
| `npm run qa` | Pass — build, 24 tooling tests, JS/CSS lint and npm audit |
| Translation catalogue freshness | Pass with pinned WP-CLI 2.12.0 |
| WordPress 6.9 / PHP 8.2 packaged smoke journey | Pass |
| WordPress 7.0.1 / PHP 8.2 packaged smoke journey | Pass |
| Packaged Playwright browser suite | Pass — 16 journeys |
| `npm run test:release` | Pass — verified and byte-for-byte reproducible archive |
| `git diff --check` | Pass before final documentation update |

The WP-CLI translation command emitted upstream deprecation notices when run by
the host's PHP 8.5 runtime. It completed successfully and confirmed a byte-current
catalogue; these notices do not originate from production plugin code.

## Senior developer review

- The implementation extends one presentation boundary instead of adding builder-
  specific markup or queries.
- Undefined optional variables preserve the existing visual contract, while the
  prior calendar button and event colors remain the fallback values.
- Responsive controls use Elementor's supported responsive API and group controls.
  A protected no-op extension method keeps atomic widget responsibilities narrow.
- Component queries remove the original viewport/container mismatch without
  JavaScript observation or an Elementor runtime dependency.
- Source assets and built assets are synchronized; the release archive remains
  deterministic.

## Senior QA review

- The old Taranartos CSS use cases can now be represented by widget controls: a
  white borderless event card and a white pagination panel with horizontal
  padding.
- Tests distinguish opt-in values from accidental new defaults, so an update-only
  visual regression is detectable.
- Real browser measurement found an issue that selector-text tests alone would
  have missed; the final rule was rechecked against actual Elementor output at two
  component widths.
- Public calendar, timezone, filter, delayed-feed, multiple-instance, Elementor
  initialization and Gutenberg journeys remain green in the packaged browser
  suite.
- Both the minimum and current supported WordPress targets passed the same bounded
  package journey with the minimum supported PHP version.

## Residual risk

The CSS fallback for browsers without container queries is protected structurally
and preserves the previous tested viewport rules, but was not manually exercised
in an obsolete browser engine. Elementor 4.2.2 received the live editor check;
the formal minimum/current Elementor compatibility inspector remains a WP6 release
qualification gate. Official WordPress Plugin Check likewise remains a CI gate for
the final versioned 0.3.0 commit rather than this unversioned work package.
