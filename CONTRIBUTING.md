# Contributing to WP Simple Events

The project is developed in small, reviewable increments. The complete product contract is in `ANALYSE-EN-BOUWSPECIFICATIE.md`; repository-wide engineering rules are in `AGENTS.md`.

## Local prerequisites

- PHP 8.3 or newer
- Composer 2
- Node.js 20 or newer and npm 10 or newer
- `zip`, `zipinfo` and `unzip` for verified release packages
- WP-CLI 2.12.0 when regenerating the translation catalogue
- Docker only for the optional Docker-backed `wp-env` environment; the automated compatibility smoke journey uses WordPress Playground

## Initial setup

```sh
composer install
npm ci
```

Start a quick WordPress environment without Docker:

```sh
npm run env:start
```

Run the automated Playground smoke test, which starts WordPress and verifies activation, the editor, REST validation, public visibility, archive filtering and custom routing, page-conflict diagnosis, shortcodes, the calendar feed and fallback, native single/archive templates and password protection before stopping WordPress again:

```sh
npm run test:smoke
```

The smoke runner recreates its own temporary `WP_ENV_HOME`; it does not reset or destroy the normal project development database. `WPSE_SMOKE_CORE` selects a WordPress core source and `WPSE_SMOKE_PLUGIN_PATH` can point the journey at the generated package staging directory. The release matrix commands are documented in `docs/RELEASE-PROCESS.md`.

Start the full Docker-backed WordPress environment when Docker is available:

```sh
npm run env:start:docker
```

## Quality commands

```sh
composer lint
composer analyse
composer test:unit
composer test:integration
composer security:audit
npm run lint:js
npm run lint:css
npm run build
npm run audit:production
npm run test:e2e:install
npm run test:e2e
npm run i18n:check
npm run test:release
```

`composer test:integration` delegates to the real WordPress Playground smoke journey described above; it is deliberately not an empty or WordPress-stubbed PHPUnit suite. `npm run test:e2e:install` installs the pinned Chromium build once, and `npm run test:e2e` first builds the exact staging package and then runs isolated browser regressions against that package in another disposable Playground site. `WPSE_E2E_CORE` and `WPSE_E2E_PLUGIN_PATH` are optional overrides for compatibility work. `composer qa` and `npm run qa` are the normal pre-handoff gates. `npm run test:release` additionally proves archive contents, shipped PHP syntax, the production autoloader, checksum integrity and byte-for-byte reproducibility. The official Plugin Check result is produced in CI against the same staging directory.

For an Elementor matrix run, activate WP Simple Events plus the target official Elementor package in an isolated WordPress site and execute `wp eval-file wp-content/plugins/wp-simple-events/tests/Compatibility/elementor-inspector.php`. The inspector verifies all widget names, categories, dependencies, field controls, optimized DOM and strict explicit-source rendering. Run it against the documented minimum 3.x and current tested 4.x packages before release; it is development tooling and is not included in the release archive.

## Change workflow

1. Confirm the requested behaviour and non-goals.
2. Identify security, compatibility, accessibility and performance risks.
3. Add or update tests.
4. Make a focused implementation.
5. Run automated gates.
6. Execute the applicable manual checks in `docs/QA-CHECKLIST.md`.
7. Update documentation and `CHANGELOG.md` when behaviour changes.

Commits should be cohesive and use an imperative summary. Do not commit generated dependency directories, secrets, local environment files, logs or test caches.

## Review expectations

A review must verify correctness against the specification, not only code style. It must also check permission boundaries, nonce handling, input validation, contextual escaping, query limits, time-zone behaviour, accessibility, translations, backward compatibility and test quality.
