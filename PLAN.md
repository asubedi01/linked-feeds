# LinkedIn Feeds Evaluation — Plan

**Date:** April 2026
**Owner:** Asmita Subedi
**Companion docs:** [PITCH.md](./PITCH.md) • [RESEARCH.md](./RESEARCH.md)
**Appetite:** 8-week evaluation rock (not a ship commitment)
**Deliverable:** Working prototype demo + written go/no-go evaluation

---

## Plan shape

This is an **8-week schedule** structured as three phases:

1. **Weeks 1–2: Alignment + scope verification + first API contact** — confirm read-scope access, understand ClickSocial relay architecture, make the first real API call
2. **Weeks 3–6: Build a vertical-slice prototype** — prove we can pull a feed end-to-end through the ClickSocial infrastructure
3. **Weeks 7–8: Evaluate and recommend** — demo + written evaluation + Q3 recommendation

**Key context update:** The original plan assumed the ClickSocial app might need a fresh LinkedIn Partner Program application. That's been resolved — **the app is already reviewed and approved** with write scopes (`w_member_social`, `w_organization_social`). The access question shrinks from "will LinkedIn approve us" to "can we add read scopes to an already-approved app", which is a much lower bar. This compresses Phase 1 from 3 weeks to 2 and gives the prototype an extra week of build time.

The schedule is calendar-based (weeks 1–8 = April 20 → June 12, 2026).

---

## Phase 1 — Alignment + scope verification (Weeks 1–2)

### Goal
By end of week 2, we've confirmed read-scope access, made the first real API call, understand the ClickSocial relay architecture, and are ready to start building.

### Week 1 — Alex sync + scope expansion + test setup (5 business days)

| Task | Deliverable | AI usage |
|---|---|---|
| **1.1 Sync call with Alex** — walk through the research doc and ClickSocial infrastructure. Three specific questions: (a) Can `r_organization_social` + `r_member_social` be added to the existing app in LinkedIn's developer console? (b) Can the auth-flow connector at `connect.clicksocial.com` serve as a read relay for ongoing `GET /rest/posts` calls, or only for token acquisition? (c) Should the feed plugin use the Secure Bubble for token storage or maintain its own? | Notes file at `.pitch/linkedin-feeds-evaluation/NOTES-alex-week1.md` with Alex's specific answers to all three | None |
| **1.2 Add read scopes** to the ClickSocial app — Alex toggles `r_organization_social` + `r_member_social` in LinkedIn's developer console. If LinkedIn requires a scope-change review, document the timeline and adapt Phase 2 accordingly. | Confirmation that read scopes are active, or a documented ETA for scope-change review | None (Alex's action) |
| **1.3 Test account setup** — create a Smash Balloon LinkedIn Company Page (if one doesn't exist). Use personal LinkedIn account for personal-feed testing. Add test posts across content types: text, image, article, video, poll. Follow ClickSocial team's guidance: **use UK IPs for test-account creation** to avoid phone verification and blocking. | Working company page with ≥5 posts across content types; personal account with test posts | None |
| **1.4 Product scope decision** (with Alex / PM): are we targeting "LinkedIn Feeds" (personal + org, like Instagram Feeds) or org-only? Recommendation: request both scopes, let LinkedIn's response determine the product name. | Decision documented in NOTES | None |

**Week-1 exit state:** Read scopes requested or active. Test content exists. Architecture decisions made. Alex has committed (or declined) relay bandwidth.

### Week 2 — First API contact + infrastructure familiarization (5 business days)

| Task | Deliverable | AI usage |
|---|---|---|
| **2.1 Build a no-auth test harness locally** — a standalone PHP script that, given a manually-pasted access token from LinkedIn Developer Console's OAuth Tools, makes `GET /rest/posts?author=urn:li:organization:{id}&q=author&count=10&sortBy=LAST_MODIFIED` and dumps JSON. Validates the endpoint/response shape with real data. | Script at `~/tmp/linkedin-probe.php`; sample JSON response saved to `.pitch/linkedin-feeds-evaluation/sample-response.json` | Heavy — Claude writes the script one-shot |
| **2.2 Also test personal-feed endpoint** — `GET /rest/posts?author=urn:li:person:{id}&q=author` with `r_member_social`. Document whether it works or returns a permission error. This determines the personal-feed half of the product scope. | Second sample JSON or documented error response | Medium |
| **2.3 ClickSocial infrastructure walkthrough** — read the ClickSocial architecture doc (6-component layout, Secure Bubble, VPC topology). Understand the auth-flow connector code in `click-social-poster` repo. Identify exactly where a "LinkedIn read relay" endpoint would plug in. | Architecture notes: "LinkedIn read relay plugs in at [X], token retrieval via [Y], response path is [Z]" | Medium |
| **2.4 Rate-limit + quota research** — read LinkedIn quota docs for the access tier. Compare against TikTok Feeds cache strategy (30-min refresh, ~48 calls/day/source). Factor in ClickSocial's existing LinkedIn quota consumption (posting uses same app). | 1-paragraph note: expected read calls vs. quota, shared-quota impact | Medium — Claude reads the docs and summarizes |
| **2.5 🚨 SCOPE-GATE CHECK — end of week 2.** If read scopes are NOT active and LinkedIn is reviewing, assess: (a) Can prototype proceed with hand-pasted tokens from the Developer Console? (yes → proceed, just delay real OAuth wiring to when scopes land) (b) Is the scope-change review expected within 2 weeks? (yes → proceed with fallback) (c) Denied outright? → Surface immediately; adjust plan. | Status documented in NOTES | None |

**Week-2 exit state:** We've made at least one real API call and seen real JSON come back. We understand the ClickSocial architecture well enough to design the relay integration. Scope status is known.

---

## Phase 2 — Vertical-slice prototype (Weeks 3–6)

### Goal
Build a proof that *a WordPress site can OAuth into LinkedIn through ClickSocial's infrastructure, pull posts (both personal and organization), cache them, and render them to the frontend.* With the access gate largely de-risked, this phase gets an extra week compared to the original plan.

**Architecture:** [RESEARCH.md §4 Option A](./RESEARCH.md#4-prototype-architecture-options), extended. Thin vertical slice through the ClickSocial relay. With the extra week, **Option A+ is realistic**: basic customizer-integrated layout in addition to the bare shortcode render, making the demo significantly more convincing.

### Week 3 — Plugin scaffold + OAuth wiring (5 business days)

The prototype scaffolds onto the **TikTok Feeds pattern** but routes OAuth and API calls through **ClickSocial's auth-flow connector** (`connect.clicksocial.com`) and Access Token manager — not a standalone relay.

| Task | Deliverable | AI usage |
|---|---|---|
| **3.1 Clone `feeds-for-tiktok` as `linkedin-feeds-prototype`** — rename constants (`SBTT_*` → `SBLI_*`), strip TikTok-specific Relay endpoint names, keep the scaffold intact. This is deliberately a fork, not a from-scratch build — the goal is to prove the existing SB architecture accommodates LinkedIn. | Plugin activates on a local WP install; admin menu shows "LinkedIn Feeds"; no runtime errors | Heavy — Claude performs the mass rename + strip |
| **3.2 Wire the LinkedIn OAuth flow** through ClickSocial. On the customer side: popup to `connect.clicksocial.com` LinkedIn OAuth endpoint → token exchange happens server-side in ClickSocial's auth-flow connector → tokens stored in Secure Bubble → WP side receives an opaque account reference. Coordinate with Alex on what the auth-flow connector needs: a new `LinkedInFeedAuthorization` handler alongside the existing `LinkedInAuthorization`, or extension of the existing one to accept read scopes. | Local user can click "Connect LinkedIn" → walks through real LinkedIn OAuth → returns to WP with account connected | Heavy — Claude writes the WP-side popup + AJAX; Alex handles auth-flow connector changes |
| **3.3 Dual-source picker** — after OAuth: (a) Show the authenticated user's personal profile as a source option ("Your LinkedIn Feed"), (b) Call `GET /v2/organizationalEntityAcls?q=roleAssignee&role=ADMINISTRATOR` to list the user's admin orgs and show them as source options ("Company Pages"). User picks one or more; save as `source` rows with `account_type` = `person` or `organization`. | UI showing personal profile + admin org pages as selectable sources | Heavy |

**Week-3 exit state:** A connected LinkedIn source (personal or organization) in the WP database. No feed display yet.

### Week 4 — Fetch + cache + render (5 business days)

| Task | Deliverable | AI usage |
|---|---|---|
| **4.1 Post-fetch service** — `SBLI_Post_Fetcher` class that given a `source_id`: requests the decrypted access token from ClickSocial's Access Token manager (or uses the relay pattern), calls `GET /rest/posts?author={person_or_org_urn}&q=author&count=10&sortBy=LAST_MODIFIED`, deserializes the response, inserts rows into `sbli_posts`. | One-shot "Fetch Posts" admin button populates the DB with real posts from the connected source | Heavy — Claude writes the fetcher + JSON parser |
| **4.2 LinkedIn-specific parsers** — `commentary` contains `little` text format with `{hashtag\|#\|coding}` and `@[Mention](urn:li:organization:...)` tokens. Write a minimal renderer that converts these to HTML (`<a>` for mentions, `<a>` for hashtags linking to `/search/content?keywords=...`). Attach a unit test with 3 fixture strings. | `SBLI_Commentary_Renderer::to_html()` with tests | Heavy — Claude writes parser + tests |
| **4.3 Media hydration** — for posts with `content.article` or `content.media` URNs, call the Images API / Videos API (through relay) to resolve thumbnail download URLs. Cache the URLs on the `sbli_posts` row. | Posts with images render with images | Medium |
| **4.4 Frontend render** — a `[linkedin-feed]` shortcode that queries `sbli_posts`, runs each through the commentary renderer, emits HTML styled as a clean vertical list (avatar, author name, commentary, media, date). Copy TikTok's CSS scaffolding and simplify. | Shortcode on a test page renders the 10 most-recent LinkedIn posts from the connected source | Heavy — Claude writes template + CSS |

**Week-4 exit state:** The prototype *works*. A WordPress page shows real LinkedIn posts from a real connected source (personal or org), updating when "Fetch" is clicked manually.

### Week 6 — Hardening + rate-limit observations (4 business days + 1-day slack)

| Task | Deliverable | AI usage |
|---|---|---|
| **5.1 Cache + scheduled refresh** — WP cron every 30 minutes per source; short-circuits if the source was refreshed within the cache window. Exponential backoff on rate-limit errors. Mirror TikTok's `FeedCacheTable` behaviour. Factor in ClickSocial relay latency. | Cron runs hands-off; errors logged to `error_log` table; feed stays current | Heavy |
| **5.2 Error-state UX** — if the token is expired, show "Reconnect LinkedIn" on the admin UI. If ClickSocial manages refresh centrally (via Access Token manager), this may be simpler — just detect when the relay returns an auth error. Also handle the intermittent `Unauthorized` errors documented in ClickSocial testing (15-min retry). | Manually-expired token triggers the reconnect prompt; intermittent errors retry silently | Medium |
| **5.3 Content-type sampling** — verify the prototype handles each content type (text, image, video, article, multiImage, poll) or documents known failures. Test posts on the page should exercise each type. | Matrix of content-types × render-correctness-verdict | Medium |

**Week-5 exit state:** A self-refreshing prototype. Feed updates automatically. Error states handled.

### Week 6 — Customizer integration + observation (5 business days)

**Bonus week** (gained from compressing Phase 1). This turns Option A into **Option A+** — making the demo significantly more convincing by integrating with the SB customizer.

| Task | Deliverable | AI usage |
|---|---|---|
| **6.1 Basic customizer integration** — wire `SBLI_FeedBuilder` extending sb-common's `Feed_Builder`. Support one layout (list/vertical). Settings: posts count, show/hide author, show/hide date, show/hide media. No advanced templates. | Admin can configure a LinkedIn feed through the customizer UI | Heavy — Claude writes the customizer config, human tests |
| **6.2 Personal vs. org feed test** — if `r_member_social` was granted in week 1, exercise the personal-feed path: connect personal account, fetch personal posts, verify they render. Document what personal posts look like vs. org posts (commentary length, media types, mention patterns). | Comparison screenshots; documented differences in post structure | Low |
| **6.3 48h observation run** — let the prototype run for 48h against both test sources (personal + org if available). Capture: actual API call count per refresh cycle, response-time distribution, throttling headers, any ClickSocial relay-specific latency. | Data table with observed call rate vs. documented limits, pasted into [PITCH.md](./PITCH.md#observations-from-the-prototype) evaluation section | Low — Claude summarizes the log data |
| **6.4 Test-account stability check** — monitor whether the connected test accounts get blocked (per ClickSocial finding: accounts can be blocked after ~24h). If blocking occurs on read-only accounts, that's a significant production risk to document. | Stability log: "Account stayed connected / was blocked after X hours" | None |

**Week-6 exit state:** A prototype with customizer integration, running hands-off for 48 hours, with known rate-limit behaviour, content-type coverage, and account-stability data. **This is the demo artifact.**

---

## Phase 3 — Evaluate and recommend (Weeks 7–8)

### Week 7 — Demo + internal review (3 business days)

| Task | Deliverable | AI usage |
|---|---|---|
| **7.1 Record a 5–8 minute demo video** walking through: OAuth connection → org picker → first fetch → auto-refresh → rendered page → a simulated error state. | MP4 at `.pitch/linkedin-feeds-evaluation/demo.mp4` | None |
| **7.2 Internal demo + Q&A** — 30-min session with the feed-plugins team (engineering + PM + Alex). Capture questions, challenges, objections into the NOTES file. | Session notes | None |
| **7.3 Write the evaluation** — the written go/no-go as a new section in [PITCH.md](./PITCH.md#observations-from-the-prototype) covering: API capabilities confirmed through prototype, observed limitations (rate, token, content-type, UX), estimated Q3 scope (ranges, not point estimates), recommendation with three specific options (full-go, scope-reduced go, no-go with prerequisites). | PITCH.md §"Observations from the prototype" populated | Medium — Claude structures the findings; human writes the recommendation |

### Week 8 — Stakeholder review + decision (2 business days + 3-day slack)

| Task | Deliverable | AI usage |
|---|---|---|
| **8.1 Stakeholder review** — walk leadership (PM + engineering lead + Alex) through the evaluation. Field challenges to the recommendation. | Meeting notes; concrete Q3-roadmap implications | None |
| **8.2 Finalize the evaluation** — incorporate review feedback, finalize the go/no-go recommendation, archive the pitch folder. | Final [PITCH.md](./PITCH.md) committed; closing memory entry written | Low |
| **8.3 (If go) Draft the follow-on rock proposal** — one-page outline of the Q3 rock that would ship this as a real plugin, pulling scope from the prototype's observations. | `.pitch/linkedin-feeds-evaluation/FOLLOW-ON-Q3-PROPOSAL.md` | Medium |
| **8.4 (If no-go) Document the unblock path** — what specifically would need to change (LinkedIn re-review, new app, product reframe) to make this viable later. | Section appended to PITCH.md | Low |

**Week-8 exit state:** Rock closed. The team has a clear go/no-go and — either way — a document that shortens next time this question comes up.

---

## Schedule summary

| Week | Dates (2026) | Focus | Critical output | Gate |
|---|---|---|---|---|
| 1 | Apr 20–24 | Alex sync + scope expansion + test setup | Read scopes requested; test content set up; architecture decisions made | — |
| 2 | Apr 27 – May 1 | First API contact + infra familiarization | Real JSON from LinkedIn API; ClickSocial relay integration plan | Scope-gate check |
| 3 | May 4–8 | Plugin scaffold + OAuth wiring | Connected LinkedIn source in WP | — |
| 4 | May 11–15 | Fetch + render | Shortcode displays real posts | — |
| 5 | May 18–22 | Hardening + error UX | Self-refreshing prototype | — |
| 6 | May 25–29 | Customizer integration + 48h observation | Customizer-integrated demo; rate-limit data captured | — |
| 7 | Jun 1–5 | Demo + review | Demo video; draft evaluation | — |
| 8 | Jun 8–12 | Stakeholder review + close | Final evaluation; go/no-go decision | Rock close |

**Total working days:** ~32 business days across 8 weeks. Weeks 1–2 are lighter (alignment + probe); weeks 3–5 are head-down build; week 6 is polish + observation; weeks 7–8 are evaluation + decision.

---

## Task budget table

| TASK | DAYS | Phase |
|---|--:|---|
| Alex sync + scope expansion request | 1 | 1 |
| Test account/page setup + product framing decision | 1 | 1 |
| API probe script + first real API call | 1 | 1 |
| Personal-feed endpoint test | 0.5 | 1 |
| ClickSocial infrastructure walkthrough | 1 | 1 |
| Rate-limit + quota research | 0.5 | 1 |
| Plugin scaffold (fork from TikTok Feeds) | 1 | 2 |
| OAuth wiring through ClickSocial relay (coord. w/ Alex) | 2 | 2 |
| Dual-source picker (personal + organization) | 1 | 2 |
| Post-fetch service + parsers | 2 | 2 |
| Media hydration + frontend render | 2 | 2 |
| Cache + scheduled refresh + error UX | 2 | 2 |
| Customizer integration (bonus week) | 2 | 2 |
| Personal vs. org feed comparison test | 0.5 | 2 |
| 48h observation + content-type sampling | 1 | 2 |
| Account-stability monitoring | 0.5 | 2 |
| Demo recording + internal review | 1 | 3 |
| Write evaluation | 1 | 3 |
| Stakeholder review + finalization | 1 | 3 |
| (Optional) Q3 follow-on rock proposal | 1 | 3 |
| Slack / buffer | 5 | — |
| **TOTAL** | **~27 focused + 5 slack ≈ 32 days over 8 weeks** | |

---

## Challenges and hurdles

- **Challenge: ~~ClickSocial app may not be approved for Community Management API.~~** **RESOLVED.** App is already approved. Remaining question is whether read-scope expansion requires a LinkedIn review cycle.
  - **Potential solution (if scope expansion requires review):** Hand-pasted tokens from LinkedIn Developer Console can unblock prototype work while scope review is in flight. Prototype proceeds; real OAuth wiring is the last step.

- **Challenge: ClickSocial relay integration requires Alex's cloud function changes — coordination risk.**
  - **Potential solution:** Scope the relay work in week 1 sync with Alex. If Alex is unavailable for weeks 3–4, a local-only "fake relay" (direct API from WP to LinkedIn with manual client_id injection) is a fallback for prototype purposes, clearly flagged as non-shippable.

- **Challenge: Prototype looks thin vs. TikTok Feeds, risks being dismissed as "not a real product demo".**
  - **Potential solution:** Bonus week 6 adds basic customizer integration — the demo will look more like a real SB plugin. Still frame it as "viability probe" but the visual gap is narrower.

- **Challenge: Test accounts may get blocked after ~24h (confirmed in ClickSocial testing).**
  - **Potential solution:** Use UK IPs for creation. Budget 3–4 test accounts. Monitor in week 6 observation run. If read-only accounts are blocked too (not just posting accounts), document this as a significant production risk.

- **Challenge: 60-day token / 365-day refresh UX feels poor — prototype doesn't fully surface this because we won't see a real 60-day cycle in 8 weeks.**
  - **Potential solution:** Simulate token expiry in week 6 by manually expiring a token in the DB and verifying the reconnect UX engages. Document the real-world annual-reauth nag as a product-design line item in the evaluation.

- **Challenge: LinkedIn `Linkedin-Version: YYYYMM` monthly-version tax — doesn't hit during 8 weeks but shapes the go/no-go.**
  - **Potential solution:** Not solved during prototype; documented in evaluation as an ongoing maintenance tax for the Q3 scope estimate.

---

## Success criteria (how we know the rock succeeded, regardless of go/no-go)

The rock succeeds if, at end of week 8, we have produced **all** of:

1. **A documented answer** to "can the ClickSocial app read LinkedIn posts (personal and/or org)" with evidence — a working prototype.
2. **A working prototype** that demonstrably fetches and renders real posts from a connected LinkedIn source, with customizer integration and self-refreshing cache.
3. **A written evaluation** covering API capabilities through the ClickSocial app, observed limitations (rate limits, token lifecycle, account stability, content-type coverage), estimated Q3 scope, and a clear go/no-go recommendation with three named options.
4. **A demo** (video + live) to the feed-plugins team.
5. **A close-out decision** accepted by leadership, with either a Q3 rock proposal drafted (if go) or an unblock path documented (if no-go).

**What the rock is NOT:** a commitment to ship, a stab at product design, a customer-validated spec. Those are downstream of the evaluation.

---

## Notes on AI usage across the plan

- **Heavy AI usage** (Claude writes most of the code, human reviews): plugin scaffold fork, OAuth popup + fragment JS, post-fetcher service, commentary renderer + tests, cron + cache, error UX.
- **Medium AI usage**: rate-limit summaries, evaluation structure, customizer config.
- **Low / no AI usage**: stakeholder review, test-page setup, demo recording, Alex coordination.

The prototype is small enough that an experienced engineer could write it all by hand in ~4 weeks; with AI pairing it fits in ~3 weeks, leaving week 6 for the customizer bonus + observation. The rock is **time-boxed, not scope-boxed** — if the prototype is running clean by end of week 5, week 6 becomes observation + polish time.
