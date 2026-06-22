# LinkedIn Feeds Evaluation — Research

**Date:** April 2026
**Researcher:** Asmita Subedi
**Purpose:** Gather the facts this pitch depends on — LinkedIn API landscape, what's reusable from ClickSocial, SB feed-plugin primitives, and the real unknowns — so the go/no-go recommendation at the end of the rock rests on evidence rather than optimism.

> ⚠️ **Historical (April 2026). Partly superseded — read alongside current findings.** This doc's "viability is strong" optimism predates the live probes that found the official-API blockers (closed `r_member_social`, "No Social Feeds" ban) and predates the RapidAPI alternative + working prototype. **Current truth: [LinkedIn-Feeds-Verdict.md](./LinkedIn-Feeds-Verdict.md), [FINDINGS.md](./FINDINGS.md), [RAPIDAPI-FINDINGS.md](./RAPIDAPI-FINDINGS.md).** Specific corrections in [FINDINGS.md §5](./FINDINGS.md). Kept for the API-landscape research and context.

---

## 1. LinkedIn API Landscape — what's actually accessible

### 1.1 API products and who they're meant for

LinkedIn's API is fragmented across several "products" that must be individually added to a developer app and approved separately:

| Product | Purpose | Access tier | Relevance to feed plugin |
|---|---|---|---|
| **Sign In with LinkedIn using OpenID Connect** | Identity / authentication only — returns `openid profile email` | Self-serve (Community tier) | Low — gives us *who* the user is, no post data |
| **Share on LinkedIn** | Write personal posts (`w_member_social`) | Self-serve (Community tier) | Irrelevant — we only read |
| **Community Management API** | **Read & write organization posts, comments, reactions** (`r_organization_social`, `w_organization_social`) | **Partner application required**, Development → Standard tier | **This is the product we need** |
| **Marketing Developer Platform** | Ads, sponsored content, analytics | Partner application required | Out of scope |
| **Talent / Learning / Sales** | Recruiter, LMS, CRM integrations | Partner-only, high bar | Out of scope |

**Critical update — app review is NOT required:** The existing **ClickSocial developer app on developer.linkedin.com is already reviewed and approved** as a LinkedIn Partner. The app already has `w_member_social` and `w_organization_social` (write scopes for posting). LinkedIn already recognizes ClickSocial as a legitimate integration. What remains is adding the **read scopes** (`r_member_social`, `r_organization_social`) to the same already-approved app — a scope expansion, not a fresh Partner Program application. This dramatically de-risks the access question.

### 1.2 What we can actually read — the Posts API

The **[Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-03)** (replaces the deprecated `/ugcPosts`) is the endpoint a LinkedIn Feeds plugin would live on:

- `GET /rest/posts?author=urn:li:organization:{id}&q=author&count=10&sortBy=LAST_MODIFIED`
- `GET /rest/posts/{encoded post URN}` — single post
- Batch get, pagination (`start`, `count`, default 10, max 100)
- Required headers: `Linkedin-Version: YYYYMM`, `X-Restli-Protocol-Version: 2.0.0`

Returns JSON with `author`, `commentary`, `content.media`, `content.article`, `lifecycleState`, `visibility`, `publishedAt`, `createdAt`, `id`, plus hashtag/mention tokens embedded in `commentary` text.

**Supported content types on reads (organic posts):** text, images, videos, documents, articles, multi-image, polls, celebration posts. Carousels are **sponsored-only** — not available organically.

### 1.3 Required permissions — and their hard constraints

| Permission | What it unlocks | Constraint that matters |
|---|---|---|
| `r_organization_social` | Read org posts/comments/reactions | Authenticated user must have `ADMINISTRATOR`, `DIRECT_SPONSORED_CONTENT_POSTER`, or `CONTENT_ADMIN` role on the LinkedIn Company Page |
| `r_member_social` | Read personal member posts | **"Restricted — available to approved users only."** However, the ClickSocial app **already has `w_member_social`** approved — meaning it's already a recognized Community Management partner for member-level actions. Adding the read counterpart to the same app is a lighter ask than applying cold. **Worth requesting alongside `r_organization_social`** — if granted, we can support personal LinkedIn feeds (like Instagram Feeds); if denied, we fall back to org-only. |
| `openid profile email` | Identity only (Sign In with LinkedIn OIDC) | Self-serve, no post access |
| `r_basicprofile` | **Deprecated** as of 2023 | The ClickSocial code still requests this — it must be replaced with OIDC `profile` |

**What this means for product scope:** The primary target is **LinkedIn Company Page Feeds** (analogous to Custom Facebook Feed for Business Pages). However, with the ClickSocial app already approved for `w_member_social`, requesting `r_member_social` on the same app is a realistic stretch goal — if granted, the plugin can also display **personal LinkedIn profile feeds** (like Instagram Feeds shows personal posts). The prototype should request both scopes and see what comes back. Org feeds are the baseline; personal feeds are the upside.

### 1.4 Access-tier timing and cost

- **Development tier** (granted after Community Management application approval): testing only, API call restrictions, **12-month clock** to upgrade to Standard
- **Standard tier** upgrade requires: completed integration, screen recording of the live app, shared test credentials, re-review
- Partner-program review: **typically 1–4 weeks**, reviewed manually, **no SLA**, can be denied

### 1.5 API maintenance cost — monthly versioning

LinkedIn's versioned APIs pin with `Linkedin-Version: 202603` headers. Versions are **sunset ~12 months after release** (the docs already warn: *"Marketing Version 202504 has been sunset"*). This is **unlike** Instagram/Facebook (breaking changes every 1–2 years) and **unlike** TikTok (quarterly). **Ongoing maintenance cadence is monthly version bumps.**

### 1.6 Token lifecycle

- Access tokens: **60-day expiry**
- Refresh tokens: **365-day expiry** (then user must re-authenticate)
- Compare: Facebook long-lived page tokens effectively never expire; Instagram Graph tokens last 60 days but refresh is silent. LinkedIn's **annual forced re-auth** is a UX regression.

### 1.7 Rate limits

LinkedIn documents Throttle Limits per app-member pair per 24 hours (e.g., ~500 daily calls for typical Community Management endpoints on Development tier). For a feeds plugin that refreshes every 30 min, a single connected page fits easily; 100+ pages per WordPress install hits the ceiling and requires exponential caching.

---

## 2. ClickSocial Reference Inventory — what's actually reusable

The rock cites three ClickSocial references. Each was read carefully. Here's what's actually liftable vs. what's decorative:

### 2.1 `LinkedInAuthorization.php` — OAuth flow
**Repo:** `awesomemotive/click-social-poster` — `development` branch — `google-cloud-projects/click-social-auth/functions/all/app/Services/Connectors/LinkedInAuthorization.php`

**What it is:** A Laravel class running on Google Cloud Functions. Handles the OAuth 2.0 authorization-code dance against `https://www.linkedin.com/oauth/v2/authorization` → `https://www.linkedin.com/oauth/v2/accessToken`, plus refresh-token flow.

**What's reusable as a pattern:**
- Authorization URL structure + query params
- Token-exchange request shape
- Refresh-token request shape
- User-info fetch via `/v2/me?projection=(id,vanityName,...)`

**What's NOT reusable as code:**
- Laravel / `Illuminate\Support\Facades\Http` stack — doesn't exist in WordPress
- Google Cloud environment (client_id/secret from cloud env)
- The scope string: **`w_member_social,w_organization_social,r_basicprofile`** is wrong for us — we need `r_organization_social` and the deprecated `r_basicprofile` must go
- The `/v2/me` endpoint path — this is the old Sign In with LinkedIn v1 endpoint; OIDC replacement is `/v2/userinfo`

**Net verdict:** A **reference for the shape** of the OAuth flow; the WordPress port is net-new code against an awesomemotive relay proxy (see §3.2 below).

### 2.2 PR #71 — Organisations support (WIP, still open)
**Repo:** `awesomemotive/click-social-poster#71` — "WIP - Proof of concept to retrieve LinkedIn organisations pages and add them as social accounts"

**Status:** Open, 1026 additions, never merged, branch `linked-organisation-support-proof-of-concept`.

**Value:** Confirms the organisation-discovery flow (list pages the authenticated user admins → pick one → register it as a social account) has been prototyped at AM once. The branch name gives us a URL to fetch reference code from. **Any organisation-discovery endpoint we use in the feed plugin will mirror this pattern.** The actual approach: `GET /v2/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR` returns the organizations the authenticated user administers.

**Net verdict:** Useful precedent that organisation flow is workable through the existing developer app; no production-shipped code to lift.

### 2.3 `clients.js` — LinkedIn post publisher
**Branch:** `linked-organisation-support-proof-of-concept` — `google-cloud-functions/linkedin/lib/integration/clients.js`

**What it is:** Node.js axios client for **writing** posts: `registerUpload`, `uploadMedia`, `publishPost` to `/v2/ugcPosts` (now deprecated — replaced by `/rest/posts`).

**What's reusable for feed reads:** **Effectively nothing.** Zero GET/read code. The header pattern (`LinkedIn-Version: 202502`, `X-Restli-Protocol-Version: 2.0.0`) is a useful template but documented in LinkedIn's own API reference.

**Net verdict:** Acknowledged for completeness — not a scaffold for the prototype.

### 2.4 Test discoveries (from ClickSocial team findings doc)

**Source:** ClickSocial internal documentation and test findings.

Key findings relevant to the LinkedIn Feeds prototype:

| Finding | Impact on prototype | Mitigation |
|---|---|---|
| **Test accounts get blocked after ~24 hours** in some cases. Must create new test accounts. | Slows prototype dev/test cycles — can't rely on one test account across the full build phase | Use UK IPs for account creation (team-proven to avoid phone verification). Budget extra accounts. |
| **Occasional `Unauthorized` errors** when connecting LinkedIn accounts — appears to be LinkedIn-side, intermittent | Non-blocking but confusing during demo. Retry logic needed. | Build retry with 15-min backoff into the prototype's connection flow |
| **Internal errors when connecting newly created accounts** — API access token becomes useless for publishing after the error | May also affect read-token flow if the same error class applies to read scopes | Retry adding the social account after 15 minutes (team-validated workaround) |
| **Must explicitly remove LinkedIn account to re-test** adding it — `linkedin.com/mypreferences/d/data-sharing-for-permitted-services` | Developer friction during iteration; document for test workflow | Add to prototype dev-notes |
| **Each user limited to 10 posts per day** (for posting) | Irrelevant for read-only feeds — but confirms LinkedIn applies per-user rate limits. Read limits may exist too. | Verify read rate limits separately during week 6 observation period |
| **LinkedIn connector types**: user account → post to main feed; LinkedIn pages the user has access to → post to page | Confirms dual-source pattern: personal + organization, matching our data-model `account_type` design | Both paths available through existing app |

**Net verdict:** No show-stoppers, but **test-account churn** and **intermittent auth errors** are real friction that extends prototype timelines by ~1 day.

### 2.5 ClickSocial infrastructure — what we're actually plugging into

The most valuable thing in ClickSocial isn't code — it's the **already-approved LinkedIn developer app** plus a **production-grade infrastructure** for social-network credential management that the feed plugin can leverage.

**Six-component architecture:**
1. **Marketing site** (`clicksocial.com`) — subscription management (irrelevant to feeds)
2. **Main application** (`app.clicksocial.com`) — Laravel API + dashboard (irrelevant to feeds)
3. **Auth Flow connectors** (`connect.clicksocial.com` / `connect-redirect.clicksocial.com`) — **OAuth2 flow for social networks. This is the relay endpoint the feed plugin connects to.** Runs as a Google Cloud Run service.
4. **Poster** (not internet-accessible) — posting queue + social-network API calls (irrelevant to feeds — we read, not post)
5. **Access Token manager** (not internet-accessible) — **stores and retrieves encrypted access tokens in a Secure Bubble.** Encrypted via KMS → stored in Hashicorp Vault with unique keys. VPC-isolated. This is where LinkedIn tokens would live for the feed plugin too.
6. **Support Admin** — debugging tool (useful during prototype but not part of the plugin)

**Security model (the "Secure Bubble"):** Token storage is spread across 4 isolated Google Cloud projects. Getting access to a customer's LinkedIn tokens would require simultaneously compromising: the connection database (encrypted IDs), the KMS project (decryption keys), and Hashicorp Vault (encrypted token blobs). This exceeds what any SB feed plugin does today — it's a significant security upgrade over the TikTok/Instagram direct-token-in-WP-database approach.

**What this means for the feed plugin:**
- OAuth flow: feed plugin opens popup to `connect.clicksocial.com` → user authorizes on LinkedIn → tokens go straight into the Secure Bubble → WP side gets only an opaque reference. Tokens **never touch the customer's WordPress database**.
- API reads: feed plugin requests posts through the Access Token manager relay → relay injects the decrypted token → calls LinkedIn API → returns response to WP. The customer site never holds the bearer token.
- This is architecturally cleaner and more secure than the TikTok Feeds relay pattern. It means **less prototype code, not more** — the hard parts (token encryption, refresh, VPC isolation) already exist.

**The real question for Alex:** Can the existing auth-flow connector at `connect.clicksocial.com` be extended to handle a **read-only feed fetch relay** (not just OAuth token exchange), or do we need a new endpoint/service alongside it? The auth-flow connector today handles token *acquisition*; the feed plugin also needs a token-*usage* relay for ongoing `GET /rest/posts` calls.

---

## 3. SB Feed-Plugin Architecture — what to clone vs. what to invent

Codebase survey confirms SB has one dominant pattern for feed plugins (instagram-feed-pro, custom-facebook-feed-pro, feeds-for-tiktok, custom-twitter-feeds-pro, youtube-feed-pro, tiktok-feeds, social-wall, sb-reviews). The newest and cleanest template is **TikTok Feeds** (feeds-for-tiktok, ~17k LOC).

### 3.1 The repeating shape

```
plugin-name/
├── plugin-name.php                 # Entry point, defines constants, bootstrap
├── bootstrap.php                   # Autoloader + service container wiring
├── inc/
│   ├── Common/                     # Free + Pro shared code
│   │   ├── Relay/                  # API proxy client (TikTok), or direct API client (IG/FB)
│   │   ├── Database/               # Sources, Posts, FeedCache, Feeds tables + migrations
│   │   ├── Customizer/             # FeedBuilder.php extending sb-common's Feed_Builder
│   │   ├── Settings/               # SettingsBuilder (General/Custom/Advanced tabs)
│   │   └── Services/               # Business logic, OAuth handlers
│   └── Pro/                        # Pro-only extensions, gated by SBTT_LITE constant
├── assets/                         # JS (OAuth fragment handler, admin UI), CSS
├── build/                          # Compiled React bits (customizer)
└── tests/
```

### 3.2 Two OAuth patterns — choose one for LinkedIn

| Pattern | Used by | How it works | Pros | Cons |
|---|---|---|---|---|
| **Relay proxy** (TikTok, newer) | `tiktok-feeds`, `feeds-for-tiktok` | User connects → popup to AM-hosted OAuth endpoint → AM proxies to TikTok → tokens returned via URL fragment → JS hand-off to WP backend. **All API calls go through an AM relay server.** | Client secret never touches customer sites. Rate-limit consolidation. App credentials centralized. Token refresh is AM's problem. | Requires AM infra (the cloud-function relay). AM is on the hot path for every feed fetch. |
| **Direct API** (Instagram, older) | `instagram-feed-pro`, `custom-facebook-feed-pro` | Each customer site holds its own OAuth tokens, talks directly to Graph API. | No AM infra on hot path. Self-contained. | Client secret exposure risk (mitigated via app-only endpoints). Rate limits per-site. |

**Recommendation for LinkedIn prototype: Relay proxy.** Reasons:
1. LinkedIn's monthly version header + 60-day token expiry are a worse fit for "customer deals with refresh" than for AM centrally managing it
2. LinkedIn Partner compliance is easier to maintain on one centralized app than delegated to thousands of sites
3. The ClickSocial infrastructure (Google Cloud Functions) is already the LinkedIn relay — we build on that

### 3.3 What sb-common provides (reuse for free)

`/plugins/sb-common/` is the shared framework across all SB plugins. A LinkedIn plugin inherits:

- **`Smashballoon\Customizer`** — feed-builder + settings-builder UI scaffolding (React components under vendor, versioned in `default`/`v2`/`v3` branches)
- **`SBNotices`** — admin notice system (success/error/reauth prompts)
- **`Packages\Blocks`** — Gutenberg block registration/rendering
- **`Packages\License_Tier`** — license validation / upgrade paths
- **`Utilities\UsageTracking`** — anonymous install telemetry

**Not provided by sb-common (plugin must build):**
- OAuth handlers (each plugin rolls its own → LinkedIn needs its own)
- API client / relay (plugin-owned → ours is new)
- DB tables + migrations (plugin-owned → we design LinkedIn's schema)
- Platform-specific API clients (plugin-owned → new code)

### 3.4 Data model LinkedIn will need

Direct clone of TikTok's schema is 90% correct:

| Table | Fields |
|---|---|
| `sources` | `id`, `account_type` (person/organization), `account_id` (LinkedIn URN), `access_token` (encrypted), `refresh_token` (encrypted), `access_token_expire_time`, `refresh_token_expire_time`, `scope`, `display_name`, `profile_image_url`, `link_url`, `vanity_name`, `error_encountered` |
| `posts` | `id`, `post_urn` (`urn:li:share:…` / `urn:li:ugcPost:…`), `author_urn`, `json_data` (full API response), `published_at`, `last_modified_at`, `commentary_text`, `media_type` (article/image/video/document/multiImage/poll), `likes_count`, `comments_count` (if Social Metadata API accessible) |
| `feed_caches` | `id`, `feed_id`, `last_fetched_at`, `raw_response`, `hash` |
| `feeds` | `id`, `feed_name`, `settings_json` (customizer settings), `sources` (array of source IDs) |

LinkedIn-specific vs. TikTok:
- `account_type` column (TikTok is personal-only; LinkedIn has person + organization)
- `post_urn` uses LinkedIn URN format (`urn:li:share:123`); TikTok uses numeric IDs
- `commentary_text` stores raw `little` text format with hashtag/mention tokens (`{hashtag|\#|coding}`) — rendering needs a token-parser on the frontend

### 3.5 LOC ballpark

- TikTok Feeds: ~17,400 LOC (PHP + JS, excl. vendor)
- Instagram Feed Pro: ~77,000 LOC (older, more features)
- **LinkedIn MVP prototype estimate: ~3,000–5,000 LOC** — just enough for OAuth + one endpoint + minimal render
- **LinkedIn full plugin (post-evaluation, if go): ~15,000–20,000 LOC** — less than TikTok because LinkedIn read surface is narrower (no comments UI, no complex display modes initially)

---

## 4. Prototype Architecture Options

Three approaches we could take for the week-4–week-7 prototype work:

### Option A — Thin vertical slice (recommended)
**Scope:** OAuth (org-only), one feed type (most recent 10 org posts), bare-bones PHP render, no customizer integration, no Pro gating, no block.
**Why:** Answers the only question that matters — *"Can we actually pull a feed out of LinkedIn through the ClickSocial app?"* Every other question is known from TikTok/IG.
**LOC:** ~2,500.
**Risk:** Looks unshippable at demo → team must understand it's a viability probe, not a product demo.

### Option B — "Looks like TikTok Feeds"
**Scope:** Everything in A, plus customizer integration, one feed layout that reuses TikTok's templates, admin settings page.
**Why:** Demo is visually convincing; easier to show stakeholders.
**LOC:** ~6,000.
**Risk:** Builds scope on top of an unproven foundation — if LinkedIn access doesn't pan out in week 3, ~3 weeks of UI work is wasted.

### Option C — Full fake
**Scope:** Mock API responses, full UI, no actual LinkedIn auth.
**Why:** Worst — answers zero of the real questions.
**Risk:** The evaluation produces false confidence.

**Recommendation:** **Option A**, with a stretch goal to add the customizer integration if access lands week 3 on time. If week-3 access is delayed past week 4, A stays A.

---

## 5. Open Questions — resolved in weeks 1–2

These must be answered before prototype work begins:

1. **~~Does the ClickSocial app already have Community Management API access approved?~~** **RESOLVED:** Yes — the app is already reviewed and approved as a LinkedIn Partner with `w_member_social` and `w_organization_social`. No fresh Partner Program application needed.
2. **Can we add `r_member_social` + `r_organization_social` read scopes to the existing approved app without re-review?** (Ask Alex week 1. If it's a scope toggle in LinkedIn's developer console, it's a 10-minute task. If LinkedIn requires scope-change approval even on an approved app, there's a short wait but not a fresh Partner application.)
3. **Can the existing auth-flow connector at `connect.clicksocial.com` serve as a read relay (ongoing `GET /rest/posts` calls), or does it only handle token acquisition?** This determines whether Alex needs to build a new relay service or extend an existing one. (Ask Alex week 1.)
4. **~~What's in the test-discoveries Google Doc?~~** **RESOLVED:** Test findings are now incorporated in §2.4 above. Key risks: test-account blocking after 24h, intermittent unauthorized errors, 15-min retry needed. No show-stoppers found.
5. **Should the feed plugin use ClickSocial's Secure Bubble for token storage, or maintain its own encrypted tokens in the WP database (like TikTok Feeds)?** The Secure Bubble is architecturally superior but creates a dependency on ClickSocial infra for every feed fetch. Decision point with Alex.
6. **What's the product positioning alongside Custom Facebook Feed Pro?** LinkedIn company page feeds are functionally similar. Are we marketing them as a bundle, a separate plugin, or a paid add-on? (Not a week-1 blocker but shapes the post-evaluation plan.)
7. **Is the product framed as "LinkedIn Feeds" (like Instagram Feeds — both personal + company page) or "LinkedIn Company Page Feeds" (org-only)?** Current recommendation: target both. Request `r_member_social` + `r_organization_social`. If `r_member_social` is granted, ship "LinkedIn Feeds" like Instagram Feeds. If only `r_organization_social` is granted, ship "LinkedIn Company Page Feeds". Let the scope come back from LinkedIn and shape the product name.

---

## 6. Risks — ordered by blast radius

| # | Risk | Likelihood | Impact | How this research changes our posture |
|---|---|---|---|---|
| 1 | ~~**LinkedIn denies Community Management API for the ClickSocial app.**~~ | ~~Medium~~ | ~~Fatal~~ | **RESOLVED:** App is already approved. No fresh Partner application needed. Risk eliminated. |
| 2 | **Read-scope expansion denied** — adding `r_member_social` and/or `r_organization_social` to the already-approved app may require LinkedIn scope-change review. | Low–Medium | Medium | Lower bar than a fresh application — the app is already a recognized partner. If `r_member_social` is denied, fall back to org-only. If *both* denied, that's the new blocker — but unlikely given existing write-scope approval. |
| 3 | **Test-account blocking after ~24h** slows prototype development. | High (confirmed in test findings) | Low | UK IPs for creation. Budget 3–4 test accounts across the build phase. |
| 4 | **Intermittent `Unauthorized` / internal errors on LinkedIn connections** — not deterministic, hard to reproduce, 15-min retry resolves. | Medium (confirmed) | Low | Build retry + backoff into connection flow. Document for demo. |
| 5 | **API version churn** forces regular maintenance burden vs. TikTok/Instagram. | High | Medium | Built into the "is this worth it" calculus for the evaluation. Frame as an ongoing tax on engineering. |
| 6 | **60-day access token / 365-day refresh** means annual forced re-auth for every customer. (If using ClickSocial Secure Bubble, refresh may be handled centrally — reducing UX impact.) | Certain | Medium | If Secure Bubble manages refresh, customer-facing impact is softer. Verify during prototype. |
| 7 | **ClickSocial infrastructure dependency** — feed plugin relies on `connect.clicksocial.com` and the Access Token manager for every fetch. If ClickSocial infra is down, LinkedIn feeds stop updating. | Low (production infra, monitored) | Medium | Same class of risk as TikTok Feeds relay dependency, which is accepted today. |
| 8 | **Prototype proves we can fetch but UX feels thin** because LinkedIn posts lack Instagram-style photo-first layouts. | Medium | Low | Not a blocker for the *evaluation* — product design is a post-go question. |
| 9 | **Development tier → Standard tier has a 12-month clock** with non-trivial requirements (screen recording, test credentials). | High | Medium | Factor into the post-evaluation roadmap. If we go, Q3 must ship *to Standard*, not just Development. |

---

## 7. Summary — what this research supports

**Viability signal is strong. The biggest risk (app approval) has been eliminated.**

- The **Posts API is real, documented, and provides the endpoint shape** we'd need — both for organization-page feeds (`r_organization_social`) and personal-profile feeds (`r_member_social`).
- The **ClickSocial app is already approved** as a LinkedIn Partner with write scopes — adding read scopes is a scope expansion, not a cold application. This was the previously-identified single decisive unknown, and it's now resolved in our favor.
- The **ClickSocial infrastructure** (Secure Bubble, Auth Flow connectors, Access Token manager) provides a production-grade relay and token-management system that the feed plugin can leverage — significantly reducing prototype code compared to building a relay from scratch.
- The **SB feed-plugin architecture** has a well-proven template (TikTok Feeds) that a LinkedIn plugin slots into with ~15–20k LOC of plugin-specific code.
- The product scope should **target both personal + organization feeds** (request `r_member_social` + `r_organization_social`). If personal feeds are granted, the product is "LinkedIn Feeds" (like Instagram Feeds). If only org feeds are granted, the product is "LinkedIn Company Page Feeds". Let LinkedIn's scope response determine the product framing.
- **Known operational quirks** from ClickSocial testing (test-account blocking, intermittent auth errors, 15-min retry pattern) are manageable — no show-stoppers.

**The remaining unknowns are execution-level, not viability-level:** Can read scopes be toggled easily or do they need scope-change review? Can the auth-flow connector serve as a read relay? How do LinkedIn's read rate limits compare to the write limits already observed? These are questions the prototype answers in weeks 2–6, not gatekeeping questions that determine whether the prototype starts.

---

## Sources

- [Posts API — Microsoft Learn (LinkedIn)](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-03)
- [Community Management — Overview](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview?view=li-lms-2026-02)
- [LinkedIn Marketing API Program Access Tiers](https://learn.microsoft.com/en-us/linkedin/marketing/integrations/marketing-tiers?view=li-lms-2026-02)
- [Sign In with LinkedIn using OpenID Connect](https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/sign-in-with-linkedin-v2)
- [Increasing Access (Development → Standard tier)](https://learn.microsoft.com/en-us/linkedin/marketing/increasing-access?view=li-lms-2026-01)
- ClickSocial `LinkedInAuthorization.php` — `awesomemotive/click-social-poster` @ `development`
- ClickSocial PR #71 — org pages support (WIP, never merged)
- ClickSocial `clients.js` — branch `linked-organisation-support-proof-of-concept`
- TikTok Feeds plugin (`plugins/feeds-for-tiktok/`) — architectural template
- sb-common (`plugins/sb-common/`) — shared primitives inventory
- ClickSocial internal infrastructure & findings documentation (provided April 2026)
