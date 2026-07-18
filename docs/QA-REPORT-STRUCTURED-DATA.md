# QA report: structured-data hardening increment

## Scope

This increment adds Schema.org Event JSON-LD to eligible individual events and a native Events → Settings opt-out. It does not add offers, pricing, recurrence, maps, ICS export or a cache.

## Automated evidence

- `composer validate --strict`: passed.
- `composer qa`: passed with 117 tests and 406 assertions, WordPress Coding Standards, PHPStan and Composer security audit.
- `npm run build`, JavaScript lint and CSS lint: passed.
- `npm audit --omit=dev --audit-level=high`: zero production vulnerabilities.
- Full WordPress Playground smoke test: passed on WordPress 7.0.1 and PHP 8.3.

The smoke run created real published, draft and password-protected events; checked schema presence only on the eligible singular; verified the timed UTC offset, postponed status and visible location; opened the administrator settings screen; saved the off state through WordPress `options.php` with its nonce; and confirmed the schema disappeared.

## Senior developer review

Structured data is split into a pure builder, a WordPress public-data adapter, a safe JSON document encoder, a small settings resolver and an output controller. The provider reuses the existing date formatter and registered event metadata instead of introducing a parallel time conversion. Singular output remains uncached to prevent stale SEO state.

The review found no query, storage or optional-integration coupling. It also corrected an existing smoke assertion that searched the whole HTML document for visible field order; JSON-LD legitimately repeats status in the head, so visible ordering is now scoped to the event article.

## Senior QA and security review

- Draft, non-event, corrupt and password-protected records produce no graph.
- Invalid required schema input suppresses the complete graph instead of inventing data.
- Empty optional values are omitted and no `offers` property exists.
- All-day boundaries stay dates; timed boundaries keep the event timezone offset.
- JSON uses hexadecimal encoding for HTML-significant characters, including attempted `</script>` breakout text.
- The global setting is administrator-only, uses the native Settings API nonce/capability path and has a strict boolean sanitizer.
- The stable `wpse_structured_data_enabled` filter permits a per-event programmatic override.

## Residual risk

- Rich-result eligibility was not submitted to Google's external validator; the graph follows the documented Event shape, but search engines never guarantee a rich result.
- WordPress 6.9 remains to be exercised in the release matrix; the runtime smoke target was WordPress 7.0.1/PHP 8.3.
- The npm audit reports one low-severity transitive `esbuild` issue inside WordPress lint configuration packages. It is development-only, the production audit is clean, and it does not block the configured high/critical release gate.
- The remaining release blockers and intentionally deferred items are recorded in `HARDENING-GAP-AUDIT.md`.
