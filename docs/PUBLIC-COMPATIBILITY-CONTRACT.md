# Public compatibility contract for 1.x

**Status:** frozen for the 0.9.0 release-candidate cycle\
**Contract version:** 1\
**Last reviewed:** 30 August 2026

This document defines which MiMe Simple Events and Calendar identities and
behaviours are public compatibility promises for the 1.x line. It complements
the detailed data, query, presentation, recurrence, editor and template
contracts; it does not make every PHP class or markup detail a public API.

## Compatibility policy

- Existing valid event data, settings, URLs and saved builder content must keep
  their meaning throughout 1.x.
- A public identity listed below is not renamed, repurposed or removed during
  1.x. Additive optional fields and controls are allowed only with defaults that
  preserve existing output.
- A public contract may be deprecated during 1.x only with a documented
  replacement and an upgrade-safe compatibility adapter. Removal is reserved
  for a later major version.
- Stored metadata is never reused for a different meaning. A shape change needs
  an explicit, idempotent migration and backward-readable data during 1.x.
- REST responses may gain optional fields, but existing fields, methods,
  eligibility rules and error privacy are not weakened during 1.x.
- Security, privacy, correctness and host-compatibility fixes may tighten
  validation of values that were never valid under the documented contract.
  Such tightening is not considered a breaking change.
- PHP classes under `MiMe\WPSimpleEvents` are implementation details unless this
  document or another normative contract explicitly marks an extension point.
  There is no public browser JavaScript API.

## WordPress content and storage identities

The stable canonical identities are:

- post type `wpse_event`;
- taxonomies `wpse_event_category` and `wpse_event_tag`;
- the registered event metadata and category-color metadata in
  [DATA-CONTRACT.md](DATA-CONTRACT.md);
- the mapped capabilities in [DATA-CONTRACT.md](DATA-CONTRACT.md);
- the options documented in [ARCHIVE-SETTINGS-CONTRACT.md](ARCHIVE-SETTINGS-CONTRACT.md),
  [DATA-CONTRACT.md](DATA-CONTRACT.md) and [UNINSTALL-CONTRACT.md](UNINSTALL-CONTRACT.md).

The occurrence projection table, projection generations, maintenance offsets,
transient editor confirmations and schema bookkeeping remain internal derived
state. They may change through an idempotent upgrade as long as canonical event
meaning, occurrence identity and the documented lifecycle remain intact.

## Visitor and template interfaces

### Shortcodes

- `[wpse_events]`
- `[wpse_calendar]`
- `[wpse_event_details]`
- `[wpse_add_to_calendar]`

Their documented attributes, validation, defaults and query semantics live in
[PUBLIC-QUERY-CONTRACT.md](PUBLIC-QUERY-CONTRACT.md),
[PRESENTATION-CONTRACT.md](PRESENTATION-CONTRACT.md) and
[ADD-TO-CALENDAR-CONTRACT.md](ADD-TO-CALENDAR-CONTRACT.md). Existing attributes
remain accepted throughout 1.x.

### Gutenberg blocks and pattern

- `wpse/event-list`
- `wpse/event-calendar`
- `wpse/event-details`
- `wpse/add-to-calendar`
- `wpse/event-title`
- `wpse/event-featured-image`
- `wpse/event-date-time`
- `wpse/event-status`
- `wpse/event-venue`
- `wpse/event-address`
- `wpse/event-location-link`
- `wpse/event-content`
- `wpse/event-excerpt`
- `wpse/event-external-action`
- `wpse/event-categories`
- `wpse/event-tags`
- pattern `mime-simple-events-calendar/single-event-fields`

The existing `block.json` attribute names, types, defaults and allowlists are
saved-content contracts. A new optional attribute may be added, but an existing
attribute cannot silently change meaning. The internal fallback blocks
`wpse/native-single` and `wpse/native-archive` retain their names while native
template fallback uses them; they are not general-purpose editor components.

### Elementor widgets

- `wpse-event-list`
- `wpse-event-calendar`
- `wpse-event-details`
- `wpse-add-to-calendar`
- `wpse-event-title`
- `wpse-event-featured-image`
- `wpse-event-date-time`
- `wpse-event-status`
- `wpse-event-venue`
- `wpse-event-address`
- `wpse-event-location-link`
- `wpse-event-content`
- `wpse-event-excerpt`
- `wpse-event-external-action`
- `wpse-event-categories`
- `wpse-event-tags`

The widget names and saved control identifiers are stable. Labels, editor help
and conditional visibility may improve without migrating content. Existing
saved settings continue to normalize through their original defaults and
allowlists. Elementor itself remains optional and template assignment remains
host-owned.

### Divi 5 modules

- `mime-simple-events-calendar/event-list`
- `mime-simple-events-calendar/event-calendar`
- `mime-simple-events-calendar/event-details`
- `mime-simple-events-calendar/add-to-calendar`
- `mime-simple-events-calendar/event-title`
- `mime-simple-events-calendar/event-featured-image`
- `mime-simple-events-calendar/event-date-time`
- `mime-simple-events-calendar/event-status`
- `mime-simple-events-calendar/event-venue`
- `mime-simple-events-calendar/event-address`
- `mime-simple-events-calendar/event-location-link`
- `mime-simple-events-calendar/event-content`
- `mime-simple-events-calendar/event-excerpt`
- `mime-simple-events-calendar/external-event-action`
- `mime-simple-events-calendar/event-categories`
- `mime-simple-events-calendar/event-tags`

Module names and existing `module.json` attribute identities are saved-layout
contracts. Divi is optional, licensed host code is never distributed and Theme
Builder assignment remains host-owned.

## Routes, query variables and REST resources

The stable visitor request names are `wpse_period`, `wpse_category`, `wpse_tag`,
the instance namespaces `wpse_N_*` and `wpse_calendar_N_*`, the exact-occurrence
query variable `wpse_occurrence`, and the calendar-export variables
`wpse_calendar_export`, `wpse_event` and `wpse_occurrence`.

The custom REST identities are:

- authenticated recurrence editing under
  `wpse/v1/events/{id}/recurrence` and its documented preview, following,
  occurrence and disable subroutes;
- authenticated Divi preview at `wpse/v1/divi-preview`;
- public exact occurrence read at
  `wpse/v2/events/{event_id}/occurrences/{occurrence}`.

Core exposes `wpse_event` through its normal REST controller. Public and editor
routes retain the permission, bounded-input and fail-closed rules in
[DATA-CONTRACT.md](DATA-CONTRACT.md), [RECURRENCE-CONTRACT.md](RECURRENCE-CONTRACT.md)
and [ADD-TO-CALENDAR-CONTRACT.md](ADD-TO-CALENDAR-CONTRACT.md).

## Supported hooks

- `wpse_loaded` fires after the plugin has registered its service graph.
- `wpse_render_single_template` renders the plugin-owned classic single body.
- `wpse_render_archive_template` renders the plugin-owned classic archive body.
- `wpse_structured_data_enabled` filters the final per-event JSON-LD setting.

Callback arguments and timing remain compatible throughout 1.x. WordPress core
hooks used internally are not redefined as plugin extension points.

## Markup and styling boundary

Only the semantic classes and custom properties explicitly listed in
[PRESENTATION-CONTRACT.md](PRESENTATION-CONTRACT.md) are stable styling targets.
Top-level component roots, named event-field classes, the filter surface, color
surface and `--wpse-*` variables documented there remain compatible during 1.x.
Incidental host wrappers, generated IDs, editor-only placeholders and third-party
FullCalendar classes are not public plugin contracts.

## Change procedure

Every proposed public-surface change must:

1. update the relevant normative contract and `docs/DECISIONS.md` before code;
2. add a regression proving old saved content and data still work;
3. update the automated public-contract inventory when the change is additive;
4. include an upgrade path when stored data changes;
5. record any deprecation and replacement in the changelog and release notes;
6. pass the supported WordPress, PHP, Gutenberg, Elementor and Divi-absence or
   Divi-presence matrix applicable to that surface.
