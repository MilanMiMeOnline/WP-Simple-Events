# Install, upgrade and lifecycle contract

**Status:** normative for the 0.9.0 release-candidate cycle\
**Last reviewed:** 30 August 2026

This contract defines which historical packages must upgrade to the current
MiMe Simple Events and Calendar package and what an upgrade is allowed to change.
It complements the canonical [data](DATA-CONTRACT.md),
[recurrence](RECURRENCE-CONTRACT.md), [maintenance](MAINTENANCE-CONTRACT.md) and
[uninstall](UNINSTALL-CONTRACT.md) contracts.

## Supported paths

### Automatic in-place upgrades

Every tagged GitHub release from **0.2.3 onward** uses the canonical
`mime-simple-events-calendar` package directory and bootstrap file. Each one is
a supported in-place source for the current release. The WordPress.org update
history starts at **0.2.4**, so WordPress.org users have the same contract for
0.2.4 and every later directory tag.

The qualification matrix exercises every published package separately:

- 0.2.3, 0.2.4, 0.2.5 and 0.3.0, which store schema version `1.0.0` and have no
  occurrence projection table;
- 0.4.0, 0.5.0, 0.6.0 and 0.7.0, which store schema version `2.1.0` and share the
  current occurrence-table shape.

No release may be skipped merely because it belongs to the same storage family.
The exact published ZIP and its pinned SHA-256 value are part of the test input.

### Manual handoff from 0.2.1 or 0.2.2

The two pre-WordPress.org packages used the old `simple-events-by-mime` directory
and bootstrap identity. WordPress cannot update that package in place to the
approved canonical slug. The supported handoff is:

1. back up the site;
2. deactivate the old plugin;
3. remove its plugin files without enabling its destructive data-deletion option;
4. install and activate the current `mime-simple-events-calendar` package.

The stable `wpse_event`, taxonomy, metadata, option, capability, shortcode,
Gutenberg and Elementor identities make the retained data readable by the
current plugin. The old and new packages must never be active together.

Downgrades, direct upgrades from untagged development snapshots and simultaneous
activation of renamed packages are not supported.

## Upgrade invariants

An upgrade may rebuild internal indexes and rewrite rules, but it must preserve:

- event IDs, post status, title, content, excerpt, featured image and password;
- canonical date, timezone, venue, address, status and external-action metadata;
- event category/tag assignments and valid category or event color intent;
- the complete recurrence aggregate, immutable series UID, exception identity
  and effective occurrence meaning;
- plugin settings and the administrator's uninstall-retention choice;
- saved Gutenberg content, Elementor data and Divi content using frozen public
  identities;
- unrelated posts, terms, media, options, roles and capabilities.

Derived UTC indexes, occurrence rows, generation numbers, coverage metadata,
migration markers, maintenance cursors and rewrite markers may change. They must
finish in a state that is complete, bounded and consistent with canonical data.
An installation failure must not claim the new schema version or expose partial
recurrence results.

## Lifecycle invariants

- A clean activation registers content, creates the current occurrence table,
  grants the documented event capabilities and stores the current schema only
  after table creation succeeds.
- Repeating activation or upgrade checks are idempotent and do not restart a
  completed migration for an unchanged schema.
- Deactivation removes event routes and all plugin-owned scheduled hooks plus
  their disposable renewal cursor. It retains canonical content, terms,
  settings, capabilities and the occurrence table.
- Reactivation recognizes retained data, restores required capabilities and
  schedules current bounded maintenance without duplicating jobs.
- Uninstall always removes plugin-owned scheduled callbacks because deleted code
  cannot execute them. All persistent data remains by default.
- Explicit destructive uninstall removes only the allowlisted plugin-owned data
  in [UNINSTALL-CONTRACT.md](UNINSTALL-CONTRACT.md); shared media and unrelated
  WordPress data remain.
- A missing occurrence table is repaired even when the stored schema option is
  already current. Successful recreation invalidates only derived projection
  state and schedules bounded rebuilding; a failed recreation does not claim a
  healthy schema or discard canonical data.

## Qualification evidence

The automated historical matrix must run against an isolated WordPress 6.9 or
newer site on PHP 8.3 so the runtime satisfies both the old package requirements
and the current PHP 8.2 floor. It must:

1. activate each checksummed public baseline package;
2. store data representative of the features available in that release;
3. replace it with the exact current release staging tree;
4. trigger normal WordPress boot and bounded migration workers;
5. compare canonical data and saved layouts before and after;
6. verify the current schema, occurrence-table columns, occurrence readiness,
   capabilities, rewrite marker completion and non-duplicated scheduled hooks;
7. separately prove clean install, deactivation/reactivation, retained uninstall
   and explicit destructive uninstall.

Unit tests remain responsible for failure injection that a real database cannot
reliably reproduce, including table-creation failure, interrupted content or term
cleanup, stale revision writes and projection-store failure.
