# QA report — WP1.2

**Date:** 2026-07-18
**Scope:** Calendar button states and Elementor control clarity (`WPSE-BL-001`)

## Delivered behaviour

- Calendar toolbar state backgrounds use the configured accent color and labels use the configured on-accent color.
- Normal, hover, pressed, focus-visible, selected and disabled states remain visually distinguishable.
- Keyboard focus has an explicit outline independent of hover or selected state.
- Default colors use WordPress block-theme contrast/base presets with adaptive system-color fallbacks.
- Elementor now labels the existing stable controls `Calendar typography` and `Button typography`.

## Senior developer review

- The defect was fixed in component-scoped CSS; no global button selector or Elementor runtime dependency was introduced.
- Existing Elementor control IDs, stored documents and shortcode output contracts are unchanged.
- Theme typography and normal text color continue to inherit; only interaction-state colors follow the explicit accent contract.
- The bundled production CSS is the only visitor-runtime change. No query, REST, metadata or persistence boundary changed.

## Senior QA and security review

- A browser regression reproduced the original failure as white text on a white hover background before the fix.
- Computed-style assertions cover default contrast, custom accent/on-accent values, hover, mouse-down, keyboard focus, selected view, disabled Today and forced-colors.
- Unit coverage verifies distinct translated labels under the unchanged `calendar_typography` and `button_typography` identifiers.
- No input, authorization, nonce, privacy or public-data surface was added.
- The translation catalogue was regenerated with WP-CLI 2.12.0 and passes its exact freshness comparison.

## Automated evidence

| Gate | Result |
|---|---|
| `composer qa` | Pass — PHPCS, PHPStan, 174 tests / 610 assertions, Composer audit |
| `npm run qa` | Pass — build, 11 tooling tests, JS/CSS lint, npm audit |
| `npm run i18n:check` | Pass |
| `composer test:integration` | Pass — real WordPress Playground smoke journey |
| `npm run test:e2e` | Pass — 7 Chromium journeys against the exact staging package |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- Visitor filter empty-state and configuration clarity (`WPSE-BL-002`) remain the next WP1.3 package.
- Final visual comparison inside supported Elementor 3.x/4.x editors remains part of release-candidate host-matrix QA; the shared frontend output and exact Elementor color/control contracts are automated here.
