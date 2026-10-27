# Local security maintenance — 26 October 2026

**Status:** Local maintenance of the unpublished 1.1.0-beta.1 worktree.
No commit, push, merge, tag, release or deployment was performed. Public 1.0.0
and remote main remain unchanged. Owner testing and publication approval are
still required.

## Changes and scope

- Updated only two npm lock resolutions: body-parser 1.20.8 → 1.20.9 and fast-uri
  3.1.8 → 3.1.9. Their parent ranges already permit these patches; no dependency,
  override or major-version migration was added. Composer manifests/lock and
  package.json are unchanged by this maintenance.
- Added three development dependency regressions: encoded URI schemes remain
  inert, malformed gzip tails cannot crash the server, and compressed requests
  finish while retaining other stream consumers. The exact final tests fail
  against both old affected packages, with the independent compatibility case
  passing. All three pass against the patched packages.
- Recorded the withdrawn braces advisory and unavailable fork source in
  ADR-106. Retained the exact reviewed fork, integrity and depth tests. No
  suppression or automatic replacement was introduced.
- Fixed a pre-existing seasonal smoke-test expectation. Its relative-date
  Brussels event incorrectly required UTC+02:00 in winter. The test now checks
  the exact event-date offset using independent Node timezone data and verifies
  the complete JSON-LD timestamp. Production date handling is unchanged.
- Updated active local Homebrew PHP paths: 8.2.33 → 8.2.35, 8.3.32 → 8.3.36 and
  8.5.8 → 8.5.12, with their required libraries. An existing OpenSSL keg conflict
  initially interrupted the upgrade; reinstalling OpenSSL with Homebrew restored
  the library link, after which the remaining upgrade completed. Old kegs were
  retained for rollback. No PHP service was started.

The pre-existing FullCalendar 7.1.2 migration was preserved. Its calendar and
Divi JavaScript bundles and PHP adapters are byte-identical to the pre-maintenance
snapshot. The two patched dependencies are development tooling and are absent
from the shipped plugin and browser dependency graph.

## Developer review

- Compared the final lock against a snapshot taken before maintenance: precisely
  the two intended package records change, including their registry integrity.
- A regular npm install initially left stale files despite the updated hidden
  lock. A clean `npm ci` reconstructed the tree and ran the existing reviewed
  wp-env postinstall; installed versions and behavior were then verified.
- Kept source permissions, nonce handling, public/private queries, recurrence
  persistence, runtime assets and stored settings unchanged.
- The smoke offset calculation is specifically for the existing 09:30 Brussels
  fixture, after the DST transition hour. It is not a general local-time parser.
- Preserved the fixture assertions, timeouts, release checks and security gates.
  The initial seasonal failure is recorded below rather than hidden.

## QA evidence

- `composer validate --strict`: passed.
- `composer qa` on PHP 8.5.12: passed, including coding standards, PHPStan,
  779 unit tests / 3,210 assertions and dependency audit.
- The same 779 unit tests / 3,210 assertions pass on PHP 8.2.35 and PHP 8.3.36.
- `npm run qa`: passed; 99 tooling tests, builds, JS/CSS lint and full npm audit.
  No npm vulnerabilities reported. The smoke-only follow-up also passes JS lint.
- Three dependency and ten retained braces regressions pass. Exact old-package
  replay confirms the URI mismatch and uncaught gzip decoder error before the
  fixes; old artifacts were isolated outside the repository.
- `npm run test:release`: passed; package contents, PHP syntax, autoloader,
  checksum and two byte-identical builds verified.
- Homebrew linkage checks and all three PHP-FPM configuration tests pass.
- Independent fixture checks cover both sides of the March and October DST
  transitions and the current October 29 event date.
- Initial WordPress smoke run failed at the fixed summer-offset expectation.
  After correcting that test oracle, the complete smoke journey passes against
  the packaged plugin, including settings, REST validation, private/protected
  events, recurrence operations, templates, calendar feeds and ICS boundaries.
  The temporary WordPress site was stopped and removed normally.
- `npm run test:e2e`: all 38 browser scenarios pass in 4.7 minutes against the
  packaged beta. Coverage includes accessibility, responsive calendars, filters,
  builder lifecycle, Brussels/Los Angeles DST presentation, Gutenberg previews,
  save/reload and recurrence editing. No timeout or assertion was relaxed.

## Remaining boundaries

Homebrew PHP is separate from Playground's embedded WebAssembly PHP runtime.
The latter was not upgraded, and this work does not establish its patch status.
No hosted website PHP, actual Elementor/Divi installation or WooCommerce site
was modified or tested. Hosting patch confirmation and Divi 5.17.0 qualification
remain external actions requiring access to the relevant test/hosting environment.

The fork's current GitHub history remains unavailable (HTTP 404). Its pinned npm
artifact is still available and matches the reviewed runtime; source availability
must continue to be monitored. Upstream 3.0.3 does not supply the depth guards.

Previously reported major tooling migrations (including unsupported ESLint 9)
remain separate compatibility work using the existing Dependabot proposals.
This patch maintenance does not claim all tooling is on its newest major version.
The existing fast-uri PR #21 still targets an older version; when this work is
prepared for GitHub, reconcile it with the 3.1.9 resolution instead of applying
that stale downgrade or creating a duplicate proposal.

No hosted CI run was triggered. A prior failed main-branch Quality run is not
resolved by these local checks. The full release compatibility matrix, official
Plugin Check, licensed builder checks and owner acceptance remain publication
requirements. No gate has been waived.

## Local artifact

`dist/mime-simple-events-calendar-1.1.0-beta.1.zip`

SHA-256: `c263d6da31f219ab3f03164808fa7918e3ac93b1cd9978f499a79e447705521e`.

The changed archive checksum reflects the updated packaged changelog; the
calendar/Divi runtime assets are unchanged from the owner's existing beta.

Audit and execution logs for this session are retained locally in
`/private/tmp/mime-maintenance-20261026-w19eo6hp`; they are not part of the release.
