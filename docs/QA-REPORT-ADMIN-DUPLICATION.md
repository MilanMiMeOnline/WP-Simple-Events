# QA report: event overview and duplication

## Scope

This increment completes the specified event list-table workflow and duplicate-to-draft acceptance path. It does not add recurrence, bulk duplication, arbitrary meta cloning or ICS export.

## Automated evidence

- Unit coverage verifies strict admin-view and sort allowlists, period boundaries, exceptional event statuses, compact timezone formatting, required columns, hook scope, duplicate post/meta/taxonomy policy, unauthorized row-action hiding, partial-copy rollback and review-flag clearing.
- The final PHPUnit suite contains 137 tests and 480 assertions.
- The full WordPress Coding Standards check and PHPStan level 8 pass without suppressing analysis errors.
- Composer validation and its locked dependency audit pass with no advisories. The JavaScript build, ESLint and Stylelint pass; the npm high/critical gate passes with one reviewed low-severity finding in the WordPress lint toolchain's nested `esbuild`, which is not shipped as runtime PHP code.
- The WordPress 7.0.1/PHP 8.3 Playground smoke path creates real event fixtures and verifies columns, upcoming/active and past boundaries, postponed and category filters, ascending upcoming order, a rejected forged nonce, a real duplicate redirect, draft status, copied canonical/location/taxonomy data, omitted external event URL, visible date-review guidance and guidance removal after a valid REST save.

## Senior developer review

Admin query construction is isolated from the public repository because visibility requirements differ: administrators must retain access to drafts and protected posts. It still uses the same UTC boundary semantics and never accepts an arbitrary meta key. The adapter is restricted to the main Events admin query and combines rather than discards existing admin meta-query clauses.

Duplication separates an explicit copy plan, persistence service and HTTP controller. The controller owns capabilities, nonce and redirect; the service owns atomic creation and rollback; the plan freezes which fields may cross into the new draft. A generic “copy all post meta” implementation was intentionally rejected.

## Senior QA and security review

- The state-changing request has an event-specific nonce and three capability checks.
- A forged nonce returns HTTP 403 and creates no draft.
- The source event is never updated.
- Partial metadata or taxonomy failure deletes the partial new draft.
- Passwords, external event URL and unknown custom metadata are not copied.
- Date review is explicit and survives until a validated persistence pass.
- Admin filters are read-only, sanitized and allowlisted; admin result pagination remains native and bounded.
- Empty overview cells are meaningful for assistive technology.

## Residual risk

- The release matrix still needs WordPress 6.9 and final Plugin Check evidence.
- The smoke run uses an administrator. Unit coverage proves capability-gated row-action hiding, but a full browser journey for a custom partially privileged role is still future E2E work.
- High-volume admin-list performance has not been benchmarked; queries use indexed-style numeric metadata patterns and native pagination, while collection caching remains a separate measured hardening task.
