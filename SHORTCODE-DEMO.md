# LinkedIn Feeds — Shortcode & Live Demo Guide

**Date:** June 18, 2026
**Scope:** What the prototype's `[linkedin_feed]` shortcode can demo today, every attribute, and the layout options — all verified against the live RapidAPI responses captured in `probe/responses/`.
**Status:** Exploratory prototype on the third-party-scraper route (see `FINDINGS.md §7` for compliance context). The shortcode, providers, layouts, and demo mode are real and rendering; media-rehosting is the one stubbed piece.

---

## TL;DR for a presentation

- **Demo with no API key, no network:** `[linkedin_feed demo="1" type="profile"]` renders 20 real Bill Gates posts from a saved capture; `type="company"` renders 10 Microsoft posts. Zero quota cost, works offline — **the safe choice for a live demo.**
- **Live demo (needs key):** `[linkedin_feed type="profile" user="williamhgates"]` / `[linkedin_feed type="company" company="microsoft"]`.
- **Four layouts:** `grid` (default), `list`, `masonry`, `carousel` — plus a click-to-open post-detail popup.
- **Two switchable data providers:** `fresh-scraper` (default, rich+fast) and `fresh-profile` (alt). Switch globally in *Settings → LinkedIn Feeds*, or per-shortcode with `provider="…"`.

---

## Shortcode attributes

`[linkedin_feed]` accepts:

| Attribute | Values | Default | Purpose |
|---|---|---|---|
| `type` | `profile` \| `company` | `profile` | Personal feed vs. company-page feed |
| `user` | public username (e.g. `williamhgates`) | — | Source for `type="profile"` (live) |
| `company` | company slug (e.g. `microsoft`) | — | Source for `type="company"` (live) |
| `demo` | `1` / `true` | off | Render bundled sample, **no API call** |
| `layout` | `grid` \| `list` \| `masonry` \| `carousel` | `grid` | Visual arrangement (see below) |
| `limit` | integer | `0` (all) | Cap number of posts shown |
| `provider` | `fresh-scraper` \| `fresh-profile` | (settings) | Override the configured data provider for this one feed |

Invalid values fall back to the default (e.g. an unknown `layout` → `grid`, an unregistered `provider` → the settings default). Admins see API errors inline; visitors see nothing.

---

## Layout options (multiple, all feasible & verified)

All four are implemented in `assets/css/linkedin-feeds.css` and confirmed rendering the real captures. Each post card is identical across layouts — only the container arrangement changes — so every media type (text, image, multi-image, video, document, article) renders in all of them.

### 1. `grid` — responsive card grid *(default)*
- `repeat(auto-fill, minmax(300px, 1fr))` — columns flow to fit width; equal-height cards.
- **Best for:** the general "wall of posts" look; the most Smash-Balloon-like default.
- `[linkedin_feed demo="1" type="company" layout="grid"]`

### 2. `list` — single centered column
- One column, max-width 640px, centered — reads like a native LinkedIn timeline.
- **Best for:** narrow sidebars, a focused "latest posts" strip, mobile-first pages.
- `[linkedin_feed demo="1" type="profile" layout="list" limit="4"]`

### 3. `masonry` — CSS columns (Pinterest-style)
- `column-width: 320px` with break-inside avoidance — cards pack by height, no row gaps.
- **Best for:** mixed media where posts vary a lot in height (long text next to a single image).
- `[linkedin_feed demo="1" type="profile" layout="masonry" limit="9"]`

### 4. `carousel` — horizontal slider with prev/next
- Flex track with CSS scroll-snap + circular prev/next arrows (lightweight JS scroll, no slider lib).
- Cards are fixed-width slides (`clamp(260px, 80%, 340px)`); swipe/drag works on touch.
- **Best for:** a compact "latest posts" strip in a section or sidebar without consuming vertical space.
- `[linkedin_feed demo="1" type="profile" layout="carousel" limit="10"]`

> Layouts are pure CSS (carousel adds a tiny scroll handler). Responsive and theme-agnostic.

## Post-detail popup

Clicking anywhere on a card (except its links, video, or an image) opens a **post-detail popup** — an enlarged, faithful copy of the post (author, full text, media, engagement, "View on LinkedIn"). Keyboard-accessible (cards are focusable; Enter/Space opens, Esc closes). Clicking an **image** instead opens the **image lightbox**. Both are dependency-free.

### Layout coverage matrix (verified)

| Layout | profile demo | company demo | Render check |
|---|---|---|---|
| grid | ✅ | ✅ | `dev/out-profile.html`, `dev/out-company.html` |
| list | ✅ | ✅ | `dev/render-layouts.php` → 4 cards |
| masonry | ✅ | ✅ | `dev/render-layouts.php` → 9 cards |
| carousel | ✅ | ✅ | live: `linkedin-demo-layouts` page |

---

## What each post card shows

Driven by the normalized model — same fields across both providers:

- **Header:** author avatar, name (links to profile/company), headline/subtitle, relative time ("3 days ago"), LinkedIn brandmark.
- **Body:** post text with line breaks and auto-linked URLs.
- **Media** (exactly one per post): image (single or grid), native `<video>` with poster, document/PDF card (title + page count), or article link-preview card.
- **Footer:** reaction/comment/repost counts (abbreviated, e.g. `1.2K`), "View on LinkedIn" permalink.

---

## Suggested 3-minute demo script

1. **Open `dev/out-profile.html` and `dev/out-company.html` in a browser** (pre-rendered from real data — guaranteed to work, no setup). Show personal vs. company feeds side by side.
2. **In WordPress**, drop `[linkedin_feed demo="1" type="profile"]` on a page → same data, live in the theme.
3. **Switch `layout="grid"` → `list` → `masonry` → `carousel`** on the same shortcode to show the four arrangements (the **`linkedin-demo-layouts`** page shows all four stacked).
4. **Click a card** to open the post-detail popup; **click an image** to open the lightbox.
5. **Show the provider switch:** *Settings → LinkedIn Feeds* dropdown, or add `provider="fresh-profile"` to the shortcode — same UI, different data source.
6. *(Optional, if a key is set)* swap `demo="1"` for `user="williamhgates"` to pull live.

**Demo-day tip:** lead with `demo="1"`. It's offline, costs no quota (free tier is 50 calls/mo), and isn't exposed to a provider hiccup mid-presentation. Keep one live call in reserve to prove it's real.

---

## Known limitation to mention up front

Media URLs from LinkedIn's CDN are **signed and expire in ~weeks**. The saved demo captures will eventually show broken images/video. The fix (download + re-host media locally on fetch) is scaffolded via the `linkedin_feeds_localize_media` filter but not yet implemented — it's the main remaining build item before this is production-ready. For a near-term demo, re-capture fresh samples shortly before presenting (`probe/rapidapi-probe.php`).

---

## Files behind the demo

- Shortcode + helpers: `includes/class-shortcode.php`
- Layouts/styles: `assets/css/linkedin-feeds.css`
- Templates: `templates/feed.php`, `templates/post.php`, `templates/media-{image,video,article,document}.php`
- Providers: `includes/providers/class-provider-fresh-scraper.php`, `…-fresh-profile.php`
- Settings (provider + key): `includes/class-settings.php`
- Pre-rendered HTML: `dev/out-profile.html`, `dev/out-company.html`, `dev/out-freshprofile.html`
- Render harnesses: `dev/render-sample.php`, `dev/render-layouts.php`, `dev/live-test.php`
