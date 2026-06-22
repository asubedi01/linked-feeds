# RapidAPI Providers — Consolidated Findings (don't reinvent the wheel)

**Date:** June 23, 2026
**Purpose:** Everything verified to date about the two RapidAPI LinkedIn providers wired into this plugin, so implementation doesn't re-discover it. All figures are **empirical** (live calls / captured responses in `probe/responses/`), not docs-guessed, unless flagged.

**The two providers:**
- **`fresh-scraper`** = Fresh LinkedIn Scraper API (`fresh-linkedin-scraper-api.p.rapidapi.com`)
- **`fresh-profile`** = Fresh LinkedIn Profile Data (`fresh-linkedin-profile-data.p.rapidapi.com`)

Both are third-party scrapers (compliance caveats in `FINDINGS.md §7`). One RapidAPI key covers both (key is per-account, not per-API).

---

## 1. Calls needed per feed (the headline reference)

| Feed | `fresh-scraper` | `fresh-profile` |
|---|---|---|
| **Personal posts** | **2**: `user/profile?username=` (→ `urn`) then `user/posts?urn=` | **1**: `get-profile-posts?linkedin_url=…/in/<user>` |
| **Company posts** | **2**: `company/profile?company=` (→ `company_id`) then `company/posts?company_id=` | **1**: `get-company-posts?linkedin_url=…/company/<slug>` |
| **+ company logo** | included in posts (and in the resolve response) — **no extra call** | **+1**: `get-company-by-linkedinurl` (`logo_url`) — posts omit it |
| **Author avatar (personal)** | included in posts | included in posts (`poster.image_url`) |

**Key insight — the resolve/extra calls are one-time and cacheable:**
- `fresh-scraper`: the `urn`/`company_id` is stable → cache it (plugin caches **1 week**). **Ongoing cost = 1 posts call per refresh.**
- `fresh-profile`: posts are 1 call (no resolve). Company **logo** needs a 2nd call, cached **1 week** → amortizes to ~0. **Ongoing cost = 1 posts call per refresh** (+ rare logo refresh).

So at steady state **both providers cost ~1 call per feed-refresh.** The difference is setup: fresh-scraper resolves an id first (logo free); fresh-profile fetches posts directly but needs a side call for the company logo.

**Posts per call:** `fresh-scraper` returns ~10–20; `fresh-profile` returns **50**. Pagination: fresh-scraper `page=`; fresh-profile `start=` offset (0, 50, 100…) + `pagination_token`.

**Cost planning:** request volume scales with **feeds × refresh frequency**, NOT page views (rendering is served from a 1-hour cache). See `API-COMPARISON.md` for the tier/scaling table.

---

## 2. Media URL expiry (captured + monitored)

Every media URL is a **signed LinkedIn CDN link** with an `e=<unix>` expiry. Observed windows (both providers identical — same LinkedIn CDN):

| Media type | Lasts from capture |
|---|---|
| Video streams + thumbnails | **~6–7 days** |
| Documents (PDF) | **~6–7 days** |
| Images (feedshare) | **~21 days** |
| Avatars / company logos | **~21 days** |

**Implication:** cannot store-and-serve URLs. Production must **download + re-host media locally** on fetch, refresh on schedule. Scaffolded via the `linkedin_feeds_localize_media` filter (`includes/class-media.php`), currently pass-through — **the main remaining build item.** Monitor: `dev/media-expiry-monitor.php` (full detail in `MEDIA-EXPIRY.md`).

---

## 3. Media display capabilities & provider differences

What the plugin renders today, per media type, and where the providers differ:

| Media | How it renders | Lightbox / interaction | Provider difference |
|---|---|---|---|
| **Image** | `<img>` `object-fit:cover`; multi-image → grid of tiles | **Lightbox: YES** — click opens a full-screen overlay (`assets/js/linkedin-feeds.js`, triggers on `[data-linkedin-lightbox]`) | `fresh-scraper`: **multi-resolution ladder** (20→1280px + high-res) → pick crisp size per layout/retina. `fresh-profile`: **single resolution** per image |
| **Video** | native `<video controls preload="none" playsinline>` in an `aspect-ratio` box; plays inline | No lightbox (native player) | `fresh-scraper`: **poster thumbnail + true `aspect_ratio` + width/height**. `fresh-profile`: **stream URL + duration only — NO poster, no dimensions** (we default 16:9). This is the one intrinsic visual gap between providers. |
| **Document (PDF)** | link "card" (title + page count) → opens PDF in new tab | No inline viewer | Both: `title`, `page_count`, PDF url. Equivalent. |
| **Article** | link-preview "card" (title/subtitle/host) → external | No | Both: `title`, `subtitle`, target url. (fresh-profile article fields are flat; fresh-scraper nested — normalized away.) |
| **Text-only** | text body, auto-linked URLs, line breaks | — | Equivalent. |

**Interaction today:** clicking an **image** opens the image **lightbox**; clicking a **card** (anywhere but its links/video/images) opens the **post-detail popup** (enlarged copy — author, full text, media, engagement), keyboard-accessible. Videos play inline; documents/articles open in a new tab.

---

## 4. Sizing & layout — are we limited to provider sizes?

**No — display sizing is fully under our CSS control, independent of source pixels.** The four layouts impose their own dimensions:

- **grid**: `repeat(auto-fill, minmax(300px,1fr))`, images `object-fit:cover` capped at `max-height:420px`, equal-height cards.
- **list**: single 640px centered column.
- **masonry**: `column-width:320px`, natural heights.
- **carousel**: horizontal scroll-snap track, fixed-width slides (`clamp(260px,80%,340px)`) + prev/next.

Provider-supplied sizes affect **quality/fidelity, not whether a layout works**:
- **Images:** `fresh-scraper`'s resolution ladder lets us request a crisp size for the target/retina; `fresh-profile`'s single size we take as-is (fine for typical widths, may soften on large tiles).
- **Video:** `fresh-scraper` gives the true aspect ratio (no letterboxing) + a poster; `fresh-profile` defaults to 16:9 (vertical videos may letterbox) and shows no poster frame until play.

**Bottom line:** fixed heights/widths and all four layouts are achievable with **either** provider. `fresh-scraper` simply gives more raw material (resolution choice, video posters, true aspect ratios) for a crisper result with less effort.

---

## 5. Schema / mapping notes (already handled in code)

The normalizer abstraction means templates never see provider differences. Captured-verified quirks the providers throw (mapped in `includes/providers/`):
- `fresh-profile` **permalink differs by feed type**: profile = `post_url`, company = `url`.
- `fresh-profile` **company poster** = `{name, linkedin_url}` only (no avatar/headline) → logo injected via the 2nd call; placeholder initial-circle if absent.
- `fresh-profile` article fields are **flat** (`article_title`…); video has **no thumbnail**.
- `fresh-scraper` nests media under `content.{images,video,document,article}`; reaction breakdown in `activity.reaction_counts[]`.
- Both expose per-type reaction counts (LIKE/EMPATHY/PRAISE/INTEREST/APPRECIATION/ENTERTAINMENT).

Full field maps: `probe/responses/README.md`.

---

## 6. Competitor display approaches & polish gap

Surveyed: EmbedSocial, Tagembed, Elfsight, SociableKIT, Juicer, Curator.io, and Smash Balloon's own IG/FB plugins (design reference). Focus = how they *display* posts, not data sourcing.

### Table-stakes — what a credible LinkedIn feed widget MUST have, vs. what we have

| Feature | Competitors | Our prototype | Gap |
|---|---|---|---|
| Multiple layouts (grid + masonry + carousel + list) | Universal (all ship 3–5) | ✅ grid, list, masonry, carousel | — (done) |
| Responsive + column control (ideally per-device) | EmbedSocial, Elfsight, Curator | responsive grid | **Add column/per-device control** |
| Card: avatar, name, relative time, "View on LinkedIn" | Universal | ✅ all present | — |
| Like/comment counts (toggleable) | Elfsight, Curator, Juicer, SB (EmbedSocial omits — a gap) | ✅ shown (not yet toggleable) | Make toggleable |
| Lightbox / popup post detail | Elfsight, EmbedSocial, Curator, SB | ✅ image lightbox + post-detail popup | — (done) |
| Light/Dark theme + Custom CSS | Universal | theme-agnostic CSS, no presets | **Add presets + CSS field** |
| "Load More" / pagination | Universal | not surfaced (providers paginate) | **Wire up** |
| Moderation + keyword/hashtag filtering | Universal | none | **Add include/exclude** |

### Differentiating polish (what the better ones do)
- **Live visual customizer w/ real-time preview** — Smash Balloon's signature; the natural bar for *this* plugin since AM already owns that playbook.
- **Starter template/preset gallery** (SB 8, Curator 20+, EmbedSocial named variants).
- **Layout-specific hover/animation**, **highlight/featured post** (SB Highlight, EmbedSocial Hero).
- **Two popup styles + per-element popup control** (Elfsight).
- **Accessibility out-of-box** (only Curator advertises it — easy credibility win).

### The whitespace — and why our providers already cover it

The competitor set converges on the same layouts/toggles, but **almost none faithfully render LinkedIn-native media** — **article link-previews, document/PDF carousels, multi-image posts, reposts**. Juicer explicitly *can't* show LinkedIn slideshows; only Curator *claims* it (marketing-level). Yet LinkedIn feeds are **dominated** by exactly those content types.

**This is the clearest place to win — and our current providers already supply the data for it:**
- ✅ **Article link-previews** — both providers return title/subtitle/url → we render cards already.
- ✅ **Document/PDF carousels** — both return title/page_count/PDF url → rendered as cards (inline first-page preview is an easy upgrade).
- ✅ **Multi-image posts** — both return image arrays → rendered as a tile grid (in-card carousel is an easy upgrade).
- ✅ **Reposts** — flagged (`reshared`) → can style distinctly.
- ✅ **Per-type reactions** — both return the breakdown → richer than competitors' single count.

---

### Is what we have enough for the extra polish? — Verdict

**Yes — the data is sufficient; the remaining work is UI, not API.** Everything the *differentiating* whitespace needs (article cards, PDF/document carousels, multi-image, reposts, reaction breakdown) is already in both providers' responses and already normalized. Carousel layout and the post-detail popup are **already built**; the remaining polish gaps (light/dark presets, load-more, in-card image carousel, inline PDF preview, live customizer, moderation/filtering) are **all front-end features buildable on the data we have** — no new provider or endpoint required.

**Three data-side caveats that do constrain polish:**
1. **Media expiry** (§2) — must re-host media locally before any of this ships, or feeds rot in 1–3 weeks. *Hard blocker, not polish.*
2. **fresh-profile video has no poster** (§3) — video tiles look blank pre-play. For a video-rich, poster-perfect feed, prefer `fresh-scraper` (or generate posters server-side).
3. **fresh-profile single image resolution** (§4) — fine for normal tiles; for large "highlight"/hero tiles or retina, `fresh-scraper`'s resolution ladder is crisper.

**Net:** for a polished, LinkedIn-*native* feed (the winning angle), **`fresh-scraper` is the stronger base** (video posters, true aspect ratios, multi-resolution images, logo-inline). `fresh-profile` is a capable fallback whose only real display deficits are video posters and image resolution — both worked around. Neither provider blocks the polish roadmap; only media re-hosting does.

---

## 7. Open items for implementation (so we don't rediscover)

1. **Media re-hosting** (required for production) — download/cache LinkedIn CDN media locally; hook `linkedin_feeds_localize_media`. Without it, feeds break in ~1–3 weeks.
2. **fresh-profile video posters** — none available from the API; either accept blank video tiles, generate a poster from the first frame server-side, or prefer `fresh-scraper` for video-heavy feeds.
3. ~~Lightbox extension / post-detail popup~~ — **done** (image lightbox + click-card post-detail popup, keyboard-accessible). Carousel layout also **done**.
4. **Document preview** — currently a link; an inline PDF/first-page thumbnail is a polish option.
5. **Pagination / "load more"** — both providers paginate; not yet surfaced in the shortcode UI.
6. **Per-feed-type logo cost (fresh-profile)** — company logo = cached 2nd call; keep the cache warm.
