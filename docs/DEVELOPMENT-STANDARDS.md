# Development standards

## Design principles

Simple Events by MiMe stays small by favouring native WordPress concepts: a custom post type, taxonomies, metadata, capabilities, REST schemas, templates and bounded `WP_Query` objects. Abstractions must remove real duplication or isolate a genuine boundary; speculative frameworks are out of scope.

The core must remain usable without WooCommerce and Elementor. Optional integrations live behind availability and version checks and must fail silently when their host plugin is absent.

## PHP

- PHP 8.3 is the language floor. Use strict types in PHP-only class files.
- Follow WordPress Coding Standards plus WordPress documentation standards.
- Put production classes under `src/` using `MiMe\WPSimpleEvents`.
- Prefer immutable value objects for validated event dates and query criteria when introduced.
- Use explicit parameter and return types. PHPDoc explains contracts that types cannot express.
- Catch only exceptions that can be handled meaningfully. Do not hide faults.
- WordPress hook callbacks should be public entry points that delegate to testable logic.

## JavaScript and CSS

- Add browser code only when native HTML and WordPress behaviour cannot satisfy the requirement.
- Use the official WordPress lint rules and packages. Add a bundler only when functional browser code requires one.
- Progressive enhancement is required for visitor-facing features.
- Component classes use the `wpse-` prefix. Never style unscoped theme elements.
- Respect reduced motion, keyboard navigation, contrast and visible focus.

## Data and API contracts

- Canonical event data is stored on `wpse_event` posts using registered metadata and the two event taxonomies.
- Store event instants in a canonical form and format them in the WordPress site time zone. The precise schema is implemented only after its dedicated design/test increment.
- Every registered meta or REST field needs a schema, sanitization callback and appropriate authorization callback.
- Public APIs must have bounded pagination, stable ordering and documented error shapes.
- Changing stored data requires an idempotent, versioned migration and rollback consideration.

## Documentation

Update the analysis/specification for product changes, `docs/DECISIONS.md` for architectural choices, public documentation for user-visible behaviour and `CHANGELOG.md` for released changes.
