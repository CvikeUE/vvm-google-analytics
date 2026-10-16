# Changelog

## 2.1.0 (2026-10-13)
- Widget detection: broad page areas (hero, header, footer, content) are now "weak" and only count when no widget encloses the click. This fixes casino list (`review_cards_rows`) clicks being counted as "Text links in content".
- Snippet is now a small inline loader that requests `t.js?d=YYYY-MM-DD`, so tracker updates reach visitors within a day without re-pasting. **Every site had to paste the new loader once.**
- "Review page paths" setting per site.

## 2.0.0 (2026-10-13)
- New **Widgets & Sources** page:
  - affiliate clicks by source (review page / widget / other)
  - per-widget affiliate, review and other interactions
  - top clicked items
- DB v2: `widget` and `page_type` columns on events, plus a new index. New click kinds: 0 = interaction without a link, 4 = review page link.
- Tracker reports the Tehoa theme widget/area of each click, the page type, and clicks on clickable non-link elements inside widgets.

## 1.x (2026-10-08 → 2026-10-12)
- First version: cookieless visit tracking, **Visits** timeline with pagination and filters, and **Sites & Code** settings with a per-site key.
- English UI and a restyled card-based admin.
- `/siirry/` added to the default affiliate patterns.
