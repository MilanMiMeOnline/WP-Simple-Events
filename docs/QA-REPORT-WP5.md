# QA report — WP5 Elementor atomic widgets

**Date:** 2026-07-19
**Scope:** `WPSE-BL-008` Elementor field components

## Result

WP5 passes its implementation, security and real-host compatibility review. Elementor now registers the original three widgets followed by twelve dedicated widgets for the complete WP4 field palette. The same field configuration renders from an explicit public event on a static page or from current event context in a template; Elementor Free and Pro require no separate plugin implementation.

## Functional evidence

- Dedicated widgets cover title, featured image, date/time/timezone, exceptional status, venue, address, location action, content, excerpt, external action, categories and tags.
- Meaningful labels can be shown, hidden or customized. Title and image linking, allowlisted headings/image sizes, attachment-alt/decorative image behaviour and action text overrides are field-specific.
- Typography, color and bottom spacing are scoped through Elementor's `{{WRAPPER}}` selector and inherit the theme until configured.
- Date/time uses WordPress' date/time settings and the existing global timezone-label preference; visual widget settings do not mutate event data or machine output.
- Empty fields emit no plugin frontend wrapper. The editor alone receives a status placeholder.
- All atomic links have a visible keyboard-focus treatment, including when fields are placed outside the composite event wrapper.

## Security and performance evidence

- Stored widget settings are shape-checked, allowlisted and plain-text sanitized before reaching named render methods.
- The selector uses the existing bounded repository and caches its maximum fifty published, password-free choices per request.
- Explicit malformed, draft, private, password-protected and non-event IDs never fall back to current context or expose content.
- Atomic widgets have no metadata-key control and never read metadata or construct queries directly.
- Independently reconstructed widget objects share one request-local resolver, field renderer and preview provider; no cross-request cache or direct SQL was introduced.
- Shared field output retains late contextual escaping, password suppression and content recursion protection.

## Automated evidence

- Focused atomic/Elementor/field tests: passed — 32 tests, 150 assertions.
- Complete PHPUnit suite: passed — 245 tests, 888 assertions.
- Strict Composer validation, PHP coding standards, PHPStan level 8 and dependency audit: passed.
- Production asset build, JavaScript lint, CSS lint, tooling tests and npm vulnerability audit: passed.
- Translation catalogue regeneration and byte-for-byte freshness check: passed with WP-CLI 2.12.0.
- Source-tree WordPress smoke journey: passed.
- Packaged smoke matrix: passed on WordPress 6.9 and WordPress 7.0.1.
- Packaged browser suite: passed — 12 critical visitor/editor journeys.
- Release verification: passed, including shipped PHP syntax, autoloading, archive allowlist and byte-for-byte reproducibility.
- Real WordPress 7.0.1 / PHP 8.3 compatibility inspector: passed with Elementor 3.35.9 and Elementor 4.1.5.
- The inspector verifies all fifteen registrations, categories, style dependencies, optimized DOM, atomic control contracts, selected-public rendering and malformed-source rejection.
- Candidate archive: `dist/simple-events-by-mime-0.1.1.zip` with SHA-256 `7903b7ab91b780756fade86f00af90c5b578d728b92d9f5be97c4da0dc51eb9d`.

## Senior developer review

The review retained one responsibility per dedicated widget while centralizing only source resolution, empty-state behaviour and common styling. Optional renderer arguments have backward-compatible defaults, so the composite details output and original saved widgets are unchanged. A request-local runtime closes Elementor's separate-object lifecycle gap without introducing persistent caching. Smoke testing also exposed that the local WordPress sanitizer double no longer matched current core behaviour; the double and its assertions were aligned with real WordPress before the complete suite was rerun.

## Senior QA review and residual risk

- Real Elementor hosts were verified server-side, including control construction and frontend field rendering. A manual drag-and-drop visual tour across every widget was not repeated in this automated package.
- Elementor Pro was not installed. Pro is not required by the widgets; only Theme Builder template assignment is Pro-owned. Current-context resolution is covered in unit tests and the shared WP4 resolver contract.
- Official WordPress Plugin Check remains the strict CI release gate because this repository intentionally has no local Plugin Check script. Its result must be observed green for the release commit before publication.
- Gutenberg equivalents and the final Elementor/Gutenberg visual parity review remain WP6 scope.
