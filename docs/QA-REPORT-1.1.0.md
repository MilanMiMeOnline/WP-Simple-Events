# MiMe 1.1.0 release qualification

**Date:** 27 October 2026
**Status:** Candidate qualification in progress; not yet published.

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

Developer review checks dependency pins/licences, local assets, stylesheet
registration for Elementor/Divi, bounded same-origin feed access, text-only
rendering, mobile/builder lifecycle, retained settings and immutable release
inputs. QA review requires exact candidate builds, regression journeys, historical
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

Full package, integration and hosted CI qualification is still running. Package
checksum and publication verification will be recorded as they complete. No
unexecuted check is counted as passed.

## Limits

Owner confirmation covers the tested calendar update without inventing specific
builder versions. Actual licensed builder versions not independently re-tested
in this release will be identified in the handoff. This release does not update
PHP or themes on customer websites. Hosting PHP patch status remains separate.
