# Test strategy

## Objectives

Testing must detect incorrect event visibility, date ordering, permission leaks and regressions before release. Confidence comes from several focused layers rather than a large number of shallow tests.

## Layers

### Static gates

PHP syntax and style, PHPStan, JavaScript/style linting, strict package validation, dependency audits and WordPress Plugin Check run automatically. New warnings are treated as failures.

The release contract additionally rejects unexpected archive roots, paths, file types, hidden/development files and missing runtime assets. The verifier checks the SHA-256 record, symbolic links, every shipped PHP file and the production Composer autoloader. Two consecutive builds must be byte-for-byte identical.

### Unit tests

Fast tests cover logic that does not require a running WordPress installation: date ranges, display status, validation, query criteria, formatter behaviour and migrations. Boundary-value and data-provider tests are preferred.

### WordPress integration tests

These verify registrations, capabilities, metadata authorization, persistence, queries, REST responses, shortcode output, templates and cache invalidation against real WordPress. Each test creates and cleans up its own data.

### End-to-end tests

The Playground smoke journey covers activation, creating, editing, publishing, filtering and opening events; admin actions and forged nonces; REST validation and visibility; settings and maintenance; list, details and calendar shortcodes; archive routing; structured data; and graceful native behaviour without Elementor or WooCommerce. Release candidates run the packaged staging directory on WordPress 6.9 and 7.0.1 with PHP 8.3.

### Manual exploratory QA

Manual checks focus on UX, theme compatibility, responsive layouts, localization, accessibility and failure recovery. They complement rather than replace automated regression coverage.

## Required scenario matrix

- Upcoming, ongoing and past events.
- Timed, all-day, same-day and multi-day events.
- Start/end equality and invalid reversed ranges.
- Site time zones with positive and negative offsets and daylight-saving transitions.
- Draft, private, password-protected, trashed and published events.
- Users with no access, edit-own, edit-others and administrator capabilities.
- No matching events, one event, pagination boundaries and high-volume bounded queries.
- Missing optional dependencies and supported versions of WordPress/PHP.
- Elementor absent, Elementor 3.35.x and the currently tested Elementor 4.x release.
- Invalid, missing, duplicated and forged input.

## Release evidence

A release candidate needs green CI, dependency audit, strict Plugin Check against the staging package, clean install/activation/deactivation tests, current translations, a reproducible verified archive, upgrade testing when applicable, supported-version matrix results and a completed QA checklist. Known limitations must be explicit.
