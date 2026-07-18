# Native event archive settings contract

## Administrator surface

Archive configuration lives under **Events → Settings → Event archive** and requires WordPress' native `manage_options` Settings API path. The plugin exposes only:

| Setting | Default | Accepted value |
|---|---|---|
| Archive slug | `events` | one sanitized non-empty URL segment, at most 200 bytes |
| Events per page | bounded site `posts_per_page` | base-ten integer from 1 through 50 |
| Default period | `upcoming` | `upcoming` or `all` |

Stored options are revalidated whenever read. Missing or malformed values fail safe to `events`, 10 events per page and `upcoming`. Until a page-size override is stored, a valid site `posts_per_page` value is inherited. The settings do not change shortcode, calendar-feed or Elementor-widget defaults.

Visitors can explicitly choose upcoming, past or all events through the native archive filter. A valid visitor choice overrides the configured default only for that request. Public visibility, password exclusion, date boundaries and maximum query size remain governed by `PUBLIC-QUERY-CONTRACT.md`.

## URL and conflict behaviour

The configured slug is used for both the archive and individual event permalinks. Changing `events` to `community-events` changes `/events/` to `/community-events/` and `/events/example/` to `/community-events/example/`. Version 1 does not create redirects for an old base, so the administrator UI explicitly warns that existing links can change.

A non-trashed WordPress page at the same root slug is reported to administrators on Events screens. The warning offers the intended choice: keep the native event archive at that address or choose another archive slug so the page can own it. Draft, pending, private, future and published pages are diagnosed because any may later claim the public route; trashed and auto-draft pages are ignored. Other plugins may register arbitrary rewrite patterns, so generic third-party route collision detection is outside this version's reliable boundary.

## Rewrite lifecycle

The archive post type is registered with the validated slug on every request. Rewrite rules are never flushed merely because a settings page loads:

1. saving an equivalent normalized slug schedules nothing;
2. a successful real slug add/update stores the validated target slug in a private one-shot option;
3. late on the next `init`, after the event post type is registered with that slug, the marker is revalidated;
4. a matching marker causes one soft `flush_rewrite_rules( false )` and is then removed;
5. malformed or stale markers are deleted without flushing.

Activation registers the complete content model before its soft flush. Deactivation unregisters `wpse_event`, `wpse_event_category` and `wpse_event_tag` before its soft flush, preventing stale plugin routes from being generated again. No event, term, role or configuration data is deleted on deactivation.
