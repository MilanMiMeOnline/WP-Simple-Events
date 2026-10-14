# QA report — 1.0.0 user rehearsal and release qualification

**Status:** qualification in progress; not released

**Reviewed:** 14 October 2026

## Beginner-user rehearsal

Before changing product code, a separate disposable WordPress Playground site
was started on port 8890 with PHP 8.2, the WordPress 7.1 line (admin reports
7.1.3), Twenty Twenty-Five, and no optional builder or commerce integration.
The initial test mounted the source tree; it was not a release-ZIP installation.
The editor was exercised in Chrome, with public presentation also inspected in
the in-app browser. This site used English WordPress labels and Dutch sample
event content. Playground automatically signs in, so these manual page views
are not evidence of anonymous-access isolation; automated security tests cover
that boundary separately.

| Journey | Observation |
| --- | --- |
| Find Events and create a first event | Native menu is clear; date fields were hidden under Gutenberg's generic Meta Boxes disclosure. |
| Publish without dates | Correctly blocked with a missing-start message. |
| End before start | Correctly blocked; no invalid event was published. |
| Publish a timed event and reopen it | Title, content, venue and 19:00–22:00 date range persisted and rendered correctly. |
| Make the event weekly, three times | Preview correctly showed two additions; applying preserved the three-event schedule. |
| Add Event Calendar to a normal page | Block inserted and published successfully. Blank initial date opened the current month. |
| Navigate to the event month | Initially only the root date appeared while occurrence migration stayed Building. |
| Create an all-day event with no end | Published successfully and normalized to the same day; public output omitted time. |
| Add a category and cancel the all-day event | Both persisted and were immediately visible on the event page. |
| Revisit calendar after the migration fix | Settings reported Healthy; all three weekly dates and the cancelled all-day event appeared. |
| Preview a fourth weekly event after the formatting fix | Displayed December 11, 2026 7:00 pm, retaining the original wall time. The preview was not applied. |
| Save first dates in a blank draft, then save a venue with a weekly rule selected | Controls appeared without reloading; the pending weekly choice survived the venue save. |

The in-app browser did not render Gutenberg's editor canvas; the same editor
worked in Chrome. This was isolated as a test-browser limitation, not treated
as a plugin defect. Playground's delayed background execution exposed an
unnecessary dependency on cron even when migration had no work left.

## Changes and risk review

- The migration scheduler checks for at most one remaining candidate using the
  exact same metadata and status predicates as its worker. With no work, it
  marks migration complete and clears a stale queued worker. It performs no
  projection during the visitor request. Schema, table, dirty-index and public
  coverage checks remain in place; existing candidates still use bounded cron
  batches. Two regression tests failed before the fix and passed afterward.
- Editor guidance explains where event dates live and distinguishes them from
  the WordPress publication date. Field and calendar help avoids implementation
  jargon and explains the current-month default.
- One-off event saves reload the recurrence context so the first valid date save
  unlocks the controls and later date changes supply the current anchor. Pending
  recurrence choices are retained; previous impact previews are invalidated.
  Controls are hidden while saving and reloading so a new rule cannot be based
  on the previous date. The regression holds a save request open to verify this
  boundary, then checks the next weekly date after shifting the start date.
  Existing recurring-series edit forms are not reset by ordinary post saves.
- Recurrence impact dates use WordPress's localized date/time formats. A UTC
  formatting carrier preserves canonical wall-clock values rather than shifting
  them to the browser timezone; all-day values omit hours. Tests cover midnight,
  the DST-change date, all-day values and malformed values.

Senior developer review: the migration shortcut shares its selection predicate
with the existing processor, does not alter canonical event data, and introduces
no new route, permission, dependency or persistent cache. Formatting changes
affect labels only, not recurrence identifiers or submitted mutations.

Senior QA review: the manual journeys exposed the missing repeated dates before
the change and verified them after it. The release still needs all exact-package
and hosted gates below; passing unit tests alone does not qualify publication.

## Qualification evidence

| Gate | Current result |
| --- | --- |
| Composer strict validation | Pass |
| PHP coding standards and static analysis | Pass |
| PHP unit suite | Pass, 779 tests / 3,210 assertions |
| Composer advisory audit | Pass, zero advisories |
| Production JS builds, tooling tests, JS/CSS lint | Pass, 94 tooling tests |
| npm advisory audit | Pass, zero vulnerabilities |
| Browser matrix | Initial 35/35 pass; final 36-test candidate run pending |
| Fresh-install and supported WordPress smoke matrix | Pending |
| Historical upgrades and lifecycle | Pending |
| Exact release package and reproducibility | Pending |
| Hosted CI and official Plugin Check | Pending |

Before changes, local performance passed the existing budgets: the 50-card list
used 18 queries with a 3,695.513 ms median and the 100-item feed used 17 queries
with a 3,593.804 ms median. The prior main-branch CI list result exceeded its
7,500 ms limit, so hosted performance remains a release gate. No budget was
increased and no failed check was suppressed.

### Profile-guided performance correction

The isolated old coverage probe measured 3,512.673, 3,503.376 and 3,519.025 ms.
Splitting its equivalent predicate into five small WordPress checks removed the
large join cost. Seventeen real-database cases pass against both the original
combined predicate and the optimized probe, with explicit expected results.
They cover absent active generations, dirty state, missing/future/expired
coverage, zero/negative generation input, one-off and empty-start cases, drafts,
private posts and password protection. The fixture uses a valid canonical
recurrence definition and verifies each requested publication state.

| Scenario | Median after optimization | Queries | Existing budget |
| --- | ---: | ---: | ---: |
| Coverage probe | 32.488 ms | 6 | New 750 ms isolated gate |
| Filtered occurrence window | 170.460 ms | 2 | 750 ms |
| 50-card event list | 241.726 ms | 22 | 7,500 ms |
| 100-item calendar feed | 170.544 ms | 21 | 7,500 ms |
| Builder options | 27.007 ms | 7 | 1,000 ms |
| 551-slot recurrence expansion | 24.599 ms | 0 | 250 ms |

Payloads and result counts are unchanged: 55,907 bytes for the list and 41,901
bytes for the feed. No direct SQL or persistent cache was introduced. These
local Playground measurements are regression evidence, not hosting guarantees.

## Publication boundary

The current public version remains 0.9.0 until the exact 1.0.0 package passes
every required gate and publication is verified. FullCalendar 7 and AI/MCP
integration remain later roadmap work. Test content is confined to disposable
local sites and must be removed with those environments after qualification.

## Qualification corrections

The first hosted smoke runs rejected the new clean-install assertion because
the rendered status wraps `Healthy` in a strong element. The assertion now
checks that actual markup and the same complete health explanation; it still
fails for Building, Unavailable and Repair needed states. Runtime readiness was
not relaxed. The first hosted browser run also exposed a save race: selecting
a rule before the updated anchor loaded could include the previous weekday.
Clearing the old context during saving removes that interaction window.
The browser regression now waits for the actual context response instead of
assuming a save has completed when an old control remains visible.
