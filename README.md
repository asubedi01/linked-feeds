# LinkedIn Feeds (exploration)

A WordPress plugin **prototype** that displays LinkedIn personal-profile and company-page post feeds via a `[linkedin_feed]` shortcode, sourcing data from third-party RapidAPI LinkedIn providers.

> **Status: exploratory.** This is a feasibility prototype, not a shipping product.
> - **Official LinkedIn API: NO** (unchanged) — closed permission for personal feeds, banned use case for company feeds. Flips only with granted permissions **+ a passed Standard-tier review**, or a LinkedIn policy change.
> - **RapidAPI third-party route: technically proven** by this prototype, but **gated by (1) legal — it's scraping** (User-Agreement clause; Proxycurl/Apollo/Seamless precedents; brand) and **(2) cost — the free tier can't sustain a product**.
> - **Path forward:** consult legal; if cleared and the cost model closes, ship RapidAPI as an **interim/parallel offering while pursuing the official API**.
>
> Read **`LinkedIn-Feeds-Verdict.md`** (primary verdict) before treating any of this as a green light.

## What's here

| Path | What |
|---|---|
| `linkedin-feeds.php`, `includes/`, `templates/`, `assets/` | The plugin: shortcode, providers, normalizers, templates, styles |
| `includes/providers/` | Pluggable data providers — `fresh-scraper` and `fresh-profile`, switchable in Settings |
| `probe/` | CLI probe tools for the official API (`linkedin-probe.php`) and RapidAPI (`rapidapi-probe.php`) |
| `dev/` | Standalone render/test harnesses (run without WordPress) |
| `LinkedIn-Feeds-Verdict.md` | **Primary verdict** — official API (No) + RapidAPI route (legal + cost gates) + parallel-track recommendation |
| `EVALUATION.md` | Stakeholder summary + decisions requested |
| `FINDINGS.md` | Full API exploration findings (official API §0–6, RapidAPI route §7) |
| `RAPIDAPI-FINDINGS.md` | Consolidated provider reference: calls/feed, media expiry, display/lightbox/video, sizing, competitor survey |
| `API-COMPARISON.md` | Comparison of RapidAPI LinkedIn providers + recommendation |
| `MEDIA-EXPIRY.md` | Measured CDN media-URL expiry windows + the monitor |
| `SHORTCODE-DEMO.md` | Shortcode attributes, layout options, live-demo script |
| `LinkedIn-Feeds-Evaluation.html` | Rendered evaluation summary (shareable) |
| `RESEARCH.md`, `PITCH.md`, `PLAN.md` | Historical April-2026 artifacts (banner-flagged) |

## Quick start

1. Symlink/copy this folder into `wp-content/plugins/` and activate **LinkedIn Feeds**.
2. *Settings → LinkedIn Feeds* → choose a provider and paste your **RapidAPI key** (subscribe to the provider's API on RapidAPI first). Or define `LINKEDIN_FEEDS_RAPIDAPI_KEY` in `wp-config.php`.
3. Add a shortcode:
   - `[linkedin_feed type="profile" user="williamhgates"]`
   - `[linkedin_feed type="company" company="microsoft" layout="masonry"]`

See `SHORTCODE-DEMO.md` for all attributes (`type`, `user`, `company`, `demo`, `layout`, `limit`, `provider`, `show_source`), the four layouts (`grid` / `list` / `masonry` / `carousel`), and the post-detail popup + image lightbox.

## Note on demo data & media

- **Demo mode** (`[linkedin_feed demo="1"]`) and the pre-rendered `dev/out-*.html` rely on captured sample JSON that is **deliberately not committed** (it contains real LinkedIn content and signed media URLs). To use demo mode locally, capture samples with `probe/rapidapi-probe.php` (see `probe/responses/README.md` for the data contract), or just use live mode with your own key.
- **Media URLs from LinkedIn's CDN are signed and expire (~weeks).** A production build must download and re-host media locally — scaffolded via the `linkedin_feeds_localize_media` filter (`includes/class-media.php`), currently a pass-through. This is the main remaining build item.

## Credentials

No API keys or secrets are committed. Provide them via the Settings screen, `wp-config.php`, or env vars (`RAPIDAPI_KEY`, `LI_CLIENT_ID`, `LI_CLIENT_SECRET` for the probe tools).
