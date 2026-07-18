# QA report: release readiness and compatibility matrix

**Date:** 2026-07-17\
**Candidate:** WP Simple Events 0.1.0\
**PHP smoke runtime:** 8.3\
**Archive SHA-256:** `fee5f9bb42f1f23ddb98ba45395bdb705fdf4fee1432be42be2ccf0947f1fa05`

## Delivered controls

- A production allowlist builds `.release/wp-simple-events` instead of archiving the working tree.
- The builder generates a class-authoritative Composer autoloader offline and excludes development packages and manifests.
- Public versions must match across the plugin header, runtime constant, WordPress stable tag and npm metadata.
- The archive contract rejects an unexpected root, traversal, duplicate entries, hidden files, development files/directories, unsupported file types and missing runtime files.
- The verifier binds the checksum to the exact filename, rejects symbolic links, lints every shipped PHP file and loads `MiMe\WPSimpleEvents\Plugin` through the shipped autoloader.
- Two clean builds must produce the same bytes.
- The required `/languages/wp-simple-events.pot` is generated deterministically and checked for freshness in CI.
- CI runs strict official WordPress Plugin Check against the staging package and uploads the zip plus checksum.
- The smoke runner can select a WordPress core version and test the staging package, including normal protected activation when Playground does not auto-activate an external mount.

## Automated evidence

| Gate | Result |
| --- | --- |
| Composer strict validation | Pass |
| PHP coding standards | Pass, 8/8 groups |
| PHPStan | Pass, 101 files, no errors |
| PHPUnit | Pass, 173 tests and 608 assertions |
| Composer security audit | Pass, no advisories |
| Node release-contract tests | Pass, 6 tests |
| JavaScript and CSS lint | Pass, zero warnings |
| Production npm audit | Pass, zero vulnerabilities |
| Full npm audit | Pass at the high/critical gate; one known low-severity transitive development-only esbuild advisory |
| Translation freshness | Pass with WP-CLI 2.12.0 on PHP 8.3 |
| Release archive verification | Pass, 120 files, 608 KiB |
| Reproducibility | Pass, two consecutive builds produced the same SHA-256 |
| Workflow syntax | Pass |

## Packaged WordPress journeys

The exact final staging package passed the complete automated journey on both targets:

- WordPress 6.9 with PHP 8.3: pass.
- WordPress 7.0.1 with PHP 8.3: pass.

The journey covers protected activation, the Events admin menu and editor, metadata schemas, REST publication validation and atomic rejected writes, public visibility, ordering and filters, password protection, duplicate-event permissions/nonces/copy policy, shortcodes, multiple instances, calendar REST/fallback/local assets, native single/archive rendering, structured data, settings, archive-route changes and conflicts, capability repair, UTC reindexing and uninstall-retention controls.

## Senior developer review

- Replaced working-tree packaging with a small explicit runtime boundary.
- Kept production dependency count at zero while still shipping the required Composer autoloader.
- Prevented release input and staging symlinks from being followed.
- Added per-directory file-type rules and checksum filename binding.
- Kept all external tooling outside the production package.
- Confirmed that Elementor and WooCommerce remain optional and no runtime release change couples the core to them.

## Senior QA review

- Confirmed tests fail for mismatched versions, wrong roots, traversal, hidden files, source/development files, unsupported types, dev vendor packages, missing translations and incorrect checksum filenames.
- Reopened and inspected the generated archive rather than trusting the staging tree.
- Repeated both supported WordPress journeys after the final shipped documentation changed the archive hash.
- Confirmed the POT catalogue reproduces byte-for-byte.
- Confirmed no Playground process remains after each journey.

## Residual release risks

1. The official `wordpress/plugin-check-action@v1` job is configured correctly against `.release/wp-simple-events`, but its result must still be observed green in GitHub Actions for the release commit. Local Docker is unavailable, and Plugin Check 2.0.0 was too heavy to run reliably inside the WebAssembly Playground; no flaky local substitute was retained.
2. The final minimum-WordPress matrix covers the complete native package. Elementor 3.35.9, Elementor 4.1.5 and WooCommerce 10.9.4 were previously verified on WordPress 7.0.1; their combined WordPress 6.9 matrix remains advisable before a public stable release.
3. The catalogue is complete as a source template, but no actual Dutch or other locale translation ships yet. A non-English exploratory journey remains advisable.
4. The low esbuild advisory exists only in nested WordPress lint-tooling development dependencies, is not shipped, and does not breach the configured high/critical release gate.

## QA conclusion

The release engineering increment is accepted. The generated 0.1.0 package is reproducible, installable and passes the native supported WordPress matrix. Publication remains conditional on a green official CI Plugin Check result and the normal final release approval described in `docs/RELEASE-PROCESS.md`.
