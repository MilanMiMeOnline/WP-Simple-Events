# 1.1.2-beta.1 — calendar fallback and address compatibility

**Date:** 5 November 2026  
**Status:** Local candidate for owner testing; not published or merged.

## Changes and data compatibility

CAL-001 and SEO-001 from the roadmap are implemented under ADR-110. The calendar
adapter now omits absent semantic colors rather than supplying undefined values.
Explicit event/category colors keep their existing precedence; component-owned
defaults remain configurable. Both public and Divi-editor bundles are rebuilt.

The schema builder wraps the existing nonempty effective address in
`{"@type":"PostalAddress","name":"<full address>"}`. Venue stays in
`Place.name`; an empty address is omitted. This is the full-address form shown
in [Google's Event documentation](https://developers.google.com/search/docs/appearance/structured-data/event#structured-data-type-definitions).
It does not claim universal rich-result eligibility.

There is no new address field, parsing, inferred country, database migration or
metadata write. Existing events keep their free-text address. Occurrences keep
their own effective address, including an explicit empty value that hides the
series address. Separate address fields are deferred without a release target.

## Senior developer review

- Runtime source changes are confined to `calendarEventInput` and
  `EventSchemaBuilder::build`. Feed shapes, date semantics, save paths, REST
  permissions, capabilities, nonces and occurrence resolution are unchanged.
- Conditional color properties retain the existing strict hexadecimal allowlist.
  The PHP graph still passes through the existing safe JSON encoder and public
  eligibility boundary; nesting an address does not introduce raw HTML output.
- No dependency version changed. Package/lock metadata identifies the local beta.
- The 384-file ZIP differs from public 1.1.1 in exactly seven expected files:
  the schema builder, two browser bundles, bootstrap version, translation header,
  changelog and readme. No test files or development dependencies are shipped.
- Added the checksummed public 1.1.1 ZIP to the existing historical upgrade matrix;
  its downloaded hash matches the previously verified release hash.

## Regression evidence and senior QA review

- Before the fix, the enhanced adapter test fails because the color keys exist,
  and three schema assertions fail because addresses remain strings.
- A browser regression against the packaged 1.1.1 reproduces the transparent
  timed multi-day bar (`rgba(0, 0, 0, 0)`). It passes against the candidate.
- The new browser matrix covers component fallback, category and event colors;
  timed single-day, timed multi-day, all-day and cross-week spans; and month/list
  rendering. It checks computed colors, readable text, two-day width, separate
  week segments, dots and resolved-color list accents.
- Address regressions cover complete and multiline legacy text, blank and
  venue-only output, exact occurrence addresses, explicit cleared overrides,
  script-significant characters, and exclusion of valid draft/private/protected
  events. Provider tests also check that reading schema leaves address metadata
  unchanged. The WordPress smoke expects the PostalAddress graph alongside the
  same visible native address.

## Executed checks

- `composer validate --strict`: passed.
- `composer qa` on PHP 8.3.36: passed; 792 tests / 3,278 assertions, coding
  standards, PHPStan and clean Composer audit. Initial test-formatting errors
  were corrected using the existing standard; no rules were suppressed.
- `npm run qa` on Node 24.21.0: passed; 107 tooling tests, builds, JS/CSS lint
  and npm audit (zero reported vulnerabilities at this run).
- `npm run i18n:pot` and `npm run i18n:check`: passed.
- `npm run test:release`: passed, including two byte-identical builds, archive
  contents, PHP syntax, production autoloader and checksum verification.
- `npm run test:e2e`: all 42 browser journeys passed in 5.1 minutes. No retries,
  timeout changes or weakened assertions were needed.
- The added historical-version manifest contract tests pass (4/4).
- Packaged WordPress 6.9 / PHP 8.2 smoke: passed; the disposable site stopped
  and cleaned up normally.
- Packaged WordPress 7.1 / PHP 8.2 smoke: passed; the disposable site stopped
  and cleaned up normally.
- Historical upgrade/lifecycle matrix on pinned WordPress 6.9 / PHP 8.3:
  passed all 12 published source versions from 0.2.3 through 1.1.1, with
  before/after canonical-data snapshots including existing address metadata.
  Clean activation, missing derived-table repair, deactivation/reactivation,
  retained uninstall and explicitly destructive uninstall also pass. This
  validates the exercised upgrade paths, not every third-party site setup.

## Candidate and remaining release gates

`dist/mime-simple-events-calendar-1.1.2-beta.1.zip`  
SHA-256: `5f831997d69b0258c7abbd499cbf427c4fa8fd0c96995a034398ed191a7d6a52`.

The owner still needs to test the candidate, including the original calendar
on a suitable test copy. No customer site was changed. The complete hosted CI
matrix and official Plugin Check have not run for this unpublished branch.
The separate bounded performance suite was not rerun for these presentation-only
changes. These remain publication gates under the release process; this report
does not waive them. The Google Rich Results Test was not submitted in this run;
the graph shape follows its documented full-address example and is covered by
local output tests.

Local logs are retained at `/private/tmp/mime-112-qa` and are not shipped.
