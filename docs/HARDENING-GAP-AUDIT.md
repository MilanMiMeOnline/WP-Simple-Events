# Hardening gap audit

This document tracks remaining product and release hardening against `PRODUCT-SPECIFICATION.md`. The specification remains the product contract. The detailed security and privacy review is in `SECURITY-PRIVACY-AUDIT.md`.

## Completed hardening

- Native event content, validation, explicit capabilities and derived UTC indexing.
- Bounded public list, archive and calendar queries limited to published, password-free events.
- Native classic/block-theme fallbacks, shortcodes, Gutenberg blocks and optional Elementor widgets over shared presentation services.
- Local FullCalendar assets and an accessible no-JavaScript fallback; no runtime CDN or remote service dependency.
- Safe Event JSON-LD with public-visibility checks and an administrator opt-out.
- Capability- and nonce-protected duplication and maintenance actions.
- Default-safe uninstall retention with explicit warned cleanup, bounded WordPress API deletion and shared-media retention.
- Deterministic production-allowlist builds, minimal production autoloading, SHA-256 verification and byte-for-byte reproducibility tests.
- WordPress 6.9 and 7.0.1 packaged smoke coverage, Elementor 3.35.9 and 4.1.5 compatibility checks, WooCommerce 10.9.4 joint activation, browser journeys and strict official Plugin Check for release 0.2.1.
- Git history and tracked-file scans showing only the configured GitHub `noreply` identity and no high-confidence secrets or local workstation paths.
- GitHub secret scanning and push protection enabled on the public repository.
- GitHub Private Vulnerability Reporting enabled with a direct private disclosure route in `SECURITY.md`.
- GitHub Actions restricted to read-only repository contents and pinned to immutable reviewed commits.
- Official Node-based GitHub Actions updated to their maintained Node 24 generations.
- Dependabot security updates enabled for reviewable remediation proposals without automatic merging.
- Public core REST protection for registered metadata belonging to password-protected events, with anonymous and authorized-editor regression coverage.
- Complete 0.9 threat model and permission matrix covering recurrence, occurrence storage/REST, Divi previews and calendar export.
- Executable security contracts that inventory REST permission callbacks, direct database adapters, prohibited runtime capabilities, browser privacy and remote URL boundaries.

## Release-readiness actions

1. Complete the remaining 0.9 accessibility, performance, new-user documentation and exploratory work packages.
2. Run every release gate against the exact frozen 0.9.0 candidate.
3. Require strict official Plugin Check and the complete hosted compatibility matrix to pass on that commit.
4. Publish identical verified GitHub and WordPress.org packages and preserve their checksums as evidence.

## Recommended repository hardening

- Keep GitHub account two-factor authentication and recovery methods current; prefer a passkey or hardware-backed second factor. This is an account setting and is not verifiable from the repository.
- Keep branch protection and required release checks under review once contribution volume grows.

## Post-release hardening

- Add actual locale translations and manually verify key editor and visitor journeys in at least one non-English locale.
- Reassess the pinned FullCalendar 6.1 line during every dependency review and before a stable 1.0 release.
- Add cache versioning only if production measurements show repeated collection/feed work warrants it, with invalidation tests for event, status and taxonomy changes.
- Consider passive Site Health diagnostics only when they add recovery value beyond the existing bounded maintenance tools.

## Deferred without blocking version 1

- Elementor Pro dynamic tags remain optional; the existing widgets work on Elementor Free pages and in host-provided current-event templates.
- Interactive maps, geocoding, ticketing, attendee management and two-way external calendar synchronization remain explicit non-goals.
