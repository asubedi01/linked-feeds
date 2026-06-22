# LinkedIn Feeds — API Exploration Findings

**Date:** June 10, 2026
**App used:** ClickSocial (client ID `783vnel7uw3ggw`) — pivoted as research vehicle
**Question answered:** Can we build an Instagram-Feeds-style product (user OAuths → app fetches their posts → posts display on their website)? Is personal-feed display feasible, or is this org-pages-only?
**Companion docs:** [PITCH.md](./PITCH.md) • [RESEARCH.md](./RESEARCH.md) • [probe toolkit](./probe/README.md)

---

## Direct answer

**Personal member feeds: not feasible via the official API.** The only scope that reads a member's own post content (`r_member_social`) is a **closed permission** — LinkedIn's current docs state: *"We're not accepting access requests at this time due to resource constraints."* It is not on the ClickSocial app, cannot be requested, and no other scope substitutes (details in §2).

**Organization (company page) feeds: technically feasible, contractually prohibited.** `r_organization_social` is on the app and `GET /rest/posts?author=urn:li:organization:{id}` works — but LinkedIn's Restricted Uses policy **explicitly bans the exact product**, verbatim:

> **"No Social Feeds:** Under our Marketing API Terms, none of the data provided via our Community Management APIs can be used in a social feed use case (e.g. to display a feed of LinkedIn company updates on the company's website or intranet)."
> — [Restricted Uses of LinkedIn Marketing APIs and Data](https://learn.microsoft.com/en-us/linkedin/marketing/restricted-use-cases?view=li-lms-2026-05) (verified live, June 10, 2026)

So unlike Instagram/Facebook/TikTok, the gap here isn't technical capability — it's that **LinkedIn has named our product category as an unapproved use case.** This finding supersedes the "viability signal is strong" conclusion in RESEARCH.md §7 (see §5, Corrections).

---

## 0. Empirical probe results (June 10, 2026 — live, ClickSocial app)

OAuth flow completed against the real app; granted scope string: `r_basicprofile,r_member_postAnalytics,r_organization_social,r_organization_social_feed,rw_organization_admin`.

| Probe | Result | What it proves |
|---|---|---|
| `token` exchange | **200** — `access_token` (`expires_in` 5,183,999s ≈ 60 days) **and `refresh_token`** (`refresh_token_expires_in` ≈ 365 days) | App is enabled for programmatic refresh (MDP/CMA partner). Silent refresh works for a year; annual interactive re-auth is the confirmed floor. |
| `me` (`/v2/me`) | **200** — full profile (name, headline, vanityName, person id) | `r_basicprofile` still functional despite deprecation notice |
| `orgs` (`organizationAcls`) | **200** — initially `elements: []` (old test account); **re-run June 16 with an account holding ADMINISTRATOR on real pages → 2 orgs returned** (`105508535`, `106536255`), `roleAssignee` `urn:li:person:Te-frTXoSC` | Endpoint + scope work; discovery returns admin pages as a source picker would. |
| `org-posts` (`/rest/posts?author=urn:li:organization:{id}&q=author`) | **200** (June 16) on **both** admin pages — `105508535` (`total: 13`) and `106536255` (`total: 2`, an `organizationBrand`/showcase URN). Full `commentary`, `content` (article `thumbnail`/`source`/`title`, video `media` URN), `publishedAt`/`lastModifiedAt`, `visibility: PUBLIC`, pagination `next` link | **Last empirical box ticked.** Reading company-page post *content* with the app's existing scopes is confirmed working across standard org and brand/showcase pages — org feeds are technically feasible. (Still contractually banned by "No Social Feeds"; see §2.) |
| `member-posts` (`/rest/posts?author=urn:li:person:…&q=author`) | **400** — `"Member permissions must be used when using member as author"` (re-confirmed June 16 with the page-admin account, person `Te-frTXoSC`) | **The key test, settled empirically:** none of the granted scopes — including `r_member_postAnalytics` — unlocks reading a member's own posts, even for an account that admins pages. PITCH.md's "does 'your posts' include content?" ambiguity is closed: it does not. Personal feeds are dead via this app. |
| `post-analytics` (`memberCreatorPostAnalytics`) | Initially **400** `QUERY_PARAM_NOT_ALLOWED` (probe bug: `metricType` → `queryType`). **Re-run June 16 → 200**, `{count: 670, metricType: IMPRESSION}` — metrics only, no content fields | **Decisive positive control.** Proves the token *can* read member-scoped data and the member *has* posts (670 lifetime impressions). Yet `member-posts` on the same member returns 400 — so the personal-feed block is a permission wall (`r_member_social` absent), **not** an empty feed. Member content is unreadable; only aggregate metrics are exposed. |

**Second app verified (June 11):** the dev counterpart **SB CS DEV** (client ID `783fpfd98xix6a`) returns the identical granted scope string and a refresh_token — the two apps are interchangeable for this evaluation, with the same gaps (no `r_member_social`, no DMA scope). Remaining probes should run on SB CS DEV to keep the production ClickSocial app out of the experiment.

~~Remaining empirical gap: `org-posts` against a company page Asmita admins.~~ **Closed June 16, 2026.** Using an account with ADMINISTRATOR role on real pages, `org-posts` returned **HTTP 200 with full post content** (org `105508535`, 13 posts). The evaluation is now empirically complete on every probe: org feeds are technically readable (and contractually banned), personal feeds are not readable at all. Nothing further to test on this app.

## 1. What the ClickSocial app's scopes actually give us

| Scope (on app) | Feed-product value | Verified status |
|---|---|---|
| `r_organization_social` | Read org posts/comments/reactions — the technical core of an org feed | Works; requires authenticating member to be page ADMIN / DSC poster / content admin |
| `r_organization_social_feed` | Engagement data on org posts | Supplementary |
| `rw_organization_admin` | Org-page discovery (`/rest/organizationAcls?q=roleAssignee`) — source picker | Works |
| `r_member_postAnalytics` | Member post **metrics only** (impressions, reactions, comments…) via `/rest/memberCreatorPostAnalytics`. Response is `{targetEntity, metricType, count, dateRange}` — **no commentary/content fields** | Documented; PITCH.md's open question ("does 'your posts' include content?") is answered: **no** per docs. The probe's `member-posts` command tests the residual ambiguity empirically |
| `r_member_profileAnalytics`, `r_1st_connections_size`, `r_basicprofile` | Profile metadata / analytics — header display at most | `r_basicprofile` deprecated; migrate to OIDC |
| `w_*` scopes (member + org) | Not needed — we read | — |

**Not on the app and not obtainable:** `r_member_social`. Closed to all new applicants ([CMA overview FAQ](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview?view=li-lms-2026-05)); absent from the [Increasing Access](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access?view=li-lms-2026-05) permission table, so even approved CMA partners don't receive it. RESEARCH.md's hope that it could be a "scope expansion on an approved app" is off the table.

**EEA-only side door, not viable:** the Member Data Portability API (DMA) lets third-party apps fetch a member's posts — but only for **EEA members**, only forward-from-consent for changelog data (28-day query window), under separate purpose-restricted terms. Not a foundation for a global feed product.

## 2. The limitation stack (why this isn't Instagram Feeds)

Ordered by severity:

1. **"No Social Feeds" restricted use** — the product category itself is banned for Community Management API data, org feeds included. Violation cost: API access revocation, which would also take down **ClickSocial's production posting integration** (same app — this risk extends to any shared developer app).
2. **48-hour storage cap** on member social activity data ([Data Storage Requirements](https://learn.microsoft.com/en-us/linkedin/marketing/data-storage-requirements)) — our cache-in-WP-database architecture conflicts with it directly.
3. **Limited Audience rule** — member data obtained to manage a Page/Profile may only be displayed "to individuals associated with that Page or Profile." A public website visitor doesn't qualify. This independently blocks public display even if caching were solved.
4. **`r_member_social` closed** — personal feeds are dead regardless of items 1–3.
5. **Standard-tier gate** — Development tier: 500 calls/app/day, 100/member/day, must upgrade within 12 months via manual review (screencast of each use case + test credentials). A feed plugin's screencast would show LinkedIn the exact use case they prohibit — review is where this dies even if we tried.
6. **Token lifecycle** — 60-day access / 365-day refresh, hard annual re-auth per customer. Programmatic refresh **confirmed working** on this app (refresh_token returned in live probe), so the 60-day expiry can be handled silently — annual re-auth remains the floor.
7. **Versioning tax** — monthly versions, sunset at ~12 months (202505 already sunset). Annual-minimum maintenance commitment per shipped plugin.
8. **Unpublished rate limits** — standard-tier per-endpoint limits visible only in the developer portal after making calls; capacity planning is empirical.

## 3. How competitors do it (and why that's not a path)

EmbedSocial, Taggbox/Tagembed, Elfsight, SociableKIT, Juicer all sell "LinkedIn feed widgets." None has found a compliant method we're missing — they fall into the two camps already described: **scraping**, or the **"No Social Feeds" ToS gray zone**. The diagnostic is simple: the official API can only read a **company page you OAuth into as an admin**. It has *no* capability to read an arbitrary personal profile, a profile/page by pasted URL, a hashtag, or a group. So any product offering those is, by definition, not using the official API for them.

**Per-plugin mechanism (investigated June 16, 2026):**

| Plugin | How it sources data | Verdict |
|---|---|---|
| **Elfsight** | No API key, no OAuth, no developer setup — paste a **public** profile or company-page URL; pulls posts on a 48-hour cache. Works on personal profiles. | **Scraping.** No OAuth + arbitrary public profiles by URL is only possible by scraping; Elfsight does *not* claim official-API use. |
| **Juicer** | Source picker takes Company/School (page URL), **Personal (profile name)**, Hashtag, Group. Markets itself as pulling LinkedIn "without the complexity of LinkedIn's official API." | **Scraping.** Personal-by-name, hashtags, and groups are impossible via the official API. (LinkedIn is also Juicer's flakiest source — it has a dedicated "limitations" help article, typical of periodically-blocked scraping.) |
| **Tagembed** | Markets "official LinkedIn API, no scraping, compliant." But also offers **personal-profile** and **hashtag** feeds. | **Mixed + overstated claim.** Only the **company-page** feed (OAuth + admin → `r_organization_social`, the call we verified) could be official API. Personal/hashtag feeds cannot be — so the blanket "no scraping" claim doesn't hold. And the company-page path still displays a company feed on a website = the exact "No Social Feeds" prohibition. |

The two camps map exactly onto our findings. URL-paste / no-login products (Elfsight, Juicer, Tagembed's personal + hashtag feeds) are **scraping** — the posture we ruled out for Smash Balloon's brand and WordPress.org standing. The OAuth/admin company-page products are doing the officially-prohibited "No Social Feeds" use case, relying on lax enforcement or private LinkedIn arrangements that aren't public or available to us. Vendor marketing ("official API," "compliant") consistently overstates what the underlying capability and terms actually allow. **Net: there is no fourth, clean method here — this reinforces the *not viable* verdict.**

Scraping is a worsening bet: LinkedIn sued Proxycurl (Jan 2025); Proxycurl **shut down July 4, 2025**. Apollo.io and Seamless.AI had their LinkedIn pages removed in March 2025. Smash Balloon's brand and WordPress.org standing make scraping a non-starter.

*Sources: [Tagembed LinkedIn widget](https://tagembed.com/linkedin-widget/), [Tagembed API guide](https://tagembed.com/blog/linkedin-official-api/), [Elfsight LinkedIn widget](https://elfsight.com/linkedin-feed-widget/wordpress/), [Elfsight source setup](https://help.elfsight.com/article/1629-step-1-setting-the-source-of-your-linkedin-feed), [Juicer add sources](https://www.juicer.io/blog/how-to-add-social-media-sources-to-your-juicer-feed), [Juicer LinkedIn limitations](https://help.juicer.io/hc/en-us/articles/360040406391-LinkedIn-sources-Adding-troubleshooting-and-limitations).*

## 4. Opportunities — what *could* ship

In descending order of compliance confidence:

**A. Curated-embeds plugin (ToS-clean).** LinkedIn officially supports single-post iframe embeds (`linkedin.com/embed/feed/update/{urn}`) for public posts. A plugin where users paste post URLs and we render a styled wall/grid of official embeds works for **both personal and company posts**, no API, no auth, no storage of LinkedIn data. Limitations: manual curation (no auto-discovery — that would need the closed read API), iframe styling constraints, no oEmbed endpoint so UX is paste-the-URL. Honest framing: a "LinkedIn post showcase," not a live feed. Low build cost; could validate demand for the category.

**A2. Publish-and-display loop (scope-grounded, ToS-defensible — the strongest "feeds" wedge).** The write scopes (`w_member_social`, `w_organization_social`) are unrestricted in ways the read scopes aren't — including **personal** posting. A plugin that publishes to LinkedIn from WordPress already holds the content, media, and the post URN returned at creation; the site can then render a "our LinkedIn posts" feed **from its own local copies** — no read API call, no CMA-provided data displayed, deep links (or official embeds) to the live posts. The social-feed ban covers "data provided via our Community Management APIs"; customer-authored content originating in their own WP composer isn't that (engagement counts would be CMA data — omit them or keep them admin-side). Limitation: shows only posts made through the plugin, not native-LinkedIn posts — acceptable for SMB pages where the site is the content source, and it's the only route that covers personal profiles. Needs a legal sanity check, but it's a materially different posture than fetch-and-display.

**B. Org analytics dashboard (compliant use case).** The allowed purpose of these APIs is *managing* Pages/Profiles, displayed to people associated with them. A **wp-admin-side** LinkedIn analytics product (org post performance, follower growth, member post analytics via `r_member_postAnalytics`) fits the rules. It's a different product than feeds — closer to ClickSocial's territory than Smash Balloon's — but it's what the granted scopes are actually *for*.

**C. Partnership exception (long shot).** Ask LinkedIn directly — through ClickSocial's existing partner relationship — whether a feed use case can be licensed. Some competitor "official API" claims suggest exceptions may exist. Cost: one conversation. Expectation: low, given the prohibition is explicit and recently reaffirmed (page updated Aug 2025).

**C2. Personal-feed salvage paths (pursued June 2026, ordered by actionability):**

1. **Add the "Member Data Portability (3rd Party)" product to the app — the only self-serve route to member post content.** Requestable from the app's **Products tab** ([docs](https://learn.microsoft.com/en-us/linkedin/dma/member-data-portability/member-data-portability-3rd-party/)): requires (a) company-page association verified by the page's super admin, (b) business verification (legal name, registered address, website, privacy policy, business email — Awesome Motive's details), (c) LinkedIn review of the access form. Grants `r_dma_portability_3rd_party`. The **Member Snapshot API** returns the member's historical **Posts** domain data; the Changelog API archives new posts from consent forward (28-day query window). **Hard catches:** consent is **EEA members only** (note: UK is *not* EEA — the team's UK-IP test-account trick doesn't help here; use an Irish/German test identity), and the data is governed by separate [Portability API Terms](https://www.linkedin.com/legal/l/portability-api-terms) tied to data-portability purposes. The wedge worth a legal read: "member ports their own posts to their own website" is arguably closer to portability than to the Marketing-API social-feed ban — unsettled, but it's a *different* contract than the one that prohibits feeds. Even at best this is an EEA-only product slice.
2. **Ask LinkedIn directly, through two channels at once.** (a) A [Developer Support Portal](https://www.linkedin.com/help/linkedin/ask/dsapi) ticket asking whether `r_member_social` can be granted to an existing approved CMA app, and on what conditions — at minimum this gets the "no" in writing for the evaluation. (b) ClickSocial's partner channel (whoever handled the CMA approval): the July 2025 analytics cohort (Hootsuite, Buffer, Metricool) proves LinkedIn selectively opens member-level data to social-management partners when it suits them. Frame the ask narrowly: *authenticated members showcasing their own posts on their own site* — own data, own audience, no third-party profiles.
3. **Request scope additions on the existing app** (Products tab → product access forms): nothing currently listed grants member post reads, but the portal's product list is the place changes surface first. While in there, add **"Sign In with LinkedIn using OpenID Connect"** to replace deprecated `r_basicprofile`.
4. **Grandfathered-partner angle (weakest).** Some legacy apps still hold `r_member_social`. Riding on one (partnership/licensing) is theoretically possible but builds the product on someone else's grant — fragile, and likely still subject to the social-feed restriction.

**D. Watch for policy change.** LinkedIn opened member *analytics* to third parties in July 2025 (Hootsuite, Buffer, Metricool cohort) — first member-level loosening in years. If `r_member_social` reopens or the social-feed restriction lifts, this evaluation's technical groundwork (probe toolkit, architecture plan) makes us fast to move. Recheck cadence: quarterly.

## 4b. Fresh-app path (independent of ClickSocial)

Explored June 11. A new app changes **independence, not the permission ceiling** — the walls are policy, not app history.

**Steps:** company page with super-admin access → create app on developer.linkedin.com → verify page association (Settings tab; unverified apps see a near-empty Products tab — observed on both ClickSocial apps, whose curated partner catalogs show only the CMA upgrade) → add "Sign In with LinkedIn using OpenID Connect" (self-serve; new apps can't get legacy `r_basicprofile`) → apply for **Community Management API** (vetted, no SLA, ~1–4 weeks historically; grants `r_organization_social` at Development tier: 500 calls/app/day, 100/member/day, 12-month clock to Standard) → apply for **Member Data Portability (3rd Party)** (business verification; grants `r_dma_portability_3rd_party`, EEA-consent-only).

**Read-only permission targets:** `r_organization_social` + `r_organization_social_feed` + `rw_organization_admin` (org feeds; via CMA), `openid profile email` (identity), `r_dma_portability_3rd_party` (EEA personal posts; via DMA). **Not obtainable by any new app:** `r_member_social`.

**The catch:** the CMA application and the Standard-tier screencast review both examine the use case — and a website feed is the *named* banned use case. Applying dishonestly defers rejection to the Standard review (≤12 months) with revocation risk; applying honestly is effectively a formal exception request. The latter is the value: **LinkedIn's official answer in writing**, which is either the unblock or the decisive no-go evidence.

**Draft use-case statement for an honest CMA application:**

> *[App] lets a LinkedIn Page administrator display their organization's own posts on the organization's own WordPress website. Access is authorized by the Page admin via OAuth; the app retrieves only posts authored by that admin's organization; post data is cached at most 48 hours; no member profile data is stored, combined, or transferred, and no data beyond the page's own published posts is accessed. We're aware of the social-feed restriction in the Restricted Uses policy and are requesting guidance on whether this narrow configuration — an organization re-publishing its own content to its own audience — can be approved, and under what conditions.*

## 5. Corrections to the April docs

- **RESEARCH.md §7 "viability signal is strong"** — no longer supported. The research correctly mapped endpoints, scopes, tokens, and versioning but did not surface the Restricted Uses page; the decisive constraint is contractual, not technical.
- **RESEARCH.md §1.3 / PITCH.md** — treating `r_member_social` as a request-able stretch goal: it's a closed permission, not grantable even to approved partners.
- **RESEARCH.md §3.2 relay recommendation** — the ClickSocial relay/Secure Bubble solves token security but does nothing for the use-case prohibition, the 48h cap (the WP-side post cache is still storage), or the limited-audience rule.
- **PITCH.md "Questions, Comments, Concerns"** — the `r_member_postAnalytics` ambiguity is resolved per docs (metrics only); the probe's `member-posts` command settles it empirically.

## 6. Recommendation for the Q3 decision

**No-go on "LinkedIn Feeds" as a classic feed plugin** — both personal (API closed) and org (use case prohibited, storage cap, audience restriction, Standard-tier review would surface the violation, and a violation endangers ClickSocial's production app).

Options to bring to stakeholder review:

1. **No-go + watch** (default): document the unblock path (`r_member_social` reopening or social-feed restriction lifting), recheck quarterly. Cost: ~0.
2. **Pivot to curated-embeds plugin** (Opportunity A): ships something in the "LinkedIn on your website" space, ToS-clean, modest scope. Validate demand before committing a rock.
3. **Pursue partnership exception** (Opportunity C) in parallel with either — one conversation via the ClickSocial relationship, before any build.

**Probe status** (§0): member-post read rejection and refresh-token availability are confirmed live. The one outstanding probe is `org-posts` against a company page the test account admins — set up admin access on a test page and re-run before the week-8 stakeholder review.

**Housekeeping:** the client secret, an access token, and a refresh token were shared in chat / pasted into probe/README.md during this exploration — rotate the secret in the developer console and revoke the token (remove the app at `linkedin.com/mypreferences/d/data-sharing-for-permitted-services`) when probing is done. Scrub the tokens from probe/README.md before committing.

---

## 7. Path 3 — RapidAPI / third-party scraper APIs (explored June 17–18, 2026)

This section evaluates a **fundamentally different route** from §0–§6: not LinkedIn's official API, but the third-party data APIs sold on the [RapidAPI](https://rapidapi.com) marketplace. The question driving this exploration (per stakeholder direction): *while the official route is pursued in parallel, can a RapidAPI provider give us the data — and the response fields — to actually build a feed?* The go/no-go on **using** RapidAPI is a stakeholder decision; this section establishes only what's technically possible and what the data looks like.

### 7.1 What these APIs are (and the one thing to keep straight)

They are resellers/aggregators that **scrape** LinkedIn and expose it as a clean JSON API authenticated by a single `x-rapidapi-key` header — **no OAuth, no LinkedIn app, no admin role.** You point them at a public profile or company by id/URL and get recent posts back. This is the same underlying mechanism as Elfsight/Juicer (§3); RapidAPI just packages it as a metered API.

**Terminology that matters for the product spec:**

| "Feed" the stakeholder means | What RapidAPI delivers | Verdict |
|---|---|---|
| A **person's** authored public posts (their profile activity) | ✅ `profile-posts` / "Get User Posts" — by username or public id | Buildable |
| An **organization's** page posts | ✅ `company-posts` / "Get Company Posts" — by company id | Buildable |
| The algorithmic **home timeline** ("who I follow") | ❌ No provider offers this | N/A — and not what a Smash-Balloon-style widget needs |

So both feeds the brief asks for (personal + org) **are technically reachable** here — the literal product spec the official API could not satisfy.

### 7.2 Provider landscape (June 2026)

| Provider (RapidAPI host) | Profile posts | Company posts | Pricing | Reliability signal |
|---|---|---|---|---|
| **Fresh LinkedIn Scraper API** (`fresh-linkedin-scraper-api`) | ✅ | ✅ | Free 50/mo → $50 (20k) → $200 (100k) → $500 (500k); 20–300 req/min by tier | Claims "98% service level"; richest documented response |
| **Real-Time LinkedIn Scraper / `linkedin-data-api`** (RockApis) | ✅ | ✅ | ~$0–$300/mo tiers | "No SLA"; widely used |
| **Generic marketplace scrapers** (dozens) | varies | varies | $10–$30/mo, some free | "many go offline without notice"; break when LinkedIn changes HTML |

Off-RapidAPI for reference: **Proxycurl** is dead (LinkedIn suit → shut down July 2025); **Netrows** (~€49/mo) and **PhantomBuster** ($69+/mo, high ban risk) are direct vendors, not RapidAPI.

### 7.3 Response fields — can we build a feed from them? (LIVE-VERIFIED June 18, 2026)

**Confirmed with real calls** against Fresh LinkedIn Scraper API — payloads saved to `probe/responses/` (data contract: `probe/responses/README.md`):
- Personal feed: `user-profile williamhgates` → `urn` → `profile-posts` = **20 posts** (8 article, 5 video, 4 image, 1 document).
- Org feed: `company-posts 1035` (Microsoft) = **10 posts** (document, image, video).
- **Both feeds returned the identical post schema** — a renderer built for one handles the other; only `author` differs (person vs company). 4 of 50 monthly requests used.

Wrapper: `{ success, message, process_time, data: [...], page, total, has_more }`. Each post object (verified):

| Response field | Type | Feed-render need it satisfies |
|---|---|---|
| `id` / `share_urn` | string | Stable key / dedupe / official-embed deep link |
| `text` | string | **Post body** ✅ |
| `url` | string | "View on LinkedIn" link / embed target ✅ |
| `created_at` | ISO timestamp | **Sort + "x days ago"** ✅ |
| `content.images[]` | array | **Image posts / grid thumbnails** ✅ |
| `content.video` | object | Video posts ✅ |
| `content.document` | object | Document/PDF carousel posts ✅ |
| `content.article` | object (`title`, `subtitle`, `url`, `images`) | **Link-preview cards** ✅ |
| `activity.num_likes` / `num_comments` / `num_shares` | int | **Engagement counts** ✅ |
| `activity.reaction_counts[]` | array (`type`, `count`) | Reaction breakdown (LIKE/PRAISE/EMPATHY…) ✅ |
| `author` (`name`, `id`, `url`, `follower_count`, avatar) | object | **Feed header / per-post byline** ✅ |
| `has_more` / `page` / `total` | wrapper | **Pagination / "load more"** ✅ |

**Conclusion on buildability:** the field set is *more* than sufficient for a Smash-Balloon-style feed — text, media (image/video/doc/article), timestamps, author, engagement, and pagination are all present and confirmed live. A grid/list/masonry layout, lightbox, and "view on LinkedIn" all map directly to returned fields. **There is no data gap** — both personal and org feeds are renderable from one provider's response.

**One architecture-shaping caveat (from the live data):** every `media.licdn.com`/`dms.licdn.com` URL — avatars, images, video thumbnails/streams, document PDFs — is a **signed CDN link with an `expires_at`** (Unix ms, ~weeks out). Text/url/engagement fields are durable; media is not. A cache-and-display plugin must **download and re-host media locally** (or proxy via WP) at fetch time and refresh on a schedule, rather than hotlinking. (Note this is independent of — and milder than — the §6 48h cap, which is a LinkedIn *terms* constraint on the official API, not this scraper route.)

### 7.4 Live probe — run, payloads captured

`probe/rapidapi-probe.php` mirrors the official-API probe toolkit and was run live June 18, 2026. Personal feed is a **two-call workflow**, org feed is one call:

```bash
cd probe
export RAPIDAPI_KEY=...                                        # rapidapi.com; free tier = 50 calls/mo
export RAPIDAPI_HOST=fresh-linkedin-scraper-api.p.rapidapi.com
php rapidapi-probe.php user-profile  williamhgates             # step 1 → data.urn
php rapidapi-probe.php profile-posts ACoAAA8B…3hc              # step 2 → personal feed (urn from step 1)
php rapidapi-probe.php company-posts 1035                       # org feed (numeric company_id)
```

Each 200 response auto-saves to `probe/responses/<cmd>-<id>.json` and prints quota headers (`x-ratelimit-requests-remaining`) + the first-post field keys. **Captured samples and the full data contract live in `probe/responses/README.md`.** A 403 "not subscribed" is proxy-rejected and costs no quota. *(Endpoint paths/params drift per provider; on a 404 check the provider's RapidAPI "Endpoints" tab and adjust `PATHS[]`.)*

⚠️ The RapidAPI key was shared in chat — unsubscribe/rotate it after this exploration (rapidapi.com → app → security), same hygiene as the LinkedIn secret in §6.

### 7.5 The caveat that survives all of the above

RapidAPI **closes the technical gap** the official API could not — but it does **not** change the §6 verdict, because every byte it returns is **scraped**:

1. **It's scraping, packaged.** The diagnostic in §3 holds: the official API cannot read an arbitrary profile/company by URL, so anything that does (every provider here) is scraping. The clean JSON hides the mechanism; it doesn't change it.
2. **Violates LinkedIn's User Agreement** anti-scraping clause — a *separate, additional* exposure on top of the Marketing-API "No Social Feeds" ban, not a way around it.
3. **Brand/standing risk** is the same non-starter §3/§6 already named for Smash Balloon and WordPress.org — now with the data sourced from a third party who can vanish ("providers go offline without notice") or be sued out of existence (Proxycurl, July 2025), taking every customer's feed down with them.
4. **Reliability:** scrapers break when LinkedIn changes markup; "no SLA" on most. A shipped plugin would inherit that fragility per-customer.

**Net for §7:** *technically yes — both feeds are buildable and the response fields are complete (§7.3).* *Strategically, this is the §3 scraping camp with a metered API in front of it; the compliance/brand objections in §6 apply unchanged.* Recorded as an explored option for stakeholders, not a recommendation. The live probe (§7.4) is ready to capture an actual payload whenever a key is available.

---

## Sources

- [Restricted Uses of LinkedIn Marketing APIs and Data](https://learn.microsoft.com/en-us/linkedin/marketing/restricted-use-cases?view=li-lms-2026-05) — verified live June 10, 2026
- [Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-05) (`r_member_social` "restricted… approved users only")
- [Community Management overview / FAQ](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview?view=li-lms-2026-05) (`r_member_social` closed)
- [Increasing Access — tiers & permissions](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access?view=li-lms-2026-05)
- [Member Post Statistics (`memberCreatorPostAnalytics`)](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/members/post-statistics?view=li-lms-2026-05)
- [Member Data Portability (3rd party, EEA-only)](https://learn.microsoft.com/en-us/linkedin/dma/member-data-portability/member-data-portability-3rd-party/)
- [Data Storage Requirements](https://learn.microsoft.com/en-us/linkedin/marketing/data-storage-requirements) • [Rate Limits](https://learn.microsoft.com/en-us/linkedin/shared/api-guide/concepts/rate-limits) • [Programmatic Refresh Tokens](https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens) • [Versioning](https://learn.microsoft.com/en-us/linkedin/marketing/versioning?view=li-lms-2026-05)
- [Marketing API Terms](https://www.linkedin.com/legal/l/marketing-api-terms) • [API Terms of Use](https://www.linkedin.com/legal/l/api-terms-of-use)
- Proxycurl shutdown: [nubela.co/blog/goodbye-proxycurl](https://nubela.co/blog/goodbye-proxycurl/) • [LinkedIn v. Proxycurl coverage](https://www.socialmediatoday.com/news/linkedin-wins-legal-case-data-scrapers-proxycurl/756101/)
- [Embed Content from the LinkedIn Feed (official single-post embeds)](https://www.linkedin.com/help/linkedin/answer/a529065/embed-content-from-the-linkedin-feed)

**RapidAPI route (§7):**
- [Best LinkedIn Data API Providers Compared (2026) — Netrows](https://www.netrows.com/blog/best-linkedin-data-api-providers-2026)
- [Best LinkedIn API Alternatives 2026 — OutX](https://www.outx.ai/blog/linkedin-api-alternatives-2026)
- [Fresh LinkedIn Scraper API guide (endpoints, pricing)](https://saleleads.ai/blog/linkedin-api-comprehensive-guide-fresh-scraper) • [Company posts response fields](https://saleleads.ai/blog/linkedin-company-posts-scraper)
- [RockApis Real-Time LinkedIn Scraper (`linkedin-data-api`)](https://rapidapi.com/rockapis-rockapis-default/api/linkedin-data-api)
