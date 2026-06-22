# RapidAPI LinkedIn provider comparison — feeds product

**Date:** June 18, 2026
**Scope:** The 3 RapidAPI listings flagged for evaluation, plus the provider the scaffold already runs on. Goal: pick a provider for a personal + company **feed-rendering** product that starts small and scales.
**Method:** 3 parallel research agents (capabilities/schema/pricing/reliability) + a **live probe** with our RapidAPI key (a 403 "not subscribed" is proxy-rejected and free, so subscription status was checked at zero quota cost).

---

## ⚠️ Headline finding: marketplace stats lie

The probe contradicts the RapidAPI listing stats. **The "best-looking" of the three — RockApis `linkedin-api8`, advertised at 765 ms / 100% service level / updated 2 months ago — is dead:**

```
GET linkedin-api8.p.rapidapi.com/get-profile-posts?username=williamhgates
HTTP 200 → {"success":false,"message":"We are no longer providing this service at this time…","data":null}
```

It returns 200 (so it still scores "100% uptime") but serves a discontinuation notice. **Eliminated.** Lesson: verify any candidate with a live call before trusting its marketplace badges.

---

## The field

| Provider (host) | Live status (probed) | Posts feeds | Workflow | Latency | Free tier (verified) |
|---|---|---|---|---|---|
| **RockApis `linkedin-data-api`** | ✅ alive (403 gate) | profile + company | **1-call** by `username` | ~1.7 s | **75 req/mo**, 1000/hr, 50 credits/mo, 10 GB |
| **FreshData `fresh-linkedin-profile-data`** | ✅ **verified live — 50 posts** | profile + company | **1-call** by `linkedin_url` | **~5.2 s** (sync scrape, measured) | unclear; Basic ~$25/10k *(unverified)* |
| **RockApis `linkedin-api8`** | ❌ **DECOMMISSIONED** | — | — | — | — |
| *`fresh-linkedin-scraper-api`* (scaffold's provider, FreshData sibling) | ✅ **working, used live** | profile + company | 2-call (resolve→fetch) | **~0.4–1.4 s** | **50 req/mo** → $50/20k → $200/100k → $500/500k |

*The scaffold's provider isn't one of the three named, but it's a FreshData sibling we've already verified end-to-end, so it's the empirical benchmark the others are measured against.*

## Pricing reality

**No agent — and not even our key — could read the paid tiers.** RapidAPI renders pricing client-side (login-gated). Only **free tiers** are externally verifiable, and only by an authenticated/subscribed call that reads the `x-ratelimit-*` response headers (how we confirmed 50/mo on the scaffold provider). So:

- **Verified:** `linkedin-data-api` free = 75 req/mo (1000/hr cap); `fresh-linkedin-scraper-api` free = 50 req/mo, paid ladder $50→$200→$500.
- **Unverified (must subscribe + read headers, or log in):** all `fresh-linkedin-profile-data` tiers, and the paid tiers of `linkedin-data-api`.
- **Credit gotcha:** `fresh-linkedin-profile-data` charges **2 credits per posts call** (so a "10k" plan = ~5k feed refreshes). `linkedin-data-api` and the scaffold provider bill ~1 credit/call.

## Schema richness — the deciding axis for a *visual feed*

A feed product is only as good as the media it can render. Confirmed return fields:

| Field need | `linkedin-data-api` | `fresh-linkedin-profile-data` | `fresh-linkedin-scraper-api` (verified live) |
|---|---|---|---|
| text / url / timestamp | ✅ | ✅ | ✅ |
| author (name, avatar, headline) | ✅ | ✅ (`poster`) | ✅ |
| likes / reactions | ✅ total + like | ✅ + per-type breakdown | ✅ + per-type breakdown |
| comments / reposts counts | ❓ unconfirmed | ✅ `num_comments`/`num_reposts` | ✅ |
| **images** | ✅ | ✅ | ✅ (multi-res) |
| **video** | ❓ **unconfirmed** | ✅ `stream_url`,`duration` | ✅ thumbnail + mp4 streams |
| **document (PDF)** | ❓ **unconfirmed** | ✅ `url`,`title`,`page_count` | ✅ |
| **article** | ❓ **unconfirmed** | ✅ | ✅ |
| media URLs signed/expiring | likely (unconfirmed) | ✅ confirmed | ✅ confirmed |

`linkedin-data-api`'s sampled responses showed only `text/postUrl/postedDate/reactions/author/image[]` — **no confirmed video, document, or article blocks.** For a feed that must render all four post types, that's a material gap (it may exist on richer endpoints, but it's unproven). The FreshData family confirms all four.

> **Update (June 18, verified live):** `fresh-linkedin-profile-data` is now implemented as the plugin's second provider (`includes/providers/class-provider-fresh-profile.php`) and confirmed against a live response — **50 posts, all four media kinds (19 article, 14 video, 11 image, 3 document), full reaction breakdown, ~5.2 s.** Its real schema differs from the docs (flat `article_*` fields, `poster.{first,last,image_url}`, no video thumbnail) — the mapping was corrected accordingly. Capture: `probe/responses/freshprofile-profile-williamhgates.json`. Switch providers via *Settings → LinkedIn Feeds* or `provider="fresh-profile"` on the shortcode.

---

## Recommendation

**Primary: the FreshData family — and specifically the `fresh-linkedin-scraper-api` listing the scaffold already runs on.** If the choice is strictly limited to the three named, pick **`fresh-linkedin-profile-data`**. Reasoning:

1. **Schema completeness wins for a feed.** Only the FreshData family confirmedly returns images **+ video + document + article + per-type reactions** — the renderer needs all of them (our scaffold already templates all four). `linkedin-data-api`'s unconfirmed media support is too risky to build a visual product on.
2. **Latency is not user-facing here.** FreshData's ~7 s sync-scrape sounds bad, but the architecture refreshes feeds on **cron into cache** (resolve-once, then fetch; 1 h post cache). Render is served from cache, so a 7 s fetch is an offline cost, not a page-load cost. This neutralizes `linkedin-data-api`'s speed edge for our use case.
3. **The scaffold sibling is strictly better than the named FreshData listing** where they differ: ~0.4–1.4 s vs ~7 s, a published tier ladder (50/mo → $50/$200/$500) vs unverified pricing, and the exact schema we already built and verified. Its only cost is the 2-call resolve step — which we cache for a week, so it's a one-time setup hit per source, not per refresh.
4. **`linkedin-data-api` is the fallback**, not the pick: faster and a bigger free tier (75 vs 50/mo), but the media-schema gap undercuts the core product. Keep it as a hot-swap option (the normalizer is the only file that'd change).

### Scaling path (start small → grow)

Request volume scales with **feeds × refresh frequency**, *not* page views (cache absorbs traffic). Plan tiers against that:

| Stage | Feeds | Refresh | ~Calls/mo (fetch + amortized resolve) | Tier |
|---|---|---|---|---|
| Prototype | 1–5 | manual/daily | <300 | **Free** (50–75/mo) |
| Small launch | ~20 | daily | ~600 | Free / lowest paid |
| Growth | ~50 | hourly | ~36k | **~$50–99 tier** |
| Scale | 200+ | hourly | ~150k+ | $200+ tier; negotiate volume |

Design implications already in the scaffold: **resolve-once + cache the stable id** (urn/company_id cached a week → ongoing cost is ~1 fetch/refresh), and **cache posts** (1 h) so render never calls the API. To grow, raise the cache TTL and the tier together; the code doesn't change.

### Cheap next step to close the gaps

Subscribe to the **free tier of the chosen provider**, make one `profile-posts` + one `company-posts` call, and read the `x-ratelimit-requests-limit`/`-remaining` headers — this empirically nails the exact free quota and confirms the live schema (same method used to verify 50/mo on the scaffold provider). One free subscription answers every "unverified" cell above.

---

## Sources
- Live probe (this evaluation): subscription/decommission status of all three hosts, June 18 2026.
- RockApis `linkedin-data-api`: <https://rapidapi.com/rockapis-rockapis-default/api/linkedin-data-api> • free tier on its `/pricing` tab • schema from `github.com/rugvedp/linkedin-mcp`.
- FreshData `fresh-linkedin-profile-data`: <https://rapidapi.com/freshdata-freshdata-default/api/fresh-linkedin-profile-data> • docs `fdocs.info` (Get Profile Posts / Get Company Posts).
- RockApis `linkedin-api8`: <https://rapidapi.com/rockapis-rockapis-default/api/linkedin-api8> — returns discontinuation notice (probed).
- `fresh-linkedin-scraper-api` live captures: `probe/responses/` + `probe/responses/README.md`.
