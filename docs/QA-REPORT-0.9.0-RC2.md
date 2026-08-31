# QA report — 0.9.0 RC2 install, upgrade and lifecycle matrix

**Date:** 31 August 2026\
**Candidate:** 0.9.0 release-candidate cycle, RC2 checkpoint\
**Scope:** clean install, public upgrades, derived repair, scheduled work,
deactivation, reactivation and uninstall

## Result

RC2 passes locally. Every canonical public GitHub package from 0.2.3 through
0.7.0 upgrades to the current staging package without changing the meaning of
canonical events, taxonomies, settings or saved Gutenberg, Elementor and Divi
content. Clean activation, missing occurrence-table repair,
deactivation/reactivation, default retained uninstall and explicit destructive
uninstall also pass in the real WordPress matrix.

The matrix found and closed two genuine lifecycle gaps before 0.9.0:

1. a missing derived occurrence table was not repaired when the stored schema
   option already claimed the current version; and
2. a completed historical migration could retain one stale scheduled worker.

Neither fix changes canonical event data. Missing derived storage is rebuilt and
repopulated from canonical metadata; completed migration now removes only its
obsolete scheduled callback.

## Supported upgrade boundary

The normative [upgrade contract](UPGRADE-CONTRACT.md) supports automatic
in-place upgrades from the exact 0.2.3, 0.2.4, 0.2.5, 0.3.0, 0.4.0, 0.5.0,
0.6.0 and 0.7.0 packages. Each archive and the WordPress 6.9 core archive are
bound to a reviewed SHA-256 value in `tools/upgrade-releases.json`.

Versions 0.2.1 and 0.2.2 used the old package directory and bootstrap identity.
Their documented safe route remains a manual retained-data handoff; activating
the old and canonical package together is unsupported. Downgrades and untagged
development snapshots are outside the contract.

## Real WordPress evidence

`npm run test:upgrade` builds the current release staging tree and runs one
isolated WordPress 6.9/PHP 8.3 environment. For every historical baseline it:

- downloads the published GitHub release, verifies its checksum and rejects an
  unexpected archive root or unsafe path;
- activates the historical package and stores representative one-off event,
  category, tag, settings and saved builder data, plus recurrence from 0.4.0,
  colors from 0.6.0 and Add to Calendar state from 0.7.0;
- replaces only the plugin files with the current staging tree and completes
  normal bounded migration work;
- proves all prior canonical fields remain byte-for-byte meaningful while
  additive current metadata is permitted;
- verifies schema 2.1.0, exact table columns, occurrence coverage, capabilities,
  completed rewrite state and exactly one continuing cleanup/renewal job; and
- removes all fixtures and the disposable WordPress environment after the run.

Automatic WP-Cron spawning is disabled only inside this harness. The runner
still asserts the stored schedule and invokes the migration hook explicitly,
which prevents a concurrent Playground request from racing the next historical
fixture while preserving the lifecycle behaviour under test.

The lifecycle continuation then drops only the derived table and proves normal
boot recreates and repopulates it. It verifies deactivation clears all three
plugin-owned jobs while retaining persistent data, reactivation restores current
bounded work without duplicates, retained uninstall clears executable jobs but
preserves data, and destructive uninstall removes only the documented plugin
data while retaining shared pages and media.

No production or persistent local website was modified.

## Automated evidence

- `composer validate --strict`: passed.
- `composer qa`: passed; coding standards and PHPStan level 8 are clean,
  **776 PHPUnit tests with 3,199 assertions** pass and Composer reports no known
  vulnerability advisory.
- `npm run qa`: passed; **69 tooling tests** pass, all builds and JavaScript/CSS
  lint are clean, and npm reports no vulnerability.
- `npm run test:upgrade`: all eight public upgrade paths and all lifecycle paths
  passed against the checksummed WordPress 6.9 package.
- `npm run test:release`: exact archive content, shipped PHP, production
  autoloader, checksum and byte-for-byte reproducibility passed.
- packaged smoke journeys: WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2 both
  passed against `.release/mime-simple-events-calendar`.

The new `Historical install and lifecycle matrix` GitHub Actions job runs the
same checks on every push and pull request. As with every checkpoint, the pinned
official strict Plugin Check result and hosted matrix must be green on the exact
pushed commit before it can become publication evidence.

## Senior developer review

Canonical data remains owned by posts, terms, metadata and settings; the
occurrence table, generation markers, migration options, rewrite markers and
cron callbacks remain disposable internal state. The repair path first proves
table creation succeeded, then invalidates only derived projection generations
and migration completion. A database creation failure neither claims the schema
nor discards projection state, so normal boot can retry safely.

Lifecycle cleanup is centralized in one final class used by deactivation and
uninstall. It includes every current plugin-owned hook and the only disposable
continuation cursor. The migration scheduler is idempotent in both directions:
it queues at most one worker while incomplete and clears obsolete workers after
completion.

Historical inputs are not inferred from mutable branches or rebuilt source.
Checksums, archive-root validation and an explicit per-release schema field keep
future manifest changes reviewable and avoid fragile lexical version logic. The
test-only REST probe is copied only into a disposable must-use plugin directory,
requires a fixed isolated token and is excluded from the production archive.

## Senior QA review and residual risk

- The matrix proves the supported canonical package identity from 0.2.3 onward;
  the necessary 0.2.1/0.2.2 directory rename remains a documented manual path,
  not something WordPress can safely automate.
- The real matrix is single-site. Network-wide activation is deliberately
  rejected; existing unit coverage protects per-site multisite cleanup, while a
  dedicated real multisite qualification remains appropriate for RC8.
- Database permission failures are deterministic unit-test scenarios because a
  real database cannot safely inject them; they prove no schema claim or state
  reset occurs on installation failure.
- Security/privacy, accessibility and performance receive fresh dedicated RC3,
  RC4 and RC5 reviews. Passing RC2 does not pre-approve those work packages.
- Official Plugin Check and all hosted CI jobs remain required on the pushed
  commit. No public 0.9.0 artifact is published by this checkpoint.

There is no open RC2 blocker.
