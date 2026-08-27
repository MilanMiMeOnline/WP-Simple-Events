# QA evidence — 0.6.0 filter and color baseline

**Date:** 2026-08-27

**Scope:** FCR-0 specification freeze and FCR-1 taxonomy-title regression

## Confirmed baseline findings

The exploratory screenshots and the existing rendered markup establish three
independent usability defects:

1. event category and tag archives can expose WordPress' decorative `<span>`
   source as visible title text when a plugin-owned template escapes the complete
   Core archive title;
2. the native event archive has an Apply action but no direct way to remove all
   event filters;
3. category and tag choices use native multiple selects, which hide the
   modifier-key interaction from mouse and keyboard users and do not present
   active selections as removable values.

The calendar screenshots also establish a discoverability limitation: every
event uses the same component-level accent. Multi-category events therefore need
an explicit, deterministic color rule before category colors can be introduced.

## Frozen behaviour

- Existing GET parameter names, apply markers, shortcode attributes, block
  names, Elementor control IDs, Divi attribute paths and public host identities
  remain compatible.
- Clear removes visitor taxonomy choices and restores the configured period.
- Restore defaults is shown separately only when initial component presets exist
  and differ from the visitor selection.
- Event color precedence is explicit event color, explicit assigned colored
  category, one unambiguous colored category, then component fallback.
- Multiple differently colored categories never resolve by incidental term
  ordering.
- Colors are normalized hexadecimal presentation metadata and never arbitrary
  CSS or public-query eligibility data.

## Red-green regression evidence

- `EventArchiveTitleTest` initially failed because no plugin-owned title resolver
  existed. It now covers both taxonomies, markup stripping, special characters,
  mismatched queried objects and neutral fallback.
- `EventArchiveRendererTest` initially rendered the escaped `<span>` source. It
  now requires the translated plain-text heading while preserving the fixed
  taxonomy archive and omitting cross-archive filters.
- `EventArchiveControlsTest` initially failed because the native form had no
  clear action. It now requires the clean event archive URL without any `wpse_*`
  filter query values.
- The WordPress smoke contract now requires the exact category and tag headings
  in every classic, hybrid and block-theme shell and rejects escaped span source.

## Deferred implementation evidence

The multiple-select and ambiguous-color findings are intentionally retained as
acceptance tests for FCR-2 and FCR-5 respectively. Their production contracts do
not yet exist, so no permanently failing test is committed merely to represent
future code. The before-state is frozen by the exploratory screenshots and this
report; each implementation package must add its failing automated regression
before changing production behaviour.

## Review conclusion

FCR-0 has a complete normative contract and durable red-green evidence for the
two current defects that can be fixed independently. FCR-1 is migration-free,
does not change SEO document-title ownership, trusts no decorative HTML and
retains the active theme shell. Shared filter markup and colors remain separate
cohesive packages so their compatibility and accessibility can be reviewed
without hiding them inside the title hotfix.
