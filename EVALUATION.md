# LinkedIn Feeds — Evaluation Summary & Recommendation

**Date:** June 23, 2026 *(updated; original close-out June 11, 2026)*
**Owner:** Asmita Subedi
**Status:** Rock close-out + RapidAPI alternative explored (working prototype built) — for stakeholder + legal review
**Detail docs:** [LinkedIn-Feeds-Verdict.md](./LinkedIn-Feeds-Verdict.md) (primary verdict) • [FINDINGS.md](./FINDINGS.md) • [RAPIDAPI-FINDINGS.md](./RAPIDAPI-FINDINGS.md) • [API-COMPARISON.md](./API-COMPARISON.md) • [SHORTCODE-DEMO.md](./SHORTCODE-DEMO.md) • [probe/](./probe/README.md)

> **Update (June 23):** the original close-out below is unchanged on the **official API** — still a no-go for both feed types. Since then we explored the **RapidAPI third-party route** and built a **working prototype** (both feed types, four layouts, media, popup). It proves the product is *technically* buildable off-platform, but raises **legal (scraping) and cost (free tier unsustainable)** constraints. See the new section "**Alternative explored: RapidAPI**" and the revised decisions. The full, current verdict lives in **[LinkedIn-Feeds-Verdict.md](./LinkedIn-Feeds-Verdict.md)**.

---

## Verdict (official API)

**A classic LinkedIn feed plugin on the *official API* — user connects account, we fetch and display their posts on their website — is not viable today, for either personal or company-page feeds.**

The block is not technical. The API works, our apps are approved partners, OAuth and token refresh work, and the org-posts endpoint is callable with scopes we already hold. The block is LinkedIn's contract:

1. **Personal feeds:** the only scope that reads a member's post content (`r_member_social`) is a **closed permission** — LinkedIn is not accepting access requests from anyone. Confirmed in current docs and **confirmed live**: our API call with every granted scope returned `400 "Member permissions must be used when using member as author"`.
2. **Organization feeds:** technically working, but LinkedIn's Restricted Uses policy names our product verbatim as banned: *"**No Social Feeds:** …none of the data provided via our Community Management APIs can be used in a social feed use case (e.g. to display a feed of LinkedIn company updates on the company's website)."* Backed by a **48-hour storage cap** (kills caching) and a **limited-audience rule** (member data displayable only to people associated with the Page — not public site visitors). Verified live against the current policy, June 10, 2026.

This supersedes the April research's "viability is strong" conclusion — that work mapped the endpoints correctly but missed the Restricted Uses page.

## Evidence base

| Claim | Evidence |
|---|---|
| Org-post read scope works mechanically | Live OAuth + API probes on both apps (ClickSocial prod + SB CS DEV); granted scope string includes `r_organization_social`; org-ACL endpoint returns 200 |
| Member post read is closed | Live 400 rejection on `/rest/posts?author=urn:li:person:…`; docs mark `r_member_social` "closed, not accepting requests" |
| `r_member_postAnalytics` is metrics-only | Docs verified (response schema has no content fields); endpoint probed |
| Token lifecycle manageable | Live token exchange returned 60-day access + 365-day refresh token on both apps — silent refresh works; annual re-auth is the floor |
| Feed use case banned, 48h storage, limited audience | Restricted Uses policy fetched and quoted June 10, 2026 |
| Competitors offering personal feeds use scraping | No API scope could power it; LinkedIn sued Proxycurl (shut down July 2025), removed Apollo/Seamless pages — scraping is a rising legal risk and a non-starter for SB |
| Both ClickSocial apps are interchangeable, with identical gaps | Identical granted scope strings + refresh tokens verified live on both |

Outstanding probe: `org-posts` against a company page we admin — **closed since**; returned 200 with full post content on two admin Pages.

## Alternative explored: RapidAPI third-party providers (prototype built)

Since the official-API no-go, we evaluated the **RapidAPI** route (third-party services exposing LinkedIn data by API key — the mechanism behind the URL-paste competitors) and built a **working WordPress prototype**:
- **All three content scopes** (personal + company + **hashtag/search**), **two switchable providers**, **four layouts** (grid/list/masonry/carousel), post-detail popup, lightbox, and full media (images, video, **PDF/document carousels**, **article previews** — the LinkedIn-native types competitors mostly miss). Verified live (incl. `#AI` search → 49 posts on fresh-profile; fresh-scraper search 429'd — auto-routes to the reliable provider).
- **Scope note:** hashtag/search is the **highest-compliance-risk** scope (arbitrary third-party posts by topic) with **no official-API fallback** — enable last, flag to legal.
- **Technically, the product is fully buildable this way** — the data is sufficient; the rest is front-end polish.

But two constraints gate it:
1. **Legal (scraping):** every RapidAPI LinkedIn provider scrapes LinkedIn underneath (the official API can't read arbitrary profiles/companies by URL). That implicates LinkedIn's **User-Agreement anti-scraping clause** and the brand/legal exposure of the **Proxycurl (sued, shut down July 2025) / Apollo / Seamless** precedents — on top of reseller risk (a provider can vanish or be de-listed). **A legal-team call, not an engineering one.**
2. **Cost (free tier won't sustain a product):** request volume scales with **feeds × refresh × installs**, not page views. One feed refreshed hourly ≈ **720 calls/mo** — ~14× the 50/mo free tier; the free tier realistically covers **~1 feed refreshed daily**. At scale, either **AM hosts a shared key** (recurring per-call COGS that grows with the install base — ~10k installs daily ≈ 300k calls/mo → the $200–500/mo+ tiers) **or customers bring their own key** (signup friction + a monthly bill, unlike our free-to-user official-API plugins). Needs a deliberate cost/architecture decision before any build. Detail: [RAPIDAPI-FINDINGS.md](./RAPIDAPI-FINDINGS.md), [API-COMPARISON.md](./API-COMPARISON.md).

## What we *can* build on the official platform (descending compliance confidence)

1. **Curated embeds showcase** — LinkedIn officially supports single-post iframe embeds for public posts. A "paste post URLs, get a styled wall" plugin is ToS-clean and covers personal *and* org posts. Manual curation, no live feed. Cheapest way to test market demand for "LinkedIn on your website."
2. **Publish-and-display loop** — write scopes (`w_member_social`, `w_organization_social`) are unrestricted, including personal. A plugin that *posts to* LinkedIn from WordPress already owns the content + post URN locally, so the site can render a feed of those posts **from its own copies** — no Community Management data displayed, deep links to live posts. Only shows posts made through the plugin, but it's automatic, covers personal profiles, and sits on defensible ToS ground (needs a legal sanity check). Natural ClickSocial × Smash Balloon play.
3. **LinkedIn analytics dashboard (wp-admin)** — org engagement, follower growth, member post/profile analytics shown *to the page admin*, which is exactly the use case the granted scopes exist for. Compliant, but a different product than feeds.
4. **DMA experiment (EEA-only)** — the Member Data Portability (3rd Party) product is the only obtainable route to real member post content. EEA-member consent only; separate portability terms. Niche evidence-builder, not a global product base.

## Paths to unblock real feeds (all routed through LinkedIn's permission)

- **Formal exception ask:** create an independent research app (also keeps experiments off ClickSocial production), apply for the Community Management API with an **honest** feed use-case statement (draft in [FINDINGS.md §4b](./FINDINGS.md)). Outcome either unblocks org feeds or produces LinkedIn's official "no" in writing. Note: applying with a disguised use case only defers rejection to the Standard-tier screencast review (≤12 months) with revocation risk.
- **Partner channel:** ask via ClickSocial's CMA relationship. Precedent: LinkedIn opened member *analytics* to a partner cohort (Hootsuite, Buffer, Metricool) in July 2025 — the channel works when LinkedIn wants it to.
- **Support ticket:** ask whether `r_member_social` can ever be granted to an approved CMA app — cheap, gets the answer on record.
- **Watch:** monthly API release notes; quarterly recheck. The 2025 analytics opening shows member-level access can thaw.

## Decisions requested

1. **Accept the official-API no-go** for the classic fetch-and-display feed plugin (both personal and org) — until permissions are granted *and* the Standard-tier review is passed, or LinkedIn changes policy.
2. **Consult the legal team on the RapidAPI route (blocking gate).** The prototype proves it works technically; whether we can ship scraped LinkedIn data given the anti-scraping User Agreement, the Proxycurl/Apollo/Seamless precedents, and SB's brand/WP.org standing is a **counsel decision**. Do this before any productization.
3. **If legal clears it, decide the RapidAPI cost model** (who pays — AM shared key vs. customer key — refresh cadence, relay/proxy, cache TTLs). The free tier is a prototype tool, not a product base.
4. **Pursue the official API in parallel as the durable goal:** independent research app + honest CMA application + DMA product application (needs AM business verification) + partner-channel conversation. ~2–3 days of effort, mostly waiting on LinkedIn.
5. **Optionally, a pivot to validate:** embeds showcase (smallest), publish-and-display loop (most strategic), analytics dashboard (different buyer).
6. **Housekeeping:** rotate both app secrets and the RapidAPI key (all shared during evaluation), revoke test tokens.

**Net recommendation:** Official API stays the long-term foundation but is a No until won. The RapidAPI prototype is real and shippable *technically* — so **consult legal first**, then solve cost; **if both clear, implement RapidAPI as an interim/parallel offering while we work on obtaining the official LinkedIn API.**

## Cost of being wrong, both directions

Shipping the feed anyway (as several widget vendors do) bets a flagship brand on a gray zone LinkedIn is actively policing — and a violation on a shared app would take down ClickSocial's production posting integration. Walking away entirely forfeits the most-requested missing platform and any first-mover position if LinkedIn's posture thaws, which the July 2025 analytics opening suggests is possible. The middle path — pivot product + formal exception ask — costs days, not a quarter, and converts "LinkedIn is hard" folklore into either a green light in writing or a documented dead end.
