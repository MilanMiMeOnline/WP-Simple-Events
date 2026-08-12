# QA report — MiMe Simple Events and Calendar 0.2.5

**Date:** 2026-08-12

**Candidate:** MiMe Simple Events and Calendar 0.2.5

**Purpose:** PHP 8.2 compatibility, dependency hardening and release
qualification

## Result

Release candidate 0.2.5 lowers the public PHP requirement from 8.3 to 8.2 while
keeping WordPress 6.9 as the minimum WordPress release. The production code
needed no compatibility shim or feature reduction: its existing language
features are valid on PHP 8.2. The Composer platform, complete PHP quality
matrix, packaged WordPress smoke runs and default Playground environment now
enforce the new floor instead of relying on metadata alone.

All local source, package, browser and optional Elementor integration gates
pass on PHP 8.2. Composer and npm report zero known dependency vulnerabilities.
No runtime production dependency was added. The official strict WordPress
Plugin Check GitHub job remains the final external release gate because it runs
after the reviewed commit is pushed.

## Changes under review

- Public and development requirements now declare PHP 8.2 or newer; PHP 8.3 or
  newer remains the operational recommendation when hosting permits it.
- Composer resolves the development graph against PHP 8.2 and uses PHPUnit 11,
  patched PHPCS/WPCS packages and patched PHPCS utilities.
- CI executes PHP quality gates on 8.2, 8.3, 8.4 and 8.5.
- The packaged WordPress 6.9 and 7.0.1 smoke targets explicitly select PHP 8.2.
- The smoke runner accepts a separate `WPSE_SMOKE_PHP` runtime selector.
- The npm development graph advances to patched transitive releases, including
  `brace-expansion` 5.0.9. Node tooling remains excluded from the plugin zip.
- The Playwright fixture clock is fixed before calendar initialization so
  hard-coded boundary events remain one deterministic navigation step away.

## Automated evidence

- Native PHP 8.2.33 CLI dependency installation and platform check: passed.
- `composer validate --strict`: passed.
- `composer qa` on PHP 8.2.33: passed.
  - WordPress/PHP coding standards: 8/8 filesets passed.
  - PHPStan level 8: 129/129 files, no errors.
  - PHPUnit 11.5.56: 271 tests, 1,023 assertions.
  - Composer audit: zero advisories.
- `composer qa` on PHP 8.5.8: passed with the same assertions.
- `npm run qa`: passed.
  - Production JavaScript builds completed.
  - Tool contract tests: 20/20.
  - JavaScript and CSS lint: zero warnings.
  - Full npm audit: zero vulnerabilities.
- Production npm audit: zero vulnerabilities.
- Translation catalogue regenerated and byte-comparison check passed with
  WP-CLI 2.12.0 running on PHP 8.2.33.
- Release archive contents, PHP 8.2 syntax, production autoloader, checksum and
  byte-for-byte reproducibility checks: passed.
- Packaged WordPress 6.9 / PHP 8.2 smoke journey: passed.
- Packaged WordPress 7.0.1 / PHP 8.2 smoke journey: passed.
- Playwright on packaged WordPress 7.0.1 / PHP 8.2: 15/15 journeys passed.
- Real Elementor host inspector on WordPress 7.0.1 / PHP 8.2:
  - Elementor 3.35.9: passed.
  - Elementor 4.1.5: passed.
- `git diff --check`: passed before final review.

## Browser-harness finding

The first browser run produced six failures because its August 2026 event
fixtures assumed the real browser month was July 2026 and navigated with a
relative **Next** action. On 12 August the same action correctly opened
September, making the events unavailable to those assertions. Nine unrelated
journeys passed, and the packaged WordPress smoke suites were already green.

The harness now fixes the browser time to 15 July 2026 before loading each
fixture page. This preserves the intended test boundary without changing plugin
runtime code, WordPress time or event metadata. The complete 15-journey suite
then passed. This correction prevents recurring month-boundary CI failures and
would fail again if FullCalendar stopped honoring the visitor's current month.

## Security and privacy review

- The compatibility change adds no endpoint, request parameter, capability,
  nonce, database query, event field, cookie, telemetry or remote runtime call.
- Publication, REST visibility, password protection, bounded queries, escaping,
  structured data and uninstall ownership were re-exercised in both packaged
  WordPress smoke runs.
- The PHP version change does not weaken validation or introduce conditional
  code paths. Every shipped PHP file parses on PHP 8.2 and the optimized runtime
  autoloader loads all 126 production classes.
- Composer and npm high/critical release gates remain strict. No advisory was
  ignored, allowlisted or suppressed.
- PHPUnit, PHPCS, WPCS, Playwright, Elementor packages and the npm override are
  development-only and absent from the production archive.
- The temporary Elementor Playground adapter and downloaded host packages were
  stored outside the repository and are not release inputs.
- Privacy behaviour is unchanged: no visitor tracking, remote assets or service
  connection is introduced.

## Senior developer review

- PHP 8.2 supports the production `readonly` classes, readonly properties,
  enums and constructor defaults already in use; no broad refactor is needed.
- Composer's `config.platform.php` prevents future development dependencies
  from silently raising the tested minimum above the public runtime contract.
- PHPUnit 11 is the current compatible test major for PHP 8.2 and preserves the
  existing 271-test suite without test suppression or changed assertions.
- Runtime selection belongs in the disposable smoke configuration rather than
  production code, and the core and PHP selectors remain independent.
- Existing public identifiers, data, URLs, hooks, templates, shortcodes, blocks
  and Elementor widget names are unchanged; upgrading is metadata-compatible.

## Senior QA review

- Compatibility is proven at four layers: dependency resolution, complete PHP
  gates, PHP 8.2 parsing/autoloading of the production package and real WordPress
  execution on both supported WordPress targets.
- The smoke journeys cover activation, publishing invariants, permissions,
  forged nonces, REST exposure, timezones, filters, shortcodes, blocks, settings,
  maintenance, uninstall boundaries and classic/hybrid/block theme shells.
- Browser coverage verifies month/list behavior, filter persistence, wall-time
  placement across visitor timezones, 12/24-hour output, controls, loading and
  failure states, multiple instances, hidden containers and Gutenberg fields.
- Both the minimum supported Elementor line and current tested 4.x line were
  exercised on the new PHP floor, including widget controls and Event editing.
- PHP 8.1 and 8.0 remain explicit non-goals for this release rather than
  unverified implied support.

## Release artifact

- Archive: `dist/mime-simple-events-calendar-0.2.5.zip`
- SHA-256:
  `8505605679b81b8527760ec4f16f2d7abb44f6796b39ebb9b5aaef176da1c85c`
- Two consecutive builds produced the same checksum.

## Remaining release gates

1. Commit and push the reviewed 0.2.5 candidate.
2. Require every GitHub Actions matrix job, including strict official WordPress
   Plugin Check, to pass for that exact commit.
3. Tag and publish the exact verified artifact only after that matrix is green.
4. Update WordPress.org SVN `trunk`, create tag `0.2.5` and copy the unchanged
   directory assets only after the GitHub release is final.

No commit, push, GitHub tag, GitHub release or WordPress.org SVN change was
performed during local qualification.
