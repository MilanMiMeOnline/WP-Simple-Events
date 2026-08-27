# QA report — 0.5.1 maintenance release candidate

**Date:** 2026-08-27

**Candidate:** MiMe Simple Events and Calendar 0.5.1

**Supported floor:** WordPress 6.9 and PHP 8.2

**Status:** locally qualified; official Plugin Check and the complete hosted CI
matrix remain release gates.

## Scope

This patch candidate closes the findings documented in
[the post-0.5 exploratory report](QA-REPORT-0.5.0-EXPLORATORY.md) without
changing public storage identifiers or widening the plugin's external-service
footprint. It contains:

- clearer open-ended and one-off recurrence-editor states;
- a compact recurrence summary in the native Events list table;
- timed multi-day list-calendar labels;
- same-origin calendar feed URLs;
- clearer Elementor source, taxonomy and typography controls;
- bounded series, previous-date and next-date navigation on native exact
  occurrence pages.

## Senior developer review

- The neighbour query is restricted to the current canonical event, excludes
  one-off projection rows, retains the shared published/password-free boundary,
  orders deterministically and uses `LIMIT 1`.
- Repository mapping rejects multiple rows, malformed projection data,
  substituted series, the current occurrence and one-off rows.
- Native series navigation catches derived read failures, escapes every URL and
  label for its output context and retains only the already-public series link
  when neighbour discovery fails.
- Calendar list enhancement changes presentation only. It does not alter saved
  timestamps, all-day semantics, public query bounds or REST schemas.
- The calendar endpoint remains a same-site WordPress REST path and preserves
  subdirectory installations without adding a remote request.
- Builder label changes add no new stored attributes and preserve existing
  Elementor output and saved defaults.

No new production dependency, cookie, visitor identifier, telemetry call,
remote asset or unbounded public expansion was introduced.

## Senior QA review

### Automated checks

- `composer validate --strict`: pass.
- PHP coding standards: pass.
- PHPStan: pass, 282 files analysed.
- PHPUnit: pass, 682 tests and 2,570 assertions.
- JavaScript tooling tests: pass, 54 tests.
- JavaScript and CSS linting: pass with zero warnings.
- Composer locked dependency audit: no advisories.
- npm dependency audit at high severity: zero vulnerabilities.
- Translation catalogue generation and freshness check: pass.
- Release archive content, PHP syntax, production autoloader, checksum and
  byte-for-byte reproducibility: pass.
- Packaged WordPress 6.9 / PHP 8.2 smoke journey: pass.
- Packaged WordPress 7.1 / PHP 8.2 smoke journey: pass.
- Packaged WordPress 7.1 browser journey: 22 of 22 tests pass.

The first complete browser attempt encountered one temporary WordPress
Playground process termination during a page reload. The exact failing test
passed in a fresh isolated environment, after which the entire 22-test suite
passed in another clean environment. No assertion, PHP error or repeatable
plugin failure remained.

### Behavioural coverage

- one-off, selected-date, generated and corrupt recurrence summaries;
- recurrence end-control visibility and open-ended projection guidance;
- same-origin feed serialization;
- timed multi-day start, continuation and end segments plus all-day retention;
- exact occurrence series context with both neighbour directions;
- Elementor control labels and defaults;
- Gutenberg editing and complete-series recurrence application;
- calendar filters, URL persistence, responsive views, time formats, captured
  timezone wall time, multiple instances and delayed or failed feeds;
- classic, hybrid and block-theme public smoke journeys on both supported
  WordPress boundaries.

## Remaining release gates

- Commit the reviewed candidate and let the pinned GitHub Actions matrix run.
- Require the official WordPress Plugin Check action in strict mode to pass
  against `.release/mime-simple-events-calendar`.
- Publish to GitHub and WordPress.org SVN only after every required hosted check
  is green.

There is no accepted security, privacy or functional exception for 0.5.1.
