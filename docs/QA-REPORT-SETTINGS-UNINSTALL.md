# QA report: settings and uninstall retention

## Scope

This increment adds only settings backed by existing behaviour: Event JSON-LD output and explicit uninstall cleanup. It does not introduce archive-slug changes, custom date formats, cache buttons or repair actions.

## Automated evidence

- Unit tests cover strict boolean normalization, default retention, explicit cleanup, event/term/option allowlists, shared-media and ordinary-post retention, capability reversal and interrupted taxonomy cleanup.
- A 101-site deterministic multisite test crosses the batch boundary, evaluates per-site options independently and verifies site-context restoration.
- The real WordPress Playground path verifies the settings field and warning, saves both enabled and disabled values through the nonce-protected Settings API, reflects both states in the UI and proves that changing the future uninstall preference does not alter current event content.
- `uninstall.php` is included in both WordPress Coding Standards and PHPStan level-8 scope.
- The final suite contains 145 PHPUnit tests and 513 assertions. Composer validation, WordPress Coding Standards, PHPStan level 8, Composer audit, the JavaScript build, ESLint, Stylelint and the WordPress 7.0.1/PHP 8.3 smoke path pass.
- The production npm audit reports zero vulnerabilities. The all-development-dependencies high/critical gate passes with one reviewed low-severity finding in the WordPress lint toolchain's nested `esbuild`; it is not shipped as plugin runtime code.

## Senior developer review

The destructive preference is checked immediately before cleanup and has no permissive fallback. The cleaner registers the inactive plugin's content types, uses bounded ID-only WordPress queries and calls core deletion APIs. There is no direct SQL and no generic option, taxonomy or post-type discovery.

Options are deleted last. If post or term deletion makes no progress, cleanup stops instead of looping forever and retains its settings. The source attachments are outside the deletion allowlist because they are independent WordPress content that may be shared.

## Senior QA and security review

- The Settings API owns capability and nonce verification; the page independently checks `manage_options` before rendering.
- Hidden unchecked values and strict sanitization prevent stale checkbox state without accepting arbitrary truthy strings.
- Deactivation and malformed/missing settings preserve data.
- Each multisite site authorizes only its own cleanup and blog context is restored in `finally`.
- Event deletion delegates metadata, revision, comment and relationship cleanup to WordPress core.
- No customer, attendee or WooCommerce data is queried or deleted.

## Residual risk

- A plugin or hook can intentionally veto a WordPress post/term deletion. The cleaner detects lack of progress and preserves options, but WordPress does not provide a transactional uninstall across all content APIs.
- Very large networks and event catalogues have bounded individual queries, but total runtime is still proportional to stored data and should be included in the final compatibility exercise.
- The final WordPress 6.9 matrix and Plugin Check evidence remain release tasks.
- WordPress Playground does not expose its CLI `run` command, so destructive cleanup is exercised through deterministic WordPress API doubles rather than by deleting the mounted smoke fixture. A Docker-backed integration run remains part of final release evidence.
