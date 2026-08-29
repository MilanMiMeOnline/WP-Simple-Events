# QA report — 0.7.0 Add to Calendar editor and native adapters

**Status:** implementation checkpoint qualified; interoperability and release
qualification remain open

**Reviewed:** 29 August 2026

## Scope

This checkpoint completes work packages 3 and 4 of the bounded 0.7.0 Add to
Calendar roadmap. It adds one shared semantic action renderer and exposes it as:

- `[wpse_add_to_calendar]` shortcode;
- dynamic Gutenberg Add to Calendar block;
- Elementor Add to Calendar widget;
- native Divi 5 Add to Calendar module;
- an off-by-default native-template setting for a local `.ics` action.

All adapters use the public snapshot resolver and the strict export endpoint
introduced in work package 2. Local ICS is the only default provider. Google and
Outlook compose links require an explicit author choice. Builder templates keep
complete placement ownership and receive no globally injected action.

## Automated evidence

The implementation passed:

- `composer qa`: WordPress coding standards, PHPStan over 320 files, 771 PHPUnit
  tests with 3,153 assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 57 tooling-contract tests,
  ESLint, Stylelint and npm audit with zero vulnerabilities;
- the complete WordPress 6.9 smoke journey in a clean Playground installation,
  including registration, default-off settings, nonce-protected opt-in,
  same-origin ICS output, protected-event suppression and cleanup. GET, HEAD,
  404 and 405 responses all prove the exact no-store/no-cache, legacy expiry,
  `nosniff` and no-cookie contract;
- the complete packaged browser matrix on WordPress 7.1/PHP 8.2: 30/30
  Playwright journeys, including public Add to Calendar output, Gutenberg
  registration and preview, one real draft save/reload, exact attribute recovery
  and unconditional test-page deletion;
- a JavaScript-free three-provider dropdown at 390 px, including native keyboard
  opening, all three semantic actions and absence of horizontal overflow;
- translation catalogue regeneration and verification;
- `git diff --check`.

## Security and privacy review

- The local download URL is rebuilt from the WordPress home origin and strict
  query values. A filtered or externally hosted event permalink cannot redirect
  the export action to another origin.
- Every request is re-resolved against current publication, password,
  recurrence and exact-occurrence eligibility. A saved component cannot expose
  a draft, private, protected, cancelled, stale or whole-series snapshot.
- Provider choices are an enum allowlist. Labels are bounded plain text; color
  and spacing controls accept only strict normalized values and fixed CSS
  custom-property names.
- External provider links open with `noopener`, `noreferrer` and a no-referrer
  policy. No provider is contacted until a visitor deliberately follows its
  link; local ICS remains available without a third party.
- The feature adds no public write endpoint, visitor storage, cookie, telemetry,
  remote asset, secret or personal-data field.

## Accessibility and host review

- The single-provider form is a normal download link and the multi-provider
  form uses native `details`/`summary` disclosure plus a semantic list.
- Labels remain visible text, focus styling is inherited from the established
  component contract and no JavaScript is required to use the action.
- Gutenberg, Elementor and Divi map bounded settings to the same renderer and
  CSS variables. Missing host plugins remain inert.
- Divi preview context is accepted only for an event post and only inside its
  established authenticated preview route. It does not add a second public
  endpoint.
- The native setting is disabled by default and evaluated only after the plugin
  owns the single-event fallback. Elementor Theme Builder and the established
  Divi Theme Builder path retain full output ownership.

## Senior developer review

The editor integrations contain no duplicate query, event-resolution or
provider logic. One immutable snapshot feeds the local endpoint and every
provider link. Presentation controls translate only to bounded options and
component-scoped variables. The native setting is isolated behind one small
policy object and participates in explicit multisite-aware uninstall cleanup.

## Senior QA review and residual work

No correctness, security, privacy, accessibility or compatibility blocker was
found in the implemented checkpoint. Work packages 3 and 4 may proceed to host
qualification.

This report deliberately does not qualify 0.7.0 for release. Work package 5 must
still verify real Apple Calendar import and the Google and Outlook compose
handoffs. Work package 6 must then run the complete supported-version, Plugin
Check, reproducible-package and publication gates.

## Local exploratory evidence

The exact reproducible development package was installed over the local 0.6.0
copy on `simpleevents.local`. The test then enabled the native option through the
normal Settings API form and confirmed:

- one eligible multi-day event received one same-origin local-download action;
- a cancelled event received no action;
- a recurring-series root received no action;
- one exact occurrence received an action containing its immutable public key;
- the resulting link completed a browser media download;
- disabling and saving the option removed the action again.

The setting was restored to disabled and no event, page, term, template or
builder assignment was created by this local journey. Google/Outlook were not
opened and no third party received event data during this pass.

Elementor Free was then activated temporarily and its Add to Calendar widget was
placed on an existing local page. An explicit public event, ICS, Google, Outlook
and dropdown layout were configured, saved and recovered after a full editor
reload. The widget was removed and that cleanup was saved; Elementor was then
deactivated again. The host returned to its original active-plugin state and no
temporary widget remained.

With the exact package still installed and Divi 5 active, its native Add to
Calendar module was then placed on the existing local calendar page. A public
multi-day event, ICS, Google, Outlook, dropdown layout and custom action label
were configured. A full Visual Builder save and reload recovered the module,
event, all three providers and custom label. The module was removed through
Divi's native module action, the cleanup was saved and a second full reload
proved that no temporary module remained. Existing calendar and event-list
modules were not altered.

The packaged browser matrix also exposed a history-restoration edge case outside
the new component: a browser may restore checkbox state independently while
revisiting a calendar URL. Calendar initialization now reapplies its namespaced
URL state before the first event request. The complete filter history journey and
all 30 packaged browser journeys pass with that production fix.
