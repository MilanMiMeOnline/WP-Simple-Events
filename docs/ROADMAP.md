# Product roadmap

**Status:** active planning contract
**Last reviewed:** 27 August 2026
**Current public release:** 0.4.0
**Active phase:** 0.5.0 release freeze after completed Divi 5 real-host qualification

This roadmap translates real-world feedback and exploratory testing into ordered,
reviewable work. The normative behaviour of the current plugin remains defined in
[PRODUCT-SPECIFICATION.md](PRODUCT-SPECIFICATION.md). A roadmap item is not part
of the supported product until its specification, tests and release are complete.

## Product direction

Work proceeds in this order:

1. sharpen the existing one-off event experience and builder integrations;
2. qualify and release the accepted lightweight recurrence model;
3. add Divi 5 through the same shared presentation and query services;
4. reassess broader platform support only when its maintenance cost is justified.

Interactive maps, geocoding, ticketing and attendee management remain outside the
roadmap. The plugin continues to favor a small, native WordPress core over a broad
event-management suite.

## Phase 1 — 0.3.0 builder and presentation polish (released 19 August 2026)

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

## Phase 2 — recurring-events implementation

ADR-044 and `RECURRENCE-CONTRACT.md` accept one series post plus a rebuildable
occurrence projection. Recurrence is not added as duplicated event posts. Its
editor, projection and public read path are now active in the 0.4.0 development
branch after one-off parity and fail-closed readiness were proven.

The ordered implementation increments are:

- install the occurrence table and immutable occurrence domain without changing
  public output;
- index existing one-off events in bounded, recoverable generations;
- implement and qualify the pure bounded rule engine, including DST, month-end,
  leap-year, overlap and exhaustion semantics;
- migrate public chronological reads to occurrence-aware repositories and prove
  exact one-off parity;
- add canonical recurrence persistence, manual dates, exclusions, segments and
  sparse overrides around the qualified engine;
- add scope-first editor UX, impact previews and occurrence management;
- add series/occurrence routes, schema, sitemaps and builder context;
- complete migration, interruption, performance, security and compatibility QA.

The user experience remains explicit at every destructive or broad edit:
**only this occurrence**, **this and following occurrences**, or **the complete
series**. Recurrence ships in version 0.4.0 only after the complete contract and
release matrix pass.

**Progress — 20 August 2026:** the occurrence foundation, bounded engine,
qualified read layer and canonical recurrence aggregate are complete as hidden
increments.
One-off saves and the bounded migration populate generation-isolated occurrence
rows without changing public output. The dependency-free recurrence engine now
supports daily, selected-weekday weekly, same-day and ordinal-weekday monthly,
yearly and specific-date schedules. Hard horizon/output/evaluation limits and a
focused DST/calendar matrix are enforced. A dedicated occurrence repository now
provides occurrence-level ordering, totals, local-window overlap and canonical
category/tag filtering while rechecking the parent post and active generation.
Real WordPress fixtures prove one-off parity for two pages, totals, combined term
filters, overlap and draft/password exclusion. A strict recurrence schedule codec
now round-trips every qualified engine variant while rejecting unknown keys, raw
RRULE input, partial shapes and weak scalar coercion. A versioned aggregate now
binds that schedule to one immutable series identity, chronological future
segments, manual additions, reversible exclusions and sparse occurrence
overrides. Exact nested shapes, strict types, relationship invariants and bounded
collections fail closed. The complete aggregate now has a protected,
revision-enabled, canonical JSON metadata envelope and a capability-, identity-
and timezone-checked replacement service. Changed definitions and restored event
revisions invalidate the derived projection before it can be trusted. This
storage remains internal and absent from core REST. Bounded engine-to-projection
orchestration now reconciles chronological segment hand-offs, generated skips and
cancellations, sparse date/status changes, manual additions, complete empty
windows and moved-in identities. Segment seeds and anchors are proven against the
engine, output identities remain unique and the canonical-first save coordinator
retries dirty definitions while preserving fail-closed repair state on failure.
A shared pure occurrence builder now powers both persistence and a server-owned
impact preview. The preview compares immutable identities, reports added, removed,
moved, status/source and exception changes, and rejects mutations outside only-
this or this-and-following scope. Broad reconciliation can retain an orphaned
modified slot as manual while preserving its generated identity and public key.
Occurrence maintenance now has an accepted health-and-repair contract: the
initial migrator and administrator repair must share a type-aware boundary so a
stored recurrence aggregate can never be rebuilt as a one-off. Administrator
repair is capped at 25 public events per request, uses the 540-day production
horizon and keeps corrupt or failed events dirty while allowing later candidates
to progress.
Active recurring projections also record their exact local coverage window. A
bounded daily worker renews below a 450-day buffer, while public readiness
requires coverage from today through at least 365 days later. Missing, malformed
or narrow windows fail closed and are eligible for administrator repair.
The concurrency boundary is implemented below the editor route.
Canonical snapshot tokens are deterministic, first-time and existing aggregate
writes use atomic compare-and-replace semantics, and stale editors cannot
overwrite a newer recurrence definition or reach projection. Dedicated
capability-checked context, preview and save routes now expose only the exact
bounded editor contract. Preview confirmations are server-signed across the
event, editor, revision, complete proposal, scope, target and generation window;
save revalidates the proposal and rejects stale or altered confirmations. A real
WordPress REST smoke journey proves anonymous denial, one-off bootstrap, exact
impact, confirmed projection and stale replay rejection.

The one-off bootstrap context is now complete: canonical stored event metadata is
revalidated and represented as a one-date specific schedule before an editor can
preview enabling recurrence. Invalid or incomplete event state fails closed. The
first scope-first Gutenberg increment is also implemented. Its complete-series
panel supports daily, weekly, both monthly modes, yearly and selected-date rules;
respects WordPress' configured week start; preserves last-weekday semantics; and
requires a bounded review-before-apply cycle. Dirty ordinary post fields and
series with exceptions or future segments fail closed. A packaged browser journey
now enables a three-occurrence daily series through the real editor UI.

The inverse explicit **does not repeat** operation is now implemented. It offers
bounded searchable effective occurrence choices, requires selection of the
surviving one-off event, previews complete-series destruction honestly, binds the
choice into server confirmation, compare-and-deletes the exact aggregate and
rolls back prepared event metadata on conflicts. Real REST and Gutenberg browser
journeys cover enablement and conversion back to one-off. The next editor
increment is the **only this occurrence** / **this and following** workflow.

The server foundation for **only this occurrence** now resolves one selected
identity into current, inherited, override and cancellation state. It handles
moved generated and manual occurrences whose original date lies outside the
visible selection window, while retaining the existing complete-aggregate
preview and save boundary. A pure lossless proposal builder now preserves
untouched sparse fields and unrelated identities, restores inheritance by key
removal and keeps cancellation separate. A real WordPress REST journey proves the
complete only-this context, preview, confirmed save and stable-identity reload.
The first occurrence-scoped Gutenberg field workflow is now implemented. Editors
enter **only this occurrence** explicitly, search a bounded period, load one stable
identity, edit its date/time or status, cancel/restore it and remove date/status
exceptions through named inheritance actions. The panel shows the captured
timezone, blocks dirty ordinary post state, preserves unexposed sparse fields and
requires the existing impact-preview/confirmed-save cycle. Browser coverage moves
an occurrence and restores its inherited range, changes and restores status,
cancels and restores the occurrence, and then returns to complete-series editing.

The pure domain layer for **this and following occurrences** is now complete. A
selected non-root generated identity becomes one replacement segment; earlier
segments remain canonical, an existing boundary keeps its ID and every later
scheduled change is replaced. Segment IDs are monotonic. A shared bounded
membership service rejects date-shaped non-occurrences, while server-side
exception reconciliation detaches future overrides, cancellations and skips that
no longer belong to the new rule without changing their immutable identity.
Detached cancellations and skips remain reversible in the occurrence builder.
The authenticated proposal endpoint is now complete. It accepts only the selected
generated boundary, current revision, bounded window and strict replacement
schedule; the server owns timezone, structural mutation and exception
reconciliation and returns the exact complete proposal for the existing signed
save route. Anonymous access, malformed or client-timezone input, skipped/manual/
root targets, stale revisions, altered confirmations and replay fail closed. A
real WordPress journey proves preview, preservation of an earlier override,
detachment of a future cancellation, confirmed persistence and stale replay
rejection.

The Gutenberg **this and following** workflow is now complete over that proposal
route. Its boundary picker excludes root, manual and detached occurrences; its
form starts from generated inheritance rather than copying a one-off exception;
all supported recurrence patterns, date/time controls and captured timezone are
available; and its warning names both the preserved past and replaced later
schedule segments. Every field or boundary change clears confirmation state. A
real browser journey covers the complete scope sequence and verifies the impact
before confirmed save.

The remaining only-this presentation controls are now complete. The authorized
server context supplies normalized inherited title, note, featured image, venue,
address, location URL, external event URL and action-label values without exposing
metadata keys. Gutenberg shows explicit individual/inherited ownership for every
field, supports deliberate hidden inherited values, uses the native media picker,
and removes sparse keys through named **Use series value** actions. Browser
validation shares PHP-owned limits while server validation and signed save remain
authoritative. The isolated WordPress 7.0.1 journey proves write, preview, save and
inheritance restoration for the new text and action fields.

The first shared public-presentation foundation is now complete but remains
unregistered. One exact bounded lookup resolves a published, password-free active
projection row by event ID and stable public key, rejects ambiguity and proves the
key against the protected aggregate's series identity. One normalized context
then overlays only that occurrence's title, note, image, location and external
action fields while retaining series-owned content and taxonomies. Positive and
negative exact contexts are reused request-locally so multiple widgets cannot
perform divergent reads.

The initially gated virtual route foundation is also complete. It adds one
strict occurrence query variable and leaf shape, binds the request to the shared exact context,
supports plain permalinks and converts malformed, private, stale or missing
identities to non-cacheable 404 responses without parent redirects. During this
review, ordinary recurring-event persistence was hardened so a normal WordPress
save can no longer replace a recurrence projection with one one-off row. Dirty
recurring parents are excluded from occurrence reads until canonical repair.

The first native presentation adapter is complete. Bundled PHP and block-theme
single output, the core document title, core
canonical and plugin Event JSON-LD now consume one effective occurrence
presentation. It preserves series body, excerpt and taxonomies while applying
occurrence date, status, title, note, image, location and external action. Until
builder context is complete, occurrence leaves intentionally use the correct
native fallback rather than an Elementor template containing series-only values.

Gutenberg occurrence adapter parity is now complete for the existing twelve
atomic fields and composite Event Details block. Current-context blocks consume
the exact validated occurrence presentation, while an existing explicit
`eventId` remains a deterministic public series selection. Query Loop descendants
for another event retain their own context and an invalid occurrence canonical
fails closed. A packaged WordPress 6.9/PHP 8.2 smoke journey proves current title
and date blocks on the exact occurrence leaf.

Elementor occurrence adapter parity is now complete for the existing twelve
atomic widgets and composite Event Details widget. Reconstructed widget objects
reuse the request-shared current resolver, explicit event selections remain
series-owned and the composite shortcode path follows the same rule. An
applicable Elementor Pro single template may own the occurrence leaf again; the
exact native output remains the fallback when no display condition matches.

The REST leaf-context increment is complete. It introduces one exact read-only
`wpse/v2` occurrence resource, sharing the
same eligibility, effective presentation and canonical URL as the browser leaf.
It does not alter the backward-compatible `wpse/v1` calendar feed or core event
REST resource. A real WordPress 6.9/PHP 8.2 journey proves draft-parent denial,
exact anonymous public output, omission of protected storage fields and one
generic unknown-identity 404. Third-party canonical adapter parity is also
complete for the documented Yoast SEO, Rank Math and AIOSEO filters. All three
consume the same validated occurrence URL without becoming plugin dependencies;
the real WordPress smoke page proves their exact filter output. The WordPress Core
sitemap contract is now fixed: only finite active
projection coverage is listed, pages are capped at 100 rows and every candidate
must pass the shared exact public resolver. SEO-plugin sitemap replacement
remains separate optional compatibility work. Exact occurrence browser leaves
use WordPress' standard no-store/no-cache policy, the de facto
`DONOTCACHEPAGE` cache boundary and LiteSpeed's documented no-cache action; this
avoids stale stable-key pages without changing caching for ordinary events or
collections.

Bounded inactive-generation cleanup is now complete. It
records an internal creation timestamp and removes only old, inactive rows for a
clean parent in at most 100-row scheduled batches. It never runs as part of public
rendering and never changes canonical or active-generation state.
A real Elementor Pro leaf-template journey remains a release qualification check.

The occurrence-aware list shortcode, calendar fallback, v1 calendar feed and
native event/taxonomy archives now share occurrence cardinality, effective
presentation and stable leaf URLs. The archive keeps `WP_Query` only as a
templateshell and reports exact occurrence totals without collapsing repeated
series IDs. The native Event details metabox now replaces recurrence-owned
schedule controls with one explicit scope notice while leaving the inherited
series status and shared content editable. Public occurrence routing and
collection reads are enabled by default for the 0.4.0 release.
Cache exclusion is qualified against the documented WP Rocket and LiteSpeed
boundaries. Replacement SEO sitemaps are not a correctness or privacy gate:
WordPress Core is supported, third-party canonicals are exact, and server-rendered
collections remain crawlable.

**Completion — 25 August 2026:** the production Elementor Pro leaf-template
journey, complete PHP 8.2–8.5 matrix, packaged WordPress 6.9/7.1 smoke journeys,
twenty browser regressions, reproducible packaging and strict official Plugin
Check all pass. The 0.4.0 recurrence phase is qualified for publication.

## Phase 3 — 0.5.0 Divi 5 integration

### Goal

Expose the existing composite and atomic event component palette as native Divi 5
modules without copying event, recurrence, query or escaping logic. Divi remains
an optional presentation host and never becomes a core runtime dependency.

The accepted implementation contract and discovery evidence are recorded in
[DIVI-5-INTEGRATION.md](DIVI-5-INTEGRATION.md). The initial spike used Divi
5.11.1 on the disposable `simpleevents.local` environment with Elementor
inactive. It confirmed that:

- the active Divi theme preserves the native event header, main content and
  footer shell;
- Divi Theme Builder already discovers event singles, archives, categories and
  tags;
- individual event editing is available only when `wpse_event` is enabled in
  Divi's Post Type Integration setting;
- the official Divi 5 module contract uses shared `module.json` metadata, a PHP
  frontend callback and a Visual Builder component;
- the implementation now registers all twelve atomic and three composite modules
  without changing native event output outside a deliberately placed Divi module.

### D5-0 — Freeze host and compatibility boundaries

1. Detect Divi 5 through feature checks for its documented module registration
   and Visual Builder asset APIs; never include Divi files directly.
2. Add `wpse_event` to Divi's supported third-party post types. A saved explicit
   `off` choice remains authoritative; a site without a choice receives Divi's
   normal supported-third-party default.
3. Keep the plugin fully functional when Divi is missing, inactive, too old or
   unable to load its Visual Builder. The dormant supported-post-type filter may
   remain registered; no Divi module hooks or assets may load.
4. Record the tested Divi floor and current tested version without promising
   unqualified future majors.

**Exit criteria:** the Events post type can use Divi's normal editor opt-in,
Theme Builder assignments remain unchanged and Divi-absent tests prove zero
fatal errors, hooks or frontend assets.

### D5-1 — Shared module runtime and one vertical slice

1. Register one atomic Event Title module through Divi 5's documented dependency
   tree, module metadata and Visual Builder package hooks.
2. Normalize module attributes through a Divi-owned allowlist adapter and render
   the frontend through `CurrentEventPresentationResolver` and
   `EventFieldRenderer`.
3. Supply a bounded authenticated editor snapshot for atomic fields that supports
   current event/occurrence context and explicit public event selection without
   exposing protected metadata. Query-backed composite previews may add a narrow
   REST route in D5-3 when live filtering requires it.
4. Verify an ordinary page, an event Theme Builder template, an exact recurring
   leaf and save/reload/frontend parity before multiplying the pattern.

**Exit criteria:** one native module proves the complete context, security,
styling, Visual Builder and frontend architecture.

**Current evidence:** Event Title is discoverable and editable in Divi 5.11.1;
current and explicit public sources, title links, heading levels and standard Divi
design controls update live. A temporary ordinary page was saved, reloaded and
rendered through the PHP callback without leaving test data behind. A temporary
All Events Theme Builder body then proved the same empty-source module on a
one-off route and an exact recurring leaf; the leaf kept its occurrence-only title
instead of falling back to the series. All temporary content and assignments were
removed.

### D5-2 — Atomic palette parity

Add native modules for Event Featured Image, Date & Time, Status, Venue, Address,
Location Link, Content, Excerpt, External Action, Categories and Tags. Controls
mirror the existing public presentation contract where the host supports an
equivalent interaction; Divi design controls may be richer, but they may not
change event semantics or private-content rules.

**Exit criteria:** all twelve atomic fields support current context, explicit
public selection, meaningful empty states, theme-inheriting defaults and exact
occurrence presentation in Theme Builder.

**Current evidence:** all twelve modules are present in the Command Center and
native global-preset manager. Date and time, featured image, status and venue were
exercised in the live editor; custom labels and explicit all-day/cancelled sources
updated immediately. The saved Event Venue module preserved its explicit event
and custom label on the public frontend. Event Title proved exact-occurrence
context in Theme Builder; every atomic module delegates source resolution to that
same tested current/explicit presentation boundary.

### D5-3 — Composite palette parity

Add Event Details, Event List / Grid and Event Calendar modules. Their content
attributes delegate to the existing settings adapters and shortcode renderers.
List/calendar queries remain bounded and public-only; visitor filters retain the
accessible no-JavaScript baseline and multiple modules keep unique namespaces.

**Exit criteria:** the three composites match the functional capabilities of
their Elementor counterparts and reuse the shared component CSS without global
theme overrides.

**Current evidence:** Event Details, Event List / Grid and Event Calendar are
native, discoverable modules in Divi 5.11.1. On temporary ordinary pages their
explicit source, visibility, layout, taxonomy and initial-date controls updated
live, survived save/reload and matched public PHP output. Calendar previews now
initialize the existing FullCalendar runtime after dynamic insertion and load the
shared component stylesheet inside Divi's app window. The test pages were deleted
and existing event content was verified unchanged. Theme Builder current context
and exact occurrence identity are proven through the same presentation resolver.
The final role/protected-source matrix returned empty output for draft, private
and password-protected explicit sources and denied subscriber/anonymous preview
access.

### D5-4 — Editor UX and resilience

1. Group content, design and advanced controls consistently with Divi.
2. Condition irrelevant controls, provide concise source/context guidance and
   distinguish empty data from unavailable/private data without leaking details.
3. Abort stale preview requests and debounce query-backed refreshes.
4. Keep editor placeholders editor-only and preserve semantic, escaped frontend
   markup.
5. Verify duplicate, copy/paste, undo/redo, save, reload, responsive modes,
   global presets and multiple-instance behaviour.

**Exit criteria:** modules feel native to Divi, remain understandable without
documentation and do not create stale previews or repeated requests.

**Current evidence:** controls use consistent logical groups and conditional
visibility. Composite previews debounce changes, abort stale requests and expose
generic loading, empty and failure states. Dynamic calendars reuse one idempotent
initializer. Each separately fetched preview namespaces its safe DOM IDs and
local `for`, ARIA and fragment references with the stable Divi module identifier.
Save/reload works for the ordinary-page composites. A disposable-page resilience
pass kept one list and three calendars healthy through duplicate, copy/paste,
undo, redo and reload, with unique Divi module IDs and no duplicate HTML IDs.
Crossing the configured mobile breakpoint after initialization exposed a stale
month view; ADR-080 and a packaged browser regression now cover desktop month →
mobile list → desktop month. Divi deactivation with that saved layout produced no
fatal frontend or native-archive error and the host theme was restored. A final
no-save pass activated tablet and phone canvas states, confirmed the 484-pixel
phone canvas, opened Divi's native global-preset manager, found all fifteen MiMe
modules and expanded the Event Calendar default preset.

### D5-5 — Release qualification

Run the complete repository gates plus a real Divi 5 matrix covering Divi absent,
the supported floor and current tested release; ordinary pages and Theme Builder;
one-off and exact recurring contexts; desktop/tablet/mobile; public, draft,
private and password-protected content; editor roles; frontend asset scope; and
save/reload parity. The licensed Divi package remains a local/CI secret and is
never committed or distributed in the plugin archive.

The matrix must also reproduce permanent browser deletion of a recurring event
and assert that no rows for the deleted parent remain in the occurrence table.
The post-delete guard and bounded repair path now pass API-level, maintenance and
real browser cleanup verification. Temporary Theme Builder assignments, events,
users and occurrence rows were all removed after qualification.

**Exit criteria:** strict Plugin Check, PHP 8.2-current, WordPress 6.9-current,
Divi browser journeys, reproducible packaging and the senior developer/security/
QA reviews all pass. Only then may Divi 5 support be advertised publicly.

**Current evidence — 27 August 2026:** the full real-host Divi 5.11.1 matrix,
protected-content and role checks, packaged browser suite, WordPress 6.9/7.1
smokes, dependency audits, reproducible archive and senior developer/security/QA
reviews pass locally. The 0.5.0 candidate now awaits the official post-commit CI
matrix and strict Plugin Check before tagging and publication.

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
