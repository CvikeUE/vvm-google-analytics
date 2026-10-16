# CLAUDE.md: rules for AI assistants working in this repo

Read [README.md](README.md) for how the plugin works. This file covers what you must and must not do.

## Hard rules (from the project owner)

1. **Scope: this repository only.**
   - Work only in `CvikeUE/vvm-google-analytics`.
   - Do not read, change, push to or open PRs in any other repo, especially **tehoa-theme**.
   - The theme may be *looked at* to find widget class names, but never modified from here.
2. **Plugin UI text is English.** Every admin label, notice and message in the code is English. (Chat with the owner, Ognjen, is in Serbian.)
3. **No secrets in the repo, memory or files.**
   - Never write wp-admin, SFTP or DB credentials into code, docs, commit messages or notes.
   - If you need them, ask the user and pass them only as environment variables for the single command.
4. **No page-speed impact on tracked sites.**
   - `t.js` stays tiny, dependency-free, async, ES5 and with no cookies.
   - Never make it render-blocking or add libraries.
5. **No injection risk.**
   - The collector endpoint is public. Every incoming value must be length-limited, sanitised and type-checked.
   - SQL must go through `$wpdb->prepare()`/`insert()`, and admin output must be escaped.
   - Do not weaken the Origin/site-key check, bot filter or rate limit.
6. **Do not touch production without being asked.** Deploying to tehoa-server, deleting data, or changing tracked sites' settings needs an explicit request from the user.

## Things that are easy to break

- **Widget keys exist in two places.** `assets/t.js` (`Z` array) and `includes/sites.php` (`tj_sources()`) must stay in sync.
  - Order in `Z` matters: specific widgets before broad areas.
  - Weak areas (`1` as the third item) must stay weak.
  - See README §5.
- **Version bump in two places.** In `tehoa-journeys.php`, update both the `Version:` header and `TJ_VERSION`.
- **Schema changes.**
  - Change `tj_install_schema()` and bump `TJ_DB_VERSION`; `dbDelta` handles adding columns and indexes.
  - Never rename or drop a column without a migration plan: live data is in it.
  - If you add a column to events, update the format array in `tj_insert_event()`; it must match the column order of `$event`.
- **Classification order** in `tj_classify_click()`: affiliate first, then review, then internal/outbound. The Widgets page relies on `kind` 3 and 4.
- **Snippet compatibility.**
  - Sites already have the loader snippet pasted. Changing `tj_snippet()` output means every site must re-paste it, so avoid that.
  - Changes to `t.js` reach visitors automatically within a day (the date-stamped URL).
  - The `data-endpoint`/`data-key` attributes and the event JSON fields (`k p pt t ti r w h x rel wg v`) are a contract between `t.js` and `collector.php`. Keep old fields working.
- **Tracker exits on `navigator.webdriver`.** Automated browser tests see no events unless you override it.
- **Times** are stored in UTC (`gmdate`) and displayed via the WP timezone.

## Workflow

1. Make the change in `tehoa-journeys/` and keep the code style (WordPress PHP style, tabs, `tj_` prefix for every function/option/table).
2. Run `php -l` on changed PHP files and `node --check tehoa-journeys/assets/t.js`.
3. Bump the version and add a `CHANGELOG.md` entry.
4. Commit to a branch and open a PR unless the user asks for a direct push to `main`.
5. Deploy only when asked: zip `tehoa-journeys/` (folder at zip root), then wp-admin → Plugins → Upload → "Replace current with uploaded". See README §7.
   - tehoa-server's firewall rejects scripted uploads without browser-like `User-Agent`, `Referer` and `Origin` headers.
6. After deploying, open **Visits** and **Widgets & Sources** to confirm they render.

## Context worth knowing

- Central install: `tehoa-server.vvm.agency`.
- Tracked sites are listed in Sites & Code and can change, so don't hard-code them.
- The plugin is installed by upload. There is no CI or auto-deploy, so the live version is whatever was last uploaded. Check the Plugins page if unsure.
- Clicks recorded before 2026-10-13 have no widget value. Clicks in the casino list before v2.1.0 were counted as "Text links in content".
- See `CHANGELOG.md` for the history.
