# LinkedIn Feeds — Feasibility Verdict & Access Requirements

**Date:** June 23, 2026 *(updated; original verdict June 16, 2026)*
**Routes tested:** Official LinkedIn API (via ClickSocial app, client ID `783vnel7uw3ggw`) · third-party RapidAPI providers (`fresh-linkedin-scraper-api`, `fresh-linkedin-profile-data`)
**Method:** Live OAuth + API probes (official) · live API probes **+ a working WordPress prototype** (RapidAPI)

---

## Verdict at a glance

| Route | Status | Why |
|---|---|---|
| **Official LinkedIn API** | ❌ **NO — unchanged** | Personal feeds need a **closed** permission; company feeds are a **contractually banned** use case. Only a LinkedIn policy change, **or** our obtaining the permission *and* passing the Standard-tier review, unblocks it. |
| **RapidAPI third-party providers** | ⚠️ **Technically viable — prototype built — gated by legal + cost** | The literal product (personal + company feeds, four layouts, media) renders end-to-end. But the data is **scraped** (User-Agreement / brand exposure) and the economics **do not sustain on free/low tiers**. Needs legal clearance + a cost-model decision. |

**The official API remains a hard No, and nothing here softens that.** What changed since June 16 is that we explored the **alternative** (RapidAPI) far enough to ship a working prototype. That reframes the question from *"can a LinkedIn feed product exist?"* to *"should we run the scraped-data version — and at what cost — while we pursue the durable official route?"*

---

# Part A — Official LinkedIn API: still NOT VIABLE

A LinkedIn feed plugin in the mold of our other products — connect an account, fetch posts via the official API, display them on the site — **cannot be built compliantly on LinkedIn's platform today.** The limitation isn't quality or access size; it's that the compliant version **cannot legally exist** on LinkedIn's API: one variant is technically blocked outright, the other is explicitly prohibited by LinkedIn's terms.

## The evidence (live API calls)

### 1. Personal / member feeds — technically impossible
Reading a member's own posts requires the scope **`r_member_social`**, which LinkedIn has closed to all new applicants: *"We're not accepting access requests at this time due to resource constraints."*

Live result: querying member posts returned **HTTP 400** — *"Member permissions must be used when using member as author."* Crucially this is **not** an empty feed: the member-analytics endpoint returned **HTTP 200** with 670 lifetime impressions, proving the account has posts and member-scoped reads work. LinkedIn hands us the *metrics* but refuses the *content* — a permission wall, not absence of data.

### 2. Company / organization Page feeds — technically works, contractually banned
Reading company-page posts **does** work: both admin Pages returned **HTTP 200** with full post content. Capability is not the blocker. The blocker is LinkedIn's *Restricted Uses* policy, which names this exact product, verbatim:

> **No Social Feeds:** Under our Marketing API Terms, none of the data provided via our Community Management APIs can be used in a social feed use case (e.g. to display a feed of LinkedIn company updates on the company's website or intranet).

Because the test app is shared with ClickSocial's production posting integration, a terms violation that triggers API revocation would take that integration down too. The risk is concrete, not theoretical.

### Probe results at a glance
| Probe | Result | What it proves |
|---|---|---|
| Org posts (2 Pages) | **200** | Full company-page post content returned. Company feeds are technically readable. |
| Member posts | **400** | No granted scope reads a member's own posts. Personal feeds blocked. |
| Member analytics | **200** (670 impr.) | Decisive control: member has posts & member reads work — yet content is refused. |
| Page discovery | **200** | A source picker (which Pages can be shown) is buildable. |
| Token exchange | **200** | 60-day access + 365-day refresh; annual re-auth is the floor. |

## The limitation stack
Any one of the first four is, on its own, sufficient to stop the product.

1. **No Social Feeds restricted use** — the product category is banned for Community Management data; violation risks API revocation (and ClickSocial's posting with it).
2. **48-hour storage cap** — member social activity data can't be cached beyond 48 hours, conflicting directly with a cache-in-WordPress architecture.
3. **Limited Audience rule** — data obtained to manage a Page may only be shown "to individuals associated with that Page." Public website visitors don't qualify.
4. **`r_member_social` is closed** — personal feeds are impossible regardless of everything else.
5. **Standard-tier review gate** — the upgrade from dev tier requires a screencast of each use case, which would show LinkedIn the very product they prohibit.
6. **Token lifecycle & versioning tax** — annual re-auth per customer is the floor; monthly API versions sunset at ~12 months, committing us to ongoing maintenance per shipped plugin.

## What access would we actually need — and can we buy it?
**No purchasable tier removes either blocker.**

- **Personal feeds** need `r_member_social` — a **closed permission**: not on any tier, not requestable, absent even from the table approved Community Management partners receive. No contract, spend, or partner status grants it today.
- **Company feeds** need *permission to display the data* — exactly what "No Social Feeds" denies. That restriction is a **platform-wide term** of the Marketing API Program ("Unapproved Use Cases"), not a tier limit; no paid/enterprise/partner exception is documented.
- **What money buys** — LinkedIn's partner-gated programs (Marketing Developer Platform, Sales Navigator API; industry estimates ~$20k–$75k+/yr) buy *higher rate limits on the same APIs*. They do **not** exempt the holder from the restricted-use policy or open closed permissions. There is no "enterprise feed API" for sale.

**The only things that flip this verdict:** (a) LinkedIn reopens `r_member_social` or lifts the social-feed restriction (not purchasable, not on any published roadmap), **or** (b) we obtain a written exception/permission through a formal application **and pass the Standard-tier review** with an honest feed use case. Until one of those happens, the official route is **No.**

## How competitors do it — and the legal climate (do not discount this)
None of the widely-marketed plugins (Elfsight, Juicer, Tagembed, EmbedSocial, SociableKIT) has found a compliant method we're missing. The diagnostic: the official API can only read a **company page you OAuth into as an admin** — it cannot read an arbitrary personal profile, a profile/page by pasted URL, a hashtag, or a group. Anything offering those is **not** using the official API for them.

- **Elfsight — scraping.** No API key/OAuth; paste a public profile/company URL, 48-hour cache, works on personal profiles. Only possible by scraping; doesn't claim official-API use.
- **Juicer — scraping.** Sources include Personal (by name), Hashtag, Group — all impossible via the official API.
- **Tagembed — mixed, overstated claim.** Markets "official API, no scraping," but offers personal-profile and hashtag feeds the API cannot serve.

**Scraping is a worsening legal bet — and this is the crux of the legal review below:**
- **LinkedIn sued Proxycurl (Jan 2025); Proxycurl shut down July 2025.**
- **Apollo.io and Seamless.AI had their LinkedIn pages removed (March 2025).**
- LinkedIn's User Agreement contains an explicit **anti-scraping clause**; *hiQ Labs v. LinkedIn* ultimately went against the scraper on contract grounds.

Smash Balloon's brand and WordPress.org standing make an unconsidered scraping posture a non-starter. **These cases are why the RapidAPI route (Part B) is a legal decision, not just a technical one.**

---

# Part B — Alternative route explored: RapidAPI third-party providers

Because the official route is closed, we evaluated the **RapidAPI marketplace** — third-party services that expose LinkedIn data via a simple API key (no OAuth, no LinkedIn app). This is the route the URL-paste competitors use. We went far enough to **build a working prototype**, so the assessment rests on a running product, not speculation.

## What we built (and proved)
A WordPress plugin with a `[linkedin_feed]` shortcode that renders **both personal and company feeds** — the literal product the official API cannot deliver:
- **Two interchangeable providers** behind a normalizer (`fresh-scraper`, `fresh-profile`), switchable in Settings or per-shortcode.
- **Four layouts** (grid / list / masonry / carousel), **post-detail popup**, image **lightbox**, and full media rendering — images, **video**, **document/PDF carousels**, **article link-previews** (the LinkedIn-native content types competitors mostly fail to render — our clearest differentiation; see `RAPIDAPI-FINDINGS.md §6`).
- Verified live against real data (Bill Gates profile, Microsoft company page).

**Conclusion: technically, the product is fully buildable on RapidAPI** — both feed types, rich media, polished UI. The data is sufficient; what remains is front-end polish, not API capability.

## Constraint 1 — Compliance (the legal question)
Every RapidAPI LinkedIn provider is **scraping LinkedIn underneath** — the official API cannot read arbitrary profiles/companies by URL, so anything that does is scraping by definition. That means:
- It implicates LinkedIn's **User Agreement anti-scraping clause** — a *separate, additional* exposure beyond the Marketing-API "No Social Feeds" ban.
- It inherits the brand/legal risk of the **Proxycurl / Apollo / Seamless** precedents above. The exposure shifts to a third-party reseller who can be sued out of existence or de-listed overnight — taking every customer's feed down with them.
- Vendor "compliant / official API" marketing does **not** hold up; we verified the underlying mechanism is scraping.

This is **not a determination we can make ourselves** — it is a legal call for AM's counsel (see Recommendation).

## Constraint 2 — Cost (the free tier will not sustain a product)
This is the constraint most likely to be underestimated, so it is spelled out.

**Request volume scales with `feeds × refresh frequency × installs` — not page views** (rendering is served from cache). The math is unforgiving:

- A **single feed refreshed hourly ≈ 720 calls/month** — already **~14× over** the `fresh-scraper` free tier (50/mo) and ~10× over the other family's free tier (75/mo).
- The free tier realistically supports **~1 feed refreshed once daily** (~30 calls/mo). That is a *demo/prototype* allowance, **not a product**.
- Free/low tiers also carry **rate caps** (e.g., 20 req/min) and **no SLA**; providers can **raise prices or disappear** (Proxycurl did).

**Paid tiers don't make the economics simple — they expose a structural problem.** Whoever holds the key pays per call, and a feed plugin's calls scale with adoption:

| Funding model | What happens | Problem |
|---|---|---|
| **AM hosts one shared key** | Cost scales with the **entire install base**. ~10k installs × 1 daily refresh ≈ **300k calls/mo** → the **$200–$500/mo+** tiers, climbing with adoption and any move to hourly refresh. | Turns a typically **one-time-license** plugin into an open-ended **recurring COGS** — a margin/clawback risk that grows with success. |
| **Each customer brings their own RapidAPI key** | Pushes **signup friction + a monthly bill** onto every customer (our official-API plugins are free to the user). | Hurts conversion, raises support load, and chains our product to a third-party scraper's pricing/uptime/ToS. |

**Bottom line on cost:** the **free RapidAPI tier is a prototyping tool, not a sustainable production base.** Any RapidAPI productization requires a deliberate decision on architecture (relay/proxy, aggressive cache TTLs, refresh cadence) and **who pays** — before, not after, a build commitment.

---

# Recommendation

1. **Official LinkedIn API — pursue it as the durable goal, but treat it as a No until won.** File the formal exception / permission applications (honest feed use case; independent research app to keep experiments off ClickSocial production), open the partner channel via ClickSocial's CMA relationship, and watch the monthly release notes. The verdict only flips with a written permission **and** a passed Standard-tier review, or a LinkedIn policy change. Keep the legal evidence above on file — it is the documented basis for the No.

2. **RapidAPI — do not ship yet; first clear two gates, in order:**
   - **Legal gate (blocking):** **Consult AM's legal team** on the scraping posture — the User-Agreement anti-scraping clause, the Proxycurl/Apollo/Seamless precedents, brand and WordPress.org exposure, and reseller risk. This is a counsel decision, not an engineering one.
   - **Cost gate:** decide the funding model and architecture (who pays, refresh cadence, relay/proxy, cache TTLs). The free tier cannot back a product.

3. **If — and only if — legal clears it and the cost model closes:** the RapidAPI prototype can be hardened and shipped as an **interim / parallel offering while we work on obtaining the official LinkedIn API.** The official API remains the long-term foundation; RapidAPI is the bridge that lets us be in-market sooner **if counsel and economics permit.** (Remaining engineering before any ship: local media re-hosting — LinkedIn CDN URLs expire in ~1–3 weeks — plus the polish items in `RAPIDAPI-FINDINGS.md §7`.)

**In one line:** Official API — still No until permissions + review are won. RapidAPI — technically proven by a working prototype, but **consult legal first**, solve the cost model, and only then consider implementing it **in parallel** while pursuing the official route.

---

## Sources
- [Restricted Uses of LinkedIn Marketing APIs and Data](https://learn.microsoft.com/en-us/linkedin/marketing/restricted-use-cases?view=li-lms-2026-05)
- [Community Management API — Overview (`r_member_social` closed)](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview?view=li-lms-2026-05)
- [Data Storage Requirements (48-hour cap)](https://learn.microsoft.com/en-us/linkedin/marketing/data-storage-requirements?view=li-lms-2026-05)
- [Additional Terms for the LinkedIn Marketing API Program](https://www.linkedin.com/legal/l/marketing-api-terms)
- Scraping legal climate: LinkedIn v. Proxycurl (shutdown July 2025); Apollo.io / Seamless.AI page removals (March 2025); LinkedIn User Agreement anti-scraping clause.
- RapidAPI route specifics, prototype, providers, cost/scaling, competitor display survey: `FINDINGS.md §7`, `API-COMPARISON.md`, `RAPIDAPI-FINDINGS.md`, `SHORTCODE-DEMO.md`.

*Live probe logs and the full technical write-up: `FINDINGS.md`.*
