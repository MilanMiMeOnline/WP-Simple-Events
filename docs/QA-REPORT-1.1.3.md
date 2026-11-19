# MiMe 1.1.3 release qualification

**Date:** 19 November 2026
**Status:** Owner-authorized final qualification; publication pending.

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

Final artifact, local checks, hosted qualification and publication verification
will be recorded below as each completes. No checks are waived.

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

Final-version WordPress matrix and hosted checks remain mandatory before
publication. Execution logs: `/private/tmp/mime-113-release`.
