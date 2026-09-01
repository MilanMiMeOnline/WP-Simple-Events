# Security and privacy audit

**Audit date:** 1 September 2026

**Reviewed baseline:** MiMe Simple Events and Calendar 0.9.0 RC3

**Repository:** `MilanMiMeOnline/WP-Simple-Events`

## Executive result

The complete frozen 1.x surface was re-audited after recurrence, occurrence
pages, Divi 5, modern filters/colors and Add to Calendar expanded the original
0.2 architecture. No unresolved high- or critical-severity vulnerability, privacy
violation or release blocker was found.

The re-audit corrected three hardening gaps:

1. the old audit incorrectly claimed that the current plugin had no custom table,
   direct database access, recurrence or calendar export;
2. CI still used official action generations whose JavaScript runtime had moved
   into GitHub's Node 20 deprecation path;
3. Dependabot security updates were disabled even though human-reviewed automated
   remediation proposals fit the repository policy.

The documentation now describes the rebuildable occurrence index and its five
reviewed database adapters. Executable tests freeze REST permissions, direct
database ownership, prohibited runtime capabilities, browser privacy and remote
URL boundaries. GitHub Actions use immutable reviewed Node 24 generations, and
Dependabot may propose—but never automatically merge—security updates.

## Threat model and trust zones

### Untrusted inputs

- anonymous query strings, shortcode attributes and REST/calendar-feed input;
- editor post/meta/taxonomy values and builder attributes;
- recurrence aggregates, occurrence identities and broad-edit confirmations;
- stored values that may predate the current validation rules;
- theme/plugin callbacks and host cache behaviour;
- lockfile packages, GitHub Actions and downloaded historical release archives.

### Protected assets

- draft, private and password-protected event metadata/content;
- editor-only recurrence aggregates, overrides and maintenance state;
- site capabilities, settings and destructive cleanup preference;
- integrity of exact occurrence identities and public calendar snapshots;
- build/release integrity and the absence of developer credentials or personal
  workstation data.

### Security objectives

- public collections expose only published, password-free eligible events;
- exact occurrence/export requests never fall back to a related public object;
- mutations require the exact mapped capability and browser-request integrity;
- every input is shape-validated and bounded before sanitization/persistence;
- output is escaped late for HTML, attribute, URL, JSON or calendar contexts;
- derived storage cannot become an authority over canonical WordPress content;
- the runtime adds no telemetry, visitor tracking, remote code or secret logging.

The normative route/action review is in
[SECURITY-PERMISSION-MATRIX.md](SECURITY-PERMISSION-MATRIX.md).

## Privacy data map

### Canonical site-local data

- WordPress event posts/revisions: title, content, excerpt, author and featured
  image reference;
- typed event metadata: schedule, captured timezone, venue, address, external
  HTTP(S) links/labels, public status and optional color intent;
- event category/tag terms, relationships and optional category color;
- protected recurrence aggregate, sparse occurrence overrides/cancellations and
  maintenance state;
- plugin options for archive/display/timezone/schema/uninstall behaviour;
- event capabilities assigned to administrator/editor roles.

The custom occurrence table is a disposable bounded index. It contains stable
identity, parent event, generation, chronological values and status required for
ordered queries. It does not copy event bodies, passwords, taxonomy records,
visitor data or remote-service identifiers and can be rebuilt from canonical
metadata.

### Public disclosure

An editor who publishes a password-free event intentionally makes its event data
available through front-end pages/components, Event JSON-LD, applicable sitemaps,
WordPress core REST, the bounded calendar feed and—in an exact eligible
context—the local ICS snapshot. Addresses, descriptions and external URLs may
contain personal information, so the public readme tells editors not to publish
private information as event content.

Draft/private events remain under WordPress capabilities. Plugin collections,
JSON-LD, public explicit builder selection, occurrence REST and ICS exclude
password-protected events. The core event REST response removes the full `meta`
member while WordPress still requires the event password; authorized edit context
retains it.

### Network and browser behaviour

- No request is sent to MiMe, an analytics service or a hosted plugin backend.
- No remote JavaScript, stylesheet, font, image, iframe or executable code loads.
- Authored browser code creates no cookie and uses no local/session storage or
  beacon telemetry.
- Calendar requests use the same WordPress REST origin.
- Google and Outlook are optional author-selected links. The plugin does not
  contact them. A visitor's deliberate click sends the public snapshot to that
  provider in an isolated tab without a referrer.
- Schema.org is a vocabulary URL in local JSON-LD, not a network request.

### Retention and WordPress privacy tools

Deactivation deletes no content. Uninstall always removes executable scheduled
jobs but preserves content/settings/derived rows by default. Permanent cleanup
requires a saved administrator opt-in and deletes only allowlisted plugin data;
shared media remains.

The plugin does not accept visitor submissions or create a visitor/user-linked
identity record, so a custom personal-data exporter or eraser would have no
plugin-specific record to return. Editorial events remain ordinary WordPress
content with native author/content controls. The public readme provides the facts
a site owner needs for a privacy notice.

## Authorization and integrity review

- Every custom REST route declares `permission_callback`.
- Only the bounded public calendar feed and exact public occurrence resolver use
  `__return_true`; their repositories repeat published/password-free eligibility.
- Eight recurrence editor routes require exact event `edit_post`. Mutation
  services recheck authority, validate optimistic revisions and require a
  server-signed confirmation for broad changes.
- Divi preview requires exact builder-document `edit_post`; module and attribute
  payloads are allowlisted/bounded and rendered server-side.
- Settings/maintenance require `manage_options` and WordPress/action nonces.
- Duplication requires source edit, event creation, term-assignment capability
  and an event-specific nonce.
- Native/Core REST saves use WordPress nonce/capability enforcement and typed
  registered metadata.

## Input, output, SQL and cache review

- Enumerations, dates, timezones, identities, taxonomy values, colors, labels,
  limits and provider choices use allowlists and explicit bounds.
- External URLs accept HTTP(S) only. Markup, attributes, URLs, JSON-LD and ICS
  receive context-specific escaping/encoding.
- JSON-LD prevents HTML script termination. ICS text is RFC 5545 escaped, folded
  at UTF-8-safe 75-octet boundaries and emitted with no-store/no-cache/nosniff.
- No authored runtime `eval`, shell execution, unsafe unserialization, dynamic
  remote include, visitor cookie/storage, unauthenticated AJAX mutation, remote
  request API or secret/request-payload logging exists.
- Direct database access is confined to the five occurrence adapters listed in
  the permission matrix. Identifiers are trusted/validated, values are prepared,
  collections are bounded and public reads join back to public parent posts.
- Public occurrence leaf responses are conservative no-store resources. Ordinary
  bounded public collections remain compatible with normal host/CDN caching;
  canonical writes and index generations prevent stale rows becoming authority.

## Supply chain and repository review

- Composer production runtime contains only the generated project autoloader.
- FullCalendar 6.1.21 and Preact are bundled locally under MIT terms with notices.
- `composer audit --locked` reports no advisory or abandoned dependency.
- `npm audit --audit-level=low` reports zero vulnerabilities; development tools
  are never included in the release package.
- CI repository permission is read-only. Every remote Action is pinned to one
  immutable commit and official Node-based actions run maintained Node 24
  generations.
- Release construction is allowlist-based, rejects links/unexpected paths/types,
  generates an authoritative production autoloader, verifies every PHP file and
  produces byte-for-byte reproducible archives plus bound SHA-256 checksums.
- Public source/build instructions and third-party notices satisfy source
  availability for bundled/minified code.
- GitHub secret scanning, push protection, vulnerability alerts, Private
  Vulnerability Reporting and Dependabot security updates are enabled. Automated
  security changes still require normal human review and green gates.

## Findings and dispositions

### DOC-SEC-01 — Historical audit contradicted the current architecture

**Severity:** Documentation/compliance risk

**Status:** Resolved in RC3

The 0.2.2 audit said there was no recurrence, custom table, direct database access
or ICS surface. It was replaced with this complete current review, permission
matrix and executable inventory tests.

### CI-SEC-01 — Official Actions used the deprecated Node 20 runtime generation

**Severity:** Low supply-chain maintenance risk

**Status:** Resolved in RC3

Checkout, setup-node, cache and artifact upload moved to reviewed immutable
commits for their maintained Node 24 generations. CI itself now runs Node 24.

### OPS-SEC-01 — Automated security remediation proposals were disabled

**Severity:** Low operational detection/response risk

**Status:** Resolved on 1 September 2026

Dependabot security updates are enabled. They open proposals only; no auto-merge
or expanded workflow permission was granted.

### DEP-SEC-01 — Bundled calendar library requires ongoing reassessment

**Severity:** Accepted maintenance risk

**Status:** Open, non-blocking

The locked dependency audit is clean. FullCalendar remains local, version-pinned,
licensed and replaceable without changing stored event data. Recheck advisories
and maintenance status before 1.0 and every dependency update.

### HOST-SEC-01 — Reverse proxies can override application cache headers

**Severity:** Accepted deployment risk

**Status:** Open, non-blocking

The plugin emits conservative headers for exact occurrence and ICS privacy
boundaries, but cannot guarantee that a misconfigured host/CDN honors them.
Deployment-specific cache exclusions remain a hosting responsibility; tests prove
the application headers and fail-closed object eligibility.

## Release acceptance

RC3 is acceptable only after the final tree passes strict Composer validation,
PHP QA, JavaScript/CSS/tool QA, supported WordPress/PHP smoke tests, browser
regressions, reproducible release verification, dependency audits and official
strict Plugin Check. The resulting CI commit and exact package—not an earlier
working tree—remain the release evidence.

## Review references

- [WordPress plugin security APIs](https://developer.wordpress.org/apis/security/)
- [WordPress escaping guidance](https://developer.wordpress.org/apis/security/escaping/)
- [REST endpoint permissions](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [WordPress nonces](https://developer.wordpress.org/apis/security/nonces/)
- [Plugin privacy guidance](https://developer.wordpress.org/plugins/privacy/)
- [Detailed Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Common Plugin Directory review issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)
