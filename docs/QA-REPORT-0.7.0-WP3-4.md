# QA report — 0.7.0 Add to Calendar editor and native adapters

**Status:** editor, native-adapter and provider interoperability checkpoint
qualified; release qualification remains open

**Reviewed:** 29 August 2026

## Scope

This checkpoint completes work packages 3, 4 and 5 of the bounded 0.7.0 Add to
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

- `composer qa`: WordPress coding standards, PHPStan over 320 files, 772 PHPUnit
  tests with 3,184 assertions and the Composer advisory audit;
- `npm run qa`: deterministic production builds, 60 tooling-contract tests,
  ESLint, Stylelint and npm audit with zero vulnerabilities;
- the complete packaged WordPress 6.9 and 7.1 smoke journeys in clean Playground
  installations,
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

The isolated WordPress 7.1 Playground intermittently stalled one unrelated
read-only page request for longer than its 30-second fetch boundary. The harness
now retries exactly once only for a real `TimeoutError` on GET/HEAD and never for
writes, HTTP failures or caller-supplied abort signals. The complete 7.1 journey
then passed; a sustained second timeout would still fail the gate. The official
Plugin Check remains the pinned CI release job against this exact staging tree.

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
found in the implemented checkpoint. Work packages 3, 4 and 5 are qualified.

This report deliberately does not qualify 0.7.0 for release. Work package 6 must
still run the complete supported-version, Plugin Check, reproducible-package,
documentation and publication gates. Google and Outlook interoperability was
verified up to each provider's authentication boundary; no test account was
available to persist the prefilled event. Their complete transferred compose
values remain visible in the destination URLs and are regression-tested.

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

The same Divi preview then exercised the external handoff with that explicit
multi-day event. Google received the complete title, UTC boundaries, IANA
timezone hints, description, canonical URL and comma-separated location before
redirecting to its public sign-in/marketing boundary. Outlook preserved the
complete subject, UTC boundaries, body and comma-separated location inside its
authenticated compose redirect. Both external actions used `_blank`,
`noopener noreferrer` and `no-referrer`; the local ICS action remained
same-origin without external-link attributes. This journey exposed that encoded
line breaks can be stripped by WordPress URL escaping and concatenate adjacent
fields. Provider descriptions now use a visible ` - ` separator and locations
use `, `, with a unit regression through the exact escaped renderer boundary and
WordPress smoke assertions for both destinations.

Apple Calendar accepted an ICS file generated directly by the production
`IcsCalendarBuilder`, identified it as one new event and displayed the normal
destination-calendar confirmation. The operation was cancelled before its final
OK action, so no calendar event was stored; the generated file and generator were
removed immediately afterwards.

The packaged browser matrix also exposed a history-restoration edge case outside
the new component: a browser may restore checkbox state independently while
revisiting a calendar URL. Calendar initialization now reapplies its namespaced
URL state before the first event request. The complete filter history journey and
all 30 packaged browser journeys pass with that production fix.
