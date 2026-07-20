# Backlog execution plan

**Status:** WP0 through WP4 completed 2026-07-19; WP5 is next\
**Inventory closed:** 2026-07-18\
**Source backlog:** `docs/BACKLOG-TESTING.md`

## 1. Objective

Resolve the eight findings from the first acceptance-testing round without mixing urgent calendar corrections with the larger template-building feature. Each work package must be independently reviewable, testable and releasable. No package may weaken the existing security, compatibility, accessibility or lightweight-product boundaries.

The intended order is:

1. establish a trustworthy baseline and browser regression capability;
2. stabilize the calendar's first render and controls;
3. correct the calendar's date/time contract;
4. improve event editorial and time-zone presentation;
5. introduce a shared atomic event-field layer;
6. expose that layer through Elementor and Gutenberg;
7. run the complete release qualification.

## 2. Decisions fixed for this execution

These choices resolve the open planning notes in the backlog. Any later change requires an entry in `docs/DECISIONS.md` before implementation.

| Area | Decision |
|---|---|
| Calendar button states | Correct the existing accent/on-accent contract first. Do not add separate hover and active color controls in this cycle. Rename the duplicate Elementor typography labels. |
| Calendar filters | Keep the existing enabled default for backward compatibility. Omit the entire form when it has no usable category or tag choices. Make the Elementor control and its distinction from initial taxonomy constraints clearer. |
| Calendar time zone | Preserve each event's captured wall-clock date and time. The visitor browser must not silently move an event to another day. A single global FullCalendar zone is not an acceptable fix for mixed event zones. |
| Time notation | Inherit WordPress `time_format` by default. Do not add a duplicate global 12/24-hour plugin setting. Presentation-level overrides may be added later to the atomic date/time component if they remain explicitly optional. |
| Public time-zone label | Add a backward-compatible global display toggle, disabled by default. When enabled, timed events show an unambiguous IANA/fixed zone and the offset applicable to the event date. All-day events omit it. |
| Event time-zone editing | Keep the native time zone server-owned and readonly in this cycle. A per-event selector is a separate future product decision because it changes the event-authoring contract. |
| External action label | Add one optional plain-text label of at most 120 characters. Empty values retain the translated `More event information` fallback. Multiple resource links remain out of scope. |
| Atomic components | Provide discoverable field-specific widgets/blocks backed by shared render services; do not expose a generic raw-meta component. |
| Elementor context | Use the same widgets in Free and Pro. On a static page the user selects a public event; in a dynamic template the widget consumes the current event context. Template assignment/routing remains host-owned and outside plugin scope. |
| Gutenberg context | Use the same dynamic field blocks on ordinary pages and in the Site Editor. A static page can select a public event; a template consumes block post context. Existing native fallback blocks remain compatible. |

## 3. Dependency and delivery map

| Work package | Backlog items | Depends on | Candidate delivery |
|---|---|---|---|
| WP0 — Baseline and browser guardrails | Test infrastructure | None | Development-only prerequisite |
| WP1 — Calendar lifecycle and controls | BL-006, BL-001, BL-002 | WP0 | Calendar stabilization release |
| WP2 — Calendar date/time correctness | BL-003, BL-004 | WP0; calendar lifecycle from WP1 | Calendar stabilization release |
| WP3 — Event editorial/time-zone UX | BL-005, BL-007 | WP2's time contract | Event presentation release |
| WP4 — Shared atomic presentation layer | Foundation for BL-008 | WP3, especially action label/time-zone output | Template-components release |
| WP5 — Elementor atomic widgets | BL-008 Elementor | WP4 | Template-components release |
| WP6 — Gutenberg dynamic blocks | BL-008 Gutenberg | WP4 | Template-components release |
| WP7 — Release qualification | All completed items | WP1–WP6 | Installable release candidate |

WP1 and WP2 should be completed before starting WP3. WP5 and WP6 may be developed independently only after WP4's field and context contracts are frozen, but they must receive parity review before release.

## 4. WP0 — Baseline and browser guardrails

### Development work

1. Capture the current repository as an agreed source-control baseline before editing runtime code. At planning time the working tree consists entirely of untracked files, which prevents reliable diff review and rollback.
2. Run and record the existing baseline gates:
   - `composer validate --strict`;
   - `composer qa`;
   - `npm run qa`;
   - `npm run test:smoke`.
3. Add a deterministic browser-test harness for the frontend behaviours that PHP/unit tests cannot verify. Prefer a pinned Playwright-based development dependency against the existing local WordPress/Playground fixture. Document purpose, maintenance status, licence and removal cost before adding it.
4. Create bounded reusable fixtures for:
   - an empty calendar;
   - categories only, tags only and both;
   - same-day, overnight, multi-day and all-day events;
   - IANA and fixed-offset time zones;
   - cancelled and postponed events;
   - multiple calendar instances.
5. Establish screenshot/geometry assertions only for stable component boundaries. Do not rely on whole-page pixel snapshots that change with the active theme.
6. Extend the real-WordPress Playground integration journey whenever a work package changes registration, REST, persistence or public-query behaviour. `composer test:integration` must invoke that journey rather than a WordPress-stubbed or empty PHPUnit suite.

### Exit criteria

- Existing quality gates are green before feature work. A pre-existing failure must be isolated and corrected as its own prerequisite; documenting it does not make it acceptable.
- A browser test can load the calendar, wait for its accessible ready state and assert usable grid geometry and controls.
- Test data setup and cleanup are isolated and reproducible.
- The dependency decision is recorded in `docs/DECISIONS.md` if a new test dependency is introduced.

**Completion note (2026-07-18):** The pinned Playwright/Chromium harness now runs against a disposable WordPress Playground site with reusable calendar boundary fixtures. It covers initial/reloaded/resized geometry, the mobile initial view, a delayed feed, feed failure, multiple instances and initially hidden integration containers. Browser tests are registered in CI and `npm run test:e2e` is a required calendar gate.

## 5. WP1 — Calendar lifecycle and controls

### WP1.1 First-render lifecycle — BL-006

1. Add a failing browser regression that hard-reloads a month calendar and checks its seven-column geometry before any control is clicked.
2. Correct the reveal/render order so FullCalendar is not measured while its canvas is hidden.
3. Trigger one explicit size recalculation after reveal if FullCalendar still requires it.
4. Test slow, immediate, empty and failed feed responses.
5. Test viewport resize, mobile initial view, multiple calendars and containers that become visible after page load.
6. Introduce `ResizeObserver` or an Elementor-specific visibility hook only if the simpler lifecycle correction cannot pass the hidden-container cases; disconnect observers on teardown.

**Completion note (2026-07-18):** FullCalendar's canvas is now made measurable before its first render while the accessible server fallback remains present until data loads. A scoped `ResizeObserver` requests a size update when a previously hidden or resized host becomes measurable. The six browser lifecycle regressions pass without whole-page snapshot coupling.

### WP1.2 Button states — BL-001

1. Add regression coverage for normal, hover, focus-visible, active and disabled toolbar buttons.
2. Replace the `currentcolor` state rule with explicit component variables for accent background and on-accent text.
3. Preserve theme-inherited typography and provide a visible focus indicator independent of hover/active state.
4. Verify light/dark defaults and custom Elementor colors in editor and frontend.
5. Rename the two Elementor typography controls so their targets are unambiguous; keep stable saved control identifiers.
6. Check default-state contrast and forced-colors/high-contrast behaviour in addition to ordinary visual states.

**Completion note (2026-07-18):** Toolbar buttons now use deterministic accent/on-accent variables for hover, focus-visible, pressed and selected states; disabled controls remain readable and keyboard focus has an independent outline. Default colors use WordPress block-theme contrast/base presets with adaptive system-color fallbacks. The original custom-Elementor-color failure, normal/hover/pressed/focus/selected/disabled states and forced-colors behaviour are protected by browser assertions. Stable Elementor control IDs were retained while their labels became `Calendar typography` and `Button typography`.

### WP1.3 Filter empty state and clarity — BL-002

1. Add renderer tests for no terms, category-only, tag-only and both-term states.
2. Return no filter form when neither taxonomy has a usable public term.
3. Retain the existing enabled default and saved widget behaviour.
4. Rename/describe the Elementor visitor-filter control clearly and distinguish it from initial category/tag constraints.
5. Verify filter selection, reset, URL state, no-JavaScript GET fallback, multiple instances and accessible status messages.

**Completion note (2026-07-18):** The existing enabled default and stable Elementor IDs remain unchanged. The complete visitor-filter form is now omitted when neither taxonomy has a non-empty public term; category-only, tag-only and combined states render only useful controls. Elementor labels its saved category/tag values as initial constraints and its switcher as `Show visitor filters`. Initial constraints are now carried in the JavaScript configuration as well as the server fallback, so disabling or omitting visitor controls cannot broaden the interactive feed. Unit and browser coverage protect GET state, selection, URL reload, reset, disabled filters, multiple instances and accessible result status.

### Exit criteria

- BL-001, BL-002 and BL-006 pass their unit/browser matrices on shortcode and Elementor output.
- The first visible calendar frame is usable without interaction.
- Keyboard navigation, focus and disabled states pass manual QA.
- No calendar assets load on pages without a calendar.

## 6. WP2 — Calendar date/time correctness

### WP2.1 Freeze the presentation contract — BL-003

1. Add an ADR stating that calendars preserve the event's captured wall-clock values rather than converting to the browser zone.
2. First reproduce the reported `+00:00` to `Europe/Brussels` date shift in a controlled browser test.
3. Add pure tests for positive/negative offsets, IANA zones, fixed offsets, events near midnight, genuine overnight events, multi-day events, all-day exclusive ends and DST transitions.
4. Adjust the FullCalendar feed adapter so timed display values are not reinterpreted into a visitor-local date. Preserve the captured zone/offset as explicit event presentation metadata.
5. Review visible-window querying at the maximum supported offsets (`-14:00` through `+14:00`). If the feed uses floating wall times, ensure boundary events are not omitted by an absolute UTC query window. Keep all queries bounded and pagination truthful.
6. Update the REST/feed schema and `docs/PUBLIC-QUERY-CONTRACT.md` to distinguish UI wall-time values from machine instants. Do not silently change a documented response contract.
7. Compare calendar month/list output with the native single-event formatter for every fixture.

**Completion note (2026-07-19):** ADR-024 freezes captured wall time as the calendar presentation contract. Timed feed values now use floating canonical local ISO values for FullCalendar placement and retain the captured timezone plus offset-bearing machine instants in `extendedProps`. Day-aligned REST windows query canonical local boundaries, so mixed zones and the supported `-14:00`/`+14:00` extremes remain inside truthful bounded pagination. Pure coverage protects fixed offsets, IANA/DST transitions, same-day, overnight, multi-day and all-day ranges; controlled Chromium journeys in positive and negative browser zones compare month, list and native event output.

### WP2.2 Consistent 12/24-hour notation — BL-004

1. Add a small tested formatter that derives the required 12/24-hour presentation from WordPress `time_format` without changing storage.
2. Pass the resulting presentation contract to FullCalendar for views that display event times.
3. Keep server-rendered details/cards on WordPress formatting and verify noon, midnight, leading zeros and translated meridiems.
4. Document that native browser time inputs may look platform-specific while saving the same canonical 24-hour value.
5. Leave global/per-widget overrides out of this package; the future atomic date/time component may add an explicit override without changing the default.

**Completion note (2026-07-19):** A bounded PHP-format adapter now derives FullCalendar's hour cycle, leading-zero, minute and meridiem presentation from WordPress `time_format`. The calendar uses explicit `h23`/`h12` cycles, while server details/cards continue through localized `wp_date()`. Native editor help explains browser-specific time controls and canonical storage. Unit and Chromium regressions cover escaped tokens, safe fallbacks, midnight, noon, leading zeros and `H:i`/`g:i a`; no duplicate plugin or widget setting was added.

### Exit criteria

- A same-day event remains on the same visible date in browsers with different zones.
- Real overnight/multi-day and all-day events still span correctly.
- Calendar and single-event output agree on 12/24-hour notation where a time is shown.
- REST validation, bounded queries and public visibility rules remain intact.

## 7. WP3 — Event editorial and time-zone UX

### WP3.1 External action label — BL-005

1. Add an allowlisted `_wpse_event_url_label` metadata field with a 120-character maximum, scalar shape validation and plain-text sanitization.
2. Extend the input DTO, admin/REST mapper, central validator, validated DTO and persistence gateway together; no write path may bypass validation.
3. Add the editor field next to the external URL and mirror it into Gutenberg's atomic REST save with the other editable event metadata.
4. Render the custom escaped label when present and retain the translated fallback otherwise. Render nothing when the URL is empty.
5. Update revisions, REST schemas, duplication policy, data contract, uninstall/maintenance allowlists where applicable and translation catalogue.
6. Test malicious input, whitespace, maximum length, empty URL/label combinations, REST permissions and existing events without the new meta key.

**Completion note (2026-07-19):** The existing external event URL now has one optional revisioned plain-text label, bounded to 120 characters in its registered REST schema and shared sanitizer. Native and Gutenberg writes use the same input, validation and persistence path. Existing/empty labels retain the translated fallback, labels without URLs remain hidden, output is escaped, and duplication omits both destination-specific values. Unit and real-WordPress coverage protect structured input, bounds, whitespace, legacy records, REST authorization, editor availability, atomic REST saves, public rendering and duplication.

### WP3.2 Time-zone visibility — BL-007

1. Show the authoritative WordPress site zone on Event Settings with an administrator-only link to General Settings.
2. Explain that new events capture that zone, existing events retain theirs and fixed offsets do not provide DST behaviour.
3. Add a validated boolean display option, defaulting to disabled for backward compatibility.
4. Extend the shared date presentation with an optional zone label such as `Europe/Brussels (UTC+02:00)`. Calculate offsets at the event boundary; if a multi-day event crosses an offset transition, represent both offsets unambiguously.
5. Omit the zone label for all-day events and when disabled.
6. Apply the same output to native details and the composite Elementor widget; atomic component overrides wait for WP5/WP6.
7. Test IANA/fixed zones, winter/summer events, DST transitions, translations, permissions and settings sanitization.

**Completion note (2026-07-19):** Event Settings reports WordPress' authoritative zone, documents capture/retention behaviour, warns about fixed offsets and links administrators to General Settings without adding a competing timezone selector. One strictly sanitized global display option defaults to disabled. When enabled, the shared native/Elementor details formatter adds an escaped event-date IANA/fixed label, represents both offsets across DST transitions, wraps long identifiers and omits all-day labels. Canonical dates, UTC indexes, cards, calendars, feeds, REST and structured-data values remain unchanged.

### Exit criteria

- Existing events render exactly as before when both new options/fields are empty or disabled.
- Every write path validates and persists the label atomically.
- Administrators can identify the active site zone without finding a duplicate plugin-owned time-zone setting.
- Machine-readable feed and structured-data dates are unaffected by the visual time-zone toggle.

## 8. WP4 — Shared atomic presentation layer

This foundation must land before host-specific components. Refactor in small steps while preserving the existing composite renderer's HTML and behaviour.

1. Introduce one access-aware event-context resolver for both current-event/template context and explicitly selected public events on static pages or in editor previews.
2. Create field presentation/render services for:
   - title;
   - featured image;
   - date/time/time zone;
   - event status;
   - venue;
   - address;
   - location action;
   - content;
   - excerpt;
   - external event action;
   - categories;
   - tags.
3. Keep metadata private to the presentation layer; adapters request named fields rather than arbitrary meta keys.
4. Preserve password protection, public-status checks, contextual escaping, content recursion guards and empty-field omission.
5. Add request-level reuse of resolved public presentation data so a template with many atomic fields does not repeat avoidable queries or formatting work. Do not cache across requests.
6. Rebuild the existing Event Details renderer from these services and prove output/backward compatibility before adding new widgets or blocks.
7. Document stable semantic classes and extension points.

**Completion note (2026-07-19):** One access-aware resolver now separates authorized current/template previews from explicit published, password-free selections and reuses normalized presentation snapshots within the request. A shared factory keeps metadata keys inside the presentation layer and a named field renderer covers the complete WP4 palette with empty-field omission, contextual escaping, password protection and request-wide content recursion guards. The existing native/shortcode/Elementor details renderer is composed from those fragments while retaining its grouping classes, field order, password form and explicit-selection visibility contract. `docs/PRESENTATION-CONTRACT.md` freezes the semantic classes and adapter boundary.

### Exit criteria

- The composite native/shortcode/Elementor details output remains backward compatible.
- Each field has isolated present/absent/corrupt/security tests.
- Draft, private and password-protected data cannot leak through explicit preview IDs.
- Repeated atomic field rendering remains bounded and does not introduce direct SQL.

## 9. WP5 — Elementor atomic widgets

1. Amend ADR-014 and `docs/ELEMENTOR-INTEGRATION.md` before expanding beyond the original three widgets.
2. Register dedicated WP Simple Events widgets for the WP4 field set, using shared abstract behaviour only where responsibilities remain clear.
3. Reuse the bounded public event selector and current-event resolution. The selected event is the real source on a static page and an editor preview source in a dynamic template; empty fields show editor-only placeholders and no frontend wrapper.
4. Provide controls appropriate to the field rather than one generic control set:
   - label visibility/text where meaningful;
   - image size, alt behaviour and optional link for featured image;
   - inherited or explicit date/time presentation where agreed;
   - typography, color and spacing through scoped selectors.
5. Keep Elementor optional and version-gated; loading/deactivating Elementor must not affect native functionality.
6. Verify supported Elementor 3.x and current 4.x, editor/frontend parity, multiple widgets and optimized DOM.
7. Verify that the same saved widget configuration behaves consistently when sourced from a selected event on a static page or the current event in a template.

**Completion note (2026-07-19):** Twelve dedicated widgets now cover the frozen WP4 field palette. All use one bounded public selector/current-context boundary and request-shared resolver/renderer set, validate stored settings through field-specific allowlists and render only shared semantic fragments. Explicit and current sources preserve markup and styling; missing/inaccessible values are empty publicly and explanatory only in the editor. Controls cover labels, title/link, image size/link/alt behaviour, action text, typography, color and spacing while date/time continues to inherit WordPress and the global timezone-label choice. The original three widgets remain registered first with unchanged identifiers. Unit regressions and a real-host inspector pass on Elementor 3.35.9 and 4.1.5.

### Exit criteria

- A complete event layout can be assembled from atomic widgets on an Elementor Free static page and in Elementor Pro Theme Builder.
- Switching only the event source—from an explicit selection to current context—does not change field semantics or styling.
- Widget controls cannot expose non-public events or raw metadata.
- Existing three widgets and saved instances remain compatible.

## 10. WP6 — Gutenberg dynamic blocks

1. Register public dynamic blocks with `block.json` metadata and server-side callbacks for the same WP4 field set.
2. Consume WordPress block context (`postId`/`postType`) in templates and offer a bounded explicit public-event selection for blocks placed on ordinary pages; do not rely only on mutable global loop state.
3. Provide editor previews/placeholders and Inspector controls that map to the same allowlisted event-source and presentation options as Elementor.
4. Use WordPress block supports for typography, colors, spacing and alignment where they preserve semantic output.
5. Add a single-event block pattern demonstrating the atomic components; do not silently replace customized templates.
6. Keep `wpse/native-single` and `wpse/native-archive` as backward-compatible internal fallback bridges.
7. Guard the Event Content block against rendering itself recursively when placed inside event content or a containing template.
8. Test serialization, server rendering, Site Editor context, query-loop context, classic themes, block themes and missing optional values.

**Completion note (2026-07-20):** Twelve dedicated `block.json` blocks now mirror the WP4/Elementor field palette through one server adapter. Empty `eventId` consumes `postId`/`postType` context and falls back only to an event queried object when context is unavailable; positive IDs resolve strictly through the bounded public boundary. The shared editor bundle supplies at most fifty public choices, field-specific Inspector controls and ServerSideRender placeholders, while frontend HTML remains dynamic and editor assets stay off public pages. Native color, link-color, typography, margin and alignment supports wrap only non-empty fields. The existing fallback blocks remain unchanged, and one opt-in pattern demonstrates the complete palette. Unit, real-WordPress smoke and Playwright coverage protect registration, serialization, explicit/current parity, non-public rejection, empty wrappers, recursion, supports and editor/frontend asset isolation.

### Exit criteria

- A block-theme single-event template and an ordinary page with a selected event can both be composed from the same dynamic blocks.
- Blocks inserted in ordinary content resolve only a valid event context and fail closed otherwise.
- Gutenberg and Elementor field output have parity for semantics, access control and empty states.
- Editor assets load only where block registration/editor use requires them.

## 11. WP7 — Senior review and release qualification

Each work package receives two explicit reviews before handoff:

### Senior developer review

- architecture and responsibility boundaries;
- backward compatibility and migration/default behaviour;
- WordPress/Elementor APIs and optional-dependency isolation;
- query bounds, performance and asset loading;
- documentation, translations and extension contracts.

### Senior QA/security review

- regression tests fail meaningfully before the fix where feasible;
- public/private/password/preview permissions;
- nonce, capability, REST schema, validation, sanitization and contextual escaping;
- keyboard, focus, semantics, empty/loading/error states and responsive layouts;
- time zones, DST, all-day/timed/same-day/multi-day boundaries;
- supported WordPress, PHP and Elementor matrix.

### Required gates

Run from the repository root after every applicable package:

```sh
composer validate --strict
composer qa
npm run qa
composer test:integration
npm run test:smoke
npm run test:e2e
```

Run the new browser suite for calendar and component changes. For a release candidate also run translation generation/checks, deterministic release build/verification, supported WordPress smoke matrix, production dependency audits and official WordPress Plugin Check against the packaged staging directory.

The browser suite is a required gate for calendar and component work rather than an informal manual step.

No installable ZIP is handed off until the package, not only the working tree, passes the complete release qualification.

## 12. Recommended execution cadence

For each work package:

1. mark only its backlog items **In progress**;
2. restate intended behaviour and risks;
3. add the regression tests;
4. implement the smallest cohesive change;
5. run focused tests, then normal quality gates;
6. perform senior developer review;
7. perform senior QA/security review;
8. update contracts, decisions, changelog and backlog state;
9. provide a locally installable candidate only at a milestone boundary.

Do not start WP4–WP6 while calendar correctness is still unresolved. This prevents the atomic field components from freezing incorrect time-zone or time-format behaviour into two additional presentation hosts.
