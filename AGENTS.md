# WP Simple Events - development guardrails

These instructions apply to the entire repository. They are mandatory for human and AI contributors.

## Product contract

- `ANALYSE-EN-BOUWSPECIFICATIE.md` is the functional and technical source of truth.
- Plugin name: WP Simple Events. Author: MiMe. Text domain and slug: `wp-simple-events`.
- Minimum versions: WordPress 6.9 and PHP 8.3.
- WooCommerce and Elementor are optional integrations, never core dependencies.
- Version 1 deliberately excludes recurrence, interactive maps, geocoding, ticketing and a custom database table.
- Do not expand scope silently. Record intentional changes in `docs/DECISIONS.md` first.

## Required workflow

1. Read the relevant specification and existing code before editing.
2. State the intended behaviour and risk before a non-trivial change.
3. Add or update tests for every behavioural change and bug fix.
4. Implement the smallest cohesive change that satisfies the specification.
5. Review the result twice: first as a senior developer, then as a senior QA engineer.
6. Run all applicable quality gates and resolve failures before handoff.
7. Report what changed, which checks ran, and any residual risk.

Never weaken a rule, add an ignore, introduce a baseline, or skip a failing test merely to make a check pass. Any exceptional suppression must be narrow, documented inline, and justified in the handoff.

## Architecture and code standards

- Use namespace `MiMe\WPSimpleEvents` and PSR-4 files under `src/`.
- Prefer one final class with one responsibility per file. Use interfaces only at genuine boundaries.
- Prefix WordPress-global identifiers with `wpse_`; constants use `WPSE_`.
- Use the WordPress APIs before custom infrastructure.
- Keep domain logic independent from WordPress globals where practical so it can be unit tested.
- Keep hook registration explicit. Constructors must not perform surprising work.
- Do not access the database directly unless a documented requirement cannot be met through WordPress APIs.
- Do not add production dependencies without documenting purpose, maintenance status, licence and removal cost.
- Maintain backward compatibility within a stable major version. Deprecate before removal.
- All user-facing strings must be translatable with text domain `wp-simple-events`.
- Front-end markup must be semantic and accessible. Keyboard use and visible focus are requirements.
- CSS must be minimal, component-scoped and inherit theme typography, colours and spacing by default.

## Security rules

- Treat every external value as untrusted, including request data, metadata, shortcodes, REST input and block attributes.
- Check capabilities for every privileged action and verify a nonce for state-changing browser requests.
- Validate against the expected shape, then sanitize at input boundaries.
- Escape late for the exact output context: HTML, attributes, URLs or JavaScript.
- Use allowlists for enumerated values. Never trust a hidden field or client-side validation.
- Do not expose private, draft or password-protected events through queries, REST or calendar feeds.
- Avoid raw SQL. If unavoidable, use `$wpdb->prepare()` and document why WordPress APIs were insufficient.
- Avoid unserializing untrusted data, dynamic includes, `eval`, shell execution and remote code or assets.
- Do not log secrets, nonces, personal data or full request payloads.
- Queries must be bounded and permission-aware. AJAX/REST endpoints require explicit schemas and permission callbacks.
- Dependency vulnerabilities rated high or critical block release unless risk acceptance is documented.

## Test and QA policy

- Pure date, status, query and formatting logic requires unit tests, including boundary cases.
- WordPress registrations, persistence, permissions, REST behaviour and queries require integration tests.
- Critical editor and visitor journeys require end-to-end coverage once those journeys exist.
- Test time-zone changes, DST boundaries, same-day/multi-day/all-day events, invalid input and unauthorised access.
- Bug fixes require a failing regression test before or with the fix whenever technically feasible.
- A test must be deterministic, isolated and meaningful; do not assert implementation trivia.
- Use `docs/QA-CHECKLIST.md` before every deliverable.

## Definition of done

Run the relevant commands from the repository root:

```sh
composer validate --strict
composer qa
npm run qa
```

For changes that touch WordPress behaviour, also run the local smoke/integration checks described in `CONTRIBUTING.md`. Release candidates must pass WordPress Plugin Check and the supported-version compatibility matrix in CI.
