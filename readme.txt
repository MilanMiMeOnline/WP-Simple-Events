=== WP Simple Events ===
Contributors: mime
Tags: events, calendar, elementor
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, native event plugin for WordPress.

== Description ==

WP Simple Events provides a focused event content model for WordPress without recurrence, interactive maps or ticketing in its first version.

The plugin is under active development. The native Events menu includes useful event columns, filters, sortable dates and a protected “Duplicate event” action that creates a draft and marks copied dates for review. Native and REST writes share strict validation, incomplete publication is blocked and UTC query indexes are derived automatically. Classic/block-theme fallbacks and bounded `[wpse_events]`, `[wpse_event_details]` and `[wpse_calendar]` shortcodes are present. The calendar uses a bounded public feed, local assets and a no-JavaScript event-list fallback. Individual public events include safe, timezone-aware Event JSON-LD that administrators can disable under Events → Settings. Administrators can configure the native archive URL, page size and default period; a colliding WordPress page is diagnosed before they choose another path. They can also repair event capabilities and rebuild validated derived UTC indexes in bounded batches. Plugin deletion preserves events by default; complete cleanup requires an explicit warned administrator opt-in and retains shared media. Elementor 3.35 or newer optionally adds three composite widgets plus twelve atomic event-field widgets. Gutenberg provides the same twelve fields as dynamic blocks with native style controls and a single-event pattern. Static pages can select a public event, while templates use their current event context.

== Installation ==

1. Install the packaged plugin through the WordPress Plugins screen.
2. Activate WP Simple Events.

== Changelog ==

= 0.1.1 =
* Fix Gutenberg publication so Event details are included in the authoritative REST save.
* Show the first actionable event validation message in the editor.

= 0.1.0 =
* Establish the secure, testable plugin foundation.
* Add the native event post type, taxonomies, metadata, capabilities and date-range model.
* Add the native Event details editor, shared publication validation and safe derived metadata persistence.
* Add the bounded public event repository, archive ordering and event list/grid shortcode.
* Add secure event-details rendering and native single/archive fallbacks for classic and block themes.
* Add a bounded public calendar feed with month/list views, filters and a no-JavaScript fallback.
* Add optional Elementor list/grid, calendar and event-details widgets with responsive and theme-inheriting style controls.
* Add dedicated Elementor widgets for every public event field, with safe explicit/current event sources and field-specific controls.
* Add dedicated dynamic Gutenberg blocks for every public event field, with native style supports, server previews and an opt-in single-event pattern.
* Add singular Event JSON-LD with a native administrator opt-out setting.
* Add event admin columns, filters, sortable dates and a safe duplicate-to-draft workflow.
* Add default-safe event retention and explicit batched uninstall cleanup.
* Add protected capability repair and bounded, validation-backed UTC-index maintenance.
* Add bounded native archive settings, page-conflict diagnosis and one-shot rewrite regeneration.
