# Security and privacy audit

**Audit date:** 2026-07-21
**Reviewed baseline:** Simple Events by MiMe 0.2.2 release candidate
**Repository:** `MilanMiMeOnline/WP-Simple-Events`

## Executive result

The plugin has a strong privacy-minimizing architecture: it has no hosted service, telemetry, analytics, advertisements, remote runtime assets, visitor storage, direct database access or custom tables. Privileged actions use explicit capabilities and nonces, public queries are bounded, production packaging is allowlisted, and event data remains under the WordPress site's control.

The audit found one release-blocking confidentiality defect. WordPress core included registered event metadata in the standard REST response for a published password-protected event even while its content remained locked. The plugin now removes the complete REST `meta` member while WordPress still requires that event password. Authorized editors retain the data in edit context. A real WordPress regression first reproduced the disclosure and now proves both boundaries.

No known unresolved code vulnerability remains after that fix and the completed review. The next release is still conditional on all quality gates and official Plugin Check passing against the final versioned package. GitHub Private Vulnerability Reporting is enabled and linked directly from the public security policy.

## Privacy data map

### Data stored by the plugin

- Native WordPress event posts and revisions, including editor-authored title, content, excerpt and featured-image reference.
- Registered event metadata: local dates/times, captured timezone, derived UTC indexes, all-day flag, venue, address, location URL, external event URL/label and event status.
- Event-specific category and tag terms and relationships.
- Plugin options for archive display/routing, timezone visibility, JSON-LD, schema version, maintenance/rewrite state and uninstall preference.
- Event capabilities granted to administrator and editor roles.

The plugin does not create a custom table, user profile field, visitor profile, analytics record or application log.

### Public disclosure boundaries

Published, password-free events may be exposed through:

- front-end event pages, archives, shortcodes, Gutenberg blocks and Elementor widgets;
- Event JSON-LD on eligible individual event pages;
- WordPress core REST for the public event post type and registered public editor metadata;
- the bounded `wpse/v1/events` calendar feed.

Drafts and private events require normal WordPress authorization. Password-protected events are excluded from plugin collections, calendar feeds, explicit public block/widget selection and JSON-LD. Composite single-event output shows WordPress' password form. The new core REST response filter also removes event metadata while the password is required.

Site editors remain responsible for deciding what they publish. Addresses, external URLs and free-form content can contain personal information even though the plugin does not require it.

### Network and browser behaviour

- No request is sent to MiMe or another vendor service.
- No CDN, remote font, tracking pixel, iframe or remotely executed code is loaded.
- Calendar JavaScript requests the same site's WordPress REST endpoint with bounded dates, pagination and category/tag slugs.
- The plugin creates no cookie and writes nothing to local storage or session storage.
- External location/event destinations are contacted only after a visitor selects a link. Those links use an isolated new tab with `noopener noreferrer`.

### Retention and deletion

Deactivation deletes nothing. Uninstall retains events, terms, settings and capabilities by default. Complete cleanup requires a saved, administrator-only opt-in with an irreversible-action warning. Cleanup uses bounded WordPress APIs and retains uploaded media because attachments may be shared. Options are deleted last so interrupted cleanup does not falsely report completion.

No custom personal-data exporter or eraser is registered. The plugin does not collect visitor submissions or create a plugin-specific identity record; editorial events remain native WordPress content governed by the site's normal content and author controls. The public readme now provides copy-ready facts for a site owner's privacy notice.

## Security review coverage

### Authorization and request integrity

- Event edit, publish, delete, duplicate and taxonomy capabilities are explicit.
- Duplication requires source edit, event creation and event-term assignment rights plus an event-specific nonce.
- Maintenance requires `manage_options` and action-specific nonces.
- Settings use the WordPress Settings API and are not exposed through REST.
- Native and REST event saves share the same validation and persistence boundaries.
- Read-only public filter query strings are allowlisted, bounded and instance-scoped.

### Input validation and output safety

- Enumerations use allowlists; date/time and timezone values are parsed strictly, including DST boundaries.
- External URLs accept only HTTP or HTTPS and are escaped at output.
- Text lengths and calendar windows/pages are bounded.
- Stored values are treated as untrusted again in the shared presentation layer.
- Output uses context-specific escaping; allowed rich content passes through WordPress formatting/KSES.
- JSON-LD escapes HTML-significant characters to prevent script termination.
- No `eval`, dynamic remote include, unsafe deserialization, shell execution or arbitrary metadata field is present in runtime code.

### Data access and queries

- No raw SQL or direct `$wpdb` access is present.
- Public event queries force the event post type, `publish`, `has_password => false`, deterministic ordering and hard page/result bounds.
- The calendar route validates a maximum 400-day window, up to 20 category/tag slugs, a maximum page of 1000 and a maximum 100 items per page.
- Derived UTC indexes are hidden from core REST and cannot be client-authored.
- The new `rest_prepare_wpse_event` boundary prevents registered metadata disclosure for locked events.

### Supply chain and packaging

- PHP production code has no third-party runtime package beyond the generated Composer autoloader.
- FullCalendar 6.1.21 modules and their Preact dependency are bundled locally under MIT terms; notices ship in the release.
- Node and Composer dependencies are lockfile-controlled development/build dependencies.
- The release builder copies an explicit allowlist, rejects symlinks, hidden/development paths and unexpected file types, and removes the development lockfile after generating a class-authoritative production autoloader.
- Release verification checks archive paths, checksum filename binding, PHP syntax, autoloading and byte-for-byte reproducibility.

### Repository privacy

- Tracked files and Git history contain no detected high-confidence token, private key, local Mac path or personal email address.
- Commit identity is `MiMe` with GitHub's `121235792+MilanMiMeOnline@users.noreply.github.com` address.
- The GitHub repository is public, secret scanning is enabled and push protection is enabled.
- CI has read-only repository permission and every third-party GitHub Action is pinned to a reviewed immutable commit.
- GitHub currently reports Dependabot security updates as disabled. Private Vulnerability Reporting is enabled.

## Findings

### SEC-01 — Protected event metadata exposed by core REST

**Status:** Fixed in the 0.2.2 candidate; release blocker until that version is published.
**Impact:** An anonymous caller could read registered schedule/location/status metadata from a published password-protected event through WordPress core REST.
**Resolution:** Remove `meta` at `rest_prepare_wpse_event` while `post_password_required()` remains true, except for an authorized edit-context request.
**Evidence:** The new smoke assertion failed against the old behaviour and passes after the fix; authorized edit access is asserted separately.

### OPS-01 — Private vulnerability intake channel

**Status:** Resolved on 2026-07-22.
**Impact:** A researcher has no clean private reporting route and may use a public issue.
**Resolution:** GitHub Private Vulnerability Reporting is enabled and `SECURITY.md` links directly to the private advisory form. No personal email address is published.

### OPS-02 — Dependabot security updates disabled

**Status:** Recommended, not a code vulnerability.
**Impact:** Existing audit gates catch known advisories during development/release, but automated patch proposals and earlier repository notification are unavailable.
**Resolution:** Enable Dependabot security updates while retaining human review and the current Composer/npm audit gates.

### DEP-01 — FullCalendar 6 line requires periodic reassessment

**Status:** Accepted maintenance risk.
**Impact:** No known vulnerability was identified in the locked dependency audit, but the modular 6.1 line must remain maintained and reviewed.
**Resolution:** Reassess before 1.0 and on every dependency review; keep the no-JavaScript fallback and local build so replacement does not affect stored data.

## Release acceptance

Before publishing 0.2.2:

1. Require the official strict Plugin Check CI result on the release commit.
2. Confirm account two-factor authentication and recovery details.
3. Submit the verified 0.2.2 package through the `mimeonline` account.

Version synchronization, translation verification, local quality gates,
reproducible packaging, packaged WordPress 6.9/7.0.1 smoke tests, WordPress 7.0.1
browser regressions and visual-asset review have been completed for the candidate.
