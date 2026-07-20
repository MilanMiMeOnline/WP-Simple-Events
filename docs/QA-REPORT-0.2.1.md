# QA report — WP Simple Events 0.2.1

**Date:** 2026-07-20

**Candidate:** WP Simple Events 0.2.1

**Scope:** external event-link behaviour and Elementor editing support

**Archive SHA-256:** `e9a7077c8bd7b6bd16aec21b6cc6d093917b8cb97a996d457f0d8f75b7a25532`

## Result

Version 0.2.1 resolves both reported issues. Public location and external event actions open in isolated new tabs. Events declare Elementor's official WordPress post-type feature support, so compatible Elementor installations recognize individual Events as editable without WP Simple Events changing the user's Elementor option.

## Functional evidence

- `EventFieldRenderer::location_action()` and `external_action()` emit `target="_blank" rel="noopener noreferrer"` only for their external destinations.
- Native details, shortcodes, Elementor widgets and Gutenberg blocks inherit the same link output from the shared renderer.
- Internal event title, image and taxonomy links keep their existing same-tab behaviour.
- `wpse_event` includes `elementor` in its native post-type supports alongside title, editor, excerpt, thumbnail, author, revisions and custom fields.
- Real Elementor 3.35.9 and 4.1.5 hosts confirm both `post_type_supports( 'wpse_event', 'elementor' )` and Elementor's own `Utils::is_post_type_support()` result. Both hosts also create an editable Event document whose official edit URL opens Elementor.
- Elementor edits the event's normal WordPress content/layout. Event date, time, location and status remain managed through the native Event details panel.

## Automated evidence

- Focused presentation/content/Elementor/Gutenberg suite: passed — 46 tests, 329 assertions.
- Complete PHPUnit suite: passed — 263 tests, 1,006 assertions.
- Composer validation, coding standards and full PHPStan level 8: passed.
- JavaScript/CSS build and lint plus Node tooling tests: passed.
- Composer and npm dependency audits: passed with no known vulnerabilities.
- Translation catalogue: regenerated and byte-for-byte current with WP-CLI 2.12.0.
- Packaged WordPress smoke: passed on WordPress 6.9 and 7.0.1 with PHP 8.3.
- Real Elementor compatibility: passed on 3.35.9 and 4.1.5 with WordPress 7.0.1 / PHP 8.3.
- Packaged Playwright suite: passed — 15/15 journeys, including exact target/rel assertions for both external actions.
- Release verification and two-build reproducibility: passed.
- Clean-checkout CI regressions are covered for optional PHPStan dependency paths, the command-scoped Plugin Check fixture exception and Gutenberg login navigation.

## Senior developer review

- The link change is implemented once at the shared semantic renderer rather than copied into four presentation hosts.
- Elementor support uses the public WordPress post-type feature mechanism that both tested Elementor lines consume. It introduces no Elementor class reference in core post-type registration and leaves Elementor optional.
- No Elementor option is created or overwritten, and existing event metadata, content and template contracts remain unchanged.
- The smoke harness now tolerates a bounded 1-second Playground REST-nonce startup race but still fails after five invalid responses; it never logs the nonce.
- Version sources, stable tag, lockfile and POT project identifier are synchronized to 0.2.1.
- Source and production archives include the complete GPL-2.0-or-later licence; the release contract rejects its omission.
- The official Plugin Check compatibility path recognizes only the loaded checker inside the leading WP-CLI `plugin check` command; other CLI commands retain the event publication invariant.
- Browser journeys retain a 30-second per-test timeout while allowing a bounded six-minute total for cold Playground startup plus all 15 tests.

## Senior QA and security review

- `noopener noreferrer` blocks opener access and referrer disclosure for the two forced new-tab destinations.
- Stored URLs continue through the existing validated metadata and late `esc_url()` boundary; stored/custom labels remain escaped plain text.
- Malformed or absent URLs continue to render no action or empty wrapper.
- Elementor support does not grant WordPress capabilities. Existing event edit permissions remain authoritative.
- Internal navigation was deliberately not changed to new-tab behaviour.

## Residual release conditions

1. Public publication remains conditional on the corrected official strict WordPress Plugin Check job passing against the 0.2.1 release commit. The first public CI run exposed the checker-fixture incompatibility now covered by ADR-030 and an isolated regression test.
2. The user should visually confirm the Elementor edit entry point on the target installation because toolbar placement differs between Elementor/WordPress editor versions; both supported host APIs recognize Events as editable in automated testing.

## Candidate

Installable archive: `dist/wp-simple-events-0.2.1.zip`

Checksum file: `dist/wp-simple-events-0.2.1.zip.sha256`
