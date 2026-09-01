# 0.9.0 RC6 new-user documentation qualification

**Date:** 1 September 2026

**Scope:** public repository and WordPress.org readmes, task-based user guides,
documentation navigation, install/upgrade advice and development dependency
security.

**Status:** complete. Local qualification and the full hosted compatibility,
browser, upgrade, performance and strict Plugin Check matrix passed.

## Outcome

RC6 replaces a technically complete but fragmented onboarding path with one
user-facing entry point and six focused task guides. A new user can now move from
installation to a published event, choose a display approach, create or safely
edit recurrence, use the supported builders, diagnose common problems and
understand privacy, deletion and updates without reading engineering contracts.

The repository README was rewritten around outcomes and a five-minute setup. The
WordPress.org readme now checks site timezone and formats before the first event,
explains the first page and mobile verification, and answers recurrence, filter,
header/footer and builder questions. All guide links use explicit GitHub URLs in
the production README because the release allowlist deliberately excludes the
internal `docs/` tree.

## Documentation set

- [User guide](USER-GUIDE.md) — task router and settings map;
- [Getting started](GETTING-STARTED.md) — install through logged-out verification;
- [Displaying events](DISPLAYING-EVENTS.md) — native pages, components, filters,
  colors, calendar export and bounded shortcode examples;
- [Recurring events](RECURRING-EVENTS.md) — creation, previews, three scopes,
  inheritance and destructive stop-repeating behavior;
- [Builders and templates](BUILDERS-AND-TEMPLATES.md) — Gutenberg, Elementor
  Free/Pro, Divi 5 and theme/template ownership;
- [Troubleshooting](TROUBLESHOOTING.md) — editor, timezone, query, recurrence,
  filter, calendar, builder and theme-shell failures; and
- [Privacy, data and updates](PRIVACY-DATA-AND-UPDATES.md) — local storage, public
  data, retention, uninstall and automatic/manual upgrade boundaries.

## Senior developer review

- Every named UI choice was checked against its current source label, including
  recurrence scopes, end modes, event-color sources and occurrence maintenance.
- Bounded shortcode examples accept only implemented attributes and values.
- The guide distinguishes Elementor Free page widgets from Pro Theme Builder and
  keeps Divi, Elementor and WooCommerce optional.
- Add to Calendar remains a one-event snapshot and separately placeable component;
  recurring series pages do not promise unsupported whole-series export.
- Privacy text distinguishes same-site processing from the visitor's deliberate
  Google, Outlook, location or event-link navigation.
- The 0.2.3+ automatic upgrade boundary and 0.2.1/0.2.2 renamed-package handoff
  match the normative lifecycle contract.
- Public text no longer advertises “manual occurrences” as an editor feature.
  Sparse overrides, cancellations and this-and-following reconciliation remain
  accurately documented.
- No runtime source, public schema, saved content identity or production
  dependency changed.

## Senior QA review

- The primary route covers install, first success, normal use, advanced use,
  failures, privacy and lifecycle ownership.
- Failure guidance avoids destructive repair by default and directs users to the
  matching health state, backup and one bounded maintenance action.
- Native header/footer behavior and intentional Theme Builder/theme overrides are
  described separately, closing a previously confusing ownership gap.
- Filter instructions distinguish remove, Clear all and Restore defaults and
  explain isolated shareable URL state.
- Color guidance uses exact source choices and explains the solid-block versus
  timed-dot calendar presentation without relying on color alone.
- All relative Markdown links across the repository resolve. Production README
  guide links remain valid from the installed ZIP through explicit GitHub URLs.
- An executable tooling contract asserts the complete guide path and its critical
  recurrence, builder, troubleshooting, privacy and upgrade topics.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| Local Markdown links | Pass, all relative links across 91 Markdown files resolve |
| Documentation contract | Pass, 2 focused contracts within 78 tooling tests |
| `composer validate --strict` | Pass |
| PHP coding standards and compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 776 tests / 3,201 assertions |
| Composer audit | Pass, zero advisories/abandoned packages |
| Frontend/Divi build, ESLint and Stylelint | Pass, zero warnings |
| npm audit | Pass, zero vulnerabilities after patched dev-tool lock update |
| Production npm audit | Pass, zero vulnerabilities |
| Translation catalogue | Pass, current |
| Release content/checksum/reproducibility | Pass, two byte-identical builds |
| Candidate archive SHA-256 | `78c16853a8dc15d0ddfd8e3f9633ad5a67e327858106bd9b47610293f959decd` |

The package continues to report public version 0.7.0 because the 0.9.0 version
bump and publication belong to RC8. RC6 does not publish this checkpoint.

## Hosted qualification evidence

GitHub Actions run
[`33546674008`](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33546674008)
qualified reviewed commit `7c6a821712e830a42c86dfc944efffb344bc5900`.
All 12 jobs completed successfully:

- PHP 8.2, 8.3, 8.4 and 8.5 quality matrices;
- WordPress 6.9 and WordPress 7.1 smoke tests on PHP 8.2;
- JavaScript, CSS and translation checks;
- browser regressions;
- historical install and lifecycle upgrades;
- bounded performance budgets; and
- reproducible release archive plus official WordPress Plugin Check.

## Residual non-blocking risks

- Documentation cannot anticipate every theme, optimization plugin or builder
  extension; RC7 retains destructive local and non-destructive production
  exploratory validation.
- WordPress.org renders `readme.txt`, while the longer task guides remain online
  in GitHub. The readme therefore stays self-contained for installation and basic
  decisions and links outward only for deeper workflows.
- Screenshots remain the current 0.7.0 set. RC8 owns the final visual and release
  metadata review after RC7 findings are closed.
