# MiMe 1.1.3-beta.1 maintenance qualification

**Date:** 19 November 2026  
**Status:** Local qualification passed; ready for owner testing. Not published.

## Scope and authorization

The owner requested necessary updates and verification following monitoring.
ADR-112 records the bounded implementation. Work starts from clean main
`debd6900dff58ef3634caa9340251a76bab43e4d` on
`codex/maintenance-20261119`. Public GitHub and WordPress.org remain 1.1.2.

- FullCalendar 7.1.2 -> 7.1.5; Temporal polyfill 1.0.6 -> 1.0.8 and
  temporal-utils 1.0.3 -> 1.0.4. Preact remains 10.29.8.
- Scoped isomorphic-git override 1.37.6 -> 1.38.6 under Playground storage.
- fast-xml-parser 5.11.2 -> 5.12.1; its compatible builder moves 1.3.1 -> 1.3.2.
  Reuse and refresh existing Dependabot PR #29 on authorized GitHub delivery.
- Both independently bundled Playground tree parsers receive an exact-source,
  version-checked reserved-path guard. Existing wp-env and braces guards remain.
- Default local WordPress and current-line CI smoke/performance target move to
  7.1.5. The minimum 6.9 and historical upgrade baselines remain isolated tests.
- Add public 1.1.2, verified by SHA-256, to the historical upgrade matrix.

No event data, schema, save path, REST interface, permissions, PHP renderer,
recurrence behavior or builder settings change. Production PHP changes only the
candidate version constant/header. No customer installation is modified.

## Security evidence

Before updating, four new regressions fail: checkout through a directory symlink,
reserved tree-entry names, circular symbolic refs and quadratic malformed XML.
After updating they pass with normal Git/XML control cases. Fixtures and symlink
sinks are confined to newly created temporary directories and always cleaned up;
child processes bound the formerly nonterminating cases.

Independent inspection found Playground's copied GitTree parser accepts reserved
names too. Its ESM/CJS test fails before the guard and passes afterward. This is
separate from the clean lockfile audit. The patch follows upstream 1.38.6's
reserved-name handling, preserves attribution, validates both source checksums
before writing either file, and refuses unknown versions or altered source.
Three additional tests check repeatability, tamper refusal and no partial change
when validation fails. There is no security exception, audit ignore or relaxed gate.

The embedded isomorphic-git entrypoints are indexPack/readObject with raw-content
reads in an in-memory filesystem; the copied tree parser is separately guarded.
This review is scoped to the reported issues, not a general Playground audit.
The normal independently installed Git API uses the patched upstream package.

A fresh npm ci in a temporary installation runs both postinstall patches and
passes all five security regressions. Dependency-tree validation reports no
problems. Composer and npm audits report zero advisories after maintenance.
The development dependencies, patches and tests are absent from the ZIP. A
fresh build metadata check identifies only fullcalendar, headless-calendar,
preact, temporal-polyfill and temporal-utils in the visitor calendar bundle;
that independent output is byte-identical to the checked-in asset.

## Completed checks

- composer validate --strict: passed.
- composer qa: passed; 792 tests / 3,278 assertions, PHP coding standards,
  static analysis and security audit on PHP 8.3.36.
- npm run qa: passed; 115 tooling tests, builds, JS/CSS lint and clean audit
  on Node 24.21.0 / npm 11.19.0.
- Translation regeneration and consistency: passed.
- Two consecutive release builds: byte-identical; content, syntax, production
  autoloader and archive verification pass.
- Targeted upgrade-contract checks pass after extending the manifest to 1.1.2.

- Full browser suite: all 42 journeys pass on WordPress 7.1 (5.2 minutes),
  then all 42 pass on WordPress 7.1.5. No retries or timeout increases.
  This includes the new navigation accessibility assertions, default/category/
  event colors, multi-day month/list rendering, mobile, time formats, Brussels
  and Los Angeles/DST, hidden-container and Elementor initialization, isolated
  Divi styles, Gutenberg previews/saves, recurrence and unknown end times.
- Visual QA of the saved desktop/month and mobile/list captures: readable,
  correct grid and mobile stacking, with no visible overflow.
- Packaged WordPress smoke: 6.9/PHP 8.2 and 7.1.5/PHP 8.2 both pass,
  including persistence, permissions, public/protected visibility and templates.
- Performance: all six unchanged budgets and 17 real-WordPress predicate cases
  pass on WordPress 7.1.5/PHP 8.2 with 500 series / 5,000 occurrences.
- Upgrade/lifecycle: all 13 checksummed historical releases, including 1.1.2,
  pass on the isolated WordPress 6.9/PHP 8.3 baseline. Clean installation,
  derived-table repair, deactivation/reactivation and both uninstall choices pass.
- Final npm audit: zero findings at every severity. Git diff whitespace check passes.

Final candidate: `dist/mime-simple-events-calendar-1.1.3-beta.1.zip`.
SHA-256: `a56d986e0214bc580553f301865b2a4a992780e6b7eff51cb17203d4f9422b5c`.
The subsequent browser/performance/upgrade rebuilds retain this same checksum.
The separate clean npm-install directory was removed after validation; its logs
are retained. Each disposable WordPress environment stopped and cleaned up.
No assertion, audit rule, performance budget or existing test was weakened.

## Review and publication boundaries

Senior developer review confirms a focused lockfile diff, preserved scoped
security overrides, unchanged plugin contracts, locally bundled assets and
installation failure on unknown patched sources. QA review checks negative and
positive security cases, original calendar color regressions, date/timezone,
protected-event, editor, builder lifecycle and upgrade coverage.

The PHP 8.3 release endpoint still reports 8.3.36. The announced 19 November
critical patch is not available at the time checked, so PHP is not falsely
reported as updated. Recheck official publication and the package manager before
installing the patch within the existing branch. No PHP prerelease is installed.

Hosted CI and official Plugin Check are not rerun because no push/publication is
authorized. Licensed live Divi/Elementor installations and customer websites are
not changed or independently requalified. These remain publication/host-specific
boundaries, not assertions of success. Owner testing and explicit publication
approval remain required under ADR-104.

Temporary execution evidence: `/private/tmp/mime-maintenance-20261119`.
