# QA report: capability and UTC-index maintenance

## Scope

This increment adds administrator recovery for event capabilities and derived UTC date indexes. It does not add archive configuration, caching, a passive Site Health test or WP-CLI commands.

## Automated evidence

- Unit tests cover stale timed indexes, numeric-string equivalence, all-day DST boundaries, invalid/reversed canonical ranges, incomplete draft clearing, incomplete published-event rejection, metadata write failure and copied-date review preservation.
- Batch tests cross the 50-event boundary and verify invalid-event accounting without aborting the remaining page.
- Controller tests prove that only authenticated `admin_post` hooks are registered.
- The WordPress Playground journey verifies both forms and action-specific nonces, rejects a forged reindex nonce with HTTP 403, executes capability repair, runs one real bounded reindex batch and renders aggregate feedback.
- The final PHP suite passes with 155 tests and 546 assertions. PHPCS and PHPStan pass without errors.
- The JavaScript build, JavaScript lint and CSS lint pass. The production npm audit reports zero vulnerabilities.
- The full development audit reports one low-severity advisory in nested WordPress lint-tooling copies of esbuild. It is not shipped as a production dependency and does not block the high/critical release gate.
- The final local smoke journey passes on WordPress 7.0.1 with PHP 8.3. The WordPress 6.9 compatibility run remains part of the release matrix.

## Senior developer review

The reindex service deliberately does not call `EventPersistence`, because that gateway owns the complete event record. It constructs a validation-only input from the four canonical date fields, reuses the central date/publication rules and mutates only the two derived UTC keys. Already correct numeric strings cause no write.

Queries return only IDs, fix post type/status/order/page size and suppress unnecessary term/meta cache priming. One request cannot process more than 50 events. Exact multiples may show one harmless final empty continuation page; they never create an automatic loop.

## Senior QA and security review

- Capability and nonce checks are separate and server-side.
- Both actions use POST and safe local redirects with no-cache headers.
- Continuation counters are treated as untrusted decimal strings and bounded before display or accumulation.
- Invalid canonical data remains byte-for-byte untouched.
- A failed second index write may leave a partial pair, but the outcome is reported as failed and rerunning converges safely from canonical data.
- Feedback contains counts only and logs no event or personal data.

## Residual risk

- Offset pages can shift under concurrent event creation/deletion; rerunning is safe and documented.
- A Docker-backed full WordPress integration suite and the WordPress 6.9/Plugin Check matrix remain release evidence.
- A passive Site Health diagnostic could list aggregate invalid/missing counts later, without becoming another mutation path.
