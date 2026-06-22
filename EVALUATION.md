# LinkedIn Feeds — Evaluation Summary & Recommendation

**Date:** June 11, 2026
**Owner:** Asmita Subedi
**Status:** Rock close-out — for stakeholder review and decision
**Detail docs:** [FINDINGS.md](./FINDINGS.md) (full analysis & sources) • [RESEARCH.md](./RESEARCH.md) • [PITCH.md](./PITCH.md) • [PLAN.md](./PLAN.md) • [probe/](./probe/README.md) (runnable evidence)

---

## Verdict

**A classic LinkedIn feed plugin — user connects account, we fetch and display their posts on their website — is not viable today, for either personal or company-page feeds.**

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

Outstanding probe: `org-posts` against a company page we admin (test account admins none yet). Expected to work; worth the screenshot for completeness.

## What we *can* build (descending compliance confidence)

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

1. **Accept the no-go** on the classic fetch-and-display feed plugin for Q3 (both personal and org).
2. **Choose a pivot to validate** (or none): embeds showcase (#1, smallest), publish-and-display loop (#2, most strategic), analytics dashboard (#3, different buyer). Recommendation: scope #2 for a demand/legal check before any build commitment.
3. **Approve the exception ask:** independent research app + honest CMA application + DMA product application (needs AM business verification: legal name, address, privacy policy, business email) + partner-channel conversation via Alex. ~2–3 days of effort total, mostly waiting on LinkedIn.
4. **Housekeeping:** rotate both app secrets (shared during this evaluation), revoke test tokens, scrub tokens from probe/README.md before commit.

## Cost of being wrong, both directions

Shipping the feed anyway (as several widget vendors do) bets a flagship brand on a gray zone LinkedIn is actively policing — and a violation on a shared app would take down ClickSocial's production posting integration. Walking away entirely forfeits the most-requested missing platform and any first-mover position if LinkedIn's posture thaws, which the July 2025 analytics opening suggests is possible. The middle path — pivot product + formal exception ask — costs days, not a quarter, and converts "LinkedIn is hard" folklore into either a green light in writing or a documented dead end.
