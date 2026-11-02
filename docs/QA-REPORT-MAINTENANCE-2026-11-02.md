# Local security maintenance — 2 November 2026

**Scope:** Tooling maintenance on `codex/security-maintenance-20261102`, based on
main commit `63b717e436a4c9c4d0dc7eb8baca00c913664cce`. This report records local
validation before the owner-authorized GitHub delivery. Hosted check results
are recorded on the corresponding pull request. Published plugin 1.1.1 remains
unchanged; this maintenance does not create a tag, release or deployment.

## Changes and scope

- Updated the exact qs override from 6.16.0 to 6.16.1 for
  [GHSA-6pq8-jhm2-2vcw](https://github.com/advisories/GHSA-6pq8-jhm2-2vcw).
- Scoped glob 14.0.3 to rimraf for
  [GHSA-3g3m-2fjr-6pw4](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4).
  The lock also updates glob's dependency subtree, including path-scurry and
  lru-cache, and removes dependencies no longer needed by that subtree.
- Scoped minimatch 11.0.4 to consumers previously requesting major 9 or newer
  for [GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249).
  Legacy consumers retain minimatch 3.1.5 and its callable CommonJS API.
- Added seven regressions in `tools/dependency-filesystem-security.test.mjs`:
  malformed query serialization, bounded optimized matching through both modern
  consumers, normal legacy matching, cancelled filesystem traversal, and both
  asynchronous and synchronous selected-file cleanup.
- Recorded the temporary override boundaries and remaining lint migration in
  ADR-109. No audit exclusions, lint-rule changes or relaxed tests were added.

The changed libraries serve development tooling; none appears in the browser
build dependency graph or the shipped plugin. FullCalendar, Composer dependencies,
plugin PHP, generated assets, event data and public version numbers are unchanged.
The guarded braces alias and wp-env/simple-git compatibility patch are retained.

## Senior developer review

The overridden modern consumers use named exports retained by their new
dependencies. Both rimraf cleanup paths are covered with disposable fixtures;
tests assert that files outside the selection survive. Cancellation tests verify
that directory reads actually stop, rather than only checking promise rejection.
The installed dependency tree resolves cleanly under `npm ls`; a clean
`npm ci --strict-peer-deps` succeeds without force or legacy peer resolution.

An isolated ESLint 10.14.0 / WordPress lint preset 28.0.0 experiment did not have
a supported peer graph: current import/react/jsx-a11y lint plugins exclude
ESLint 10, and the selected Vitest lint plugin requires TypeScript >=6 while
this project uses 5.9.3. The experiment was not copied into the project.
Retaining the existing rule set avoids silently losing checks. ESLint 9 is
nevertheless past its support period, so this remains maintenance debt, not a
completed major migration. Continue existing
[Dependabot PR #16](https://github.com/MilanMiMeOnline/WP-Simple-Events/pull/16)
once its complete peer graph is supported; its older target needs refreshing.

## Senior QA review and evidence

- Before updating, the new qs, two modern minimatch and glob cancellation
  regressions failed against the installed old dependencies. The legacy API
  and asynchronous cleanup compatibility cases passed. All seven final cases
  pass after the update, including the subsequently added synchronous cleanup.
- `composer validate --strict`: passed.
- `composer qa` on PHP 8.3.36: passed, including coding standards, PHPStan,
  791 tests / 3,264 assertions and a clean Composer security audit.
- `npm run qa` on Node 24.21.0: passed, including asset builds, 107 tooling
  tests, JS/CSS lint and the existing high-severity audit gate. This does not
  mean the npm audit is empty; see the remaining findings below.
- `npm run test:release`: passed, including archive contents, PHP syntax,
  autoloader, checksum and two byte-for-byte identical builds.
- Packaged WordPress smoke validation: passed with
  `WPSE_SMOKE_CORE=WordPress/WordPress#7.1`, `WPSE_SMOKE_PHP=8.2` and
  `WPSE_SMOKE_PLUGIN_PATH=.release/mime-simple-events-calendar`. The disposable
  site completed the settings, persistence, REST validation, protected/private
  event, recurrence, template, calendar and ICS journeys. It stopped and was
  cleaned up normally. These selectors describe the Playground test runtime;
  they do not establish the patch versions of any customer installation.

The rebuilt 1.1.1 ZIP is also byte-for-byte identical to the previously published
GitHub archive, with SHA-256
`2016b44584de2fea309183f163e5c289b9b71ccf3741800e777233a362d3267c`.
It was built for verification only and was not uploaded.

## Remaining findings and boundaries

The full npm audit decreases from 30 to 22 moderate affected-package entries;
there are zero high or critical entries. The 22 entries trace to **one** advisory,
GHSA-g3hv-75wg-m249, through legacy minimatch 3.1.5 and its dependent lint packages.
The advisory's broad version range includes this version, but source inspection
finds no `optimizationLevel` / `firstPhasePreProcess` expansion algorithm there.
The pathological-pattern regression retains one pattern on that implementation.
This is a reachability assessment of the reported algorithm, not a claim that
all possible denial-of-service inputs or future advisories are ruled out. Keep
the advisory visible and monitor upstream corrections and the supported lint
migration. Do not replace the callable legacy API with minimatch 11 globally.

The exact `@dieub/braces-depth-guard@3.0.3-pn.3` artifact retains its reviewed
runtime files and integrity; the ten existing depth regressions pass. Its GitHub
source remains unavailable (HTTP 404), while the npm artifact remains available.
Official upstream braces 3.0.3 still lacks the depth guards. Continue checking
upstream advisories as well as the fork; no automatic replacement was made.

No customer website, hosted WordPress/PHP, or licensed builder was updated or
tested. The separately reported WordPress and Divi host updates still require
checking actual site versions and a suitable site maintenance window. This work
does not establish whether any customer installation is affected.

At the local validation stage, no hosted CI, full browser suite, historical
upgrade matrix, supported-version matrix or official Plugin Check was rerun.
The subsequent GitHub delivery runs the existing Quality workflow; consult its
results on the pull request for that additional evidence.
The production ZIP is unchanged; this is not a new release candidate or a waiver
of future publication checks. No production permissions, REST routes, privacy
boundaries or event persistence changed.

Local audit and execution logs are retained in
`/private/tmp/mime-maintenance-20261102-Y9tEQe`; the preceding monitoring evidence
is in `/private/tmp/mime-security-20261102-RgCmdy`. Neither directory is part of
the repository or release.
