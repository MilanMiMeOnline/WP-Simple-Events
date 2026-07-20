# Simple Events by MiMe

Simple Events by MiMe is a lightweight, native event plugin for WordPress. It is designed for a WooCommerce website built with Elementor, while keeping both plugins optional and keeping the event core independent.

The secure development foundation, native event data model, editor workflow, native presentation layer, progressively enhanced calendar, optional Elementor integration and singular Event JSON-LD are implemented. The Events overview includes practical date/location/status columns and filters, while “Duplicate event” creates a safe draft and marks copied dates for review. WordPress centrally validates writes, maintains UTC query indexes, renders classic/block-theme single and archive fallbacks, and exposes bounded `[wpse_events]`, `[wpse_event_details]` and `[wpse_calendar]` shortcodes. The calendar uses a bounded public REST feed, a local minimal FullCalendar bundle and an event-list fallback when JavaScript is unavailable. Elementor 3.35 or newer adds the three composite widgets plus twelve atomic event-field widgets without becoming a core dependency. Gutenberg exposes the same twelve fields as server-rendered blocks with native style supports and an opt-in single-event pattern. Both hosts support an explicit public event on static pages and current event context in templates. Events → Settings provides bounded archive URL/page/default-period controls, diagnoses a colliding WordPress page and includes administrator maintenance for capabilities and derived UTC indexes. Plugin deletion preserves all event data by default and requires an explicit destructive opt-in for cleanup.

The agreed scope and build specification are documented in `ANALYSE-EN-BOUWSPECIFICATIE.md`. Frozen storage, archive routing, public-query, calendar, presentation, Elementor, Gutenberg, SEO, maintenance and data-retention contracts are documented under `docs/`, including `DATA-CONTRACT.md`, `ARCHIVE-SETTINGS-CONTRACT.md`, `PUBLIC-QUERY-CONTRACT.md`, `TEMPLATE-CONTRACT.md`, `ELEMENTOR-INTEGRATION.md`, `GUTENBERG-INTEGRATION.md`, `STRUCTURED-DATA.md`, `MAINTENANCE-CONTRACT.md` and `UNINSTALL-CONTRACT.md`.

Release candidates are built from a strict production allowlist, include a minimal production autoloader and translation template, and are checked for content, checksum integrity and reproducibility. See `docs/RELEASE-PROCESS.md`.

## Requirements

- WordPress 6.9 or newer
- PHP 8.3 or newer

## Installing over a private test build

Earlier private packages used a different plugin directory. Confirm that destructive uninstall cleanup is disabled, then deactivate and remove the earlier package before installing `simple-events-by-mime`. The event post type, taxonomies, metadata, shortcodes, blocks and Elementor widget identifiers are unchanged, so retained event content remains compatible.

## Development

Read `AGENTS.md` before making changes and use `CONTRIBUTING.md` for setup and quality commands. Security and testing are release gates from the first increment.

## Licence

[GPL-2.0-or-later](LICENSE).
