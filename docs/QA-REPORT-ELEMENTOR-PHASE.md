# QA report — Elementor phase

**Date:** 2026-07-16\
**Scope:** optional Elementor widgets, compatibility boundary and native regression safety

## Result

The phase passes its automated and host-level acceptance checks. Elementor remains optional; the same WordPress smoke suite passes with Elementor absent, with Elementor 4.1.5 active, and with Elementor 4.1.5 plus WooCommerce 10.9.4 active.

## Automated evidence

- `composer validate --strict`: passed.
- `composer qa`: passed; PHP coding standards clean, PHPStan level 8 clean, 103 PHPUnit tests with 354 assertions, Composer audit clean.
- `npm run qa`: build and JavaScript/CSS lint passed; the audit has no high or critical finding.
- `npm run test:smoke`: passed on WordPress 7.0.1 and PHP 8.3 without Elementor.
- The same complete smoke suite passed with Elementor 4.1.5 active.
- The same complete smoke suite passed with Elementor 4.1.5 and WooCommerce 10.9.4 active together.

## Elementor host evidence

The official WordPress.org packages for Elementor 3.35.9 and 4.1.5 were loaded separately into the WordPress 7.0.1/PHP 8.3 Playground environment. An authenticated local-only inspector verified:

- all three widget types are registered through the host manager;
- the dedicated `simple-events-by-mime` category is present;
- list/grid controls include query, filters, visibility, responsive columns and style groups;
- calendar controls include both views, filters, style groups and only the `wpse-calendar` script dependency;
- details controls include a bounded public preview selector and style groups;
- all widgets declare `wpse-frontend` and opt out of Elementor's removable inner wrapper.

The inspector and all local override files were removed after verification.

## Senior developer review

The review found and fixed one Elementor lifecycle defect before handoff: Elementor reconstructs every placed widget as a separate PHP object, so per-object counters could duplicate DOM IDs. A request-wide, component-specific sequence and regression tests now cover separately constructed renderers.

The integration contains no event query or metadata rendering duplication. Settings cross an allowlisted adapter and then the existing shortcode normalization, repository and renderer boundaries.

## Senior QA review and residual risk

- No interactive drag-and-drop browser session was executed; server-rendered editor configuration, real host control construction and complete WordPress HTTP smoke paths were verified instead.
- Elementor Pro was not installed. Pro dynamic tags are explicitly deferred, while existing Theme Builder precedence remains part of the native template contract.
- The phase matrix used WordPress 7.0.1. The full release matrix must still include the declared WordPress 6.9 minimum.
- Preview controls intentionally show at most 50 public events and 100 terms per taxonomy. This is documented and prevents unbounded editor queries.
- `npm audit` reports one low-severity transitive development-only `esbuild` advisory inside WordPress lint configuration packages. The direct production build uses `esbuild` 0.28.1, production dependencies have no advisory, and high/critical release gates remain clean.
