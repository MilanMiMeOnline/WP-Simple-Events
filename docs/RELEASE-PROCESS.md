# Release process

This process produces and verifies the installable MiMe Simple Events and Calendar package. `docs/DECISIONS.md` ADR-020 is the architectural contract.

## Prerequisites

- PHP 8.2 or newer and Composer 2.
- Node.js 20 or newer and npm 10 or newer.
- The system `zip`, `zipinfo` and `unzip` commands.
- WP-CLI 2.12.0 for translation-catalogue generation.

Use the verified WP-CLI installation guidance from the official WP-CLI project. The generator version is pinned because its output metadata and extraction behaviour are part of the reviewed catalogue.

## Candidate preparation

1. Make the version identical in `mime-simple-events-calendar.php`, `WPSE_VERSION`, `readme.txt` and `package.json`.
2. Confirm `LICENSE` is present and the public metadata still declares `GPL-2.0-or-later`.
3. Regenerate translations with `npm run i18n:pot` and verify them with `npm run i18n:check`.
4. Run `composer validate --strict`, `composer qa` and `npm run qa`.
5. Run `npm run test:release`. This builds and verifies the candidate twice and fails when the two SHA-256 values differ.
6. Run `npm run test:upgrade`. This rebuilds the candidate and qualifies clean
   activation, every checksummed public release from 0.2.3 onward, derived-table
   repair, deactivation/reactivation and both uninstall-retention modes against
   the pinned WordPress 6.9 baseline.
7. Run the packaged smoke journey on both supported WordPress versions:

   ```sh
   WPSE_SMOKE_CORE='WordPress/WordPress#6.9' WPSE_SMOKE_PHP='8.2' WPSE_SMOKE_PLUGIN_PATH='.release/mime-simple-events-calendar' npm run test:smoke
   WPSE_SMOKE_CORE='WordPress/WordPress#7.1' WPSE_SMOKE_PHP='8.2' WPSE_SMOKE_PLUGIN_PATH='.release/mime-simple-events-calendar' npm run test:smoke
   ```

8. Require both GitHub Actions `Release archive and Plugin Check` and
   `Historical install and lifecycle matrix` jobs to pass. Plugin Check runs in
   strict mode against `.release/mime-simple-events-calendar`; the matrix uses
   the immutable package and core checksums in `tools/upgrade-releases.json`.
   The release job uploads the verified zip and checksum as one CI artifact.

## Outputs

- `.release/mime-simple-events-calendar/` is the exact uncompressed staging tree used by Plugin Check and smoke tests.
- `dist/mime-simple-events-calendar-{version}.zip` is the installable WordPress package.
- `dist/mime-simple-events-calendar-{version}.zip.sha256` binds the archive hash to its exact filename.

Both output directories are generated and ignored by Git. Do not edit their contents manually. Rebuild from reviewed source instead.

## Release acceptance

Do not publish a candidate unless all local gates, every historical upgrade,
both WordPress smoke targets and the official CI Plugin Check job are green.
Record dependency findings, compatibility exceptions and any intentionally
deferred issue in the QA report before distribution.

## Public release notes

1. Copy `docs/RELEASE-NOTES-TEMPLATE.md` and replace every placeholder with verified release information.
2. Describe user-visible outcomes in plain language and keep the established `Highlights` and `Safety and compatibility` sections.
3. Keep test counts, CI job lists, commit hashes, checksums and other qualification evidence in the matching QA report, not in the public GitHub release description.
4. Link to the tagged `CHANGELOG.md` for the complete technical record.
5. Re-read the rendered GitHub release before publishing and confirm that no template instruction or placeholder remains.
