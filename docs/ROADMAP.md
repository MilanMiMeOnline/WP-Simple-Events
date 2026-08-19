# Product roadmap

**Status:** active planning contract
**Last reviewed:** 19 August 2026
**Current public release:** 0.2.5
**Next planned release:** 0.3.0 — builder and presentation polish

This roadmap translates real-world feedback and exploratory testing into ordered,
reviewable work. The normative behaviour of the current plugin remains defined in
[PRODUCT-SPECIFICATION.md](PRODUCT-SPECIFICATION.md). A roadmap item is not part
of the supported product until its specification, tests and release are complete.

## Product direction

Work proceeds in this order:

1. sharpen the existing one-off event experience and builder integrations;
2. design and validate a lightweight recurrence model before implementing it;
3. add Divi 5 through the same shared presentation and query services;
4. reassess broader platform support only when its maintenance cost is justified.

Interactive maps, geocoding, ticketing and attendee management remain outside the
roadmap. The plugin continues to favor a small, native WordPress core over a broad
event-management suite.

## Phase 1 — 0.3.0 builder and presentation polish

### Goal

Make the existing feature set feel complete in Elementor, Gutenberg and native
WordPress without requiring routine custom CSS. Correct event-aware archive
semantics and preserve the current storage, shortcode, block and widget contracts.

### Evidence base

The phase is based on exploratory testing on:

- the production site `taranartos.be`, using Elementor Pro without leaving test
  content or template changes behind;
- the disposable local site `simpleevents.local`, using WordPress 7.0.4, PHP
  8.3.30, Elementor Free 4.2.2 and Twenty Twenty-Five 1.5;
- the automated WordPress, Elementor and browser suites already in the repository.

The round confirmed that native single/archive templates, event date variants,
public calendar filtering, safe external actions and the atomic Elementor palette
work. It also exposed the following gaps.

### Prioritized backlog

| ID | Priority | Improvement | Acceptance outcome |
|---|---|---|---|
| UX-001 | P1 | Initialize and refresh the interactive Event Calendar inside the Elementor editor lifecycle. | A newly added, duplicated or changed widget shows the same calendar state in the editor as on the public page, without duplicate instances or listeners. |
| UX-002 | P1 | Make event category and tag archives event-aware. Investigate event presentation in mixed WordPress search separately. | Taxonomy archives order and label events by event date rather than publication date. Mixed search is not globally reordered unless a safe event-only rule can be proven. |
| UX-003 | P1 | Complete Event List / Grid styling controls. | Card background, border, radius, shadow, image ratio/height/fit, content padding, gaps and interactive states can be controlled without page-level CSS. |
| UX-004 | P1 | Split Event Calendar styling into useful component groups. | Toolbar, navigation/view buttons, event chips, grid/cells, today state, filters and status copy can be styled independently with normal, hover, focus, active and disabled states. |
| UX-005 | P1 | Make grid responsiveness depend on available component width. | Cards do not remain in cramped multi-column layouts inside narrow theme or builder containers, even on a wide viewport. |
| UX-006 | P1 | Add public Gutenberg List / Grid, Calendar and composite Details blocks that reuse existing renderers. | Gutenberg users no longer need shortcodes for the three main public components; editor previews and frontend output share the same security and query rules. |
| UX-007 | P2 | Improve visitor filter usability while preserving the no-JavaScript GET baseline. | Category/tag filtering is understandable with keyboard, touch and assistive technology; initial constraints remain distinct from visitor choices. |
| UX-008 | P2 | Complete atomic presentation controls. | External Action supports a true button presentation; Featured Image has practical size/fit controls; composite Details can hide fields and customize meaningful labels without replacing atomic widgets. |
| UX-009 | P2 | Clarify and condition builder controls. | Irrelevant controls such as grid columns in list mode are hidden, duplicate generic labels are field-specific, and saved control identifiers remain compatible. |
| UX-010 | P2 | Add bounded content options where they remove routine work. | List/Grid can control image, title/date visibility and excerpt length; Calendar can choose a safe initial date and optional toolbar parts without becoming a scheduling suite. |
| UX-011 | P2 | Normalize link and heading semantics. | External location links behave consistently, calendar event links remain internal, and configurable heading levels prevent avoidable hierarchy skips. |
| UX-012 | Investigation | Revalidate the Gutenberg editor canvas on clean supported WordPress 6.9 and current WordPress installations. | The prior gray editor canvas is either reproduced with a plugin-owned cause and regression test, or recorded as an environment/core limitation before block work begins. |

P1 means the 0.3.0 release is not complete without the item. P2 items belong to
0.3.0 when their implementation stays cohesive and backward compatible; they may
be split into 0.3.1 only if doing so reduces release risk. Investigation UX-012 is
a prerequisite, not an assumed plugin defect.

**Progress — 18 August 2026:** UX-001 is complete. The calendar initializer now
supports both ordinary document load and Elementor's element-ready lifecycle,
remains idempotent when a hook repeats and initializes one fresh instance after a
widget-root replacement. A browser regression protects the delayed-init,
repeat-hook and rerender journeys.

### 0.3.0 implementation plan

#### WP0 — Freeze evidence and regressions

1. Capture each P1 observation as a reproducible fixture or focused browser test.
2. Revalidate Elementor editor behaviour against the supported 3.x line and the
   current tested 4.x line.
3. Revalidate Gutenberg on clean minimum/current WordPress environments before
   attributing the gray local editor canvas to the plugin.
4. Inventory stable widget control IDs, block names, shortcodes, CSS classes and
   custom properties that must remain backward compatible.

**Exit criteria:** every confirmed defect has a failing regression or a documented
manual reproduction; uncertain environment behaviour is not implemented as a fix.

#### WP1 — Elementor calendar lifecycle

1. Refactor calendar startup into an idempotent initializer scoped to one component
   root.
2. Run it both on the initial document and Elementor's supported
   `element_ready/wpse-event-calendar.default` lifecycle.
3. Update or tear down an existing instance safely when Elementor rerenders it.
4. Cover add, edit, duplicate, responsive-preview and remove/reinsert journeys.

**Exit criteria:** editor and public calendars have equivalent functional output,
and multiple widgets create neither duplicate requests nor event listeners.

**Completion note — 18 August 2026:** The initializer is scoped to the rendered
widget root and tracks live instances without adding an Elementor dependency.
Repeated hooks request only a safe geometry update; changed or replaced roots
receive clean listeners, resize observation and one FullCalendar instance. The
isolated browser regression first failed against the document-only initializer and
passes with the lifecycle adapter. A manual check of the installed development
package also passed in the Elementor 4.2.2 editor; detailed evidence is recorded in
[QA-REPORT-0.3.0-WP1.md](QA-REPORT-0.3.0-WP1.md).

#### WP2 — Event-aware discovery surfaces

1. Reuse the bounded event query and presentation services for event category and
   event tag archives while keeping the active theme shell.
2. Apply event-date ordering and event-date labels only where the query is known to
   be an event collection.
3. Research mixed search independently: preserve normal WordPress relevance and
   non-event publication dates unless scoped event markup can be introduced safely.
4. Cover protected, private, draft and password-protected event exclusions.

**Exit criteria:** taxonomy archives are chronologically correct and no global
query/date filter changes blog posts, products or mixed search unexpectedly.

**Completion note — 18 August 2026:** Event category and tag main queries now
reuse the bounded public event-query rules without replacing WordPress' resolved
term constraint. Both classic and block themes receive the shared event archive
inside their normal shell, including generic and taxonomy-specific override
paths. Term archives show their term title, all eligible events in ascending
start order, shared cards and pagination; they omit the general filter form so a
visitor cannot escape the fixed term. Unit and packaged WordPress smoke coverage
freeze query isolation, styling, theme shells, chronology and protected-content
exclusions. Mixed search remains untouched after review because changing its
ordering or date labels globally would damage relevance and non-event results.
Detailed evidence is recorded in
[QA-REPORT-0.3.0-WP2.md](QA-REPORT-0.3.0-WP2.md).

#### WP3 — Shared presentation control system

1. Define component-scoped CSS variables and stable control targets for cards,
   images, content, filters, buttons, pagination and calendar subcomponents.
2. Add Elementor controls with conditions and responsive values while preserving
   existing saved identifiers and defaults.
3. Extend the External Action, Featured Image and composite Details widgets only
   through shared field/render contracts.
4. Replace viewport-only card decisions with component-width behaviour and a safe
   fallback for older browsers.
5. Verify theme inheritance when no control is set and specificity against common
   theme and Elementor rules.

**Exit criteria:** the CSS customizations observed on `taranartos.be` can be
expressed in widget controls, defaults remain restrained, and existing pages do not
visually change merely by updating the plugin.

**Completion note — 18 August 2026:** The shared component stylesheet now exposes
opt-in, wrapper-scoped targets for card content, independent grid gaps, images,
filters, form controls, pagination, composite details and calendar interaction
states. Elementor exposes these targets without adding saved defaults; the prior
widget/control identifiers remain unchanged. Event grids respond to their own
container width with a feature-detected viewport fallback. The production CSS
needed on `taranartos.be`—a white borderless card and a padded white pagination
container—can now be expressed through normal widget controls. Focused PHP and
stylesheet-contract regressions protect the control IDs, selectors, theme
inheritance and fallback structure. Host-editor and packaged regression evidence
is recorded in [QA-REPORT-0.3.0-WP3.md](QA-REPORT-0.3.0-WP3.md); the final
cross-version builder and Plugin Check gates remain part of WP6.

#### WP4 — Gutenberg composite parity

1. Add metadata-registered dynamic Event List / Grid, Event Calendar and Event
   Details blocks.
2. Delegate attributes to the same allowlisted shortcode/render services instead
   of adding block-specific queries or metadata access.
3. Provide bounded Inspector controls, ServerSideRender previews and clear
   editor-only empty/error states.
4. Keep existing atomic blocks, internal native bridge blocks and serialized
   content compatible.

**Exit criteria:** the three primary components are discoverable without shortcodes,
remain useful with JavaScript disabled on the frontend and expose no non-public
event data.

**Completion note — 19 August 2026:** Event List / Grid, Event Calendar and Event
Details are now metadata-registered dynamic blocks in the existing plugin inserter
category. Their bounded Inspector controls map to the same shortcode and composite
renderers used elsewhere, including category/tag constraints, visibility options,
responsive calendar views and explicit public-event selection. Server previews
show editor-only loading, empty and error states while serialized content contains
attributes only. Unit and isolated browser regressions cover strict normalization,
renderer delegation, context and explicit-source access, registration,
serialization, authenticated previews, anonymous frontend output and the
no-JavaScript calendar fallback. Detailed evidence is recorded in
[QA-REPORT-0.3.0-WP4.md](QA-REPORT-0.3.0-WP4.md).

#### WP5 — Interaction and semantic polish

1. Improve filter controls while retaining a valid GET form and unique per-instance
   namespaces.
2. Add only the bounded content options accepted in UX-010.
3. Make control names, conditional visibility, external-link behaviour, focus
   states and heading semantics consistent across hosts.
4. Check empty, loading, error, reset and multiple-instance journeys.

**Exit criteria:** all functionality is keyboard-operable, understandable without
documentation and consistent between shortcode, Gutenberg and Elementor hosts.

**Completion note — 19 August 2026:** List/Grid now exposes bounded title/date
visibility, excerpt length and title-heading controls; Calendar accepts a real
initial date, optional navigation/Today/view groups and a fallback heading; and
composite Details can hide established field groups and override meaningful
labels. Elementor conditions irrelevant controls and Gutenberg offers the same
Inspector choices. Both no-JavaScript GET forms use a namespaced apply marker and
an explicit reset that restores configured initial constraints without disturbing
other instances. Interactive reset mirrors that contract. External location
links are isolated in a new tab, hidden-title components retain accessible names,
focus remains visible and all-hidden Details output is omitted. Detailed evidence
is recorded in [QA-REPORT-0.3.0-WP5.md](QA-REPORT-0.3.0-WP5.md).

#### WP6 — Release qualification

1. Run unit, integration, smoke, Elementor and browser regressions for every work
   package.
2. Execute the WordPress 6.9/current and PHP 8.2/current compatibility matrix.
3. Run accessibility, translation, dependency, release-archive and official Plugin
   Check gates.
4. Perform a final non-destructive production check on `taranartos.be`; remove every
   temporary event, page, widget and Theme Builder condition immediately afterward.
5. Update user documentation, screenshots, changelog and release notes.

**Exit criteria:** all required gates pass, the production site contains no test
data or temporary template change, and residual risks are recorded in the release
QA report.

**Qualification note — 19 August 2026:** The synchronized 0.3.0 candidate passes
the complete local PHP/Node quality gates, translation freshness, reproducible
packaging, nineteen packaged browser journeys, WordPress 6.9 and 7.0.1 smoke
journeys on PHP 8.2, and the real Elementor 3.35.9/4.2.2 inspector. A read-only
check of the live 0.2.5 installation on `taranartos.be` confirmed the site shell,
calendar feed, representative event, external-link isolation, JSON-LD and narrow
layout without changing production data or settings. Detailed evidence is in
[QA-REPORT-0.3.0.md](QA-REPORT-0.3.0.md). WP6 is complete: all ten jobs in
[GitHub Actions attempt 2](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/32279672029/attempts/2)
passed for release commit `73b7b43`, including the official strict Plugin Check,
and the CI release artifact is byte-identical to the locally qualified ZIP.

### 0.3.0 release criteria

- No P1 item remains open.
- Existing saved shortcodes, Elementor controls, blocks and event data remain valid.
- A practical event page/list/calendar can be styled without routine custom CSS.
- Elementor editor previews behave like their frontend output.
- Gutenberg offers atomic and composite public components.
- Event taxonomy archives use event chronology without altering unrelated queries.
- Theme inheritance, keyboard operation, narrow containers and multiple component
  instances pass manual and automated review.
- Security, privacy and bounded-query contracts remain unchanged or stronger.

## Phase 2 — recurring-events discovery and specification

Recurrence is not added directly to event posts as a collection of duplicated
dates. This phase must first produce an ADR, data contract and tested prototype.

The research must answer:

- which recurrence rules cover the valuable KISS use cases;
- how one occurrence, this-and-future occurrences or the full series are edited;
- how cancellations, exceptions and moved occurrences retain their relationship;
- how editors navigate between a series and an occurrence without ambiguity;
- how lists, feeds, calendars, templates, REST and structured data obtain bounded
  occurrences;
- how deletion, duplication, timezone changes, DST and migrations behave;
- whether native posts plus a small occurrence index remain performant, or a
  dedicated occurrence store is justified.

The preferred user experience is explicit at every destructive or broad edit:
**only this event**, **this and future events**, or **the complete series**. The
prototype must prove that this model stays understandable and lightweight before
production implementation receives a version target.

## Phase 3 — Divi 5 integration

Divi 5 support should expose functional parity with the Elementor component
palette without copying event logic. The adapter must use documented Divi 5 APIs,
the shared event context resolver, renderers, query services and component CSS.

The phase begins with a supplied licensed Divi 5 test environment and an API/
compatibility spike. Its acceptance matrix covers ordinary pages, Theme Builder
templates, current-event context, explicit public-event selection, responsive
controls, editor/frontend parity and operation when Divi is absent. Divi remains an
optional integration and may not become a core runtime dependency.

## Phase 4 — compatibility and maintenance

WordPress 6.9 and PHP 8.2 remain the supported floor. The plugin continues to test
the current WordPress, PHP and supported builder lines. Lowering either floor is not
a standing goal: it requires evidence of meaningful user reach, a bounded refactor
and a complete security/compatibility matrix. Compatibility work must never add
conditional feature loss or weaken input, output or dependency controls.

## Deferred ideas

These ideas require a separate product decision and are not implied by any phase:

- per-event timezone editing;
- visitor-local timezone conversion;
- Elementor Pro dynamic tags;
- external calendar synchronization or ICS import/export;
- multiple arbitrary external resource links;
- interactive maps or geocoding;
- ticket sales, registrations or attendee management.
