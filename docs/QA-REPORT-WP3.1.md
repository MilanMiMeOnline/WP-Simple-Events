# QA report — WP3.1

**Date:** 2026-07-19
**Scope:** Optional external event action label (`WPSE-BL-005`)

## Delivered behaviour

- Editors can enter one optional external event link label beside the existing external event URL.
- The label is revisioned, exposed through the typed core REST contract and saved atomically by both the native editor and Gutenberg.
- Custom labels are plain text and limited to 120 characters. Structured values are rejected at mapping/schema boundaries and HTML is removed by the shared sanitizer.
- A valid URL with an empty or missing label uses the translated `More event information` fallback.
- A label without an external URL may remain stored for editing but produces no public action or orphaned text.
- Event duplication omits both the destination-specific external URL and its label.

## Senior developer review

- `EventMeta`, `EventInput`, `EventInputMapper`, `EventValidator`, `ValidatedEventData` and `EventPersistence` were extended together, leaving no alternate write path for the new field.
- Core REST enforces a string schema and a 120-character maximum; the metadata authorization callback continues to delegate to the event edit capability.
- Gutenberg mirrors the field into its existing registered-meta save, preserving unrelated metadata and the single-request publication invariant.
- Public rendering requires the existing validated URL, trims missing/whitespace labels, escapes custom text late and keeps the translated fallback for legacy records.
- Revisions are enabled through the common metadata contract. Uninstall needs no new deletion path because WordPress deletes post metadata with its event; UTC-index maintenance does not read or mutate the field.

## Senior QA and security review

- Unit coverage includes scalar/structured input, malicious markup, whitespace, truncation at 120 characters, persistence/deletion, partial REST mapping, legacy records and duplication exclusion.
- The real-WordPress journey verifies REST registration and bounds, editor markup, atomic REST persistence, unauthenticated update rejection and post-rejection integrity.
- Public smoke coverage verifies safe custom output, the translated fallback and suppression of a stored label when no URL exists.
- The packaged plugin passes the same journey on WordPress 6.9 and 7.0.1. Existing Chromium calendar journeys remain green.
- No database query, remote request, new dependency, nonce surface or public feed field was introduced.

## Automated evidence

| Gate | Result |
|---|---|
| Focused red/green regression suite | Pass — 31 PHP tests / 165 assertions plus 3 editor-mirror tests |
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 204 tests / 698 assertions, Composer audit |
| `npm run qa` | Pass — production build, 11 tooling tests, JavaScript/CSS lint and npm audit |
| `npm run i18n:check` | Pass |
| Source WordPress integration smoke | Pass |
| `npm run test:e2e` | Pass — existing Chromium regression suite against the staging package |
| Supported WordPress packaged smoke matrix | Pass — WordPress 6.9 and 7.0.1 |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- One URL still has one label. Multiple resource links remain deliberately outside version 1.
- The label is available through the existing composite Event Details output. Dedicated atomic Elementor/Gutenberg action components belong to WP4–WP6.
- Official WordPress Plugin Check remains the CI release gate; this checkout has no local `test:plugin-check` package script.
