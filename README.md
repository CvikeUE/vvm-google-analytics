# Tehoa Journeys (vvm-google-analytics)

WordPress plugin that records visitor journeys (page views and clicks) on external sites and shows them in wp-admin under **Visitor Journeys**:

- **Visits** – each visit as a timeline of pages and clicks, affiliate clicks highlighted.
- **Widgets & Sources** – which Tehoa theme widgets get interactions and affiliate clicks, and whether affiliate clicks come from review pages, widgets or other parts of the site.
- **Sites & Code** – tracked sites, affiliate and review path rules, and the snippet to add to each site.

## Install

Zip the `tehoa-journeys/` folder and upload it under Plugins → Add New → Upload Plugin, then copy each site's code from **Sites & Code** into that site's `<head>`.

## Privacy and performance

The tracker (`assets/t.js`) is about 1.5 KB gzipped, loads async and sets no cookies. Visitors are identified by a daily-rotating hash of IP and user agent; raw IPs are never stored.
