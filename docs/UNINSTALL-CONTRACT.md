# Uninstall and data ownership contract

## Administrator behaviour

Deactivating Simple Events by MiMe never deletes anything. Deleting the plugin also keeps events, event terms, options and event capabilities unless an administrator first enables **Events → Settings → Delete plugin data**.

The checkbox is off by default and is saved through WordPress' administrator-only Settings API. Its warning names the irreversible data classes. Any missing, malformed or unchecked value means **retain data**.

## Explicit cleanup scope

After opt-in, WordPress plugin deletion removes:

- every `wpse_event` post in every publication status;
- event metadata, revisions, comments and taxonomy relationships removed by WordPress together with each event;
- every `wpse_event_category` and `wpse_event_tag` term;
- plugin-owned schema, archive-routing, structured-data, timezone-display, pending-rewrite and uninstall-preference options;
- event capabilities previously granted by the plugin to administrator and editor.

It does not remove:

- uploaded attachments or featured images, because they can be reused elsewhere;
- ordinary posts, pages, products, blog categories or blog tags;
- Elementor or WooCommerce data;
- third-party options or database records.

The implementation uses WordPress deletion APIs rather than direct SQL. Events and terms are queried in batches of 100. Options are deleted only after content and term cleanup completes; an intercepted or failed deletion therefore leaves diagnostic state instead of silently claiming success.

## Multisite

Network-wide activation remains blocked, but the plugin can be activated separately on individual sites. During plugin deletion, sites are enumerated in batches of 100. Each site is switched and restored with WordPress APIs, and only that site's own opt-in controls its data. One site's destructive choice never authorizes deletion on another site.

On very large networks the bounded queries prevent unbounded result sets, but total uninstall execution time remains proportional to the number of sites and events.
