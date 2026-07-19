# Testing backlog

This backlog records findings from manual acceptance testing. The tester closed the inventory on **2026-07-18**. Items remain **Triaged** until their work package starts; implementation follows `docs/BACKLOG-EXECUTION-PLAN.md` after that plan is approved.

## WPSE-BL-001 — Calendar buttons lose readable state styling

- **Type:** Bug — visual styling, accessibility and Elementor integration
- **State:** Resolved in development on 2026-07-18; pending release qualification
- **Severity:** Major
- **Suggested priority:** P1
- **Affected surfaces:** Native calendar shortcode, Elementor Event Calendar widget, keyboard and pointer interaction
- **Reported:** 2026-07-18

### Observation

On a white background, calendar toolbar labels can become invisible while hovering. With Elementor calendar colors configured, the month/list selection and hover states use an unexpected fill/text combination; the selected month button can appear as an empty colored block. The normal, hover, focus, active and disabled states do not form a predictable visual system.

The Elementor Style panel also shows two controls both labelled `Typography`, making it unclear which one targets the whole calendar and which one targets toolbar buttons. This is a related configuration-clarity improvement to consider with the fix.

### Preliminary technical analysis

The component CSS overrides FullCalendar's button variables with explicit rules. Its hover/active rule sets `background: currentcolor` and also changes `color` in the same computed style. Consequently, `currentcolor` resolves to the new foreground (`--wpse-calendar-on-accent`) instead of reliably using `--wpse-calendar-accent`; background and label can therefore become identical. The explicit rule also bypasses the intended FullCalendar accent variables, so Elementor's `Accent color` and `Accent text color` controls do not map cleanly to the button states.

This appears to be a plugin CSS/control-mapping defect rather than a theme-only conflict.

### Expected behaviour

- Toolbar buttons remain identifiable and readable in normal, hover, keyboard-focus, active and disabled states.
- Hover and active backgrounds use the configured accent color; their labels use the configured on-accent text color.
- The active month/list view is visibly selected without making its label disappear.
- Default styling remains usable on light and dark theme backgrounds while continuing to inherit site typography.
- Elementor's color controls have unambiguous, consistent effects in both the editor preview and frontend.
- Typography controls have distinct labels that identify calendar typography versus button typography.

### Acceptance and regression coverage

- Verify Previous, Next, Today, Month and List in every interaction state with default settings.
- Repeat with contrasting custom Elementor colors and confirm editor/frontend parity.
- Verify visible keyboard focus independently of hover and active state.
- Verify disabled Today remains readable and visually distinct.
- Verify shortcode and Elementor output on light and dark backgrounds and at mobile/desktop toolbar layouts.
- Add CSS/Elementor control-contract regression coverage where practical and a browser-level visual interaction check.

### Evidence

- `codex-clipboard-eb8990cb-20bf-41e7-b76a-2f4849163c29.png` — hovered view button loses its label.
- `codex-clipboard-8958396c-8796-4b9d-9c11-d6016ee1e9fc.png` — selected month/list state with custom Elementor colors.
- `codex-clipboard-629ff457-3180-4212-be94-6a2d192e8e7c.png` — Elementor calendar color and typography controls.
- `tests/E2E/calendar-harness.spec.mjs` — computed-style regressions for normal, hover, pressed, focus-visible, selected, disabled and forced-colors states with custom accent colors.
- `tests/Unit/ElementorWidgetsTest.php` — stable group-control registration with distinct typography labels.

### Resolution

The state rules no longer use `background: currentcolor`. Hover, focus-visible, pressed and selected buttons now use `--wpse-calendar-accent` for their border/background and `--wpse-calendar-on-accent` for text. Defaults follow WordPress contrast/base presets when present and otherwise use adaptive `CanvasText`/`Canvas` system colors. Disabled buttons keep their normal foreground/background contract with reduced opacity, and keyboard focus receives a separate accent outline. Elementor's saved `calendar_typography` and `button_typography` identifiers remain unchanged; only their translated panel labels were clarified.

### Planning note

Decide during backlog planning whether one accent/on-accent pair is sufficient for hover and active states, or whether Elementor should expose separate normal/hover/active button controls. The smallest safe correction is to make the existing accent contract deterministic first.

## WPSE-BL-002 — Empty calendar filter action and unclear opt-in behaviour

- **Type:** Bug and usability improvement — calendar filtering and Elementor configuration
- **State:** Resolved in WP1.3 on 2026-07-18
- **Severity:** Moderate
- **Suggested priority:** P2
- **Affected surfaces:** Calendar shortcode and Elementor Event Calendar widget
- **Reported:** 2026-07-18

### Observation

The calendar can show an `Apply filters` button without any category or tag selector. The action has no effect and suggests that filter controls are missing. Filtering should be optional, and when enabled it should provide useful visitor choices such as event categories and tags.

### Preliminary technical analysis

Category and tag visitor filters already exist in `CalendarControls`, and the Elementor widget already has a `Show filters` switcher. However, filtering defaults to enabled in both the calendar shortcode contract and Elementor. The renderer retrieves only non-empty event terms; when no published event currently uses a category or tag, both term collections are empty but the form and submit button are still rendered.

This is therefore primarily an empty-state rendering defect plus a configuration/defaults question. The Elementor category/tag controls also serve as initial calendar constraints, which can be confused with enabling visitor-facing filters.

### Expected behaviour

- Calendar visitor filters can be explicitly enabled or disabled in Elementor and through the shortcode contract.
- A disabled filter setting renders no filter form or filter action.
- An enabled setting shows category and/or tag selectors when usable non-empty event terms exist.
- If neither taxonomy has a usable option, the complete filter region—including `Apply filters`—is omitted.
- Category/tag choices update the visible calendar and its accessible status message without a full-page reload when JavaScript is available; the GET fallback remains functional without JavaScript.
- Elementor clearly distinguishes initial category/tag constraints from the option to expose those filters to visitors.

### Acceptance and regression coverage

- Verify enabled filters with categories only, tags only and both taxonomies.
- Verify no terms and terms without assigned public events render no empty action.
- Verify disabled filters ignore visitor query parameters belonging to that instance.
- Verify selected filters, reset behaviour, multiple calendar instances and no-JavaScript submission.
- Verify the Elementor editor preview and frontend agree after toggling `Show filters`.
- Verify existing saved widgets retain their stored choice if defaults change for newly created widgets.

### Evidence

- `codex-clipboard-b226699b-40ed-46f6-b24c-227f34786cce.png` — `Apply filters` is visible without any filter field.

### Planning note

During backlog planning, decide whether filters should remain enabled by default for backward compatibility or become opt-in for newly created calendars. Hiding an empty filter form is required independently of that product-default decision.

### Resolution

The existing enabled default was retained for backward compatibility. The renderer omits the entire form when both non-empty public term collections are empty, and renders only the category/tag selectors that have choices. Elementor keeps the existing stable `category`, `tag` and `filters` identifiers while clarifying initial constraints versus visitor-facing controls. The interactive feed now also receives initial category/tag constraints when visitor controls are disabled or unavailable, matching the no-JavaScript fallback.

## WPSE-BL-003 — Same-day timed event incorrectly spans two calendar days

- **Type:** Bug — date/time correctness and time-zone handling
- **State:** Resolved in WP2.1 on 2026-07-19
- **Severity:** Major
- **Suggested priority:** P1
- **Affected surfaces:** Calendar month/list views, calendar event feed and consistency with the single-event page
- **Reported:** 2026-07-18

### Observation

An event configured for 19 July 2026 from 12:05 until 22:05, with the same start and end date, appears in the month calendar on both 19 and 20 July. The single-event page correctly keeps both times on 19 July. The editor reports that the event was saved with time zone `+00:00`.

The `pm` label on the single-event page is not evidence that the end time was moved to the following day: `10:05 pm` is the correct 12-hour representation of `22:05`.

### Preliminary technical analysis

The strongest current hypothesis is an unintended conversion between the event's captured time zone and the visitor browser's local time zone. Timed calendar-feed values include their saved offset—for example, `2026-07-19T22:05:00+00:00`—while the FullCalendar client is not given an explicit time-zone contract. FullCalendar can therefore interpret and display the instant in the browser's local zone. In Europe/Brussels on this date, 22:05 UTC becomes 00:05 on 20 July, causing a same-day event to cross midnight visually.

This explains the screenshots and the discrepancy with the server-rendered single-event page, but must be confirmed with a controlled browser-time-zone regression test before implementation. The fix must also account for the possibility that different events retain different captured time zones; forcing one global zone without defining the product contract may introduce a different error.

### Expected behaviour

- A timed event whose start and end fall on the same saved event date appears on that one date in all calendar views.
- The calendar and single-event page agree about the event's dates and times.
- A visitor's browser time zone does not silently move an event to another calendar day unless an explicitly documented visitor-local-time feature is introduced.
- Genuine overnight and multi-day events continue to span the correct dates.
- All-day events retain their exclusive-end-date semantics and are not shifted by time-zone conversion.
- The chosen event/site/visitor time-zone display contract is documented and applied consistently.

### Acceptance and regression coverage

- Reproduce with an event saved at `+00:00` and a browser set to `Europe/Brussels`, including an end time that crosses midnight only after conversion.
- Repeat with positive and negative browser offsets, IANA zones and fixed-offset site settings.
- Cover events close to midnight, same-day events, genuine overnight events, multi-day events and all-day events.
- Cover DST transitions in representative European and North American IANA zones.
- Compare month view, list view, calendar feed and single-event output for the same event.
- Verify that mixed captured event time zones do not corrupt date placement.

### Evidence

- `codex-clipboard-fcde883c-31b2-4f5c-b614-081327ec1833.png` — one loaded event is rendered on 19 and 20 July.
- `codex-clipboard-03b2b6ba-caa1-4957-af65-734d1cf6e38b.png` — single-event output keeps both times on 19 July.
- `codex-clipboard-0d2d4bc9-23ec-432d-b540-ca4a13e692cd.png` — editor values are 19 July, 12:05–22:05, captured at `+00:00`.

### Planning note

Define whether public calendars preserve each event's saved wall-clock date/time or convert all events to one documented site zone. The current product behaviour implies preservation of the event's captured zone. Choose the FullCalendar feed and client configuration only after that contract is explicit; a blanket `UTC` setting is not automatically safe for mixed event zones.

### Resolution

ADR-024 makes captured event wall time authoritative for calendar placement. The feed supplies floating canonical local values to FullCalendar, while timed records retain their stored timezone and offset-bearing start/end instants as explicit metadata. Calendar windows are validated as bounded, day-aligned wall-time ranges and queried against canonical local metadata, preventing browser offsets and mixed event zones from omitting or moving records. Browser regressions reproduce the original `+00:00` case in `Europe/Brussels`, repeat it in `America/Los_Angeles`, cover `±14:00` boundary fixtures and compare month, list and native single-event output.

## WPSE-BL-004 — Make 12/24-hour time presentation consistent and configurable

- **Type:** Improvement — formatting, localisation and configuration
- **State:** Resolved in WP2.2 on 2026-07-19
- **Severity:** Moderate
- **Suggested priority:** P2
- **Affected surfaces:** Single-event details, event lists/cards, calendar month/list views, Elementor widgets and editor guidance
- **Reported:** 2026-07-18

### Observation

The editor accepts `22:05`, while the single-event page displays `10:05 pm`. Although this conversion is correct, the difference can look like a data error—especially alongside the separate calendar date-shift defect. A clear and consistent choice between 24-hour and 12-hour display is desirable.

### Preliminary technical analysis

Server-rendered event details already use WordPress's global `time_format` option, so the observed `pm` output most likely follows the site's current General Settings. The calendar client does not currently receive an explicit equivalent of that WordPress format and can fall back to its own locale/browser formatting. The native editor `time` input stores a 24-hour value, but its visible controls are browser/platform dependent.

Time presentation must remain separate from time storage and time-zone conversion: changing 12/24-hour notation may not change saved timestamps, query indexes, event duration or calendar-day placement.

### Expected behaviour

- The default public time notation inherits WordPress's configured time format.
- All plugin-rendered surfaces use a consistent 12-hour or 24-hour presentation for the same setting.
- If plugin or widget overrides are added, their choices are explicit: `Inherit WordPress`, `24 hour` and `12 hour`.
- Midnight and noon are represented unambiguously in 12-hour mode; 24-hour mode never adds AM/PM labels.
- Machine-readable values, REST/feed dates and structured data remain standards-based and independent of visual notation.
- Editor help makes clear that a native time control's appearance may follow the browser while the saved value remains equivalent.

### Acceptance and regression coverage

- Verify WordPress formats such as `H:i` and `g:i a` across single details, lists/cards, calendar month view and calendar list view.
- Verify optional Elementor/plugin overrides, if adopted, in both editor preview and frontend.
- Cover midnight, noon, leading-zero behaviour and translated AM/PM output.
- Verify locale changes do not override an explicit format choice unexpectedly.
- Confirm switching notation does not change stored event data, duration, query results or date placement.

### Evidence

- `codex-clipboard-03b2b6ba-caa1-4957-af65-734d1cf6e38b.png` — public output uses 12-hour notation.
- `codex-clipboard-0d2d4bc9-23ec-432d-b540-ca4a13e692cd.png` — editor shows the equivalent 24-hour values.

### Planning note

Prefer WordPress General Settings as the zero-configuration default. During backlog planning, decide whether a global plugin override is genuinely needed and whether Elementor needs a per-widget override. Avoid adding a redundant setting unless users need different notation from the rest of the site.

### Resolution

Calendar configuration now derives a bounded FullCalendar format from the active WordPress `time_format`. Explicit `h12`/`h23` hour cycles prevent the browser locale from overriding WordPress' 12/24-hour choice; `H/h` preserve leading-zero intent, `G/g` remain unpadded, and `a/A` retain the intended meridiem mode while allowing localized browser output. Server-rendered details and cards continue to use `wp_date()`.

The editor now explains that its native time control can look different per browser while the saved canonical value remains equivalent. No duplicate global plugin setting and no Elementor override were added. Unit coverage protects PHP escaping and malformed fallbacks; real WordPress/Chromium journeys verify midnight and noon output for `H:i` and `g:i a` without changing feed dates or event placement.

## WPSE-BL-005 — Editable external-link label per event

- **Type:** Improvement — editorial flexibility and link clarity
- **State:** Resolved in WP3.1 on 2026-07-19
- **Severity:** Minor
- **Suggested priority:** P2
- **Affected surfaces:** Event editor, single-event action link, metadata/REST contract and Elementor single-event rendering
- **Reported:** 2026-07-18

### Observation

When an external event URL is entered, the single-event page always labels the action `More event information`. That text is not suitable for every destination. The same URL field may point to registration, a programme, practical information or a parking plan, so editors need to describe the actual action per event.

### Preliminary technical analysis

The external URL is stored as `_wpse_event_url`, but the renderer currently hard-codes the translated `More event information` label. There is no corresponding label field in the input, validation, persistence or metadata contracts.

This can remain a small extension: add one optional plain-text label associated with the existing external URL. Existing events and events with an empty custom label should continue to use the current translated default. The label must be treated as untrusted text, length-bounded, sanitized on input and escaped on output; HTML in button text is unnecessary.

### Expected behaviour

- The event editor provides an optional label next to the external event URL.
- An editor can use descriptive text such as `View parking plan`, `Register now` or `View programme`.
- A non-empty custom label is shown for that event's external action link.
- An empty label falls back to the translated `More event information` default, preserving existing events.
- No action link or orphaned label is rendered when the external URL is empty.
- The link remains semantically an anchor and its accessible name clearly describes its destination.
- The custom label contains plain text only and cannot inject markup or scripts.

### Acceptance and regression coverage

- Save, reload, revise and render an event with a custom external-link label.
- Verify the empty-label fallback for both new and existing events.
- Verify a label without a URL does not render an action.
- Verify trimming, maximum length, special characters, translations and malicious HTML input.
- Verify metadata REST permissions, revisions and all existing save-validation paths.
- Verify consistent output in the native single template and any Elementor component that renders event details.

### Evidence

- `codex-clipboard-094f21e7-8414-4676-81cf-40fbc5b5d88b.png` — the external action always displays `More event information`.

### Planning note

Keep this item scoped to one customizable label for the existing single external URL. Supporting multiple independently labelled resources—for example registration, parking and programme links on the same event—would require a separate product decision and a larger repeatable-link data model.

## WPSE-BL-006 — Calendar is malformed on initial page load until its view changes

- **Type:** Bug — frontend initialization, responsive layout and Elementor integration
- **State:** Resolved in development on 2026-07-18; pending release qualification
- **Severity:** Major
- **Suggested priority:** P1
- **Affected surfaces:** Calendar shortcode and Elementor Event Calendar widget on initial frontend load
- **Reported:** 2026-07-18

### Observation

On a fresh page load, the calendar toolbar is present but the month grid is severely malformed: weekday headings overlap, the grid dimensions are incorrect and events appear at unrelated vertical positions. Clicking the Month or List view button causes the calendar to render correctly. Reloading the page returns it to the broken state.

### Preliminary technical analysis

The calendar canvas is server-rendered with the HTML `hidden` attribute. JavaScript constructs FullCalendar and immediately calls `calendar.render()` while that canvas is still hidden. The asynchronous event loader removes `hidden` only after the feed succeeds, but no FullCalendar size recalculation follows. FullCalendar therefore performs its initial measurements against a non-rendered element and retains invalid dimensions until a view change triggers a fresh layout.

This is a strong code-supported cause rather than an Elementor styling issue, although Elementor's own delayed/responsive layout can further expose the defect. The malformed active-view button visible in the same screenshot remains covered separately by WPSE-BL-001; the empty filter action remains WPSE-BL-002.

### Expected behaviour

- A calendar is correctly laid out on its first visible render without visitor interaction.
- Loading and empty/error states do not expose a half-rendered or incorrectly measured calendar.
- Revealing the calendar after its data arrives triggers a reliable render or size update at the correct moment.
- The layout remains correct when embedded in Elementor containers, responsive columns, tabs or other containers that become visible later.
- Switching Month/List and resizing the viewport continue to work without cumulative layout corruption.
- Multiple calendars on one page initialize independently.

### Acceptance and regression coverage

- Hard-reload a frontend page directly into month and list initial views and verify the first rendered frame after loading.
- Test slow, immediate, empty and failed calendar-feed responses.
- Test shortcode and Elementor widget at desktop, tablet and mobile breakpoints.
- Test initially hidden containers where practical, including an Elementor tab/accordion or equivalent visibility transition.
- Resize the viewport and toggle Month/List repeatedly after initialization.
- Verify two or more calendar instances on one page.
- Add a browser-level regression asserting usable seven-column geometry before any calendar control is clicked.

### Evidence

- `codex-clipboard-4ff0e9f0-f43a-41c2-9ee2-799172f23fae.png` — malformed first-load month grid that is corrected by changing the view.
- `tests/E2E/calendar-harness.spec.mjs` — browser regressions for initial geometry, delayed/error feeds, multiple instances and hidden-container recovery.

### Resolution

The calendar canvas is revealed before FullCalendar performs its first measurement. The no-JavaScript/server fallback remains available until the initial feed succeeds and is restored if that feed fails. A component-scoped `ResizeObserver` calls FullCalendar's size update when a calendar host changes from zero to a measurable width, covering delayed Elementor/tab/accordion layouts without adding an Elementor runtime dependency.

### Planning note

The smallest likely fix is to avoid measuring while the canvas is hidden and to request a FullCalendar size recalculation immediately after it becomes visible. The implementation plan should also decide whether a `ResizeObserver` or Elementor visibility hook is needed for calendars placed in containers that can remain hidden beyond the initial data load; do not add continuous observers unless the simpler lifecycle correction fails those cases.

## WPSE-BL-007 — Make the active event time zone clear in settings and public output

- **Type:** Improvement — administration clarity, internationalisation and date/time presentation
- **State:** Resolved in WP3.2 on 2026-07-19
- **Severity:** Moderate
- **Suggested priority:** P2
- **Affected surfaces:** Event settings, event editor guidance, single-event details and Elementor Event Details widget
- **Reported:** 2026-07-18

### Observation

It is not readily apparent which time zone WP Simple Events uses. Administrators may expect a plugin-specific setting, while international visitors cannot currently see which zone applies to a displayed event time. This becomes particularly important when an event audience spans multiple time zones.

### Preliminary technical analysis

The plugin already uses WordPress's configured time zone for a new event through `wp_timezone_string()`. It stores that value as `_wpse_timezone` when the event is saved. Existing events deliberately retain their captured time zone when the WordPress site setting changes, protecting their intended wall-clock time.

The native event editor already includes a small readonly message containing the captured zone and explaining that existing events retain it. The plugin Settings page does not show the current site zone or explain the capture behaviour. Public date details format in the captured event zone but do not label it. Structured data and calendar-feed values already carry standards-based offsets.

This should not become a second, competing site-time-zone setting. The administration screen should report the authoritative WordPress setting and link administrators to WordPress General Settings. For visitor output, an IANA identifier such as `Europe/Brussels` together with the offset applicable on the event date is safer than an ambiguous abbreviation alone. WordPress fixed offsets remain supported but cannot account for daylight-saving changes.

### Expected behaviour

- Event settings clearly show the current WordPress site time zone used for newly created events.
- The explanation states that each event captures its zone and that existing events do not automatically change when the site zone changes.
- Administrators receive a direct, capability-safe link to the WordPress General Settings screen instead of a duplicate time-zone control.
- A fixed-offset configuration is identified as having no daylight-saving behaviour.
- Timed single-event output can show the event's captured time zone in an unambiguous, translatable format.
- Public time-zone visibility can be configured without altering saved dates or time-zone data; all-day events do not receive a misleading time-zone label.
- Native and Elementor event-detail output follow the same visibility and formatting contract.
- The displayed UTC offset is calculated for the event date, not for the current date.

### Acceptance and regression coverage

- Verify a new event captures the current WordPress IANA zone and an existing event retains its saved zone after the site setting changes.
- Verify the Settings and event-editor explanations for both IANA zones and WordPress fixed offsets.
- Verify access and links for administrators while users without `manage_options` cannot reach plugin settings.
- Verify public output with visibility enabled and disabled, including Elementor editor/frontend parity.
- Cover winter/summer offsets, DST boundaries, multi-day timed events and all-day events.
- Test translated output and long IANA identifiers without breaking responsive layouts.
- Confirm REST/feed and structured-data machine values remain unchanged by the visual setting.

### Resolution

Events Settings now reports WordPress' authoritative IANA zone or numeric offset, explains capture and retention semantics, warns that fixed offsets have no DST behaviour and exposes the General Settings link only to administrators. A strictly sanitized global display option defaults to off.

When enabled, the shared native/Elementor details renderer adds an escaped timezone label for timed events. The formatter uses offsets at the actual event boundaries, shows both across European and North-American DST transitions, supports fixed offsets and long IANA identifiers, and omits all-day labels. Calendar, feed, card and structured-data contracts remain unchanged. Unit tests cover settings sanitization, permissions, site-zone capture/retention, winter/summer/DST/fixed labels and cleanup; real-WordPress smoke coverage verifies settings persistence, native public output and unchanged JSON-LD instants.

### Planning note

Decide whether public time-zone visibility is a global display setting, an Elementor override, or both, and choose a backward-compatible default. Also decide whether international editors require a true per-event time-zone selector in the native editor. The current native workflow intentionally inherits the site zone and displays it readonly; making it editable is a larger behavioural choice than merely exposing it to visitors.

## WPSE-BL-008 — Atomic event-field components for Elementor and Gutenberg templates

- **Type:** Feature epic — template building, Elementor integration and Gutenberg blocks
- **State:** In progress; WP4 foundation completed 2026-07-19, WP5/WP6 pending
- **Severity:** Major product gap
- **Suggested priority:** P2
- **Affected surfaces:** Elementor widget library, Gutenberg editor/Site Editor, single-event templates and shared frontend render services
- **Reported:** 2026-07-18

### Observation

Elementor currently exposes Event List / Grid, Event Calendar and one composite Event Details widget. The composite widget renders the complete event in a fixed internal order, so a designer cannot independently place the date, address, featured image or other event fields in a custom layout. Equivalent public Gutenberg blocks do not exist; the current `wpse/native-single` and `wpse/native-archive` blocks are internal full-template bridges rather than editable field components.

### Preliminary technical analysis

This should be implemented as one shared field-presentation layer with thin Elementor and Gutenberg adapters. Copying metadata reads, permissions, date formatting and markup into every host would create inconsistent behaviour and a large security/test surface. Both hosts should use the same access-aware resolver for either current-event context or a bounded explicit public-event selection; Gutenberg remains dynamically rendered on the server.

The screenshot also indicates Elementor Free: its Single/Theme Builder functionality is marked as a Pro upgrade. That does not require a different widget implementation. The same atomic component can render the current queried event in a template context or an explicitly selected public event on a static page. Template assignment/routing belongs to the host and is not a responsibility WP Simple Events needs to reproduce. For Gutenberg, the same distinction applies: block context can supply the current event in a Site Editor template, while an explicit public event selection makes the component usable on an ordinary page.

### Proposed component set

The first complete palette should cover the event's public building blocks without exposing raw metadata:

- Event title.
- Featured image, including alt text, link and image-size controls.
- Date and time, including all-day/multi-day formatting and the decisions from WPSE-BL-004 and WPSE-BL-007.
- Event status.
- Venue.
- Address.
- Location link.
- Event content and excerpt.
- External event action, including the editable label proposed in WPSE-BL-005.
- Event categories.
- Event tags.

Empty optional fields should render nothing on the frontend and an explicit, non-public placeholder in editors. Components may offer label visibility/custom label controls where meaningful, but must inherit theme typography and colors by default.

### Expected behaviour

- Designers can place, order and style individual event fields independently in Elementor and Gutenberg.
- Each component supports both a current event supplied by its page/template context and an explicitly selected public event for static-page use and editor preview.
- A component never falls back to an unrelated event on the public frontend.
- Draft, private, password-protected and non-event content cannot be exposed through preview selectors or explicit IDs.
- Missing optional values create no empty frontend wrapper, spacing or orphaned label.
- Elementor and Gutenberg produce equivalent semantic, accessible output from the same field renderer.
- Core-owned fields such as title, content and featured image retain normal WordPress behaviour, including image alt text and content filtering, without recursive rendering.
- Blocks/widgets provide appropriate host-native style controls but remain usable without custom styling.
- Existing composite Event Details, list, calendar, shortcodes and native templates remain backward compatible.

### Acceptance and regression coverage

- Build the same custom single-event layout in Elementor Pro Theme Builder and a block-theme Site Editor template and compare all field output.
- Verify Elementor Free on a normal page with an explicitly selected event and Elementor Pro Theme Builder with the current event; field output must be equivalent.
- Verify current-event resolution, bounded editor previews and empty-preview placeholders in both hosts.
- Test every field when present and absent, including malicious stored text/URLs and corrupt metadata.
- Test published, authorized preview, draft, private, password-protected and non-event contexts.
- Cover all-day, same-day, multi-day, exceptional status, time-zone and 12/24-hour formatting cases.
- Verify image size, alt text, responsive image attributes and optional permalink behaviour.
- Verify content rendering cannot recursively invoke the containing event template or component.
- Verify block serialization, server rendering, revisions and editor/frontend parity across supported WordPress and Elementor versions.
- Confirm assets are loaded only when required and that adding several atomic fields does not repeat queries or metadata work unnecessarily.

### Evidence

- `codex-clipboard-98a40ec1-23ba-4b84-9254-6c020a291ba0.png` — only the three composite WP Simple Events widgets are available; Elementor Single template functionality is shown as a Pro upgrade.

### Planning note

Treat this as an epic and deliver it in phases: first define/test shared event-context and field renderers, then add Elementor adapters, then Gutenberg dynamic blocks and template patterns. Decide before implementation whether discoverable dedicated widgets/blocks are preferred for every field or whether closely related fields should use a configurable component; avoid one generic raw-meta widget. Template assignment remains host-owned and outside plugin scope; the components themselves must work identically on static pages and in dynamic templates.

### WP4 foundation progress — 2026-07-19

The shared foundation is complete. Current/template context and explicit public selection now use one resolver with request-local presentation reuse. Named field methods cover the complete proposed palette, keep metadata keys private, omit missing/corrupt output and centralize escaping, password protection and recursion handling. The existing composite native/shortcode/Elementor details output is rebuilt from this layer. The BL-008 epic remains open until the dedicated Elementor widgets and Gutenberg blocks in WP5/WP6 are delivered.
