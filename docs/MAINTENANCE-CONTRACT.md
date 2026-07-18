# Event maintenance contract

## Access and request boundary

Maintenance lives under **Events → Settings → Maintenance** and is available only to users with `manage_options`. Each tool submits to its own authenticated `admin-post.php` action through POST and carries a separate WordPress nonce. Nonces verify intent; the capability check independently verifies authorization.

No anonymous `admin_post_nopriv` handler exists. Feedback redirects only to the local event settings page and contains aggregate counters, never event titles, metadata values, user data or request payloads.

## Repair event capabilities

This action idempotently restores the documented event primitive capabilities to administrator and editor. It does not reset a role, remove third-party permissions, grant event rights to WooCommerce roles or change individual user capabilities.

## Rebuild event date indexes

The repair source of truth is:

- `_wpse_start_local`;
- `_wpse_end_local`;
- `_wpse_all_day`;
- `_wpse_timezone`;
- the WordPress publication status.

Stored metadata is untrusted. Canonical dates and the captured timezone pass through the central validator. Published, future and private events must have a complete valid range. Draft and pending events may be incomplete; when they have no canonical range, stale UTC indexes can be removed safely.

The action may mutate only:

- `_wpse_start_utc`;
- `_wpse_end_utc`.

It never changes canonical local dates, timezone, all-day state, venue, address, URLs, event status, publication status or `_wpse_dates_need_review`.

Outcomes per event are aggregated as:

- changed: repaired timestamps or cleared stale draft indexes;
- unchanged: already correct indexes or a clean incomplete draft;
- skipped: invalid canonical data requiring manual review;
- failed: a WordPress metadata write/delete failed and can be retried.

## Batching and continuation

One request inspects at most 50 event IDs ordered ascending. It includes publish, future, draft, pending and private states and excludes trash/auto-drafts. If a full batch is returned, the settings page presents a Continue button with a fresh nonce and cumulative bounded counters. It never starts an automatic redirect loop.

Offset pagination can shift when another administrator inserts or deletes events during a multi-batch run. Repair is idempotent, so rerunning from the beginning safely covers any missed record. A later WP-CLI command can reuse the same repairer for very large catalogues without changing this data contract.
