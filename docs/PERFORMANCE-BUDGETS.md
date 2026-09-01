# Performance budgets

**Status:** normative release-candidate contract

**Applies from:** 0.9.0 RC5

**Last reviewed:** 1 September 2026

This contract protects the bounded public read paths of MiMe Simple Events and
Calendar against accidental query, payload and execution-time regressions. It is
not a marketing benchmark and does not promise identical response times on every
host. WordPress hosting, themes, other plugins, object caches and network latency
remain outside the plugin's control.

## Reference dataset

The automated performance environment starts from an empty supported WordPress
installation and installs the generated release package plus one test-only
fixture plugin. The fixture creates:

- 500 published, password-free event series;
- 400 one-off events spread across one calendar year;
- 100 recurring-series representatives with 50 active projected occurrences
  each, for 5,000 recurring occurrence rows;
- 20 event categories and 40 event tags with deterministic assignments; and
- enough matches to fill every public page at its production limit.

The direct occurrence inserts belong only to the disposable fixture. They model
valid derived projection rows and never bypass the production write path in a
shipped build. No fixture, page or event is created on a developer or production
site.

## Acceptance budgets

Each scenario runs five times in fresh HTTP requests after one warm-up request.
The median PHP execution time is compared with a deliberately generous hard
ceiling; query count, result count and payload size are deterministic hard gates.

| Scenario | Surface represented | Result budget | Query budget | Payload budget | Median PHP ceiling |
|---|---|---:|---:|---:|---:|
| Filtered occurrence window | occurrence repository and taxonomy predicates | exactly 100 rows from 5,000 matches | 2 | n/a | 750 ms |
| Event list with visitor filters | shortcodes, blocks, builders and archive collection rendering | 50 rendered cards | 24 | 1 MiB HTML | 7,500 ms |
| Calendar REST feed | interactive calendars in every editor host | 100 JSON items | 24 | 256 KiB JSON | 7,500 ms |
| Builder event options | Elementor and Divi event selectors | 50 options over 16 repeated reads | 10 | n/a | 1,000 ms |
| Maximum supported recurrence expansion | recurrence generation and projection planning | 551 daily slots across 550 days | 0 | n/a | 250 ms |

The repository scenario intentionally has the strictest query budget: its public
page contract is one rows statement plus one exact count statement. Rendering
budgets include WordPress term, post-meta and presentation preparation, so their
query ceilings are higher while still rejecting per-result growth. Their wall-time
ceilings include the substantial overhead of the CI-safe WebAssembly Playground
runtime and are not representative hosting targets. Output sizes are checked
before JSON transport wrapping.

## Interpretation

- A hard-budget failure blocks merge and release. Do not raise a budget merely to
  make a failure pass; first identify the changed query plan or output.
- Wall time is a regression backstop, not a speed claim. Query count, bounded
  result count and payload size carry more diagnostic weight across CI hardware.
- A performance optimization must preserve publication, permission, recurrence,
  timezone and presentation contracts. Caching is not added without invalidation
  and privacy analysis.
- The suite records observed medians and maxima so a future change can justify a
  tighter budget with evidence.

## Running the qualification

From the repository root:

```sh
npm run test:performance
```

The command builds the production release package, starts a clean isolated
WordPress Playground, seeds the bounded dataset, runs every scenario and always
destroys the environment afterward. `WPSE_PERFORMANCE_CORE` may select another
supported WordPress checkout for diagnosis; the hosted release gate uses the
current supported WordPress version with PHP 8.2.

## Review triggers

Re-run and explicitly review these budgets when changing occurrence indexes,
public query predicates, filters, list/calendar presentation, builder option
providers, recurrence bounds, supported WordPress/PHP floors or FullCalendar's
data contract. Replacing the reference dataset or increasing a hard ceiling
requires a decision record and before/after evidence.
