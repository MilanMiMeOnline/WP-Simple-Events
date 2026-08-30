# QA report — 0.7.0 bounded Add to Calendar portability

**Status:** locally qualified release candidate; hosted CI and publication pending

**Reviewed:** 29 August 2026

**Candidate:** MiMe Simple Events and Calendar 0.7.0

**Release SHA-256:**
`91f5d1c441fe175a4fcb89540e44f881b000269e8be9077dd38eb0dafd7b48de`

## Scope

This report closes the local work-package 6 release gates for the bounded Add to
Calendar phase. The candidate adds one-way portability for one eligible public
one-off event or one exact recurring occurrence through:

- one same-origin RFC 5545 `.ics` download;
- optional Google Calendar and best-effort Outlook compose links;
- one shared semantic renderer exposed through shortcode, Gutenberg, Elementor
  and native Divi 5 components;
- one explicit, off-by-default native single-event fallback setting.

The release does not add calendar synchronization, subscriptions, inbound
imports, whole-series export, an RRULE approximation, visitor accounts, tracking
or a provider API integration. A recurring-series root intentionally emits no
action because its segmented schedule and sparse overrides cannot always be
represented truthfully as one external calendar rule.

## Exact candidate and local qualification

The plugin header, `WPSE_VERSION`, WordPress.org stable tag, npm package metadata,
translation catalogue and public changelogs identify version 0.7.0. Two
consecutive production builds produced byte-for-byte identical archives. The
release verifier reopened the archive and checked its rooted directory,
allowlisted production files, PHP syntax, optimized Composer autoloader,
licences, third-party notices and checksum binding.

The exact archive contains 377 paths and no test suites, development tools, Node
packages, repository data, environment files, licensed Elementor/Divi source or
credentials. Its SHA-256 is the value recorded above.

The completed candidate passed:

- `composer validate --strict`;
- `composer qa`: WordPress coding standards, PHPStan over 320 files, 772 PHPUnit
  tests with 3,184 assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 60 tooling-contract tests,
  ESLint, Stylelint and npm audit with zero vulnerabilities;
- translation-template regeneration and catalogue verification;
- `npm run test:release` with two identical 0.7.0 archives;
- 30/30 packaged Playwright journeys on WordPress 7.1/PHP 8.2;
- the exact staged package on WordPress 6.9/PHP 8.2 and WordPress 7.1/PHP 8.2;
- `git diff --check` and focused release metadata/content inspection.

The host WP-CLI 2.12 process emitted deprecations from its own bundled packages
under local PHP 8.5 during catalogue generation. Generation and verification
passed; none of those messages originated in the runtime plugin or production
archive.

## Browser and editor evidence

The browser matrix requalified the calendar, responsive filters, colors,
timezones, delayed/failed feeds, multiple instances, Elementor lifecycle,
Gutenberg blocks, recurrence editing and no-JavaScript fallbacks. The Add to
Calendar-specific journeys proved:

- explicit-source and current-context block rendering;
- keyboard-friendly three-provider output without horizontal overflow at
  390 pixels and without JavaScript;
- real Gutenberg create, configure, save, reload, attribute recovery, removal
  and authenticated cleanup;
- exact one-off and occurrence-only public eligibility.

A pre-candidate hosted run exposed that the real Gutenberg save/reload/cleanup
journey can exceed the suite's generic 30-second deadline on a cold runner even
after its functional assertions have passed. That journey now has one bounded
60-second test deadline covering the save, full reload and cleanup together. The
complete local matrix subsequently passed in 4.3 minutes; no assertion, cleanup
step or product boundary was skipped.

The detailed manual host evidence remains recorded in
`QA-REPORT-0.7.0-WP3-4.md`: Elementor Free and Divi 5 both passed native
place/configure/save/reload/remove/save journeys and were restored without a
remaining test component. Apple Calendar accepted the production ICS as one new
event; the import was cancelled before persistence. Google and Outlook received
the complete multi-day snapshot through their compose URLs up to their respective
authentication boundaries.

## Security and privacy review

- Every export request re-resolves its event through the published,
  password-free public boundary; draft, private, protected, cancelled, stale,
  corrupt and ambiguous contexts fail closed without disclosing their state.
- Exact recurring occurrences require their immutable public identity and active
  canonical projection. A series root cannot fall through to one misleading
  event date.
- GET and HEAD are the only accepted download methods. Responses use attachment,
  no-store/no-cache, expiry, `nosniff` and no-cookie headers; error responses are
  generic and non-cacheable.
- ICS values are bounded, escaped and UTF-8 folded according to the frozen RFC
  5545 contract. Provider titles, descriptions, locations, URLs and component
  controls are validated and escaped at their input and output boundaries.
- Google and Outlook are disabled by default. The plugin sends no background
  request; data leaves the site only when a visitor deliberately follows an
  isolated provider link.
- The release adds no telemetry, analytics, remote assets, visitor cookie,
  personal-data store, public write endpoint or production dependency.
- Composer and npm report no known vulnerability; the archive contains no secret,
  licence key, personal test data or licensed builder source.

## Accessibility and compatibility review

- List and dropdown layouts use semantic links/buttons, visible text, keyboard
  operation, visible focus and a useful no-JavaScript path.
- Narrow-screen reflow and three-provider output have executable 390-pixel
  coverage; the external links retain complete accessible names and safe new-tab
  attributes.
- Builder adapters map bounded settings into the same renderer and never fork
  export eligibility, provider ordering or occurrence resolution.
- The native fallback remains disabled until an administrator opts in and is
  considered only after plugin-owned template ownership. Elementor and Divi
  Theme Builder templates receive no forced action.
- Existing post types, metadata, occurrence identities, shortcodes, blocks,
  Elementor widget IDs, Divi module names and public CSS targets remain stable.

## Senior developer review

The implementation keeps calendar snapshot construction, provider URL building,
endpoint policy, semantic rendering and host adapters at explicit boundaries.
One request-local resolver owns event/occurrence eligibility. Host integrations
select bounded presentation options but cannot weaken public access or invent a
different snapshot. The native setting is isolated behind one policy decision
and participates in uninstall cleanup.

The provider-separator regression is covered through the exact
`CalendarActionBuilder` plus WordPress `esc_url()` boundary, not a parallel test
implementation. The smoke harness cleanup is deterministic, and its single
read-only retry applies only to an actual transport timeout; writes, HTTP
failures, caller cancellation and repeated timeouts still fail immediately.

## Senior QA review and remaining release gate

No local correctness, security, privacy, accessibility, compatibility,
dependency or reproducibility blocker remains. The candidate is locally
qualified with the SHA-256 above.

This report does not yet authorize publication. The version commit must still
pass all ten hosted GitHub Actions jobs, including PHP 8.2–8.5, both packaged
WordPress smoke targets, the complete browser matrix, translation verification
and the pinned official WordPress Plugin Check action. The hosted artifact must
match the local checksum before GitHub and WordPress.org publication.
