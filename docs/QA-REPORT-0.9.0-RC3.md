# 0.9.0 RC3 security and privacy qualification

**Date:** 1 September 2026

**Scope:** frozen 1.x permissions, REST, recurrence/occurrence storage, Divi
preview, public calendar export, stored input/output, privacy, dependencies,
repository security, build supply chain and official Plugin Check readiness.

**Status:** complete; local qualification and the complete hosted matrix pass.

## Outcome

No unresolved high/critical vulnerability, privacy violation, P1/P2 defect or
release blocker was found. The audit produced three corrective hardening changes:

1. The historical 0.2.2 audit was replaced because it incorrectly described the
   current recurrence, custom occurrence table, direct database and ICS surface.
2. Official GitHub Actions moved from deprecated Node 20 generations to reviewed
   immutable Node 24 generations.
3. Dependabot security updates were enabled for reviewable proposals without
   granting auto-merge or extra workflow permissions.

The new [permission matrix](SECURITY-PERMISSION-MATRIX.md) inventories every
public and privileged surface. ADR-095 makes those boundaries executable through
repository tests.

## Senior developer review

### Authorization and request integrity

- All eleven custom REST route registrations declare an explicit permission
  callback.
- Only the bounded calendar collection and exact occurrence resolver are public;
  both repeat published/password-free eligibility and fail without fallback.
- Recurrence routes require exact `edit_post`, recheck it in the service, validate
  optimistic revisions and require server-signed confirmation for broad writes.
- Divi preview requires the exact editable document, an allowlisted module and a
  bounded object payload.
- Settings, maintenance, duplication and taxonomy color writes retain exact
  capabilities plus WordPress/action-specific nonces.
- ICS is GET/HEAD only, re-resolves one exact public snapshot and returns generic
  errors plus no-store/no-cache/nosniff on success.

### Input, output and storage

- Dates, timezones, recurrence identities, enumerations, taxonomy values, labels,
  colors, page/window limits and provider choices are validated and bounded.
- HTML, attributes, URLs, JSON-LD and ICS use context-specific late escaping or
  encoding; stored values are not trusted merely because they were saved before.
- Canonical recurrence remains protected WordPress metadata. The derived table
  stores only bounded chronology/identity/status/generation/parent values.
- `$wpdb` is confined to five reviewed occurrence adapters. Their query builders
  validate identifiers, prepare typed values, bound collections and join public
  reads to eligible parent posts.
- No runtime remote request API, visitor cookie/storage, telemetry, unauthenticated
  AJAX mutation, unsafe deserialization, code/shell execution or sensitive
  payload logging was found.

### Privacy and external services

- All event/editor data stays in the site's WordPress database.
- Published event content is deliberately public through documented HTML,
  JSON-LD, core REST, feed and exact ICS boundaries.
- Draft, private and password-protected plugin collections fail closed; protected
  core REST removes the event `meta` member until the password boundary is met.
- Google/Outlook actions are author opt-ins and visitor-initiated links. The
  plugin sends no background provider request and isolates the new tab/referrer.
- No custom exporter/eraser is necessary because the plugin creates no visitor or
  user-linked submission record; editorial events remain native WordPress content.

### Supply chain and repository

- Composer audit: zero advisories and zero abandoned packages.
- npm audit at low threshold: zero vulnerabilities across the locked development
  tree; development packages are excluded from production.
- FullCalendar 6.1.21/Preact stay local and MIT-noticed; the public repository
  supplies human-readable sources and build instructions.
- Every GitHub Action remains SHA-pinned; checkout 6.0.2, setup-node 6.5.0,
  cache 5.0.5 and upload-artifact 7.0.1 use maintained Node 24 runtimes.
- GitHub vulnerability alerts, secret scanning, push protection, Private
  Vulnerability Reporting and Dependabot security updates are confirmed enabled.

## Senior QA review

The first complete browser run exposed a date-dependent test, not a product
failure: the recurrence journey selected the first admin event, whose position
and remaining future occurrences changed on 1 September 2026. It now creates its
own event 30 days ahead, uses day-aligned dynamic feed bounds and deletes it after
the journey. A second full run exposed unrealistically simultaneous React input
events; the test now enters and verifies fields sequentially like a user. The
targeted journey and final complete suite pass.

These fixes make the test deterministic across wall-clock dates and admin
ordering while retaining all product assertions: preview/apply, exact feed URLs,
archive occurrences, occurrence navigation, sparse overrides/restores, move,
status, cancellation, following split and recurrence disable.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| `composer validate --strict` | Pass |
| PHP coding standards and compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 776 tests / 3,199 assertions |
| `composer audit --locked` | Pass, zero advisories/abandoned packages |
| Frontend build and generated Divi modules | Pass |
| Tool/security contracts | Pass, 75 tests |
| ESLint and Stylelint | Pass, zero warnings |
| `npm audit --audit-level=low` | Pass, zero vulnerabilities |
| Release allowlist/autoloader/PHP/checksum verification | Pass |
| Reproducible package | Pass, two byte-identical builds |
| Package SHA-256 | `f361895f690796c46543d898f9fb0fa32b0a881fed06d9a55ae45dc198089b15` |
| WordPress 6.9 / PHP 8.2 packaged smoke | Pass |
| WordPress 7.1 / PHP 8.2 packaged smoke | Pass |
| Playwright packaged browser matrix | Pass, 30/30 |

The generated installable package remains version 0.7.0 because RC3 changes
development/release contracts and tests, not the public runtime package.

## Hosted acceptance evidence

[GitHub Actions run 33472987700](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33472987700)
completed successfully on reviewed commit
`a9374bde1344ce2577c79a7cc666c232f6d77c90`:

- official pinned strict WordPress Plugin Check and release artifact: pass;
- PHP 8.2, 8.3, 8.4 and 8.5: pass;
- JavaScript/CSS/security contracts and translation catalogue: pass;
- WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2 packaged smokes: pass;
- packaged 30-browser regression matrix: pass;
- checksum-pinned historical install/upgrade/lifecycle matrix: pass.

All eleven hosted jobs are green. The documentation-only completion commit must
retain those same gates before handoff.

## Residual non-blocking risks

- FullCalendar requires ongoing advisory and maintenance review.
- A reverse proxy/CDN can ignore application cache headers; deployment-specific
  exclusions remain a host responsibility.
- GitHub account 2FA/recovery remains an owner setting not verifiable from code.
- RC4–RC8 still own accessibility, performance, new-user documentation,
  exploratory production validation and final 0.9 publication.

## Current guidance reviewed

- [Detailed Plugin Directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Common Plugin Directory issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)
- [WordPress security APIs](https://developer.wordpress.org/apis/security/)
- [REST endpoint permissions](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [WordPress plugin privacy guidance](https://developer.wordpress.org/plugins/privacy/)
