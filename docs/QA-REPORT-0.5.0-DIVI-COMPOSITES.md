# QA report — 0.5.0 Divi composites and Theme Builder

**Status:** qualified for public release

**Reviewed:** 27 August 2026

**Host exercised:** licensed Divi 5.11.1 on disposable `simpleevents.local`

## Scope

This increment completes the native Divi 5 palette and its first reusable
Theme Builder qualification:

- twelve atomic event-field modules;
- Event Details;
- Event List / Grid;
- Event Calendar;
- bounded authenticated live previews for the three query-backed composites;
- dynamic calendar initialization and shared editor presentation CSS;
- editor-only namespacing of independently fetched component IDs and references;
- race-hardened permanent event deletion plus bounded repair of unreachable
  occurrence-index rows discovered during cleanup verification;
- mounted-calendar responsive view switching across builder device previews;
- current Divi wrapper, decoration and global-preset metadata across all fifteen
  modules without renaming saved settings;
- production packaging without Divi theme code, credentials or dependencies.

Divi remains optional. The implementation delegates event presentation, public
eligibility, occurrence resolution, queries and escaping to existing shared
services.

## Real Visual Builder evidence

All fifteen modules were discoverable through the native Divi module interface.
Temporary ordinary pages proved:

- Event Details selected an explicit public event, updated visibility controls,
  persisted normalized attributes and matched the public page;
- Event List / Grid switched live from grid to list, filtered to one event
  category, persisted that taxonomy selection and rendered the same single public
  card;
- Event Calendar initialized as a real FullCalendar instance after dynamic REST
  insertion, changed to September 2026 with one category selection, persisted its
  values and initialized correctly on the public page;
- no plugin JavaScript error appeared in the builder or public browser logs;
- shared component CSS loaded in Divi's app window and public calendar assets
  remained under the established conditional frontend contract.

Every temporary page was permanently removed. Existing event 66 was inspected
afterward and its original content was unchanged.

A final no-save Visual Builder pass reopened the existing local composite lab on
Divi 5.11.1. The canvas, MiMe controls and dynamic previews loaded without a
plugin or host console error. An auto-draft created while entering that journey
was moved to trash and permanently deleted; the pre-existing trash count was
restored. No page or Theme Builder assignment was saved or changed.

Comparison with Divi 5.11.1's native module metadata then exposed a compatibility
gap in the MiMe wrapper declarations. The implementation now declares the
current native meta, advanced and decoration keys for all fifteen modules, keeps
the earlier `htmlAttributes` key for saved-layout compatibility and continues to
use Divi's native font preset group/style clipboard category. A test written
before the fix failed on the missing keys and now enforces the full shared shape.

The final no-save interaction pass used Divi's stable device and preset-manager
classes after generic icon clicks had proved unreliable. Desktop, tablet and
phone each became the active host state; the phone canvas reported 484 pixels at
100%. The global preset manager opened, listed every MiMe module and expanded
Event Calendar to its native `Event Calendar Preset 1`. No setting, preset or
layout was changed or saved.

## Real protected-source and role evidence

A final local security pass created one disposable draft, one private event and
one published password-protected event from an existing valid public fixture.
Divi's real editor bootstrap exposed only the seven eligible public,
password-free events in every explicit-source selector; none of the three
protected fixture IDs was present.

The registered preview route was then exercised through WordPress' real REST
dispatcher, with no nonce, password or request payload logged:

- administrator and editor requests for the public fixture returned `200` and a
  non-empty escaped Event Details preview;
- the same users received `200` with an intentionally empty preview for draft,
  private and password-protected explicit sources;
- a subscriber received `403` for the active editor document;
- an anonymous request received `401`.

Anonymous HTTP checks independently confirmed that draft and private event routes
returned `404`, the public calendar feed and native archive omitted all three
fixtures, and the password-protected route exposed only WordPress' title and
password form. Its copied date, venue and plugin event details were absent.

Both temporary role accounts were deleted by the test harness even after the
first invalid-payload probe. The three event fixtures were then permanently
deleted and a post-run inventory confirmed exactly the original seven published
local QA events and no `wpse_security_*` users. The Local/WP-CLI PHP 8.5 messages
were upstream deprecation and duplicate-bootstrap warnings; the supported plugin
runtime matrix remains PHP 8.2 and was unaffected.

## Real editor resilience evidence

A separate temporary two-column page placed one Event List / Grid module beside
an Event Calendar. Duplicating the calendar, undoing and redoing that operation,
and copying/pasting it produced three simultaneously ready calendar instances.
Every Divi module identifier was distinct and the resulting document contained
no duplicate HTML ID. Saving and fully reloading the page preserved the list and
all three calendars.

Changing the mounted builder canvas from desktop to mobile exposed one real
defect: the calendar repaired its dimensions but retained the desktop month view.
The calendar lifecycle now observes the same 599-pixel responsive breakpoint used
for initial rendering, changes to the configured mobile/desktop view only when
that breakpoint is crossed and removes the listener during instance cleanup. A
new packaged Playwright regression proves desktop month → mobile list → desktop
month while the same instance remains healthy. The manual same-version reinstall
continued serving the already cached script, so it is recorded as a cache-limited
manual retest rather than counted as evidence for the fix.

Finally, Twenty Twenty-Five temporarily replaced Divi while the saved layout
remained in the database. Both the layout page and native event archive returned
HTTP 200 and their HTML contained no fatal PHP error. Divi was immediately
restored and confirmed active. Page 150 and the three auto-drafts created during
this pass were permanently deleted; no test page remains.

## Real Theme Builder and occurrence evidence

A temporary **All Events** custom body was created in Divi Theme Builder with
the native Event Title module and an empty source. The generic layout editor
showed the safe no-context placeholder. On public event routes the same saved
module resolved the request context:

- the one-off **Nu-Metal Night** route rendered its canonical event title inside
  the Divi header and footer shell;
- a three-date weekly test series rendered its exact second occurrence leaf;
- after applying an occurrence-only title override, that leaf rendered only the
  overridden title and did not fall back to the canonical series title;
- the public occurrence REST route and sitemap used the same exact occurrence
  identity.

The temporary Theme Builder body and assignment were deleted and saved. The
temporary recurring post and its public occurrence leaves were permanently
removed. The pre-existing event route again rendered its native date and location,
and the sitemap contained neither the temporary slug nor an occurrence provider.

A post-run database inspection initially found seven unreachable projection rows
for the deleted temporary event ID. They could not pass the repository's
parent-post and active-generation joins and exposed no public route, but retained
derived state was still a cleanup defect. Permanent deletion now has a second
post-delete table guard, projection replacement rechecks that the event still
exists after insertion, and the bounded old-generation worker also repairs orphan
rows. Running that worker removed the retained test state and verified zero
orphan rows. A trash-to-permanent-delete regression retained its one projection
while restorable and removed it on permanent deletion (`1 → 1 → 0`).

## Senior developer review

- The preview route is read-only, uses an explicit module allowlist and bounded
  attribute object, requires an existing document and its exact `edit_post`
  capability, and relies on WordPress REST-cookie nonce validation.
- Explicit sources remain public and password-free through the shared presentation
  boundary; the client never reads protected event metadata directly.
- The localized current-event snapshot and editor document ID require the exact
  current document plus its server-side `edit_post` capability. Divi editor state
  alone never authorizes draft, private or password-protected presentation data.
- Module attributes cross a Divi-specific allowlist before shared renderers.
- FullCalendar is bundled only for the Visual Builder, where preview HTML is
  inserted after normal WordPress enqueue decisions; ordinary public output keeps
  the existing conditional calendar package.
- Separate REST responses can each start their server-side component sequence at
  one. The editor therefore rewrites safe HTML IDs plus matching `for`, ARIA and
  fragment references with the stable Divi instance ID before insertion.
- Divi and React packages stay external. No licensed theme file, key or updater
  data is present in source or release staging.

## Senior QA review

The ordinary-page composite and current-context Theme Builder paths pass live
edit, save, public rendering and cleanup. Automated contracts cover host absence,
compatibility detection, module metadata, all fifteen generated modules,
attribute bounds, public-source rules, exact occurrence versus explicit series
resolution, preview authorization and response namespacing.

Current automated evidence for this implementation slice:

- `composer validate --strict`: passed;
- `composer qa`: passed — coding standards, PHPStan across 280 files, 677
  PHPUnit tests with 2,536 assertions and Composer security audit;
- `npm run qa`: passed — all production builds, 53 tooling tests, JavaScript/CSS
  lint and npm audit with zero vulnerabilities;
- translation catalogue regeneration and freshness check: passed;
- final `0.5.0` release verification: passed twice with identical SHA-256
  `4b489c0c499cc86f4ba3ba21f821930be2a37a72d7415124820b042d3c69ecd0`;
- packaged smoke journey: passed on WordPress 6.9/PHP 8.2 and WordPress
  7.1/PHP 8.2;
- packaged Playwright suite: 21/21 passed, including multiple calendar instances,
  delayed/failed feeds, hidden-container repair, Elementor lifecycle, Gutenberg
  blocks, recurrence editing and mounted responsive view switching;
- the temporary E2E server was confirmed stopped after completion.

All of the above repository, archive, WordPress 6.9/7.1 and 21-journey browser
gates were repeated after synchronizing the public plugin, npm and WordPress.org
metadata to `0.5.0`; no result relies solely on a pre-version development build.

One non-terminal browser attempt lost its Playground process and produced an
initial login timeout plus a still-loading occurrence selector. An isolated retry
then confirmed the host was refusing all connections. Re-running the complete
suite in a persistent terminal kept the host healthy and passed all 21 journeys,
including both previously interrupted cases. No product code or timeout was
changed to obtain the passing result.

Official strict Plugin Check is deliberately a pinned GitHub Actions release gate;
this checkout has no local replacement script. GitHub Actions run
[`33057715576`](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/33057715576)
passed all ten jobs for release commit `3e688ab`: strict Plugin Check, the release
archive, translations, JavaScript/CSS, PHP 8.2–8.5, WordPress 6.9 and 7.1 on PHP
8.2, and all 21 browser journeys. The downloaded CI archive is byte-identical to
the locally qualified archive with SHA-256
`4b489c0c499cc86f4ba3ba21f821930be2a37a72d7415124820b042d3c69ecd0`.

Real-host Divi qualification and every mandatory automated release gate are
complete. No known security, privacy, compatibility or WordPress.org compliance
blocker remains for the 0.5.0 publication.

Divi 5.11.1 is now the explicit supported floor and current-tested package. The
version boundary rejects earlier Divi 5 builds and all unqualified Divi 6 builds
without affecting core plugin boot. Lowering that floor requires the full real
host matrix against the additional licensed package.

The earlier recurrence/archive observation is now reproduced and closed. A new
browser assertion initially failed because a future series projected from its
anchor instead of from today's production boundary. That made readiness fail
closed and reduced both the public feed and native archive to one canonical card.
ADR-081 separates the signed preview window from the 540-day production window.
The same test now requires three feed items, three archive cards and three unique
occurrence URLs immediately after applying the three-date series.

The orphan-repair defect is fixed and its retained local rows are removed. The
final Divi matrix still repeats permanent deletion through the real browser UI so
that this lifecycle regression remains covered at both API and user-flow levels.

Divi 5 may therefore be advertised as qualified optional public support from
version 0.5.0 onward.
