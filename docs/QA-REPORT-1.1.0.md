# MiMe 1.1.0 release qualification

**Date:** 27 October 2026
**Status:** Released and verified on GitHub and WordPress.org.

## Owner acceptance and scope

The owner explicitly confirmed having tested FullCalendar 7 and requested its
publication as 1.1.0 on 26 October. This supersedes the earlier hold on publication
under ADR-104. Publication remains conditional on all release gates passing.

The candidate contains the tested FullCalendar 7.1.2 adapter, local classic theme
and Temporal support, plus the body-parser/fast-uri development security patches
and the date-correct smoke expectation. It adds no new event-management feature,
REST surface, storage schema or data migration. Exact historical 1.0.0 upgrade
coverage is added to the existing matrix.

## Review

Senior developer review verified dependency pins/licences, local assets, stylesheet
registration for Elementor/Divi, bounded same-origin feed access, text-only
rendering, mobile/builder lifecycle, retained settings and immutable release
inputs. Senior QA review verified exact candidate builds, regression journeys, historical
upgrades, performance budgets, both supported WordPress targets and strict CI
Plugin Check. No test, timeout, permission rule or security gate is relaxed.

The earlier beta and maintenance reports are historical evidence; they do not
substitute for the final candidate checks recorded below.

## Candidate evidence

- Composer strict validation and QA pass: 779 tests / 3,210 assertions.
- npm QA passes: 99 tooling tests, JavaScript/CSS lint and zero audit findings.
- Translation catalogue regeneration and consistency check pass.
- Developer review of the final diff confirms stable feed, saved settings and
  storage contracts; text-only event rendering and strict color validation remain.
- QA review confirms negative visibility, error recovery, time-zone, mobile and
  builder lifecycle cases remain in the required suites.

The first hosted PHP matrix exposed a non-portable legacy timezone alias in the
long-label test. The fixture now uses canonical `America/Argentina/Buenos_Aires`
from the [PHP timezone list](https://www.php.net/manual/en/timezones.america.php),
retaining the exact full-label and offset assertion. Runtime code is unchanged.
All ten historical upgrades and lifecycle checks pass locally.



## Final qualification evidence

- `composer validate --strict` and `composer qa`: passed, 779 tests / 3,210 assertions; lint, static analysis and Composer audit clean.
- `npm run qa`: passed, 99 tooling tests; JS/CSS lint and npm audit clean.
- Translation catalogue generation and consistency: passed.
- Local reproducibility from release commit `9418c0908f62dfb1b48f1a49b9782253c997a598`: two consecutive final ZIP builds identical. Local, independently rebuilt and CI artifacts share SHA-256 `756514672b97cbc18bcc116f1992110e72218e876f141ddce0046156f23d116b`.
- Historical upgrades: all ten immutable public versions from 0.2.3 through 1.0.0, clean activation, repair, reactivation and retained/destructive uninstall passed locally and in CI.
- Performance: all six unchanged budgets and 17 coverage cases passed locally and in CI on 500 series / 5,000 occurrence rows. The local feed returned 100 items in 41,901 bytes, 21 queries, median 174.971 ms; this is a test-environment observation.
- Packaged smoke on WordPress 6.9 and 7.1 / PHP 8.2 passed locally and in CI.
- Hosted browser suite: all 38 scenarios passed in 4.7 minutes. The final local suite also passed all 38 scenarios in 4.5 minutes.
- [Hosted qualification run](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/40473351629): all 12 jobs passed on `f11fca046db0f8757ee2ecdfd2143182863ae320`, including PHP 8.2–8.5 and strict official Plugin Check. The merge commit has an identical source tree.
- Official Plugin Check: no errors. Its optional PR-comment upload lacked repository permission, but the actual checker and result artifact passed; no permission expansion was introduced.

## Actual builder checks

Elementor 3.35.9 and 4.3.5 both passed the real-host compatibility inspector on WordPress 7.1.3, first under Playground PHP 8.5.6 and then explicitly under PHP 8.2.31. The inspector covers all widget registrations, style dependencies, controls, event editor support, selected-event rendering and rejection of malformed sources.

A separate temporary WordPress site using the existing licensed Divi 5.11.1 package loaded the actual Visual Builder calendar with the versioned 1.1.0 combined stylesheet and no JavaScript exceptions. The preview was visually inspected; nothing was saved. Divi's editing overlay intercepts visitor buttons inside the editing canvas, so this is editor-load evidence, not a claim of interactive visitor-button testing there. Month/list controls and responsive lifecycle are covered by the packaged browser suite and the owner's calendar acceptance. Current Divi releases beyond 5.11.1 and global-preset editing were not independently requalified in this patch.

Builder code, temporary fixtures, authentication state and logs remain outside Git and release archives. Playground PHP versions are test runtimes; this release does not update hosting PHP or themes on customer websites.

## Publication

- Owner-approved release merged in [PR #25](https://github.com/MilanMiMeOnline/WP-Simple-Events/pull/25).
- Release tag `v1.1.0` points to `9418c0908f62dfb1b48f1a49b9782253c997a598`. The [main-branch qualification](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/40474801500) also passed all twelve jobs.
- [GitHub 1.1.0](https://github.com/MilanMiMeOnline/WP-Simple-Events/releases/tag/v1.1.0) was published on 27 October 2026 at 08:43:11 UTC. Its public ZIP was downloaded and matches the qualified SHA-256 above.
- WordPress.org trunk and tag `1.1.0` were committed atomically in SVN revision `3768051`. Both staging trees matched all 384 qualified package files byte for byte before the commit. The public WordPress.org API reports version 1.1.0. Its public ZIP was downloaded and all 384 file paths and file contents match the qualified GitHub package. WordPress.org repackages ZIP metadata; its archive SHA-256 is `f08d7180e7d4843c1733a815431fa31a7cbc38362a5ad71f035150aa0e980baa`.
