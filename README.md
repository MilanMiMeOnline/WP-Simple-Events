# Simple Events by MiMe

![Simple Events by MiMe](.wordpress-org/banner-1544x500.png)

Simple Events by MiMe is a focused, native event plugin for WordPress. It provides one-off event publishing, lists, grids, an accessible calendar, Gutenberg blocks and optional Elementor widgets without requiring a large event-management suite.

The plugin is free software licensed under GPL-2.0-or-later. Its public WordPress.org identity is `simple-events-by-mime`; established internal `wpse_*` storage and content identifiers remain stable.

## What it includes

- A dedicated Events area using the familiar WordPress editor.
- Timed, all-day, same-day and multi-day events with captured timezones.
- Event-specific categories, tags and scheduled, postponed or cancelled states.
- Venue, address, location link and customizable external action link.
- Native single and archive templates for classic and block themes.
- A responsive list/grid and month/list calendar with bounded filters.
- A no-JavaScript upcoming-event fallback.
- Twelve dynamic Gutenberg event-field blocks and a single-event pattern.
- Optional Elementor 3.35+ composite and atomic widgets.
- Safe Event JSON-LD for eligible individual events.
- Default-safe data retention and explicit administrator maintenance tools.

Recurrence, interactive maps, geocoding, ticketing and external calendar synchronization are deliberate non-goals for version 1.

## Requirements

- WordPress 6.9 or newer
- PHP 8.3 or newer
- Elementor 3.35 or newer only when using the optional Elementor integration

WooCommerce and Elementor are optional and never core dependencies.

## Quick start

1. Install and activate an official release package.
2. Open **Events > Add New**.
3. Add normal WordPress content and complete the Event details panel. A valid start is required before publication.
4. Publish the event.
5. Add one of these shortcodes to a page, or use the matching Gutenberg or Elementor component:

```text
[wpse_calendar]
[wpse_events]
[wpse_event_details]
```

`[wpse_calendar]` defaults to a desktop month view, a mobile list view and visible category/tag filters. `[wpse_events]` defaults to an upcoming three-column grid with pagination. Both shortcodes accept only documented, bounded attributes; invalid values fall back safely.

## Editors and page builders

Gutenberg provides twelve server-rendered blocks for event title, featured image, date and time, status, venue, address, location link, content, excerpt, external action, categories and tags. Static pages can select one published event; dynamic templates can use the current event context.

Elementor 3.35+ provides the same twelve fields plus Event List / Grid, Event Calendar and Event Details widgets. The widgets work on ordinary Elementor Free pages. Elementor Pro is only relevant when a site owner chooses to build dynamic templates with Elementor Theme Builder.

## Privacy and security

The runtime plugin:

- creates no visitor cookies;
- collects no analytics or telemetry;
- loads no remote scripts, fonts, images or tracking pixels;
- sends no information to MiMe or another external service;
- uses no custom database table;
- keeps calendar requests on the same WordPress installation.

Event content entered by editors is stored as native WordPress content and metadata. Published event details can be exposed through front-end HTML, Event JSON-LD, core REST and the bounded calendar feed. Drafts, private events and protected event details are excluded from public plugin queries. The core REST response also removes registered event metadata while a post password is required.

External location and event links open in an isolated new tab with `noopener noreferrer`. Deactivation never deletes data, and uninstall cleanup is disabled by default. See [SECURITY.md](SECURITY.md), [the public-query contract](docs/PUBLIC-QUERY-CONTRACT.md) and [the uninstall contract](docs/UNINSTALL-CONTRACT.md) for the reviewed boundaries.

## Development

Read [AGENTS.md](AGENTS.md) before changing the project and use [CONTRIBUTING.md](CONTRIBUTING.md) for setup, testing and review commands. The functional source of truth is [docs/PRODUCT-SPECIFICATION.md](docs/PRODUCT-SPECIFICATION.md); the documentation index links the supporting architectural, security, QA and release contracts.

Release archives are built from an explicit production allowlist, contain local production assets and a minimal optimized autoloader, and are verified for contents, PHP syntax, checksum integrity and byte-for-byte reproducibility. The full process is in [docs/RELEASE-PROCESS.md](docs/RELEASE-PROCESS.md).

The [documentation index](docs/README.md) links the WordPress.org handoff and visual-asset requirements.

## Support and security reports

Use [GitHub Issues](https://github.com/MilanMiMeOnline/WP-Simple-Events/issues) for ordinary defects and reproducible feature requests. Never place exploit details, credentials, nonces or personal data in a public issue; use the process in [SECURITY.md](SECURITY.md).

## Licence

[GPL-2.0-or-later](LICENSE). Bundled third-party notices are in [THIRD-PARTY-NOTICES.txt](THIRD-PARTY-NOTICES.txt).
