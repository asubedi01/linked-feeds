# Evaluate LinkedIn Feeds — Pitch

**Date:** April 2026
**Owner:** Asmita Subedi
**Figma:** N/A
**Time Estimate:** 10 Business Days

---

## Summary

Research LinkedIn's API capabilities and limitations, build a prototype, and produce a clear-eyed evaluation of whether LinkedIn Feeds is viable as a Q3+ major milestone. This is a scoping and validation exercise — not a commitment to build.

API access is provided by Alex through the existing ClickSocial developer app on developer.linkedin.com — no external application or approval process required. The app is already reviewed and approved as a LinkedIn Partner with **`r_organization_social` already active** — meaning we can read organization (company page) posts today with no scope changes. Personal member feeds (`r_member_social`) are **not currently available** on the app and would need to be requested separately.

The deliverable is a working prototype demo'd to the team and a written evaluation covering: API capabilities available through the ClickSocial app, key limitations, estimated development scope, and a concrete go/no-go recommendation for Q3 planning.

---

## Benefits

- **Replaces folklore with evidence.** Every prior look at LinkedIn has stalled at "the API is hard." This rock produces a documented answer — either a working prototype or a specific unblock path — so Q3 planning is based on facts, not vibes.
- **Leverages existing investment.** The ClickSocial team already has a LinkedIn integration with the read scopes we need for org feeds (`r_organization_social`) already approved. Exploring feed capabilities through the same app is the cheapest possible starting point.
- **Fills the last major platform gap.** Smash Balloon ships feed plugins for Instagram, Facebook, Twitter, YouTube, and TikTok. LinkedIn is the single most common missing-plugin request from B2B and enterprise customers.
- **De-risks Q3 commitment.** A go/no-go evaluation before committing to a full development rock avoids a quarter wasted on a platform that turns out to be unworkable.

---

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `r_member_social` (personal feeds) is not on the app and may be difficult to get approved — LinkedIn marks it as "restricted, approved users only" | High | Medium | Org feeds are the baseline and already work. Personal feeds are a stretch goal — nice to have, not required for a viable product. |
| Alex's bandwidth blocks ClickSocial relay integration | Medium | High | Fallback to local-only direct API calls for prototype (not shippable, but proves we can fetch) |
| Test accounts get blocked after ~24h (confirmed in ClickSocial testing) | High | Low | Use UK IPs for account creation. Budget 3–4 test accounts. |
| Intermittent `Unauthorized` errors on LinkedIn connections (confirmed) | Medium | Low | Retry with 15-min backoff. Document as known behavior. |
| LinkedIn's 60-day token / 365-day refresh lifecycle creates poor customer UX | Certain | Medium | Explore whether ClickSocial's centralized token management can handle refresh transparently. Document as a product-design consideration. |
| LinkedIn monthly API versioning (`Linkedin-Version: YYYYMM`) creates ongoing maintenance tax | High | Low | Document in evaluation as an ongoing cost (~0.5 dev-day/quarter) for the go/no-go calculus |
| Prototype built on a TikTok Feeds fork may look unfinished to stakeholders | Medium | Low | Frame demo as viability probe. Written evaluation carries the recommendation, not visual polish. |

---

## Implementation Overview

**NOTE: This is an exploration rock. Each step involves significant research and unknowns. AI usage is noted per step.**

### Step 1: Research & Alignment with Alex
- Sync with Alex to understand ClickSocial's LinkedIn infrastructure (auth-flow connectors at `connect.clicksocial.com`, Secure Bubble token storage via KMS + Hashicorp Vault, Access Token manager)
- Confirm the existing `r_organization_social` scope is sufficient to call `GET /rest/posts?author=urn:li:organization:{id}`
- Discuss whether `r_member_social` can be requested on the same app, and what the process/timeline looks like
- Decide: should the feed plugin use ClickSocial's Secure Bubble for token storage, or maintain its own?
- Decide: can `connect.clicksocial.com` serve as a read relay for ongoing API calls, or does it only handle token acquisition?
- **AI Usage:** Low — summarization and note-taking only

### Step 2: Explore LinkedIn API Capabilities
- Build a standalone probe script to test the Posts API with a token from LinkedIn Developer Console
- Test `GET /rest/posts?author=urn:li:organization:{id}&q=author` using the already-available `r_organization_social` scope
- If `r_member_social` becomes available, also test `GET /rest/posts?author=urn:li:person:{id}`
- Map out what content types LinkedIn returns (text, images, videos, articles, polls, documents, multi-image)
- Understand LinkedIn's `little` text format for commentary (hashtags as `{hashtag|\#|coding}`, mentions as `@[Name](urn:li:organization:...)`)
- Test the Images API / Videos API for resolving media URNs to thumbnail download URLs
- Document rate limits, response shapes, pagination behavior, error patterns
- Explore what `r_organization_social_feed` and `r_member_postAnalytics` provide beyond basic post data (engagement metrics, analytics)
- **AI Usage:** Heavy — Claude writes the probe scripts and summarizes API responses

### Step 3: Set Up Test Content & Accounts
- Create or verify a Smash Balloon LinkedIn Company Page with admin access
- Populate test posts across all content types (text, image, article, video, poll, multi-image, document)
- Follow ClickSocial team guidance: UK IPs for account creation to avoid phone verification and blocking
- **AI Usage:** None

### Step 4: Build Proof-of-Concept Prototype
- Fork `feeds-for-tiktok` as the starting scaffold (rename `SBTT_*` constants to `SBLI_*`, strip TikTok-specific code)
- Wire LinkedIn OAuth flow through ClickSocial's auth-flow connector (or direct API if relay isn't ready)
- Build organization-page source picker (list pages user admins via `GET /v2/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR`)
- Build post-fetch service: calls Posts API with `r_organization_social`, deserializes response, caches in custom WP database table
- Build LinkedIn `little`-text commentary parser (hashtags, mentions → HTML links)
- Build media hydration (resolve image/video URNs to download URLs via Images API / Videos API)
- Build minimal frontend render as `[linkedin-feed]` shortcode (vertical list: avatar, org name, commentary, media, date)
- Add WP-cron for auto-refresh (30-min intervals with exponential backoff on errors)
- Add error-state UX (token expired → "Reconnect LinkedIn" prompt; intermittent errors → silent retry)
- **AI Usage:** Heavy — Claude writes most of the scaffold, fetcher, parser, and render code. Human reviews and tests.

### Step 5: Observe & Collect Evidence
- Let prototype run for 48h against test org page
- Capture actual API call counts per refresh cycle, response-time distribution, throttling headers
- Verify content-type coverage: which post types render correctly, which fail or have edge cases
- Monitor test-account stability (do read-only connections get blocked like posting accounts do?)
- Simulate token expiry to verify reconnect UX works
- **AI Usage:** Low — Claude summarizes log data

### Step 6: Write Evaluation & Demo
- Record a 5–8 minute demo video (OAuth → org picker → fetch → rendered page → error state)
- Write the evaluation: API capabilities confirmed, limitations observed, estimated Q3 scope (range, not point estimate), go/no-go recommendation
- Present to feed-plugins team (engineering + PM + Alex), capture questions and objections
- Incorporate feedback, finalize recommendation
- If go: draft Q3 follow-on rock proposal
- If no-go: document specific unblock path
- **AI Usage:** Medium — Claude structures findings. Human writes the recommendation.

---

## Challenges and Hurdles

- **Challenge:** ClickSocial's auth-flow connector may only handle token *acquisition*, not ongoing API reads. The feed plugin needs a relay for every `GET /rest/posts` call.
  - **Potential Solution:** Sync with Alex in Step 1. If the connector can't relay reads, either Alex adds a new endpoint or the prototype falls back to direct API calls (flagged as non-shippable architecture).

- **Challenge:** LinkedIn's `little` text format requires a custom parser — no off-the-shelf library exists.
  - **Potential Solution:** Write a minimal regex-based parser with unit tests. Focus on common patterns (hashtags, org mentions). Edge cases documented, not solved in prototype.

- **Challenge:** `r_member_social` (personal feeds) is not on the app. Getting it may be difficult since LinkedIn restricts it.
  - **Potential Solution:** Build the prototype on org feeds only (which are confirmed working). If `r_member_social` is later approved, adding personal-feed support is incremental — same API shape, different author URN type.

- **Challenge:** Test accounts may get blocked after ~24h (confirmed in ClickSocial testing for posting accounts).
  - **Potential Solution:** Monitor during 48h observation. If read-only accounts are also affected, document as a significant production risk in the evaluation.

- **Challenge:** The prototype won't surface the real 60-day token expiry during the evaluation window.
  - **Potential Solution:** Manually expire a token in the database. Verify reconnect UX triggers. Document annual re-auth requirement as a product-design line item.

---

## Tasks and Time Estimates

| TASK | # DAYS |
|------|-------:|
| Research ClickSocial infrastructure + sync with Alex + test account setup | 1 |
| Research LinkedIn API capabilities (Claude writes probe scripts, maps content types, summarizes rate limits/token lifecycle) | 1 |
| Create proof of concept — plugin scaffold (Claude forks + renames TikTok Feeds) + OAuth wiring | 1.5 |
| Create proof of concept — org source picker + post fetch + commentary parser + media hydration | 2 |
| Create proof of concept — frontend render + shortcode + cron refresh + error UX | 1.5 |
| 48h observation over weekend (rate limits, account stability, content-type coverage) — review results Monday | 0.5 |
| Write evaluation document + go/no-go recommendation | 1.5 |
| Record demo video + present to team + stakeholder review + finalize | 1 |
| **TOTAL** | **10** |

---

## Unknowns and Questions That Need Upfront Answers from Stakeholders

These should be resolved **before or during the first week** — answers shape the rest of the rock:

### For Alex (ClickSocial)

1. **Can `connect.clicksocial.com` serve as a read relay?** Today the auth-flow connector handles OAuth token exchange. Can it also relay ongoing `GET /rest/posts` calls (injecting the decrypted access token from the Secure Bubble), or does it only handle token acquisition? If not, what's the effort to add a read-relay endpoint?

2. **Should the feed plugin use the Secure Bubble for token storage?** ClickSocial stores tokens in a multi-layer encrypted system (KMS + Hashicorp Vault, VPC-isolated across 4 GCP projects). Should the feed plugin use this (architecturally superior, but creates a ClickSocial infrastructure dependency), or maintain its own encrypted tokens in the WP database (simpler, self-contained, like TikTok Feeds does)?

3. **Can `r_member_social` be added to the app?** This scope is the difference between "LinkedIn Company Page Feeds" (org-only) and "LinkedIn Feeds" (personal + org, like Instagram Feeds). It's currently not on the app and LinkedIn marks it as restricted. Is it worth requesting? What's the process and expected timeline?

4. **What's Alex's bandwidth for relay work?** The prototype needs ~0.5–1 day of Alex's time to wire the LinkedIn relay endpoint. When in the next 4 weeks can that happen?

5. **Who escalates if something stalls?** If adding `r_member_social` or getting relay work done hits a snag, who is the escalation target?

### For PM / Leadership

6. **Is an org-only product ("LinkedIn Company Page Feeds") worth shipping?** `r_organization_social` is confirmed available. `r_member_social` is not. If personal feeds can't be added, is a company-page-only plugin still a viable product for Q3?

7. **If the evaluation lands "go", can a Q3 slot be held?** If Q3 is already fully committed, the evaluation's upside is diminished.

8. **What's the positioning alongside Custom Facebook Feed Pro?** Facebook company pages and LinkedIn company pages target the same buyer. Bundled? Separate plugin? Paid add-on?

---

## Available OAuth 2.0 Scopes (current state)

These scopes are **already approved** on the ClickSocial LinkedIn developer app:

| Scope | Description | Relevance to Feed Plugin |
|-------|-------------|-------------------------|
| **`r_organization_social`** | Retrieve org posts, comments, reactions, engagement data | **Core scope for org feeds — already available** |
| **`r_organization_social_feed`** | Retrieve comments, reactions on org posts | Useful for displaying engagement metrics |
| `rw_organization_admin` | Manage org pages and retrieve reporting data | Useful for org discovery (list pages user admins) |
| `r_organization_followers` | Org follower data | Nice-to-have for analytics display |
| `r_basicprofile` | Name, photo, headline, public profile URL | Used for feed header / account display (note: deprecated scope, should migrate to OIDC `profile`) |
| `r_member_postAnalytics` | Member's own post reporting data | Potentially useful if personal feeds are added later |
| `r_member_profileAnalytics` | Profile analytics (viewers, followers, search appearances) | Not directly needed for feed display |
| `r_1st_connections_size` | Number of 1st-degree connections | Not needed |
| `w_member_social` | Create/modify/delete member posts | Not needed (we only read) |
| `w_organization_social` | Create/modify/delete org posts | Not needed (we only read) |
| `w_member_social_feed` | Create/modify/delete comments/reactions on member posts | Not needed |
| `w_organization_social_feed` | Create/modify/delete comments/reactions on org posts | Not needed |

**Not available:** `r_member_social` (read personal member posts) — this is the scope needed for personal-profile feeds like Instagram Feeds. Would need to be requested separately.

---

## Questions, Comments, and Concerns

- [ ] `r_basicprofile` is deprecated since 2023. The feed plugin's OAuth flow should use OpenID Connect scopes (`openid profile email`) instead. Coordinate with Alex to ensure this doesn't break ClickSocial's existing LinkedIn integration.
- [ ] The test-discoveries doc notes LinkedIn test accounts can get blocked after ~24h. This was observed for *posting* accounts — unclear if *read-only* accounts have the same issue. If they do, it's a production risk (customer connections randomly breaking). Monitor during 48h observation.
- [ ] LinkedIn's API uses monthly versioning (`Linkedin-Version: 202604`) and sunsets old versions ~12 months after release. This is more aggressive than Instagram/Facebook. Shipping means committing to a quarterly version-bump maintenance task.
- [ ] LinkedIn's Posts API returns max 100 posts per request. For a feed displaying 10-20 posts this is fine. "Load more" / infinite scroll would need pagination testing — docs note you may receive fewer results than `count` even when more posts exist.
- [ ] `r_member_postAnalytics` says "Retrieve your posts and their reporting data" — the "your posts" part is ambiguous. Does this scope also grant read access to the member's own posts (not just analytics)? Worth testing during API exploration. If so, it could partially substitute for `r_member_social`.

---

## Notes

### References from ClickSocial

- **OAuth2 authorization:** `awesomemotive/click-social-poster` @ `development` — `LinkedInAuthorization.php`. Laravel class for authorization-code OAuth. Pattern reference only (not liftable as WordPress code).
- **Organisation support (WIP):** `awesomemotive/click-social-poster#71` — proof-of-concept for discovering LinkedIn org pages. Open PR, never merged. Confirms org-discovery endpoint works.
- **Posting client:** `clients.js` @ branch `linked-organisation-support-proof-of-concept`. Write-only code. Not relevant for feed reads. Header pattern (`LinkedIn-Version`, `X-Restli-Protocol-Version: 2.0.0`) is useful reference.
- **Test findings:** Test-account blocking after 24h, UK IPs avoid verification, intermittent Unauthorized errors (15-min retry), explicit account removal needed at `linkedin.com/mypreferences/d/data-sharing-for-permitted-services` to re-test.

### LinkedIn API — Key Endpoints

| Endpoint | Purpose | Required Scope |
|----------|---------|---------------|
| `GET /rest/posts?author={urn}&q=author&count=10&sortBy=LAST_MODIFIED` | Fetch posts by author | `r_organization_social` (org) or `r_member_social` (person) |
| `GET /rest/posts/{encoded URN}` | Single post | Same |
| `GET /rest/posts?ids=List({URN},{URN})` | Batch get | Same |
| `GET /v2/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR` | Discover org pages user admins | `r_organization_social` |
| Images API / Videos API | Resolve media URNs to download URLs | Same as post scope |

Required headers: `Linkedin-Version: YYYYMM`, `X-Restli-Protocol-Version: 2.0.0`

### SB Feed Plugin Architecture

Prototype forks **TikTok Feeds** (`plugins/feeds-for-tiktok/`, ~17k LOC). Key elements: plugin bootstrap, custom DB tables (sources, posts, feed_caches, feeds), settings/customizer via `sb-common`, relay proxy pattern. Full architecture details in [RESEARCH.md](./RESEARCH.md).
