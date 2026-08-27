# Exploratory QA report — 0.5.0 editors, recurrence and public UX

**Status:** findings resolved and locally qualified; official release CI pending

**Reviewed:** 27 August 2026

**Environment:** WordPress 7.1, MiMe Simple Events and Calendar 0.5.0,
Divi 5.11.1 and Elementor 4.2.2 on disposable `simpleevents.local`

## Purpose and method

This pass deliberately looked beyond release-gate assertions at discoverability,
editor confidence, practical styling, recurrence mental models and the complete
journey from an admin event to public list, calendar and occurrence pages.

The pass used existing local QA content. It temporarily activated Elementor for
its editor checks and disabled it again before handoff. Divi remained the active
theme. No production site, Theme Builder assignment or permanent template was
changed. Event 75 was converted from an existing one-off QA fixture into a
ten-occurrence weekly series so the maintainer can immediately continue testing
the recurrence workflow locally. Its temporary cancellation was restored.

## Executive result

The core visitor and recurrence journeys are coherent and stable. Public
occurrence URLs, bounded filtering, responsive calendar views, theme shell,
timezone presentation and protected external links behaved correctly.

Fresh-environment reproduction closed the two initial P1 editor observations as
local editor-state artefacts rather than plugin defects. The Gutenberg inspector
loaded all atomic settings on a clean WordPress 7.1 document, and Divi's
activation URL initialized the Visual Builder on a fresh event. Both paths now
have stronger regression evidence where the host permits it.

Every confirmed P2 finding in this report has a code change, focused regression
coverage and a documented product contract. No security or privacy finding was
identified during the round.

## Findings

### EX-001 — Closed — Atomic Gutenberg inspector observation was environmental

Selecting an existing atomic event block, including **Event Date & Time**, shows
the block name and description in the WordPress 7.1 sidebar but none of the
registered event controls. **Event settings**, **Event source**, **Show label**
and the field-specific options are absent. The frontend output still renders.

The same result occurred with Divi and with Twenty Twenty-Five, so it is not a
theme conflict. The compiled `event-fields-editor` asset and localized event data
were both present. Composite Event List, Calendar and Details controls remain
available and are covered by the browser suite.

The observation could not be reproduced on a fresh supported editor document.
All twelve atomic blocks registered, an Event Date & Time block exposed **Event
source**, **Show label** and **Label text**, and its server preview rendered. The
original local document had an unmounted editor canvas and is not evidence of a
plugin registration failure.

**Resolution:** the browser regression now resets a document to all twelve atomic
blocks, selects the date/time block and asserts its inspector controls. No
production workaround was added for a host state the plugin does not own.

### EX-002 — Closed — Fresh Divi activation observation was environmental

An event already marked as Divi content opens correctly with
`?et_fb=1&PageSpeed=off`. For events that have never used Divi, the Events list
produces Divi's activation-nonce URL. Following that **Edit With Divi** link does
not start the Visual Builder; it redirects to an unrelated recently visited
WordPress admin screen.

The result was reproduced after Elementor was fully deactivated. It is therefore
not an Elementor conflict. The problem affects the transition into Divi, not the
rendering of already-enabled Divi documents or the MiMe module palette itself.

The direct activation link was repeated on an untouched event with Elementor
inactive. Divi redirected to `?et_fb=1&PageSpeed=off` and initialized its builder
as expected. The temporary event mutation was restored exactly afterward.

**Resolution:** no plugin redirect or activation workaround was added. The Divi
supported-post-type and module contracts remain automated; fresh activation stays
part of the licensed-host manual qualification because the package cannot be
distributed in the repository.

### EX-003 — Resolved — The Events list identifies recurring series

After applying a ten-occurrence weekly schedule, the admin list row still looks
like a one-off event. It shows only the canonical start and end and offers no
recurrence badge, cadence, occurrence count or direct series affordance.

**Impact:** editors cannot scan which rows are series or distinguish the event
date shown in the table from the complete schedule.

**Resolution:** a sortable-neutral **Repeats** column now distinguishes one-off
events, selected dates, generated cadence and end condition, split schedule
segments and unavailable/corrupt recurrence state. It reads only the protected
canonical aggregate and never expands occurrence rows.

### EX-004 — Resolved — Never-ending schedules explain their projection horizon

Choosing **Never** correctly describes a no-end-date rule, but previewing it
silently shows a finite 18-month projection (77 added occurrences in the tested
weekly schedule). The bounded horizon is only explained elsewhere in the
occurrence search workflow.

**Impact:** an editor can interpret the preview as an accidental end date or
believe only the displayed occurrences will exist.

**Resolution:** both complete-series and this-and-following editors explain that
the preview is limited to the next 540 days while the rule has no end and the
public rolling window renews automatically. The help disappears for count- or
date-bounded schedules.

### EX-005 — Resolved — One-off mode hides recurrence-only end controls

When **Does not repeat** is selected, the previous **Number of events: 10** field
remains visible even though the UI is asking which single occurrence should be
retained.

**Impact:** the stale value suggests that it may still influence the destructive
schedule change.

**Resolution:** **Ends**, **Last event date** and **Number of events** render only
for generated recurring rules. One-off and explicit-date modes cannot display a
stale termination control.

### EX-006 — Resolved — Multi-day list segments use explicit continuation language

A three-day timed event renders in calendar list view as an opening segment
ending at `12:00 am`, a middle segment labelled `all-day`, and a closing segment
starting at `12:00 am`. The geometry is technically correct for segmented
FullCalendar data, but the wording can make the middle day look like an actual
all-day event.

**Resolution:** list-view segments now use localized **Starts at**, **Continues**
and **Ends at** language. Genuine all-day events remain unchanged. The public
feed URL was also made same-origin relative, preserving subdirectory installs
while avoiding proxy or host-alias CORS failures.

### EX-007 — Resolved — Elementor typography controls identify their target

The Event List / Grid style panel exposes two consecutive controls both labelled
**Typography**. Their targets are not clear until the editor experiments with
them.

**Resolution:** controls now use target-specific names including **Event title
typography**, **Button and pagination typography**, **Event details typography**
and **Field label typography**. Saved control identifiers and selectors are
unchanged.

### EX-008 — Resolved — Elementor source and taxonomy selectors are explicit

Several Elementor select controls have useful visible text but weak accessible
names in the editor DOM. The Event Details preview-event combobox is effectively
unlabelled, and some Select2 event/category/tag controls announce their selected
value instead of the purpose of the field.

**Impact:** screen-reader and voice-control users may not know which source or
filter they are changing.

**Resolution:** event selectors are consistently labelled **Event source** with
**Current event (automatic)** as the empty context. Taxonomy selectors use **All
categories** and **All tags** placeholders. Control metadata has focused unit
coverage; complete Select2 host announcements remain part of manual builder
accessibility qualification.

### EX-009 — Resolved — Native occurrence pages expose bounded series context

An exact occurrence leaf shows the right occurrence, override and canonical URL,
but does not tell a visitor that it belongs to a repeating series. There is no
previous/next occurrence or “all dates” route from the single page.

**Resolution:** the native occurrence fallback now identifies a repeating event,
links to its canonical series and exposes bounded previous/next dates when they
exist. Two permission-aware `LIMIT 1` projection reads repeat parent visibility
and active-generation guards; recurrence is never expanded during the visitor
request. Elementor Theme Builder keeps full ownership when it handles the single
location.

### EX-010 — Observation — WordPress did not immediately offer 0.5.0

The local site initially ran 0.4.0 and did not receive a MiMe update offer after
a manual WordPress update check, although the public 0.5.0 package was available.
The exact release zip was installed manually for this pass. This is provisionally
classified as WordPress.org propagation/cache behaviour, not a plugin defect.

## Positive evidence

- Event settings are concise and expose timezone, structured data, retention and
  occurrence-index health without unnecessary service dependencies.
- The recurrence editor clearly separates **all occurrences**, **only this
  occurrence** and **this and following**, previews destructive impact before
  applying, and successfully restores a cancelled occurrence.
- The archive and calendar use stable exact occurrence URLs and retain period,
  category, tag, month and view state across navigation.
- Multiple list/calendar instances keep independent pagination and request
  namespaces.
- Desktop and mobile calendar views switch as configured without clipping long
  cards.
- Theme header and footer are preserved on archive, event and occurrence routes.
- Location and external action links use a new tab with
  `noopener noreferrer`.
- Elementor exposes the complete fifteen-widget palette and substantial native
  card, calendar, pagination and responsive styling controls.
- Empty explicit-source widgets provide an understandable editor-only state.
- Public event, archive, filter and occurrence checks did not expose draft,
  private or password-protected data.

## Automated evidence and its limits

The completed implementation pass produced the following local evidence:

- strict Composer validation, coding standards and PHPStan passed;
- **682 PHPUnit tests with 2,570 assertions** passed on PHP 8.5;
- **54 tooling tests** passed, including recurrence-editor and release contracts;
- JavaScript and CSS linting passed;
- Composer security advisories and npm's high/critical audit reported no known
  vulnerabilities;
- the packaged WordPress 7.1 Playwright suite passed **22/22 tests** in 3.5
  minutes, including the strengthened atomic inspector, recurrence and native
  occurrence-navigation assertions;
- the complete packaged smoke journey passed on minimum WordPress 6.9;
- the translation catalogue is current;
- two consecutive release builds were byte-for-byte reproducible with SHA-256
  `952d36a20c1b304c836e69fc7226b8f9bff1b8421e8f5828b31a3c8c3986b1ab`.

The green result is evidence for the asserted contracts, not a claim that host
builders can be exhaustively automated. Licensed Divi activation and assistive
technology interaction with host-owned Select2 controls remain bounded manual
checks. Official strict Plugin Check intentionally runs in GitHub Actions against
the final commit rather than through a divergent local script.

## Consolidation result

1. Initial observations were preserved until they were reproduced or closed.
2. EX-001 and EX-002 were closed with fresh-environment evidence and without
   speculative production workarounds.
3. EX-003 through EX-009 were implemented as small shared-service or
   presentation changes with focused regressions.
4. Product, admin, public-query, recurrence and decision contracts were updated.
5. Senior developer and QA review found no unresolved blocker in this worktree.
6. Versioning, commit, CI Plugin Check and publication remain a separate explicit
   release decision.

## Residual risk

This was an exploratory compatibility and UX pass, not a replacement security
audit. No security or privacy regression was observed. Keyboard-only traversal
of every Divi/Elementor popover, non-admin role editing and the full PHP 8.2–8.5
matrix were not repeated manually. The final release commit must still pass the
repository's GitHub matrix and official strict Plugin Check before publication.
