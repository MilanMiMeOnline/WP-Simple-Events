# Release process

This process produces and verifies the installable WP Simple Events package. `docs/DECISIONS.md` ADR-020 is the architectural contract.

## Prerequisites

- PHP 8.3 or newer and Composer 2.
- Node.js 20 or newer and npm 10 or newer.
- The system `zip`, `zipinfo` and `unzip` commands.
- WP-CLI 2.12.0 for translation-catalogue generation.

Use the verified WP-CLI installation guidance from the official WP-CLI project. The generator version is pinned because its output metadata and extraction behaviour are part of the reviewed catalogue.

## Candidate preparation

1. Make the version identical in `wp-simple-events.php`, `WPSE_VERSION`, `readme.txt` and `package.json`.
2. Confirm `LICENSE` is present and the public metadata still declares `GPL-2.0-or-later`.
3. Regenerate translations with `npm run i18n:pot` and verify them with `npm run i18n:check`.
4. Run `composer validate --strict`, `composer qa` and `npm run qa`.
5. Run `npm run test:release`. This builds and verifies the candidate twice and fails when the two SHA-256 values differ.
6. Run the packaged smoke journey on both supported WordPress versions:

   ```sh
   WPSE_SMOKE_CORE='WordPress/WordPress#6.9' WPSE_SMOKE_PLUGIN_PATH='.release/wp-simple-events' npm run test:smoke
   WPSE_SMOKE_CORE='WordPress/WordPress#7.0.1' WPSE_SMOKE_PLUGIN_PATH='.release/wp-simple-events' npm run test:smoke
   ```

7. Require the GitHub Actions `Release archive and Plugin Check` job to pass. It runs the official WordPress Plugin Check action in strict mode against `.release/wp-simple-events` and uploads the verified zip and checksum as one CI artifact.

## Outputs

- `.release/wp-simple-events/` is the exact uncompressed staging tree used by Plugin Check and smoke tests.
- `dist/wp-simple-events-{version}.zip` is the installable WordPress package.
- `dist/wp-simple-events-{version}.zip.sha256` binds the archive hash to its exact filename.

Both output directories are generated and ignored by Git. Do not edit their contents manually. Rebuild from reviewed source instead.

## Release acceptance

Do not publish a candidate unless all local gates, both WordPress smoke targets and the official CI Plugin Check job are green. Record dependency findings, compatibility exceptions and any intentionally deferred issue in the QA report before distribution.
