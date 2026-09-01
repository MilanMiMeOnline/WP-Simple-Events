# 0.9.0 RC7 exploratory qualification

**Date:** 1 September 2026

**Scope:** destructive local exploratory testing, reversible production
validation, Gutenberg recurrence, Elementor presentation controls, Divi 5 state,
multiple component instances, mobile calendar reflow and release-candidate
maintenance compatibility.

**Status:** complete locally. No unresolved P1/P2 product, security, privacy or
documentation defect remains from this exploratory round. Hosted acceptance is
owned by the next reviewed commit and RC8 publication qualification.

## Outcome

The feature-frozen product is ready to enter final 0.9.0 qualification. The round
found three real defects that were small enough to fix without changing a public
schema, saved-content contract or product scope:

1. Rendering the same event in two independent list/grid components could repeat
   the card heading ID, making `aria-labelledby` ambiguous.
2. FullCalendar's mobile list table could exceed a 320 CSS-pixel component when
   date/time text and a long title competed for intrinsic width.
3. The recurrence sidebar still read `PluginDocumentSettingPanel` from the
   deprecated `wp.editPost` compatibility export instead of `wp.editor`.

Collection-scoped normalized ID segments now keep every card reference unique.
The mobile list table uses a bounded fixed layout and permits time/title wrapping.
The Gutenberg asset now declares `wp-editor` and reads the documented editor
slot-fill export. Each defect has a regression that failed against the previous
implementation and passes against the candidate.

## Exploratory journeys

### Disposable local site

The authenticated `simpleevents.local` site was used for editor and host
inspection. This environment deliberately allowed temporary theme and plugin
activation changes, but the round did not save an event, page or builder layout.

- The weekly recurrence panel exposed the complete-series scope first, separate
  `Edit one occurrence…` and `Change this and following…` actions, bounded repeat
  and end controls, a plain-language summary and an explicit preview step.
- The ordinary metabox correctly explained recurrence ownership while retaining
  venue, status, address, external actions and series-level color controls.
- Elementor's Event Calendar visitor-filter controls exposed independent panel,
  trigger, option, checkbox, chip, action and status styling. Event List / Grid
  exposed responsive layout, content visibility, image, card, border, typography,
  button and pagination controls. The formerly required white borderless card and
  padded white pagination treatment can be authored without page-level CSS.
- The atomic and composite widgets remained separately placeable; no component
  or event detail was forced into the page layout.
- Divi was restored as the active theme and the temporarily enabled Elementor
  plugin was deactivated after inspection.
- Event counts remained `All 7 / Published 7 / Trash 1`; page counts remained
  `All 6 / Published 5 / Draft 1 / Trash 1`. No test content or assignment was
  created, updated or deleted.

### Production site

Production validation on `taranartos.be` remained read-only and non-destructive.
The public events surface retained the site header/footer, builder-owned page
layout and expected event output. No event, page, Elementor Theme Builder
template, setting or plugin state was changed. Destructive recurrence and builder
save journeys were intentionally confined to disposable automation and prior
qualified host tests.

## Senior developer review

- The DOM identity fix derives its new scope from the already unique, normalized
  collection results ID and keeps the existing single-card ID shape when no scope
  is supplied.
- Both the collection scope and occurrence identity are lowercased, allowlisted
  and bounded before entering an HTML ID. Output continues to be escaped at the
  renderer boundary.
- The calendar fix is limited to `.wpse-calendar` inside the existing mobile
  breakpoint. It changes no FullCalendar data, dates, links or event eligibility.
- `wp.editor.PluginDocumentSettingPanel` and the matching `wp-editor` script
  dependency follow the current WordPress package documentation and remain
  available throughout the supported WordPress 6.9-current matrix.
- No REST route, permission, nonce, persistence, query, metadata, occurrence,
  shortcode, block, Elementor or Divi saved schema changed.
- No production dependency, remote request, telemetry, cookie, browser storage or
  personal-data path was added.

## Senior QA review

- A unit regression renders the same post through two list instances and verifies
  unique matching heading IDs and `aria-labelledby` references.
- Existing occurrence-card coverage now verifies the combined collection and
  occurrence identity without weakening occurrence URL assertions.
- The mobile browser regression now uses a 320 CSS-pixel viewport, navigates to a
  populated list month and asserts both the calendar and document have no
  horizontal overflow.
- An executable source contract rejects both `wp.editPost` and `wp-edit-post` for
  the recurrence adapter and requires their current replacements.
- The complete browser suite validates visitor accessibility, filters, mobile
  reflow, colors, timezones, failure states, multiple calendars, Elementor
  lifecycle, Gutenberg blocks, Add to Calendar and recurrence.
- The supported WordPress smokes validate the packaged plugin independently on
  WordPress 6.9 and 7.1 with PHP 8.2 compatibility enforced by the repository.
- The local UI was restored to its initial Divi-active, Elementor-inactive state.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| `composer validate --strict` | Pass |
| PHP coding standards and compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 777 tests / 3,205 assertions |
| Composer audit | Pass, zero advisories/abandoned packages |
| Frontend/Divi build and tooling contracts | Pass, 79/79 |
| ESLint and Stylelint | Pass, zero warnings |
| npm audit | Pass, zero vulnerabilities |
| Complete packaged browser matrix | Pass, 35/35 |
| WordPress 6.9 / PHP 8.2-compatible packaged smoke | Pass |
| WordPress 7.1 / PHP 8.2-compatible packaged smoke | Pass |
| Historical upgrade matrix | Pass, all eight supported paths |
| Release verification and reproducibility | Pass, two byte-identical builds |
| Candidate archive SHA-256 | `2441186e06701bd3e357f13e3bc6f6252607fe9b426945e8715eedd7a216ccdf` |

The staging archive still reports public version 0.7.0 because the feature-frozen
0.9.0 version bump and publication belong to RC8. RC7 does not publish this
checkpoint.

## Residual non-blocking risks

- Production mutation was prohibited by design. Authenticated recurrence,
  Gutenberg save/reload and builder lifecycle mutations remain covered by the
  disposable browser, upgrade and prior host qualification layers.
- The plugin is fully translation-ready, but a Dutch WordPress.org language pack
  is not part of this source repository. A Dutch site therefore shows English
  plugin labels until community or project translations are published.
- Theme, caching/CDN and builder-extension CSS can still alter surrounding layout
  outside plugin-owned components. The plugin's bounded style controls and
  troubleshooting guidance cover the supported ownership boundary.
- FullCalendar 6.1.21 remains frozen for 1.0; the planned FullCalendar 7 evaluation
  remains a post-1.0 compatibility project rather than a release-candidate change.

## References reviewed

- [WordPress `@wordpress/editor` package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-editor/)
- [PluginDocumentSettingPanel SlotFill](https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-document-setting-panel/)
- [WordPress `@wordpress/edit-post` package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-edit-post/)
