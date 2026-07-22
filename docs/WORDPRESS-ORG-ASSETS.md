# WordPress.org visual assets

WordPress.org display assets belong in the top-level `assets/` directory of the
future plugin SVN repository. Their reviewed Git sources are stored in
`.wordpress-org/`. They do not belong inside the installable plugin zip or the
runtime `assets/` directory in this repository.

## Prepared files

| File | Purpose | Dimensions |
|---|---|---:|
| `icon-128x128.png` | Standard directory icon | 128 x 128 |
| `icon-256x256.png` | Retina directory icon | 256 x 256 |
| `banner-772x250.png` | Standard plugin banner | 772 x 250 |
| `banner-1544x500.png` | Retina plugin banner | 1544 x 500 |
| `screenshot-1.png` | Events overview | 1920 x 557 |
| `screenshot-2.png` | Native event details editor | 1920 x 624 |
| `screenshot-3.png` | Public calendar and list | 1200 x 1280 |
| `screenshot-4.png` | Public single event | 1200 x 999 |
| `screenshot-5.png` | Event settings | 1440 x 917 |
| `screenshot-6.png` | Elementor widget collection | 295 x 1000 |
| `screenshot-7.png` | Elementor calendar configuration | 1600 x 1012 |

The source screenshots use fictional event content. The prepared editor
screenshot ends before external URL values, and the public calendar crop excludes
the browser's local-link status display.

## Icon files

Both PNG icon sizes use the calendar mark without small wordmark text:

- `icon-128x128.png`
- `icon-256x256.png`

The mark remains centered with enough breathing room to be recognizable at 128
pixels. An optional `icon.svg` may be added later only together with these PNG
fallbacks. The current raster source does not justify manufacturing an artificial
SVG.

## Banner files

The standard and retina banners use the same approved composition:

- `banner-772x250.png`
- `banner-1544x500.png`

The retina banner supplements rather than replaces the standard banner. Both
files must remain below WordPress.org's 4 MB limit.

## Screenshot set

1. `screenshot-1.png` — Events overview with useful date, location, category and
   status columns and filters.
2. `screenshot-2.png` — WordPress Event details panel with a safe fictional
   event.
3. `screenshot-3.png` — Public month calendar and the matching event list.
4. `screenshot-4.png` — Individual event page with date, venue, location link,
   external action and terms.
5. `screenshot-5.png` — Archive, display, timezone and data-retention settings.
6. `screenshot-6.png` — Optional Simple Events by MiMe widget collection in
   Elementor.
7. `screenshot-7.png` — Calendar and event-list configuration in the Elementor
   editor.

The matching captions live under `== Screenshots ==` in `readme.txt`. Renumber
both files and captions together without gaps if the set changes.

## Privacy and visual QA checklist

- Use only fictional names, venues, addresses, domains and event images with
  known rights.
- Hide the WordPress user/account menu when it exposes a real username or avatar.
- Do not show browser developer tools, credentials, nonces, local paths, private
  URLs, `localhost`, `.local` or unrelated tabs.
- Verify that English interface text is intentional.
- Avoid accidental hover, focus or loading states unless the image explains that
  state.
- Check readability at WordPress.org display size, not only at source size.
- Export in RGB/sRGB and optimize file size without blurring interface text.
- Keep filenames lowercase and exactly aligned with the readme numbering.

## Publication handoff

After WordPress.org approval, copy the contents of `.wordpress-org/` into the SVN
repository's top-level `assets/` directory. Do not place the `.wordpress-org`
directory itself in `trunk/` or a version tag.

WordPress.org caches display assets aggressively. Replace files only after local
comparison of the standard and retina sizes, and expect CDN updates to take time.
