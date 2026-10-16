# Tehoa Journeys (vvm-google-analytics)

A WordPress plugin that records **visitor journeys** (page views and clicks) on our casino/affiliate sites and shows them in wp-admin of a central WordPress install. Think of it as a small, private "Google Analytics" built for one question: *what path did a visitor take before (or without) clicking an affiliate link, and which widget did they click it in?*

- **Central install (where the plugin runs):** `https://tehoa-server.vvm.agency` (wp-admin → **Visitor Journeys**)
- **Tracked sites:** any site that has the tracker snippet in its `<head>` (configured in **Sites & Code**). At the time of writing: vironkasinot.fi, kaikkimgakasinot.fi, onlinecasinofinder.ca and onlinecasinorad.
- **Current version:** 2.1.0 (see [CHANGELOG.md](CHANGELOG.md))

> Working on this code with Claude or another AI assistant? Read [CLAUDE.md](CLAUDE.md) first. It lists the rules that must not be broken.

---

## 1. How it works

```
 Tracked site (e.g. vironkasinot.fi)                 tehoa-server.vvm.agency (this plugin)
 ┌───────────────────────────────────┐              ┌──────────────────────────────────────┐
 │ <head> loader snippet (WPCode)     │              │                                      │
 │   └─ loads assets/t.js?d=YYYY-MM-DD│── GET t.js ─▶│ static file, cached by nginx         │
 │ t.js                               │              │                                      │
 │   • page view after DOMContentLoaded              │ POST /wp-json/tehoa/v1/e             │
 │   • every click (links, buttons,   │── beacon ───▶│  includes/collector.php              │
 │     clickable elements in widgets) │  text/plain  │   checks key + Origin, filters bots, │
 └───────────────────────────────────┘    JSON      │   rate-limits, classifies, stores    │
                                                    │  ▼                                   │
                                                    │ wp_tj_visits / wp_tj_events tables   │
                                                    │  ▼                                   │
                                                    │ wp-admin → Visitor Journeys          │
                                                    └──────────────────────────────────────┘
```

1. Each tracked site has a **loader snippet** in its `<head>`. It adds `assets/t.js` with today's date in the URL (`?d=2026-10-16`). The file itself is cached for a long time, but the date changes daily, so a new `t.js` reaches every visitor within one day without anyone touching the sites.
2. `t.js` sends one event per page view and one per click with `navigator.sendBeacon` (falls back to `fetch` with `keepalive`). It never blocks rendering and sets no cookies.
3. The collector (`POST /wp-json/tehoa/v1/e`) accepts an event only if the site key is known **and** the browser's `Origin` header matches that site's domain. Bots and headless browsers are dropped, with a limit of 60 events per minute per IP. It always answers `204` with no body.
4. Events are grouped into **visits**. A visit ends after 30 minutes without activity (`TJ_VISIT_GAP`).
5. The admin pages read the two tables and render them.

### Visitor identity (privacy)

- The visitor id is `HMAC(site_id | IP | user agent)` with a salt that **rotates daily** (`tj_salt` option), truncated to 16 hex characters.
  - The same person is recognised within one day, but cannot be linked across days.
  - Raw IPs are never stored.
- The same browser in several tabs counts as one visit. A different network (a phone switching Wi-Fi to 4G) counts as a new visitor.
- `t.js` also supports `data-persist="1"`, which uses a random id stored in `localStorage` instead. It is **not used** today: the loader snippet does not set it, and it should only be enabled after cookie consent.
- Query strings and fragments are stripped from tracked paths, because they can contain personal data.

## 2. Admin pages (wp-admin → Visitor Journeys)

All pages require the `manage_options` capability. The UI is in **English** (project rule).

| Page | Slug | File | What it shows |
|---|---|---|---|
| **Visits** | `tehoa-journeys` | `includes/admin.php` → `tj_render_journeys()` | Site + range (Today / 7 / 30 / 90 days), "affiliate only" filter, per-visitor filter. Each visit is a timeline of pages and clicks; affiliate clicks are highlighted and widget badges are shown. 30 visits per page with pagination. |
| **Widgets & Sources** | `tehoa-journeys-widgets` | `includes/widgets-page.php` → `tj_render_widgets()` | (1) Where affiliate clicks come from: review pages / widgets / other areas. (2) Per widget and per page area: affiliate clicks, review clicks, other interactions, visitors, pages, top clicked items. Default range is 30 days. |
| **Sites & Code** | `tehoa-journeys-settings` | `includes/admin.php` → `tj_render_settings()` / `tj_save_settings()` | Add/edit/remove tracked sites, affiliate path patterns, review page paths, data retention, and the snippet to copy for each site. |

## 3. Data model

Two custom tables (prefix `wp_` may differ). Schema lives in `includes/install.php` → `tj_install_schema()` and is applied with `dbDelta`.

**`wp_tj_visits`**: one row per visit.
`visit_id` (16 hex), `site_id`, `visitor` (16 hex hash), `started_at`, `last_at`, `entry_path`, `referrer` (host only), `device` (0 unknown, 1 mobile, 2 tablet, 3 desktop), counters `pageviews`, `clicks`, `aff_clicks`.

**`wp_tj_events`**: one row per page view or click.

| Column | Meaning |
|---|---|
| `type` | 1 = page view, 2 = click |
| `kind` (clicks) | 0 = interaction without a link (tab, toggle, button), 1 = internal link, 2 = outbound link, 3 = **affiliate**, 4 = **review page link** |
| `path` | page the event happened on (path only) |
| `title` | page title (page views) |
| `target` | link target (clicks) |
| `label` | visible text of the clicked element (max 150 chars) |
| `widget` | source key of the clicked area, e.g. `review_cards_rows` (see `tj_sources()`) |
| `page_type` | 1 home, 2 review, 3 page, 4 article, 5 list, 6 other (from the `<body>` class) |
| `created_at` | UTC |

All times are stored in **UTC** and shown in the WordPress timezone.

**Options** (`wp_options`):
- `tj_sites`: array `id => {name, domain, key, patterns, reviews}`. `key` is the public site key in the snippet; `patterns` holds affiliate path rules and `reviews` holds review page prefixes, one per line.
- `tj_last_site_id`: last used site id. Ids are never reused.
- `tj_retention_days` (default 90): a daily cron `tj_daily_cleanup` deletes older data.
- `tj_salt`: the daily visitor salt.
- `tj_db_version`: schema version (`TJ_DB_VERSION`).
- `tj_widgets_since`: when widget tracking started. The Widgets page shows it, because older clicks have no widget.

## 4. Click classification

Done on the server in `tj_classify_click()` (`includes/collector.php`), in this order:

1. **Affiliate (3)** if the link has `rel="sponsored"`, **or** its path contains one of the site's affiliate patterns (default: `/go/ /out/ /visit/ /goto/ /recommends/ /refer/ /link/ /play/ /siirry/`), **or** it is an external link with a typical tracking parameter (`btag`, `affid`, `clickid`, …).
   - In the Tehoa theme, affiliate links are the `affiliate-links` CPT with the slug `/go/` or `/siirry/`.
2. **Review link (4)** if the link is internal and starts with a review path (default: `/arvostelut/ /arvostelu/ /reviews/ /kokemuksia/ /recension/ /resenas/`), editable per site as "Review page paths".
3. **Internal (1)** otherwise for the same domain, and **outbound (2)** for other domains.
4. Elements that are not links (`button`, `summary`, elements with `cursor: pointer` inside a widget) become **kind 0**.

## 5. Widget / source detection (Tehoa theme)

The tracker (`assets/t.js`) decides which widget a click came from. It walks up from the clicked element and matches CSS selectors in the `Z` array.

- **Strong** entries (widgets and specific areas) win as soon as one encloses the click.
- **Weak** entries (third value `1`: `hero`, `header`, `footer`, `content`) only count when no strong entry encloses the click.
  - This matters because some theme widgets (e.g. the casino list, `review_cards_rows`) open `.content.ugc` *inside* themselves.
  - Before v2.1.0 that made clicks in the widget count as "Text links in content".

Keys and labels are defined **twice**, and both places must stay in sync:
- `assets/t.js` → `Z` array (selector → key)
- `includes/sites.php` → `tj_sources()` (key → [label, is_widget])

**Adding or changing a widget:**
1. Find the widget's outer wrapper class in the tehoa-theme shortcode template. Read it only; never change the theme from this repo.
2. Add `['.wrapper-class', 'my_widget']` to `Z`, placed before any broader selector that could also match.
3. Add `'my_widget' => array( 'My widget', true )` to `tj_sources()`. Use `true` if it is a content widget and `false` for a fixed page area.
4. Keys must match `^[a-z0-9_]{1,32}$`, or the collector drops the value.

The "review page" bucket on the Widgets page uses `page_type = review`, which comes from the `single-review` body class.

## 6. Adding a site to tracking

1. wp-admin → Visitor Journeys → **Sites & Code** → add the domain (without `www`; `www.` is accepted automatically).
2. Copy the generated snippet.
3. On the site, install **WPCode** (Insert Headers and Footers) and paste the snippet into **Header**.
   - Any way of putting it in `<head>` works.
4. If the site uses a JS delay/optimiser (WP Rocket "Delay JS", Breeze, LiteSpeed…), **exclude `tehoa-journeys`** from it, or the tracker only starts after user interaction.
5. Open the site in a normal browser, click something, and check **Visits**.

The snippet only contains the public site key. The key is useless on any other domain, because the collector checks `Origin`.

## 7. Development and deployment

There is no build step, no Composer/npm and no test suite. The plugin is plain PHP 7.4+ and vanilla JS (ES5, no dependencies).

**Release a change:**
1. Change code in `tehoa-journeys/`.
2. Bump the version in **both** places in `tehoa-journeys/tehoa-journeys.php`:
   - the `Version:` header
   - `TJ_VERSION`
3. If you changed the DB schema:
   - Edit `tj_install_schema()` (dbDelta only adds; it never drops columns).
   - Bump `TJ_DB_VERSION`. `tj_maybe_upgrade()` runs the migration on the next request.
4. Add an entry to `CHANGELOG.md` and commit.
5. Build the zip (the folder must be the zip root):
   ```bash
   zip -r tehoa-journeys.zip tehoa-journeys -x '*.DS_Store'
   ```
6. On tehoa-server: Plugins → Add New → Upload Plugin → choose the zip → **Replace current with uploaded**. Data and settings are kept.
7. Check that **Visits** and **Widgets & Sources** load.

**Caching:**
- `t.js` is served with a long cache lifetime.
- Visitors get the new tracker the next day, because the date in the loader URL changes daily.
- To test a new `t.js` immediately, open it with a different `?d=` value or hard-refresh.

**Local testing (optional):**
- Any WordPress plus MySQL/MariaDB works.
- Without pretty permalinks, the endpoint is `/?rest_route=/tehoa/v1/e`.
- The tracker exits when `navigator.webdriver` is true. In Playwright/Puppeteer, override it with an init script, or no events are sent.
- The collector also rejects headless user agents and requests whose `Origin` doesn't match a configured site.

**Uninstall:**
- Deleting the plugin from wp-admin runs `uninstall.php`.
- It drops both tables and removes the options. This deletes all collected data.
- **Deactivating** keeps all data.

## 8. Security notes

- The collector is public, so treat every field as hostile. Rules followed in `includes/collector.php`:
  - body ≤ 4 KB, JSON only
  - every string is length-limited and stripped of tags and control characters
  - paths are filtered to RFC 3986 characters
  - widget keys are regex-checked
  - all SQL goes through `$wpdb->prepare` / `$wpdb->insert`
  - nothing is echoed back
- Admin output escapes everything (`esc_html`, `esc_attr`, `esc_url`). The settings form uses a nonce (`tj_save_settings`) plus a `manage_options` check.
- Never commit credentials (wp-admin, SFTP, DB) or `wp-config.php` values to this repo.

## 9. Repository layout

```
tehoa-journeys/                 ← the plugin (this folder is what gets zipped)
├── tehoa-journeys.php          bootstrap: constants, requires, hooks
├── uninstall.php               drops tables/options on delete
├── assets/t.js                 tracker loaded on tracked sites
└── includes/
    ├── install.php             schema, defaults, daily cleanup
    ├── sites.php               site helpers, snippet, page types, widget source list
    ├── collector.php           REST endpoint, validation, visits, click classification
    ├── admin.php               menu, shared styles, Visits page, Sites & Code page
    └── widgets-page.php        Widgets & Sources page
README.md / CLAUDE.md / CHANGELOG.md
```
