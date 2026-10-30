# Unknown event end regression — local patch review

Date: 30 October 2026. Branch: `codex/fix-unknown-event-end`.
Baseline: `7831f7b` (published 1.1.0 follow-up). This is unpublished work;
version metadata remains 1.1.0 and locally rebuilt archives are test artifacts,
not the published release archive. No commit, push, merge or deployment was made.

The following is historical pre-release evidence. Final versioned qualification
and publication are recorded in [the 1.1.1 report](QA-REPORT-1.1.1.md).

## Contract and implementation review

ADR-108 records the owner-approved behavior: both timed end fields blank means
unknown. Canonical metadata and recurrence serialization preserve that empty
value. A separate derived query boundary keeps the event upcoming through the last instant of
its captured local start day (normally 23:59:59). DST conversion uses the event timezone and subtracts one second from the next
local midnight. A regression covers a zone that skips its final clock hour, so
the cutoff cannot normalize into the following date.

Developer review traced every domain end accessor and every stored end-index
consumer. Persistence, index repair, recurrence generation, overrides, disabling
recurrence, impact previews, public occurrence reads, calendar feeds, admin
columns, presentation and JSON-LD now preserve the distinction. Existing domain
arithmetic and actual-duration calendar exports retain their normalized anchor;
only query indexes use the end-of-day boundary. Public SQL keeps its publication,
password, generation and dirty-index predicates outside the added overlap clause.
No dependencies, permissions, nonce checks, routes or table schema were changed.

QA review checked blank versus partial end input, explicit equal ends, real end
times, single/multi-day all-day events, DST, recurrence round trips and restoration
of derived indexes. A same-start explicit end and an unknown end are distinct in
impact previews. Missing all-day end dates still mean the same calendar day.

Legacy equal-start/end timed metadata is deliberately not migrated: its original
intent is unknowable. JSON-LD omits those ambiguous timed `endDate` values without
removing the event graph. To adopt the new upcoming-until-midnight behavior for an
old event, clear both end fields and save. All-day equal dates remain valid.

## Verification

- Regression-first run reproduced the failure: missing-end schema was rejected,
  and the explicit/unknown-end distinction did not exist in the old domain.
- `composer validate --strict`: passed.
- `composer qa`: passed, including static analysis, 791 unit tests / 3264
  assertions and Composer advisory audit.
- `npm run qa`: passed, including build, 100 tooling tests, JS/CSS lint and the
  configured high/critical audit gate.
- `npm run i18n:check`: passed after regenerating changed source references.
- `npm run test:smoke`: passed on configured WordPress 7.1 / PHP 8.2, including
  actual metadata/projection queries on both sides of local midnight and calendar
  membership on the event day versus the next day.
- Browser verification: all 39 scenarios verified on the final runtime code.
  The full run passed all 38 existing scenarios. After correcting the new test
  harness, its targeted rerun passed (1 test, 1.3 minutes), including editor
  save/reload, partial REST update, public feed, JSON-LD and an exact assertion
  that the calendar list shows only the saved start time. The runtime code did
  not change between those final runs.
- `git diff --check`: passed.

### Existing-site upgrade verification

Additional owner-requested compatibility check on 30 October 2026:
`npm run test:upgrade` passed all 11 checksummed historical packages (0.2.3,
0.2.4, 0.2.5, 0.3.0, 0.4.0, 0.5.0, 0.6.0, 0.7.0, 0.9.0, 1.0.0 and 1.1.0)
on isolated WordPress 6.9 / PHP 8.3. Each package was activated and populated
before its files were replaced with the patched staging tree. This includes
the original published 1.1.0 ZIP, independently checksum-verified, rather than
the locally rebuilt ZIP bearing the same version metadata.

The lifecycle fixture now includes both past and future timed events with an
explicit stored end equal to the start. The before/after comparisons preserved
their canonical values, together with normal timed events, recurrence aggregates
where supported, event IDs, content, terms, settings and saved builder layouts.
Clean activation, missing occurrence-table repair, deactivation/reactivation,
retained uninstall and explicit destructive uninstall also passed. No customer
database was accessed. This proves the tested upgrade paths, not every possible
hosting/plugin combination or the WordPress.org update delivery mechanism.

Developer review confirmed the patch adds no schema change or automatic
canonical-date rewrite; unchanged current-schema installations exit the existing
upgrade handler. QA review confirmed that the fixture checks both historical
equal-end cases, that published package checksums are enforced and that fixture
cleanup is confined to the disposable test site. The upgrade contract tests
(4 tests), fixture PHPCS and whitespace checks passed after these test-only
additions. Runtime plugin code was unchanged from the earlier QA runs.

An earlier broad browser run encountered Playground database-connection errors
on core WordPress admin/public pages. A clean rerun recovered; that failed run
was not counted as a pass. New test-harness corrections used the existing list
query limit, the required ISO calendar-request boundaries, the actual accessible
button name and an explicit UTC formatting carrier for the expected wall time.
No production guard or test assertion was suppressed to obtain a passing result.

The npm audit also reports a moderate `qs` advisory,
[GHSA-6pq8-jhm2-2vcw](https://github.com/advisories/GHSA-6pq8-jhm2-2vcw), propagated
through local development-server dependencies. This patch changes no manifests
or lockfiles and does not resolve that separate maintenance finding. Do not
interpret the passing high/critical gate as an entirely clean npm audit or run
the suggested breaking `audit fix --force` as part of this date fix.

## Owner check before publication

On a test installation, open an event with a start time, clear both end date and
end time, save and reopen it. Both end controls should remain blank. Its public
page should show only the start, and its Event JSON-LD should omit `endDate`.
Confirm that an event with a real end still shows the complete range. Check the
same behavior in your actual Divi/Elementor templates before approving release.

Full release qualification (official Plugin Check, the supported-version CI
matrix and owner testing) is still required before
publication. This local bug fix does not claim those release checks or testing
on any customer website.
