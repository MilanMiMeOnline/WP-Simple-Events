# QA report — Gutenberg Event details save fix

**Candidate:** WP Simple Events 0.1.1\
**Date:** 2026-07-18\
**Scope:** Event details entered in the native metabox must be part of Gutenberg's authoritative publication request.

## Defect and acceptance result

Gutenberg published the post through REST while WordPress saved the legacy metabox through a separate request. The REST publication validator could therefore receive no `_wpse_start_local` value and correctly reject a visually complete event as `missing_start_date`.

The editor script now mirrors the editable Event details into Gutenberg's registered post-meta state. The post status and complete event record travel in one REST request. The classic-editor nonce/capability path remains intact, the REST validator remains authoritative, unrelated registered metadata is preserved and internal UTC indexes remain server-owned.

The REST error response now uses the first allowlisted validation message as its public message. The screenshot payload's scheme-less `mime-online.be` value will therefore produce `Enter a valid HTTP or HTTPS event URL.` after the date payload reaches the server.

## Senior developer review

- No server validation, capability check, nonce boundary or sanitizer was removed or weakened.
- The bridge uses WordPress' supported `core/editor` data store and registered REST metadata rather than adding a custom endpoint.
- Timed values use local ISO date-time strings; all-day values remain date-only.
- Other registered post metadata is merged rather than replaced.
- The script has no remote dependency and keeps the classic-editor fallback when the Gutenberg store is absent.
- Version 0.1.1 provides browser cache busting for the corrected editor asset.

## Senior QA evidence

- Regression test was observed failing before implementation and passes after the fix.
- JavaScript regression coverage proves timed values, all-day values, unrelated-meta preservation and classic-editor degradation.
- REST smoke coverage proves actionable missing-start and invalid-URL messages and atomic rejection of invalid writes.
- `composer validate --strict`: pass.
- `composer qa`: pass — PHP style, PHPStan, 173 tests / 608 assertions and Composer security audit.
- `npm run qa`: pass — build, 9 tool/editor tests, JavaScript/CSS lint and zero npm audit findings.
- Translation catalogue freshness: pass.
- Reproducible release build and archive verification: pass.
- Packaged WordPress smoke journey: pass on WordPress 6.9 and 7.0.1 with PHP 8.3.
- Archive SHA-256: `414baa9ced137d774f6a739e298f0887f515dd12b6a22e395991f70daf530378`.

## Residual verification

The user's `simpleevents.local` administrator session was not available to the automated in-app browser, so that exact installation was not mutated. The package is intended for the user's final visual confirmation in their existing Gutenberg session. Official WordPress Plugin Check remains the CI publication gate; this candidate is supplied for local acceptance testing rather than public release.
