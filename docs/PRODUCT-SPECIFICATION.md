# Simple Events by MiMe — product specification

**Status:** normative product and technical contract
**Last reviewed:** 21 July 2026
**Current release candidate:** 0.2.2
**Maintainer:** MiMe

This document defines what Simple Events by MiMe must do and the boundaries it
must preserve. Detailed implementation contracts, decisions and QA evidence are
linked from [the documentation index](README.md).

## 1. Product purpose

Simple Events by MiMe is a focused WordPress event-publishing plugin. It gives
editors a native Events area, adds event-specific dates and location fields, and
provides lists, grids, a calendar, Gutenberg blocks and optional Elementor
widgets.

The plugin must remain useful without WooCommerce, Elementor or another service.
It should feel like publishing a WordPress post, not operating a separate event
platform.

### Product principles

- Native WordPress content and APIs are the default.
- The editor experience is familiar, lightweight and reversible.
- Themes own typography and broad visual styling by default.
- Public queries are bounded and never disclose unpublished or locked content.
- Accessibility, translation, privacy and security are release requirements.
- Optional integrations enhance presentation; they never become core
  dependencies.

## 2. Scope

### Included

- A public `wpse_event` custom post type with its own Events admin menu.
- Title, content, excerpt, featured image, author and revisions.
- Timed, all-day, same-day and multi-day one-off events.
- Venue, postal address, location link and external action link.
- A customizable external action label per event.
- Scheduled, postponed and cancelled event states.
- Separate event categories and event tags.
- Native single-event and archive presentation.
- Public list, grid and month/list calendar views.
- Category and tag filters.
- Shortcodes, dynamic Gutenberg blocks and an event block pattern.
- Optional Elementor composite and atomic widgets.
- Event JSON-LD on eligible public event pages.
- Safe duplication and explicit uninstall-data ownership controls.

### Deliberately excluded from version 1

- Recurring events or occurrence editing.
- Interactive maps, geocoding, coordinates or map providers.
- Ticketing, registration, payments or attendee management.
- External calendar synchronization.
- A custom database table.

A repeated real-world event is represented by separate WordPress event posts.
The duplicate action may help editors create them, but it must never invent a
recurrence model.

## 3. Platform and identity

| Item | Contract |
|---|---|
| Plugin name | Simple Events by MiMe |
| Author | MiMe |
| Directory, slug and text domain | `simple-events-by-mime` |
| Namespace | `MiMe\WPSimpleEvents` |
| WordPress-global prefix | `wpse_` |
| Event post type | `wpse_event` |
| Category taxonomy | `wpse_event_category` |
| Tag taxonomy | `wpse_event_tag` |
| Event meta prefix | `_wpse_` |
| REST namespace | `wpse/v1` |
| Minimum WordPress | 6.9 |
| Minimum PHP | 8.3 |
| Optional Elementor minimum | 3.35 |
| Licence | GPL-2.0-or-later |

WooCommerce and Elementor are optional. Their absence must not create an admin
notice, break activation or disable core event functionality.

## 4. Native WordPress content model

Events use WordPress posts rather than a custom table. Standard content maps as
follows:

| Content | WordPress field |
|---|---|
| Name | `post_title` |
| Main description | `post_content` |
| Summary | `post_excerpt` |
| Image | featured image |
| Publication state | `post_status` |
| URL | `post_name` and the configured archive slug |
| Owner | `post_author` |
| History | revisions |

The WordPress publication date is not the event date.

### Event metadata

The plugin stores independent, registered metadata values for:

- canonical local start and end;
- comparable UTC start and end indices;
- the captured event timezone;
- all-day state;
- venue and address;
- location URL;
- external event URL and its optional label;
- event status.

Metadata must not be stored as one opaque serialized event object. Registered
metadata has an explicit type, sanitization callback and authorization callback.

### Taxonomies

Event categories are hierarchical. Event tags are non-hierarchical. They remain
separate from blog categories and tags so archives, counts and filters do not mix
unrelated content.

## 5. Date, time and timezone contract

1. Editors enter local wall time.
2. New events capture the current WordPress site timezone.
3. Existing events keep their captured timezone if the site setting changes.
4. UTC values support chronological comparison and query indices.
5. Canonical local values keep calendar placement stable across visitor browser
   timezones.
6. Public formatting follows WordPress date and time formats.
7. Calendar presentation derives a safe 12- or 24-hour configuration without
   changing stored values.

### Validation

- A valid start is required before publication.
- Drafts may remain incomplete.
- A missing end is normalized to the start.
- An end may not precede its start.
- All-day events do not expose a time.
- Multi-day all-day input is inclusive in the editor and converted to the
  calendar library's exclusive end where required.
- Invalid or ambiguous input never silently becomes another date.

An event is current or upcoming while its normalized end is greater than or equal
to now. This keeps an event visible while it is in progress.

## 6. Editorial experience

The Events admin area contains:

- All Events;
- Add New Event;
- Event Categories;
- Event Tags;
- Settings.

The WordPress editor contains an Event details panel with:

- all-day toggle;
- start date and time;
- optional end date and time;
- captured timezone information;
- venue;
- address;
- HTTP(S) location URL;
- scheduled, postponed or cancelled state;
- HTTP(S) external event URL;
- optional external action label.

Publication errors must identify the exact invalid fields. Saving, autosaving,
REST updates, Quick Edit and other WordPress paths must enforce the same invariant
instead of relying only on browser validation.

The Events list provides useful start, end, all-day, location, category, event
state and publication information, with practical date, category and state
filters. Duplicate Event creates a draft and marks date values for editorial
review.

## 7. Permissions and mutations

Events use dedicated capabilities mapped through WordPress roles. Installation
grants the intended administrator and editor permissions without changing visitor
access.

Every privileged mutation must:

1. verify the relevant capability;
2. verify a nonce for browser-originated state changes;
3. validate the expected shape;
4. sanitize at the input boundary;
5. fail closed on ambiguity.

Hidden fields and client-side controls are never authorization mechanisms.

## 8. Public visibility and queries

Public plugin collections include only published, password-free events. Draft,
pending, private, trashed and password-protected event details must not appear in:

- archives;
- lists or grids;
- calendar feeds;
- public block or widget selection lists;
- JSON-LD;
- public plugin REST output.

Core REST metadata for a published password-protected event must remain absent
until WordPress considers the password requirement satisfied. Authorized editors
using edit context retain normal access.

Queries are bounded, use WordPress APIs and apply deterministic ordering. Public
filter values are allowlisted and normalized. The complete rules are in
[PUBLIC-QUERY-CONTRACT.md](PUBLIC-QUERY-CONTRACT.md).

## 9. Native front end

### Single event

The fallback single template can display:

- title and featured image;
- status when postponed or cancelled;
- localized date and time;
- optional timezone label;
- venue, address and location action;
- main content;
- external event action;
- categories and tags.

Location and external actions open in a new tab with `noopener noreferrer` and no
referrer. Empty values produce no empty wrapper.

### Archive

The native archive supports upcoming, past and all-event periods, pagination and
the configured event archive slug. It remains functional without JavaScript.

### Template ownership

The plugin supplies safe native fallbacks for classic and block themes. A theme
may override them through documented WordPress template rules. Elementor Free
widgets work on ordinary pages. Elementor Pro users may build dynamic event
templates with Theme Builder, but Pro is not required by this plugin.

## 10. Lists, grids and shortcodes

`[wpse_events]` provides bounded event collections with:

- list or grid layout;
- upcoming, past or all periods;
- ascending or descending chronological order;
- category and tag constraints;
- optional visitor filters;
- bounded items per page and pagination;
- deliberate empty-state copy.

`[wpse_event_details]` displays the normalized detail group for the current or an
explicit eligible event.

Multiple instances on one page must use unique IDs and must not share accidental
state.

## 11. Calendar

`[wpse_calendar]` provides responsive month and list views. Desktop and mobile
defaults are configurable per instance. Category and tag filters are optional and
hidden when there are no meaningful choices.

Calendar requirements:

- same-origin public REST requests only;
- bounded date windows and result counts;
- canonical local dates to prevent browser-timezone day shifts;
- accessible buttons, labels, focus states and keyboard operation;
- theme-compatible controls with scoped CSS;
- a useful server-rendered upcoming-event fallback;
- clear loading, empty and error states;
- no remote JavaScript, stylesheet, font or map dependency.

The calendar library is bundled locally. Its licence, version and removal cost are
recorded in the third-party notices and decision log.

## 12. Gutenberg

Server-rendered Gutenberg blocks provide:

- event title;
- featured image;
- date and time;
- status;
- venue;
- address;
- location link;
- content;
- excerpt;
- external action;
- categories;
- tags.

On a static page, an editor may select a published event. In an event template or
query context, a block may use the current event. The plugin also provides a
single-event pattern composed from these blocks.

Editor previews and front-end rendering must share the same eligibility,
sanitization and presentation services.

## 13. Elementor

Elementor 3.35 or newer conditionally adds:

- Event List / Grid;
- Event Calendar;
- Event Details;
- the same twelve atomic event fields available in Gutenberg.

Widgets work on normal Elementor Free pages and may explicitly select a published
event. Dynamic event context is available to templates where Elementor provides
it. Controls must expose only bounded settings and must not duplicate query,
formatting or security logic.

The integration is registered only through supported Elementor hooks. It must not
modify the user's Elementor settings automatically.

## 14. Styling and accessibility

Plugin CSS is component-scoped. Font families, broad color choices and general
spacing inherit the active theme unless a component needs a minimal accessible
default.

Required accessibility behaviour includes:

- semantic headings, lists, time elements and links;
- keyboard-reachable controls;
- visible focus styles;
- sufficient default contrast;
- accessible names and state announcements;
- no color-only status communication;
- responsive layouts without horizontal page overflow;
- reduced-motion respect where motion exists.

Elementor style controls may override plugin variables within the widget scope.
They must not create global button or typography rules.

## 15. SEO and structured data

Eligible individual public events may output one Event JSON-LD document. It uses
the canonical URL, localized dates with timezone offsets, location information,
image and event status when available.

JSON-LD is disabled for unpublished, protected or invalid events. Administrators
can disable it when an SEO plugin already supplies equivalent Event data. Output
is encoded safely rather than assembled through HTML string concatenation.

## 16. Privacy and security

The runtime plugin:

- creates no visitor cookie or browser storage;
- collects no telemetry or analytics;
- contacts no MiMe or vendor service;
- loads no remote runtime asset;
- stores event data in the site's own WordPress database;
- performs no geocoding or map lookup;
- logs no secrets, nonces, personal data or full request bodies.

Editor-entered public event information may appear in front-end HTML, JSON-LD,
WordPress REST responses and the bounded calendar feed. Documentation must make
that disclosure boundary clear.

Every external value is untrusted. URLs are restricted to HTTP(S), input is
sanitized, and output is escaped for its exact context. Raw SQL, dynamic includes,
unsafe deserialization, `eval`, shell execution and remote code are prohibited.

See [SECURITY-PRIVACY-AUDIT.md](SECURITY-PRIVACY-AUDIT.md) and
[SECURITY.md](../SECURITY.md).

## 17. Settings and data ownership

The settings page provides only product-level controls:

- archive slug;
- events per archive page;
- default archive period;
- captured site timezone information;
- optional public timezone display;
- Event JSON-LD toggle;
- explicit uninstall cleanup opt-in.

Deactivation never deletes content. Uninstall preserves events, terms and settings
unless an administrator enabled the clearly warned cleanup option. Shared Media
Library files remain untouched. Cleanup is capability-protected, fail-safe and
batched through WordPress APIs. The complete contract is in
[UNINSTALL-CONTRACT.md](UNINSTALL-CONTRACT.md).

## 18. Performance and maintainability

- No custom table is introduced for version 1.
- Date comparison uses normalized metadata indices.
- Public queries are bounded and avoid unbounded `posts_per_page=-1` behaviour.
- Assets load only where their component is rendered or administered.
- Production dependencies require a documented purpose, licence, maintenance
  status and removal cost.
- Storage identifiers remain backward compatible throughout the stable major
  version.
- Intentional product changes require an entry in
  [DECISIONS.md](DECISIONS.md).

## 19. Quality strategy

### Automated coverage

- Unit tests cover date ranges, timezones, DST boundaries, validation, status,
  formatting, query criteria and settings.
- WordPress integration smoke tests cover registration, persistence, permissions,
  REST behaviour, public visibility, templates, shortcodes, feeds and blocks.
- Browser tests cover critical editor and visitor journeys.
- Compatibility checks cover WordPress 6.9 and the current tested WordPress line,
  plus the declared Elementor range where applicable.
- Release tooling verifies archive contents, PHP syntax, autoloading, checksums,
  reproducibility and WordPress Plugin Check.
- Dependency audits block high or critical findings unless a documented risk
  acceptance exists.

### Required release gates

```text
composer validate --strict
composer qa
npm run qa
npm run test:release
supported WordPress smoke matrix
official WordPress Plugin Check in CI
```

Manual release QA follows [QA-CHECKLIST.md](QA-CHECKLIST.md).

## 20. Acceptance criteria

The version 1 product contract is satisfied when:

1. an authorized editor can create and publish a valid event using native
   WordPress;
2. invalid dates cannot be published through any supported save path;
3. timezones and wall-time calendar placement remain stable;
4. visitors can browse accessible single, archive, list, grid and calendar views;
5. optional filters work without exposing ineligible events;
6. Gutenberg and Elementor reuse the same public presentation rules;
7. the plugin remains functional without Elementor or WooCommerce;
8. protected, private and unpublished details remain private;
9. uninstall behaviour is explicit and safe by default;
10. all release gates and supported-version checks pass.

## 21. Post-version-1 roadmap boundary

Divi 5 compatibility may be planned after the first public release has real-world
feedback. It must reuse the shared presentation and query services rather than
forking event logic. Recurrence and interactive maps remain separate product
decisions and are not implied by a page-builder integration.
