# QA report — 0.9.0 RC8 final qualification

**Status:** qualified and authorized for publication; publication evidence pending

**Reviewed:** 2 September 2026

**Candidate:** MiMe Simple Events and Calendar 0.9.0

**Candidate commit:**
`1fb28f5863d8c0099ceda4c54c9766829126dd65`

**Release SHA-256:**
`43296b58f357cc19a56eae4b48b5910e72bdac397bd6af21f30399ce48825095`

## Scope and outcome

RC8 freezes and qualifies the complete 0.9.0 package after RC1–RC7 completed
the public compatibility inventory, lifecycle matrix, security/privacy audit,
accessibility review, performance budgets, new-user documentation and
exploratory QA. This checkpoint adds no late product capability. It synchronizes
the release identity, public changelogs, translation catalogue and user-facing
release notes, then proves the exact package locally and independently in hosted
CI.

No P1 or P2 product, security, privacy, accessibility, compatibility,
documentation or release blocker remains. The exact candidate is authorized for
GitHub and WordPress.org publication. Publication URLs, the SVN revision and the
independently repackaged WordPress.org download remain deliberately pending until
that external state exists.

## Candidate identity and package

- The plugin header, `WPSE_VERSION`, `readme.txt` stable tag, npm package and
  lock metadata, translation catalogue and public changelogs all report 0.9.0.
- The package retains WordPress 6.9, PHP 8.2, GPL-2.0-or-later, the canonical
  `mime-simple-events-calendar` slug/text domain and tested WordPress 7.1 and
  Elementor 4.2.3 declarations.
- Two consecutive production builds produced the same SHA-256 shown above.
- The verifier reopened the archive and checked its single canonical root,
  allowlisted production contents, licences and third-party notices, PHP syntax,
  class-authoritative Composer autoloader and checksum binding.
- The archive contains 380 paths and excludes tests, engineering documentation,
  Node packages, development dependencies, environment files, test fixtures,
  licensed Divi/Elementor source and credentials.
- All six WordPress.org screenshots, both banners and both icons retain their
  required names and verified dimensions. No visual asset changed in RC8.
- Public GitHub release notes follow the repository template, describe user
  outcomes, contain no placeholders and keep test counts, commit identifiers and
  checksums in this QA report.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| `composer validate --strict` | Pass |
| WordPress coding standards and PHP compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 777 tests / 3,205 assertions |
| Composer advisory audit | Pass, zero advisories |
| Production frontend and Divi builds | Pass |
| Tooling contracts | Pass, 79/79 |
| ESLint and Stylelint | Pass, zero warnings |
| npm audit | Pass, zero vulnerabilities |
| Translation regeneration and verification | Pass |
| Release content, checksum, PHP and autoloader verification | Pass |
| Reproducibility | Pass, two byte-identical 0.9.0 builds |
| Historical upgrades and lifecycle paths | Pass, all eight public sources |
| Bounded performance budgets | Pass |
| Packaged browser matrix | Pass, 35/35 |
| WordPress 6.9 / PHP 8.2 package smoke | Pass |
| WordPress 7.1 / PHP 8.2 package smoke | Pass |

The first local performance attempt started WordPress but encountered a transient
`fetch failed` transport error before fixture seeding or measurement. Its
temporary environment was automatically removed. One clean rerun passed every
unchanged budget: the filtered occurrence window used two queries, a 50-card
event list used 18, a 100-item calendar feed used 17, builder options used seven
and the maximum recurrence scenario returned 551 dates without a database query.
No budget, assertion or dataset size was changed.

Local WP-CLI 2.12.0 emitted deprecation notices from its own bundled packages
under host PHP 8.5 while regenerating the POT file. Generation and verification
passed; the messages do not originate in authored plugin code or the production
archive.

## Independent hosted qualification

GitHub Actions run
[`33568347988`](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33568347988)
passed all 12 jobs on candidate commit `1fb28f5`:

- plugin QA on PHP 8.2, 8.3, 8.4 and 8.5;
- deterministic frontend builds, tooling contracts and translation verification;
- 35 packaged browser regressions;
- WordPress 6.9 and 7.1 package smoke tests on the PHP 8.2 floor;
- all eight historical upgrade paths and lifecycle/repair cases;
- the representative bounded performance dataset;
- two-build archive reproducibility and the strict official WordPress Plugin
  Check against the same staging tree.

The hosted release artifact has SHA-256
`43296b58f357cc19a56eae4b48b5910e72bdac397bd6af21f30399ce48825095`.
`cmp` confirmed it is byte-for-byte identical to the locally qualified zip.

## Senior developer review

- RC8 changes release identity and public documentation only; it does not change
  runtime control flow, storage, permissions, queries, routes, event eligibility
  or any frozen shortcode/block/builder schema.
- The version appears at the existing reviewed metadata boundaries and the
  release builder rejects disagreement before producing an archive.
- The changelog preserves historical version entries and moves only the completed
  release-candidate work under the dated 0.9.0 heading.
- The installable allowlist remains unchanged. RC8 engineering evidence and
  release-note source remain outside the production package.
- No production dependency, remote request, telemetry, cookie, browser storage,
  personal-data path or public write capability is added.

## Senior QA review

- Existing one-off events, recurring series, occurrence overrides, taxonomy and
  color data, settings, pages and saved Gutenberg/Elementor/Divi content retain
  their documented identities and passed every supported upgrade path.
- The exact package passed same-day, multi-day, all-day, timezone, 12/24-hour,
  recurrence, protected-content, no-JavaScript, failure, narrow-screen,
  accessibility and multiple-instance browser coverage.
- Classic, hybrid and block-theme shell ownership remains covered by the package
  smoke matrix; builder integrations remain optional and plugin absence paths are
  inert.
- Security-sensitive routes and exports remain covered by the frozen permission
  matrix and fail-closed regression layers. Dependency audits and strict Plugin
  Check are clean.
- The local and hosted candidates agree byte for byte, removing ambiguity about
  which archive is authorized for publication.

## Residual non-blocking boundaries

- A Dutch WordPress.org language pack is not bundled. The plugin is
  translation-ready but remains English until a translation is published.
- FullCalendar 6.1.21 remains intentionally frozen for 1.0. A FullCalendar 7
  evaluation belongs after the stable release and is not mixed into this
  candidate.
- External themes, caching/CDN layers and builder extensions can affect layout
  outside plugin-owned components; the documented ownership and troubleshooting
  boundaries remain unchanged.
- The 0.9 observation window accepts blocker fixes, translations and release
  documentation only. New product functionality remains frozen until after 1.0.

## Release decision

The exact 0.9.0 candidate with SHA-256
`43296b58f357cc19a56eae4b48b5910e72bdac397bd6af21f30399ce48825095`
is approved for tagging and publication. The production contents must not change
between this decision, the GitHub release and WordPress.org trunk/tag. After
publication, the public WordPress.org package must be downloaded, extracted and
compared recursively with `.release/mime-simple-events-calendar` before RC8 is
marked complete.
