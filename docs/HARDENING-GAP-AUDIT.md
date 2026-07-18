# Hardening gap audit

This audit compares the current implementation with `ANALYSE-EN-BOUWSPECIFICATIE.md`. It is a prioritization aid; the specification remains the product contract.

## Completed hardening increments

- Native event content, validation, permissions and UTC indexing.
- Bounded list, archive and calendar queries with public visibility rules.
- Classic and block-theme fallbacks plus shortcodes.
- Local FullCalendar bundle and accessible no-JavaScript fallback.
- Optional Elementor Free integration over native render contracts.
- Singular Event JSON-LD with safe encoding, public visibility checks, correct all-day/timed values, an administrator setting and a stable per-event filter.
- Event admin columns for dates, all-day state, location, categories, event status and publication status, with timing/status/category filters and sortable UTC date indexes.
- A capability- and nonce-protected duplicate action that creates a draft, copies only agreed content/location/taxonomy data, omits the external event URL and requires explicit date review.
- A deliberately small settings page with working structured-data and uninstall-retention controls.
- Default-safe uninstall behaviour with explicit per-site opt-in, bounded WordPress API cleanup, shared-media retention and multisite batching.
- Administrator-only event-capability repair and validation-backed UTC-index rebuilding in explicit batches of 50, with invalid/failure accounting.
- Bounded archive slug, page-size and default-period controls, persistent WordPress-page conflict diagnosis and change-driven one-shot rewrite regeneration.
- A deterministic production-allowlist build with a minimal Composer autoloader, strict archive verification, SHA-256 binding and byte-for-byte reproducibility testing.
- A generated `/languages/wp-simple-events.pot` catalogue with deterministic CI freshness verification.
- Packaged activation and full smoke journeys on WordPress 6.9 and 7.0.1 with PHP 8.3.
- Official Plugin Check is configured as a strict CI gate against the exact package staging directory.

## Release-blocking functional gaps

1. **External release gate:** the official GitHub Actions Plugin Check job must be observed green for the release commit. It cannot be claimed from local configuration alone.
2. **Broader integration matrix:** Docker-backed WordPress integration tests and a WordPress 6.9 matrix including the supported Elementor/WooCommerce versions remain desirable before a public stable release. The native packaged smoke matrix is complete.

## Hardening work that should follow the missing core workflows

- Add cache versioning only around measured repeated collection/feed work, with invalidation tests for event, status and taxonomy changes. Singular JSON-LD intentionally remains uncached.
- Add actual locale translations and verify the main editor and visitor journeys in a non-English locale.
- Repeat compatibility checks with supported Elementor and current WooCommerce across the final release matrix.
- Add Site Health repair/diagnostic actions where they materially improve recovery.
- Consider a passive Site Health summary for aggregate invalid/missing event data. The existing maintenance result already reports skipped invalid records, so this must add diagnostic value without becoming another mutation path.

## Deferred without blocking version 1.0

- A single-event `.ics` download is useful but is classified as optional/should-have in the specification. Operational recovery and release evidence still take priority.
- Elementor Pro dynamic tags are optional; existing Theme Builder precedence remains supported.
- Recurrence, interactive maps, geocoding, ticketing and external calendar synchronization remain explicit non-goals.
