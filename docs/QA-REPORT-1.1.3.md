# MiMe 1.1.3 release qualification

**Date:** 19 November 2026
**Status:** Released and publicly verified on GitHub and WordPress.org.

## Authorization and scope

The owner approved going live after receiving the qualified 1.1.3-beta.1
candidate. ADR-113 records that authorization. The final package preserves the
candidate runtime and dependencies; only version metadata and public release
wording change. The [beta report](QA-REPORT-1.1.3-beta.1.md) contains the developer
and QA review, security regressions, fresh-install evidence, 792 PHP tests, 115
tooling tests, 42 browser journeys on both WordPress 7.1 and 7.1.5, both packaged
smoke targets, all 13 historical upgrades and the performance qualification.

The release updates bundled calendar behavior and development security tools,
without changing stored events, settings, schema, public APIs or editor fields.
No customer website or hosting PHP is changed by publishing this plugin.

Final qualification and publication evidence follow. No checks were waived.

## Final local gates and review

- Strict Composer validation and composer qa pass: 792 tests / 3,278 assertions,
  coding standards, static analysis and clean security audit.
- npm run qa passes: 115 tooling tests, builds, lint and zero audit findings.
  The first final run exposed a stale assertion for the old WordPress 7.1
  test environment. It now requires the exact intended 7.1.5 target; no test,
  budget or security rule was removed or relaxed.
- Translation consistency and two byte-identical release builds pass.
- The final ZIP has the same 384 paths as the approved beta. Only CHANGELOG,
  readme, translation version metadata and the bootstrap version differ.
  Normalizing the bootstrap version makes it byte-identical. All other PHP,
  JavaScript, CSS and production dependency files are identical.
- Senior developer review confirms scoped dependency/security changes, preserved
  plugin interfaces and no unrelated files. Senior QA review confirms retained
  negative security fixtures, calendar regressions and historical upgrade gates.

Final artifact: `mime-simple-events-calendar-1.1.3.zip`.
SHA-256: `07654df2a78ff3f38c990ff1164f1e678219422aa539e218d5385f0daa0dd5e8`.

Execution logs: `/private/tmp/mime-113-release`.

## Final matrix and hosted qualification

- Final local WordPress 6.9 and 7.1.5 / PHP 8.2 smoke journeys passed.
- All six performance budgets and 17 predicate cases passed on WordPress 7.1.5.
- All 13 checksummed historical upgrades and lifecycle cases passed.
- All 42 final browser journeys passed locally.
- [Hosted Quality](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/44538633758) passed all 13 jobs on `37cdde93f54fcfa91f84b4112fa9d12185c8e577`: PHP 8.2–8.5, JS/CSS, translation, both browser shards, both smoke targets, performance, historical upgrades and strict Plugin Check.
- The independently built CI ZIP is byte-identical to the qualified local ZIP.

## Publication

- [PR #30](https://github.com/MilanMiMeOnline/WP-Simple-Events/pull/30) merged as `7643c2ee78b402e3fe5e716316c56e61120f646e`; its tree matches the qualified PR head.
- [GitHub v1.1.3](https://github.com/MilanMiMeOnline/WP-Simple-Events/releases/tag/v1.1.3) published at 2026-11-19T14:19:15Z; its tag points to the qualified merge commit. The public ZIP matches the local and CI ZIP byte for byte.
- WordPress.org trunk and tag 1.1.3 published atomically in SVN revision `3811742`. Both matched the qualified 384-file tree before commit. The public plugin API reports 1.1.3 and all 384 paths and uncompressed file contents in its download match the qualified package.

## Boundaries

No customer installation, database or hosting PHP was changed. Licensed live Divi/Elementor host combinations were not independently requalified; their supported interfaces and shared rendering remain covered by existing regression tests.

The narrower Dependabot proposals #29 and #17 were closed as superseded by
this release. Immediately after merge, GitHub's alert dashboard still displayed
the three Git/XML findings as open; the
qualified lockfile contains the fixed versions and both local and hosted audits
are clean. A processing delay is a possible explanation, not independently
confirmed. Dashboard closure has not been verified.
