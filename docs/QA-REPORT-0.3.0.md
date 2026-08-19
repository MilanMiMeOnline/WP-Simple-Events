# QA report — MiMe Simple Events and Calendar 0.3.0

**Date:** 19 August 2026
**Candidate:** 0.3.0
**Scope:** builder and presentation polish release qualification
**Status:** locally qualified; official Plugin Check CI pending

## Result

Version 0.3.0 completes the first roadmap phase without changing event storage,
public identifiers or the lightweight one-off-event product boundary. Existing
events, shortcodes, atomic blocks, Elementor widget names and saved control IDs
remain valid. The release adds composite Gutenberg blocks, event-aware taxonomy
archives, practical opt-in presentation controls and interaction/semantic polish.

All local source, package, WordPress, browser, translation and real Elementor
gates pass. The exact release commit must still be pushed so GitHub Actions can
run the official strict WordPress Plugin Check against the generated staging
directory. Publication is not approved until that external result is green.

## Senior developer review

- Collection, calendar, details, block and Elementor adapters continue to reuse
  the established bounded query and presentation services.
- New shortcode, block and stored Elementor values cross strict boolean, choice,
  date, integer, term-slug, heading and text-label allowlists before rendering.
- Taxonomy chronology is limited to event category/tag main queries. Blog posts,
  products and mixed search keep normal WordPress ordering and date semantics.
- Presentation controls are wrapper-scoped and opt-in. No new saved default
  changes an existing page merely by updating the plugin.
- Existing post type, taxonomies, metadata, REST namespace, shortcode names,
  block names, widget names, control identifiers and CSS component names remain
  backward compatible.
- The release adds no custom table, remote runtime asset, telemetry, cookie,
  direct database query or production dependency.

## Senior QA review

### Automated quality gates

- `composer validate --strict`: passed.
- `composer qa`: passed.
  - PHPCS/WPCS: clean.
  - PHPStan: 133 production files, no errors.
  - PHPUnit 11.5.56: 292 tests, 1,241 assertions.
  - Composer audit: no advisory.
- `npm run qa`: passed.
  - 24 tooling contract tests passed.
  - JavaScript and CSS lint passed with no warning.
  - npm audit reported zero vulnerabilities.
- Translation catalogue: regenerated and byte-comparison verified with the
  checksum-pinned WP-CLI 2.12.0 package.
- Packaged WordPress 7.0.1 browser suite: 19/19 journeys passed.
- Packaged WordPress smoke matrix on PHP 8.2:
  - WordPress 6.9: passed.
  - WordPress 7.0.1: passed.
- Real Elementor host inspector on WordPress 7.0.1/PHP 8.2:
  - Elementor 3.35.9: passed.
  - Elementor 4.2.2: passed.
- Release archive verification and two-build reproducibility: passed.
- `git diff --check`: passed.

Final local artifact:

- `dist/mime-simple-events-calendar-0.3.0.zip`
- SHA-256:
  `7c338dd7a207d2d257fab85205198348ee877092b0011ee29d786070b0be03f7`

### Real Elementor coverage

The official WordPress.org Elementor packages were loaded separately into
disposable WordPress installations. The inspector verified all fifteen widgets,
their category, style/script dependencies, optimized DOM contract, atomic field
controls, event editor support, explicit public-event rendering and malformed
source rejection. Each temporary compatibility event was deleted by the
inspector and both environments were stopped.

### Read-only production check

The existing 0.2.5 installation on `taranartos.be` was inspected without logging
in, uploading the candidate, creating content or changing a template/setting.

- `/events/` preserved the visible Elementor header and footer, loaded ten
  calendar events, retained six visible paginated cards and emitted no browser
  console warning/error.
- The archive had no horizontal overflow at 1280 px or 390 px. At 390 px the
  configured month view, toolbar and calendar canvas fit the 370 px component.
- A representative event retained one H1, one main landmark, the complete site
  shell and valid Event JSON-LD with its captured UTC offset.
- Location and external action destinations used `_blank` with
  `noopener noreferrer`.
- The live Elementor archive content itself has no `main` landmark. This is a
  Theme Builder template choice, not plugin fallback markup: both classic and
  block plugin fallbacks contain a semantic `main`, and the event detail template
  on the same site does expose one.
- The live asset query confirmed that production remained on version 0.2.5.
  No production data or configuration was changed.

## Security and privacy review

- Public collections continue to require published, password-free events and
  bounded result sets. Draft, private, scheduled and locked details remain
  excluded from plugin collection surfaces.
- No privileged or state-changing browser action was added. Existing editor,
  settings, duplication and maintenance boundaries keep their capability and
  nonce checks.
- External values are validated at their boundary and escaped late for HTML,
  attribute, URL or JavaScript context. No raw metadata key is exposed to a
  block or builder control.
- GET filter preservation accepts only documented instance keys and bounded
  scalar values; it creates no cookie, telemetry record or personal-data log.
- Composer and npm report no known dependency vulnerability. Development and
  test tooling remain outside the production archive.

## Documentation and visual review

The public description, installation steps, privacy statement, FAQ and changelog
now describe the composite Gutenberg blocks and 0.3.0 presentation controls. The
six existing WordPress.org screenshots and captions remain factually accurate;
they present the editor, archive, calendar, single event, settings and Elementor
configuration without claiming every available style control.

## Remaining release gate

The official `wordpress/plugin-check-action` job is immutable-pinned and targets
`.release/mime-simple-events-calendar` in strict mode. It cannot produce evidence
for the unpushed worktree. After review and explicit approval:

1. commit and push the complete 0.3.0 candidate;
2. require every GitHub Actions job, especially **Release archive and Plugin
   Check**, to pass for that exact commit;
3. compare the CI artifact checksum with the reviewed release artifact;
4. only then create the GitHub release and update WordPress.org SVN trunk/tag.

No commit, push, GitHub release or WordPress.org SVN mutation was performed in
this local qualification step.
