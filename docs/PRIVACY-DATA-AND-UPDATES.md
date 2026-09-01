# Privacy, data and updates

## What the plugin does not do

MiMe Simple Events and Calendar creates no visitor cookies, analytics or
telemetry; loads no remote fonts, scripts, images or tracking pixels; and sends no
information to MiMe. Calendar data is requested from the same WordPress site.

## What your site stores and publishes

Event titles, content, excerpts, images, dates, status, venues, addresses, links,
categories, tags, colors and recurrence rules are stored in the site's own
WordPress database. A local rebuildable occurrence index stores only bounded
dates, status, stable identity and the parent event relationship needed for
chronological queries. It does not copy event bodies, passwords or visitor data.

Published event information may appear in:

- front-end HTML and calendar/list output;
- Event JSON-LD when enabled;
- WordPress core REST responses;
- the plugin's bounded public calendar feed;
- an ICS, Google or Outlook snapshot deliberately requested by a visitor.

Do not enter private information in a published event. Draft, private and
password-protected events are excluded from public plugin collections. Protected
event metadata is removed from public core REST responses while the WordPress
password is still required.

External location and event-action links open in an isolated new tab without a
referrer. Their destination has its own privacy practices. Google and Outlook
actions are disabled unless a template author enables them; the plugin never
contacts those providers in the background.

## Deactivation and deletion

Deactivation never deletes event content or settings.

Plugin deletion also preserves events, event terms, settings and capabilities by
default. An administrator can deliberately enable **Delete plugin data** under
**Events > Settings** before deletion. That choice is permanent and removes only
allowlisted plugin-owned data. Uploaded media remains because another page may
use it.

Back up the database before enabling destructive deletion.

## Updating

Normal in-place updates are supported from every official release from 0.2.3
onward. WordPress.org installations began at 0.2.4. Updates may rebuild derived
indexes and rewrite rules, but preserve canonical events, recurrence, terms,
settings and supported saved builder content.

Before an important production update:

1. back up files and database;
2. test the update on staging when practical;
3. update the plugin through WordPress;
4. verify one normal event, one recurring occurrence, the archive, calendar and
   any builder template used by the site;
5. inspect **Events > Settings** only if WordPress reports an index or capability
   health problem.

Downgrades and untagged development snapshots are not supported.

## Manual handoff from private 0.2.1 or 0.2.2 builds

Those pre-directory packages used the old `simple-events-by-mime` plugin folder
and cannot be updated in place to the approved WordPress.org slug.

1. Back up the site.
2. Deactivate the old plugin.
3. Remove only its plugin files without enabling destructive data deletion.
4. Install and activate the current `mime-simple-events-calendar` package.

The retained event data is read by the current plugin. Never activate the old and
new packages together. This handoff is relevant only to sites that received one
of those early private test packages.

For the detailed lifecycle guarantees, see the
[Install, upgrade and lifecycle contract](UPGRADE-CONTRACT.md) and
[uninstall contract](UNINSTALL-CONTRACT.md).
