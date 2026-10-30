# MiMe 1.1.1 release qualification

**Date:** 30 October 2026
**Status:** Candidate; publication conditional on the release gates below.

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

## Review and remaining qualification

Developer review traced canonical end serialization versus derived query indexes,
recurrence identity, calendar exports and unknown-end feed fields. QA review
covers partial/blank input, known ends, all-day events, DST, midnight membership,
existing-event retention and saved editor layouts. Public visibility predicates
and bounded queries remain enforced. No guard, test or budget was weakened.

Final 1.1.1 qualification is in progress: Composer and npm QA, translations,
reproducible package, historical upgrades, performance, both packaged WordPress
smoke targets and hosted PHP/browser/Plugin Check jobs. Publication must wait for
the final results. The separate moderate development-server qs advisory noted in
the regression report remains outside this date fix; no dependency package
versions were changed (only root project version metadata).
