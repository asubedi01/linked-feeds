# LinkedIn Feeds (exploration)

A WordPress plugin **prototype** that displays LinkedIn personal-profile and company-page post feeds via a `[linkedin_feed]` shortcode, sourcing data from third-party RapidAPI LinkedIn providers.

> **Status: exploratory.** This is a feasibility prototype, not a shipping product. The official LinkedIn API route was evaluated and found **not viable** (see `LinkedIn-Feeds-Verdict.md` / `FINDINGS.md`). This repo explores the **RapidAPI third-party-scraper** route as an alternative — which works technically but carries compliance/brand considerations the team must weigh (`FINDINGS.md §7`). Read the verdict before treating any of this as a green light.

## What's here

| Path | What |
|---|---|
| `linkedin-feeds.php`, `includes/`, `templates/`, `assets/` | The plugin: shortcode, providers, normalizers, templates, styles |
| `includes/providers/` | Pluggable data providers — `fresh-scraper` and `fresh-profile`, switchable in Settings |
| `probe/` | CLI probe tools for the official API (`linkedin-probe.php`) and RapidAPI (`rapidapi-probe.php`) |
| `dev/` | Standalone render/test harnesses (run without WordPress) |
| `FINDINGS.md` | Full API exploration findings (official API §0–6, RapidAPI route §7) |
| `API-COMPARISON.md` | Comparison of RapidAPI LinkedIn providers + recommendation |
| `SHORTCODE-DEMO.md` | Shortcode attributes, layout options, and a live-demo script |
| `LinkedIn-Feeds-Verdict.md` | The official-API feasibility verdict (NOT VIABLE) |
| `RESEARCH.md`, `PITCH.md`, `PLAN.md`, `EVALUATION.md` | Earlier research and planning artifacts |

## Quick start

1. Symlink/copy this folder into `wp-content/plugins/` and activate **LinkedIn Feeds**.
2. *Settings → LinkedIn Feeds* → choose a provider and paste your **RapidAPI key** (subscribe to the provider's API on RapidAPI first). Or define `LINKEDIN_FEEDS_RAPIDAPI_KEY` in `wp-config.php`.
3. Add a shortcode:
   - `[linkedin_feed type="profile" user="williamhgates"]`
   - `[linkedin_feed type="company" company="microsoft" layout="masonry"]`

See `SHORTCODE-DEMO.md` for all attributes (`type`, `user`, `company`, `demo`, `layout`, `limit`, `provider`) and layouts (`grid` / `list` / `masonry`).

## Note on demo data & media

- **Demo mode** (`[linkedin_feed demo="1"]`) and the pre-rendered `dev/out-*.html` rely on captured sample JSON that is **deliberately not committed** (it contains real LinkedIn content and signed media URLs). To use demo mode locally, capture samples with `probe/rapidapi-probe.php` (see `probe/responses/README.md` for the data contract), or just use live mode with your own key.
- **Media URLs from LinkedIn's CDN are signed and expire (~weeks).** A production build must download and re-host media locally — scaffolded via the `linkedin_feeds_localize_media` filter (`includes/class-media.php`), currently a pass-through. This is the main remaining build item.

## Credentials

No API keys or secrets are committed. Provide them via the Settings screen, `wp-config.php`, or env vars (`RAPIDAPI_KEY`, `LI_CLIENT_ID`, `LI_CLIENT_SECRET` for the probe tools).
