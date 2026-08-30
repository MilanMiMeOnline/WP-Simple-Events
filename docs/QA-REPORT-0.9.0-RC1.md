# QA report — 0.9.0 RC1 public contract freeze

**Date:** 30 August 2026\
**Candidate:** 0.9.0 release-candidate cycle, RC1 checkpoint\
**Scope:** public 1.x identities, compatibility policy and contract drift guards

## Result

RC1 passes. The documented public WordPress, visitor, Gutenberg, Elementor,
Divi, REST, hook and presentation identities now have an explicit 1.x
compatibility and deprecation policy. Automated fingerprints protect all saved
Gutenberg block and Divi module attribute schemas. The complete sixteen-widget
Elementor palette is protected by source inventory and passed real host
construction on the supported 3.x line and current tested 4.x line.

No production runtime behaviour, event data or release version changed in this
checkpoint. The work closes one real qualification gap: the generic Elementor
inspector still covered fifteen widgets after Add to Calendar became the
sixteenth widget in 0.7.0.

## Frozen boundary

The normative inventory is in
[PUBLIC-COMPATIBILITY-CONTRACT.md](PUBLIC-COMPATIBILITY-CONTRACT.md). It freezes:

- the event post type, taxonomies and delegated data/options/capability contracts;
- four shortcodes, sixteen Gutenberg blocks and the single-event field pattern;
- sixteen Elementor widget names and saved control identifiers;
- sixteen Divi 5 module names and saved attribute schemas;
- public request variables, exact-occurrence and calendar-export identities;
- custom REST namespaces/routes and their existing permission boundary;
- four documented plugin extension hooks; and
- only the semantic CSS classes and custom properties already declared stable
  by the presentation contract.

Implementation classes, generated IDs, derived projection storage, maintenance
cursors, editor transports, third-party classes and undocumented markup remain
internal. Additive optional changes may preserve compatibility; repurposing or
removal is reserved for a later major version.

## Automated evidence

- `composer validate --strict`: passed.
- `composer qa`: passed; PHP coding standards and PHPStan level 8 are clean,
  **772 PHPUnit tests with 3,184 assertions** pass and Composer reports no known
  vulnerability advisory.
- `npm run qa`: passed; all builds are reproducible, **65 tooling tests** pass,
  JavaScript and CSS lint are clean, and npm reports no vulnerability.
- `tools/public-contract.test.mjs`: fingerprints all sixteen Gutenberg and all
  sixteen Divi saved attribute schemas and inventories the shortcode, request,
  route, pattern, native fallback, widget and extension identities.
- `tests/Compatibility/elementor-inspector.php`: now includes Add to Calendar,
  its source/provider/layout and presentation controls, optimized DOM, style
  dependency and strict explicit-event behaviour.

## Real host evidence

The official WordPress.org Elementor 3.35.9 and 4.2.2 packages were each loaded
into an isolated WordPress 7.1/PHP 8.2 Playground. The repository compatibility
inspector completed without an exception for both hosts and verified:

- all sixteen widgets are registered in the plugin category;
- every widget declares the shared frontend stylesheet and optimized wrapper
  contract;
- every atomic widget and Add to Calendar expose the required saved controls;
- Events remain recognized as editable Elementor documents;
- a selected public event renders through an atomic widget; and
- malformed explicit sources render nothing instead of leaking or falling back.

The temporary packages, WordPress databases and compatibility event existed only
inside disposable Playground runs. No production or local development website
was modified.

## Senior developer review

The compatibility boundary is intentionally narrower than the namespace. This
preserves freedom to refactor internals while giving saved content, public URLs,
data and supported styling targets a meaningful 1.x promise. Fingerprints use
stable key sorting, so harmless JSON key order does not create false failures.
An intentional additive schema change must update its normative contract,
decision record and expected fingerprint in the same reviewed change.

The review found no runtime or migration change hidden inside RC1. Historical QA
reports retain their historically correct fifteen-component counts; only current
contracts and current qualification instructions were updated to sixteen.

## Senior QA review and residual risk

- RC1 proves identity and host construction, not every clean-install or upgrade
  path. That work is owned by RC2.
- Security/privacy, accessibility, performance and new-user documentation receive
  dedicated fresh qualification in RC3 through RC6; prior release evidence is
  not treated as a substitute.
- The final Gutenberg browser, Elementor editor, licensed Divi host and exact
  release-package matrices remain mandatory in RC7/RC8.
- No public API removal is permitted during the 0.9/1.x line without the
  documented deprecation and later-major process.

There is no open RC1 blocker.
