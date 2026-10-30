# MiMe 1.1.1 release qualification

**Date:** 30 October 2026
**Status:** Released and verified on GitHub and WordPress.org.

## Authorization and scope

The owner explicitly authorized a new version and publication to GitHub and
WordPress.org after reviewing the unknown-end fix and historical-upgrade evidence.
This is publication approval under ADR-104; no additional feature work is included.

The patch preserves empty timed ends, uses a private end-of-local-day boundary
for upcoming queries and omits unknown/equal timed ends from Event JSON-LD.
Existing canonical dates are not automatically migrated. Schema, permissions,
nonces and optional integration interfaces are unchanged. ADR-108 is the behavior
contract; [the regression report](QA-REPORT-UNKNOWN-END.md) records development
review, boundary cases and the original 11-path upgrade qualification.

## Review

Developer review traced canonical end serialization versus derived query indexes,
recurrence identity, calendar exports and unknown-end feed fields. QA review
covers partial/blank input, known ends, all-day events, DST, midnight membership,
existing-event retention and saved editor layouts. Public visibility predicates
and bounded queries remain enforced. No guard, test or budget was weakened.

## Final candidate evidence

- Composer strict validation and QA passed: 791 tests / 3,264 assertions, lint,
  static analysis and a clean Composer audit.
- npm QA passed: 100 tooling tests, JavaScript/CSS lint and the high/critical
  audit gate. Five moderate findings remain in the local development-server
  dependency graph, as described in the regression report; the runtime package
  contains no npm installation or development server.
- Translation catalogue generation and consistency passed.
- Two consecutive package builds are byte-for-byte reproducible. The local and
  independently built CI ZIP share SHA-256
  `2016b44584de2fea309183f163e5c289b9b71ccf3741800e777233a362d3267c`.
- All eleven checksummed historical upgrades from 0.2.3 through 1.1.0 passed
  locally and in CI, including past/future equal-end fixtures and lifecycle
  retention/repair checks.
- All six unchanged performance budgets and 17 real-WordPress coverage cases
  passed locally and in CI on the prescribed 500-series/5,000-occurrence dataset.
  The local feed returned 100 items in 41,901 bytes, 21 queries and median
  157.44 ms; these are test-environment observations.
- [Hosted qualification](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/41107872664)
  passed all twelve jobs on `c81d13eab0a5193652452c2e749c1c99a57ce959`, including
  PHP 8.2–8.5, both WordPress smoke targets, 39 browser scenarios and strict
  official Plugin Check with no errors.
- The first PHP 8.2 job passed its 791 tests but failed when Packagist's advisory
  API timed out. Its unchanged rerun passed the full job, including the live
  audit. No network check was bypassed or converted to offline success.

No customer database or hosting configuration was modified. Real proprietary
Divi/Elementor host combinations were not independently requalified for this
patch; saved-layout retention and the supported browser/registration regressions
were tested. Existing integration interfaces are unchanged.

Both packaged local smoke targets, WordPress 6.9 and 7.1 / PHP 8.2, passed before publication.

## Publication

- Owner-approved [PR #26](https://github.com/MilanMiMeOnline/WP-Simple-Events/pull/26) merged as `ef878864143531342921c0e2d7e8de3cce166747`, whose source tree is identical to the qualified PR head.
- Tag `v1.1.1` points to that merge commit. [GitHub 1.1.1](https://github.com/MilanMiMeOnline/WP-Simple-Events/releases/tag/v1.1.1) was published at 18:48:25 UTC on 30 October 2026. Its public ZIP was downloaded and matches the qualified SHA-256.
- WordPress.org trunk and tag `1.1.1` were committed atomically in SVN revision `3775898`. Both staging trees matched all 384 qualified package files byte for byte before commit. The public WordPress.org API reports version 1.1.1.
- The public WordPress.org ZIP was downloaded and all 384 paths and file contents match the qualified GitHub package. WordPress.org repackages archive metadata; its ZIP SHA-256 is `d35182f1bdaa00abe70584883cc8e361db9d5952971c08b4e8facc92f30dfb22`. Initial download requests returned 404 while distribution caught up; the subsequent public download succeeded.
