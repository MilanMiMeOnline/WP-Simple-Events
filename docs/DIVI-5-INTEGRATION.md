# Divi 5 integration contract

**Status:** accepted implementation contract; not yet public support
**Target release:** 0.5.0
**Qualified host floor/current package:** Divi 5.11.1
**Last reviewed:** 27 August 2026

## 1. Outcome

MiMe Simple Events and Calendar will provide native Divi 5 modules for the same
three composite components and twelve atomic event fields available in Elementor.
They work on ordinary Divi pages with an explicit public event and in Divi Theme
Builder templates using the current event or exact recurring occurrence.

Divi is a presentation adapter only. WordPress remains the event editor and owns
all canonical event and recurrence data.

## 2. Discovery evidence

The supplied licensed Divi 5.11.1 theme was installed only on the disposable
`simpleevents.local` site. Elementor was inactive during the spike.

Observed behaviour:

- the native event single and archive render inside one Divi header, main region
  and footer without plugin-specific shell changes;
- Theme Builder exposes All Events, specific Events, the Events archive and event
  category/tag conditions without an adapter;
- Divi Theme Options initially listed Events under **Builder > Post Type
  Integration** as off before the adapter declared it as a supported third-party
  type;
- enabling that option adds **Use The Divi Builder** to Gutenberg's event top bar;
- a Divi-enabled local event opens in the Divi 5 Visual Builder while the native
  event details remain server-rendered by the plugin;
- the first implementation slice now exposes all twelve atomic MiMe modules in
  the Divi Command Center;
- all three composite modules now render through the existing details, list and
  calendar services; an ordinary Divi page proved live control updates,
  save/reload and matching public output for each composite;
- a temporary All Events Theme Builder body proved current-context resolution for
  both a one-off event and an exact recurring occurrence with an occurrence-only
  title override;
- Event Calendar initializes FullCalendar after an authenticated dynamic preview
  is inserted. Its existing public runtime is bundled only into the Visual
  Builder package, while the shared component stylesheet is loaded in Divi's app
  window through the supported package-style hook;
- independently fetched Visual Builder previews namespace their server-rendered
  IDs and local label/ARIA/fragment references with the stable Divi module ID,
  preventing duplicate editor IDs without changing public markup;
- the initial Gutenberg **Use The Divi Builder** click did not transition until
  Divi's own `_et_pb_use_builder` state was set locally. This host interaction is
  a regression target, not a reason to write directly to that metadata in the
  plugin;
- a final no-save Visual Builder pass loaded the existing composite test layout,
  module controls and previews without a plugin or host console error. The
  disposable page accidentally created while entering that journey was
  permanently deleted;
- comparing all module wrappers with Divi 5.11.1's native module metadata exposed
  missing current decoration and advanced keys. All fifteen MiMe modules now use
  the same wrapper/preset shape while preserving their saved settings and the
  earlier `htmlAttributes` compatibility key;
- a real Divi bootstrap snapshot offered only seven public, password-free events
  in every explicit-source selector. Temporary draft, private and
  password-protected fixtures were absent;
- real WordPress REST dispatch proved that administrators and editors can preview
  the active Divi document, but explicit draft, private and password-protected
  sources still return an empty presentation. A subscriber received `403` and an
  anonymous request received `401`;
- anonymous frontend checks returned `404` for draft and private fixtures, omitted
  all three protected fixtures from the calendar feed and archive, and rendered
  only WordPress' password form for the password-protected event—without event
  date, venue or details. All temporary fixtures and QA users were then
  permanently removed.

Official Divi 5 guidance and the current Elegant Themes example extension confirm
the native architecture: `module.json` metadata is shared by frontend and editor,
PHP registers server render callbacks and the Visual Builder registers React
module components. The current atomic palette uses one bounded authenticated
editor snapshot; query-backed composites may use Divi's REST fetch hook.

## 3. Scope

### Composite modules

1. Event List / Grid
2. Event Calendar
3. Event Details

### Atomic modules

1. Event Title
2. Event Featured Image
3. Event Date & Time
4. Event Status
5. Event Venue
6. Event Address
7. Event Location Link
8. Event Content
9. Event Excerpt
10. External Event Action
11. Event Categories
12. Event Tags

### Deliberate exclusions

- Divi 4 shortcode modules or automatic D4 conversion.
- A Divi-specific event or recurrence editor.
- Direct reads from protected event or recurrence metadata in module code.
- Divi dynamic tags as a replacement for the module palette.
- Bundled Divi code, a Divi licence key or updater credentials.
- Automatic Theme Builder template creation or modification.

## 4. Host compatibility

The module adapter loads only when all required Divi 5 feature checks pass.
Detection may read public functions/classes but may not include theme paths. The
dormant supported-post-type WordPress filter can be registered before Divi loads;
an absent host never consumes it. If Divi is absent, inactive, an unsupported
major, partially loaded or replaced by another theme, the core plugin boots
normally and registers no Divi assets or modules.

The first qualified floor is selected from real module API compatibility, not the
theme marketing version. Divi 5.11.1 is both the initial supported floor and the
current tested release for 0.5.0. Earlier Divi 5 builds fail the explicit version
boundary and leave the core plugin operational. A lower floor may be declared
only after running the same Visual Builder and frontend matrix against that
package. Divi 6 remains outside this contract until separately qualified.

The plugin adds `wpse_event` to Divi's supported third-party post types. This
allows Divi's normal default to enable event content editing on a new integration.
An existing explicit `off` value remains off. Theme Builder assignments work
independently and are never created, changed or removed by the plugin.

## 5. Architecture

```text
Divi module attributes / current Divi document
                  |
                  v
        strict Divi settings adapter
                  |
       +----------+-----------+
       |                      |
       v                      v
CurrentEventPresentation   Public explicit event
Resolver                   / occurrence collections
       |                      |
       +----------+-----------+
                  v
 Shared field, details, list and calendar renderers
                  |
                  v
     semantic component-scoped public markup/CSS
```

### PHP boundary

- `DiviIntegration` owns active Divi 5 product detection and hook registration.
- `DiviPostTypeIntegration` owns only Divi's post-type allowlist filter.
- `DiviModuleRegistrar` separately feature-detects the required module classes
  and registers metadata/shared callbacks after Divi's dependency tree is
  available.
- `DiviModuleSettings` normalizes every attribute through explicit allowlists,
  limits and boolean parsing before it reaches shared services.
- atomic render callbacks resolve one `EventPresentation` and call
  `EventFieldRenderer`;
- composite callbacks call the existing event-list, calendar and details
  shortcode renderers with normalized attributes;
- the Divi module container owns Divi decoration/style output, while event markup
  continues to use stable `wpse-` component classes.

### Visual Builder boundary

- one compiled package registers all MiMe modules;
- module definitions and shared editor helpers are source-controlled; generated
  production assets are included in the release package;
- Divi/WordPress/React packages provided by the host stay external and are not
  bundled;
- atomic Visual Builder previews receive only named public presentation values in
  Divi's authenticated app window and never read event REST metadata directly;
- current editorial snapshots and the document ID are localized only after the
  plugin verifies the exact current post and its `edit_post` capability;
- query-backed composite previews use a narrow plugin REST boundary only when
  live filtering requires it; request abort and a small debounce prevent stale
  responses;
- dynamically inserted calendar previews invoke the same idempotent calendar
  initializer used on first page load. The editor package includes that runtime
  because Divi inserts preview HTML after WordPress' normal enqueue decision;
- every independently fetched preview rewrites only its own safe server-generated
  IDs and matching local references with the stable Divi module identifier;
- editor placeholders and errors are concise, non-sensitive and absent from
  frontend output.

### Optional REST preview boundary

Any composite preview route is editor-only and uses an explicit schema. It requires a
logged-in user, a valid REST nonce and the capability appropriate to the previewed
document. Current draft event context may be rendered only to a user who can read
that post. Explicit event selections and collection previews use only eligible
public, password-free events. Inputs are bounded and output is already escaped
shared HTML; no protected metadata keys, recurrence aggregates or arbitrary post
data are returned.

Frontend rendering does not depend on the preview route or JavaScript.

## 6. Context rules

### Ordinary Divi page

Atomic fields and Event Details require an explicit public event. An empty source
shows an editor-only instruction and renders nothing publicly. Event List / Grid
and Event Calendar use their configured bounded collection settings.

### Event Theme Builder template

An empty source resolves the current event. On an exact recurring leaf, it resolves
the validated occurrence presentation, including sparse occurrence overrides and
the stable occurrence URL. An explicit event selection intentionally resolves the
canonical public series and does not inherit the leaf context.

### Individual Divi-built event content

The event body remains normal `post_content`. Atomic modules with an empty source
use the current event. Native/template details outside the content area remain
owned by the plugin or Theme Builder; the module adapter does not suppress them.
Documentation must steer users toward Theme Builder for reusable full event-page
layouts and warn against unintentionally outputting the same field twice.

## 7. Controls and presentation

Content controls preserve the existing normalized values and defaults. Design
controls use Divi's standard decoration groups where they safely style the module
wrapper or a stable field selector. Defaults inherit theme typography, colours
and spacing.

Controls are conditional and field-specific. For example, columns appear only in
grid mode; custom labels appear only when labels are shown; visitor filter controls
appear only when visitor filters are enabled. Unsupported or malformed saved
values fall back to the shared default rather than reaching a query or renderer.

Every module declares Divi 5.11.1's current wrapper settings for meta, advanced
HTML/link/loop/text handling and decoration. Existing `htmlAttributes` metadata is
retained for saved-layout compatibility. Style groups use Divi's native font
preset group and style clipboard category, so global presets and copy/paste remain
owned by Divi rather than duplicated in plugin state. The same contract is
checked across all generated modules and the hand-authored Event Title slice.

Frontend markup remains keyboard-operable, semantic and accessible. Divi styling
may not remove visible focus, convert links into invalid buttons or duplicate DOM
IDs/request namespaces across module instances.

## 8. Module mapping

| Divi module | Shared server boundary | Key content controls |
|---|---|---|
| Event List / Grid | `EventListShortcode` | view, period, limit, terms, card visibility and heading |
| Event Calendar | `CalendarShortcode` | views, initial date/terms, filters and toolbar visibility |
| Event Details | `EventDetailsShortcode` | source, field visibility and labels |
| Event Title | `EventFieldRenderer::title()` | source, heading and link |
| Event Featured Image | `EventFieldRenderer::featured_image()` | source, size, link and alt mode |
| Event Date & Time | `EventFieldRenderer::date_time()` | source, label visibility/text |
| Event Status | `EventFieldRenderer::status()` | source |
| Event Venue | `EventFieldRenderer::venue()` | source, label visibility/text |
| Event Address | `EventFieldRenderer::address()` | source |
| Event Location Link | `EventFieldRenderer::location_action()` | source and link text |
| Event Content | `EventFieldRenderer::content()` | source |
| Event Excerpt | `EventFieldRenderer::excerpt()` | source |
| External Event Action | `EventFieldRenderer::external_action()` | source and link text |
| Event Categories | `EventFieldRenderer::categories()` | source, label visibility/text |
| Event Tags | `EventFieldRenderer::tags()` | source, label visibility/text |

The implementation may share metadata factories and renderer classes, but every
module keeps a stable, namespaced Divi block name so saved layouts remain backward
compatible.

## 9. Security and privacy invariants

- Client attributes and Divi document context are untrusted.
- All enumerations use allowlists; IDs, dates and limits have strict bounds.
- State-changing requests require WordPress capabilities and nonce verification.
- Anonymous rendering never exposes drafts, private or password-protected events.
- A user may not preview another draft merely because its numeric ID was saved in
  a module.
- Invalid exact occurrence context fails closed; it never degrades to series data.
- Query modules reuse bounded public occurrence/event repositories.
- Render callbacks escape at the shared output boundary; Divi wrappers receive
  only escaped class/attribute values.
- No event content, URL, recurrence definition, nonce or licence data is logged.
- Divi theme assets and credentials stay outside Git, release archives and CI
  artifacts.

## 10. Test matrix

The final qualification matrix includes both automated and real-host evidence:

- supported and unsupported Divi host detection;
- all fifteen module metadata, registration, rendering and packaging contracts;
- administrator/editor/subscriber/anonymous preview authorization;
- public, draft, private and password-protected explicit sources;
- ordinary page and exact Theme Builder event/occurrence context;
- multiple instances, save/reload, responsive calendar switching and host-theme
  deactivation resilience;
- public archive, calendar feed, event route, occurrence route and sitemap
  eligibility;
- permanent cleanup of temporary posts, users, assignments and occurrence rows;
- WordPress 6.9/PHP 8.2 and current WordPress/PHP 8.2 packaged smoke journeys.

The final no-save host pass activated Divi's icon-only tablet and phone controls
through their stable host classes, confirmed the expected active device state and
484-pixel phone canvas, and opened the native global-preset manager. All fifteen
MiMe modules appeared there; Event Calendar expanded to its native default preset.
No layout or preset was changed or saved.

### Automated unit/contract tests

- Divi compatibility detection: absent, D4/unsupported, supported D5 and partial
  host APIs.
- Post-type integration: new default on, explicit off preserved and unrelated
  post types unchanged.
- Attribute normalization: malformed types, unknown choices, excessive limits,
  invalid IDs/dates and legacy saved values.
- Context: current one-off, current exact occurrence, explicit public series,
  draft/private/password-protected and invalid occurrence.
- Module metadata names, categories, attributes and generated package manifest.
- Registration and frontend asset scope.

### Real WordPress/Divi browser journeys

1. Divi absent: plugin activation, native event editor and public components.
2. Supported Divi: module category and all fifteen modules are discoverable.
3. Ordinary page: select a public event, modify controls, save, reload and compare
   frontend output.
4. Theme Builder: temporary All Events body with current-context atomic/details
   modules; one-off and exact recurring leaf output; delete template after test.
5. Individual event: use Divi content, save/reload, preserve normal event fields
   and recurrence editor ownership.
6. Collections: list/calendar filtering, pagination, responsive mode, multiple
   modules and no-JavaScript fallback.
7. Access: editor/admin previews, anonymous output, draft/private/password denial
   and no protected REST fields.
8. Resilience: copy/paste, duplicate, undo/redo, responsive modes, stale-request
   abort, empty/error state and Divi deactivation after layouts exist.

### Completed ordinary-page and Theme Builder evidence

Divi 5.11.1 on `simpleevents.local` has proven all fifteen modules are native and
discoverable. Temporary ordinary pages exercised Event Details, Event List / Grid
and Event Calendar with explicit public sources, taxonomy filters and non-default
controls. Live previews updated, saved block comments retained the normalized
attributes, reloaded public pages matched the editor and the calendar initialized
as a real FullCalendar instance. Browser logs contained no plugin error. All
temporary pages were permanently removed and pre-existing event content remained
unchanged.

A later resilience pass placed one list and three calendars on the same temporary
page. Calendar duplication, undo, redo and copy/paste kept every instance ready,
gave every module a distinct Divi identifier and produced no duplicate HTML ID.
Saving and reloading preserved all four modules. The pass exposed that a mounted
calendar kept its desktop month view when a builder canvas crossed to mobile.
ADR-080 now makes configured responsive view changes part of the calendar
lifecycle, with a packaged browser regression covering desktop → mobile list →
desktop month. The local same-version reinstall retained the previous script in
the browser cache, so this individual fix is qualified by the newly built package
test rather than misreported as a live-cache retest.

This evidence completes ordinary-page composite parity. It does not yet qualify
public Divi support on its own.

A temporary **All Events** custom body subsequently proved the empty-source Event
Title module against a canonical one-off event and an exact recurring leaf. The
exact leaf rendered its occurrence-only title instead of the series title, while
the generic layout editor retained the non-sensitive no-context placeholder. The
temporary body, Theme Builder assignment, series post and public occurrence URLs
were removed; the original one-off route and native details were restored. A
post-run inspection found unreachable rows for that deleted test ID in the
derived index. The implementation now repeats cleanup after WordPress deletes the
post, rejects projection activation when the parent disappears and repairs old
orphans in the existing bounded maintenance worker. Local repair reached zero
orphan rows and a trash/permanent-delete regression produced `1 → 1 → 0` rows.
The final matrix still repeats the actual browser deletion flow.

With a saved Divi layout still present, switching the disposable site to Twenty
Twenty-Five left both that page and the native event archive at HTTP 200 without
a fatal error; Divi was restored immediately. The temporary page and its new
auto-drafts were then permanently removed. A subsequent protected-source and
role matrix completed the editor/public denial gate and again removed every
temporary event and user. The final icon-only device/preset interaction pass also
completed without saving. Official post-commit CI and Plugin Check remain release
gates.

A final no-save Visual Builder load on Divi 5.11.1 showed the existing layout,
controls and previews without console errors. The module wrapper and preset
metadata now matches the host's native contract and is covered automatically for
all fifteen modules. A stable class-based interaction then switched tablet and
phone canvas modes, confirmed the phone canvas at 484 pixels, opened Divi's
global-preset manager, found all fifteen MiMe modules and expanded Event Calendar
to its native default preset. Nothing was edited or saved. Responsive calendar
behaviour remains independently covered by the packaged browser regression.

### Release matrix

- WordPress 6.9 and current WordPress.
- PHP 8.2 through current supported PHP.
- Divi supported floor and current tested Divi 5.
- Plugin Check, dependency audits, reproducible archive and Divi-absent smoke.

## 11. Definition of done

Divi 5 support may be advertised only when all fifteen modules pass ordinary-page
and Theme Builder journeys, exact recurrence remains correct, Divi absence is
clean, no licensed code is distributed, all repository/release gates pass and the
manual senior developer, security/privacy and senior QA reviews record no open P1
or P2 defects.
