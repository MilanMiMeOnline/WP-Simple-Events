# QA report — WP Simple Events 0.2.0

**Date:** 2026-07-20  
**Candidate:** WP Simple Events 0.2.0  
**Branch:** `codex/release-0.2.0`  
**Archive SHA-256:** `ab7984251afd1d36ca144176e47f9ddac4f7d9c49588ddd4b88b35bd02c8c927`

## Result

WP Simple Events 0.2.0 is a locally qualified installable release candidate. The plugin header, runtime constant, WordPress stable tag, npm package metadata, lockfile and translation catalogue all identify the release as 0.2.0. The packaged archive was built from the production allowlist, verified and reproduced byte-for-byte.

Public publication remains conditional on the official strict WordPress Plugin Check job passing against the release commit in CI.

## Release contents

- Calendar filtering, initial geometry, accessible control states, captured wall-time placement and WordPress 12/24-hour presentation are stabilized.
- Editors can customize the external event action label and optionally expose an unambiguous event timezone.
- Elementor exposes twelve atomic event-field widgets in addition to the three composite widgets.
- Gutenberg exposes the same twelve fields as dynamic blocks with server previews, native style supports and an opt-in single-event pattern.
- The complete eight-item acceptance backlog and WP0–WP7 quality programme are included.

## Automated evidence

| Gate | Result |
| --- | --- |
| Public version consistency | Pass, all sources report 0.2.0 |
| Composer strict validation | Pass |
| PHP coding standards | Pass, 8/8 groups |
| PHPStan level 8 | Pass, 129 files, no errors |
| PHPUnit | Pass, 262 tests and 1,003 assertions |
| Composer dependency audit | Pass, no advisories |
| JavaScript/CSS build and lint | Pass, zero warnings |
| Node tooling contracts | Pass, 11 tests |
| npm dependency audit | Pass, zero vulnerabilities |
| Translation catalogue | Pass, regenerated and current with WP-CLI 2.12.0 |
| Release archive verification | Pass, including allowlist, shipped PHP, autoloader and checksum binding |
| Reproducibility | Pass, two consecutive archives are byte-for-byte identical |
| Packaged WordPress 6.9 / PHP 8.3 smoke | Pass |
| Packaged WordPress 7.0.1 / PHP 8.3 smoke | Pass |
| Packaged Playwright browser suite | Pass, 15/15 journeys |
| Elementor 3.x host contract | Pass on Elementor 3.35.9 / WordPress 7.0.1 / PHP 8.3 |
| Elementor current host contract | Pass on Elementor 4.1.5 / WordPress 7.0.1 / PHP 8.3 |

The first WordPress 6.9 attempt encountered a temporary Playground administrator-login race and stopped before obtaining a REST nonce. A new isolated run passed completely, and the infrastructure failure did not reproduce.

## Senior developer review

- The version change is limited to release metadata, changelogs and the generated translation header; no storage schema, hook, API or compatibility boundary changed.
- Historical `@since` annotations, earlier QA reports and release evidence retain their original version numbers.
- WordPress and npm public version sources are protected by existing mismatch tests and the release builder.
- The WordPress changelog now distinguishes the substantial 0.2.0 feature release from the earlier 0.1.1 Gutenberg-save patch.
- The generated archive contains 0.2.0 in its plugin header, runtime constant, stable tag and POT project identifier.

## Senior QA and security review

- The exact 0.2.0 staging package passed both supported WordPress targets and all browser journeys.
- The final archive checksum matches its generated checksum file, and no development-only files or temporary test configuration are shipped.
- Dependency results contain no unreviewed high or critical finding; current audits report no vulnerabilities.
- The release does not broaden the agreed scope and still excludes recurrence, interactive maps, geocoding, ticketing and a custom event table.
- No Playground process or temporary Elementor configuration remains after qualification.

## Residual release conditions

1. Observe the configured `wordpress/plugin-check-action@v1` job green for the release commit before publishing 0.2.0 publicly.
2. A final stakeholder visual acceptance pass in the target site's theme and Elementor setup remains advisable, although automated semantic, geometry and host-contract coverage is green.

## Candidate

Installable archive: `dist/wp-simple-events-0.2.0.zip`  
Checksum file: `dist/wp-simple-events-0.2.0.zip.sha256`
