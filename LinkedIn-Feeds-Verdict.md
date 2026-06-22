# LinkedIn Feeds — Feasibility Verdict & Access Requirements

**Date:** June 16, 2026
**App tested:** ClickSocial (client ID `783vnel7uw3ggw`)
**Method:** Live OAuth + API probes against a real account with Administrator role on two LinkedIn Pages

---

## Verdict

> ### ❌ NOT VIABLE
> **Bucket 1** — document findings & limitations; do not build a prototype.

This is a **Bucket 1** case: a LinkedIn feed plugin in the mold of our other products — connect an account, fetch posts via the API, display them on the site — cannot be built compliantly on this platform. We should document the findings and limitations and **not invest time in a prototype.**

Worth being precise about *why*, because it's a stronger reason than "the prototype would be too limited." The limitation isn't quality — it's that the compliant version of this product **cannot legally exist** on LinkedIn's API: one variant is technically blocked outright, the other is explicitly prohibited by LinkedIn's terms.

## How this maps to the decision framework

The brief set two buckets:

- **Bucket 1** — if the prototype is very limited and would waste time: document findings, limitations, and a **not viable** verdict.
- **Bucket 2** — if we can build even a basic feed like our other plugins that don't have huge API access: build the prototype.

We fail the Bucket 2 test, but not because LinkedIn's access is merely small. Our other plugins (Instagram, Facebook, etc.) ship with modest API access because that access is *permitted*. Here the gap isn't access *size* — it's that LinkedIn has formally named this product category as a prohibited use case, and the one permission that would enable a personal feed is closed to everyone. That lands us firmly in Bucket 1.

## The evidence

There are only two shapes this product could take. We tested both with live API calls.

### 1. Personal / member feeds — technically impossible

Reading a member's own posts requires the scope **`r_member_social`**, which LinkedIn has closed to all new applicants: *"We're not accepting access requests at this time due to resource constraints."*

Live result: querying member posts returned **HTTP 400** — *"Member permissions must be used when using member as author."* The API rejects the request class entirely.

Crucially, this is **not** because the test account had no posts. The member-analytics endpoint returned **HTTP 200** with 670 lifetime impressions — proving the account has published posts and that member-scoped reads work. LinkedIn will hand us the *metrics* but refuses the *content*. An empty feed would have returned 200 with an empty list; a 400 is a permission wall.

### 2. Company / organization Page feeds — technically works, contractually banned

Reading company-page posts **does** work: both admin Pages returned **HTTP 200** with full post content — commentary, article and video media, timestamps. So capability is not the blocker. The blocker is LinkedIn's *Restricted Uses* policy, which names this exact product, verbatim:

> **No Social Feeds:** Under our Marketing API Terms, none of the data provided via our Community Management APIs can be used in a social feed use case (e.g. to display a feed of LinkedIn company updates on the company's website or intranet).

Because the test app is shared with ClickSocial's production posting integration, a terms violation that triggers API revocation would take that integration down too. The risk is concrete, not theoretical.

### Probe results at a glance

| Probe | Result | What it proves |
|---|---|---|
| Org posts (2 Pages) | **200** | Full company-page post content returned. Company feeds are technically readable. |
| Member posts | **400** | No granted scope reads a member's own posts. Personal feeds blocked. |
| Member analytics | **200** (670 impr.) | Decisive control: member has posts & member reads work — yet content is refused. The 400 is a permission wall, not an empty feed. |
| Page discovery | **200** | A source picker (which Pages can be shown) is buildable. |
| Token exchange | **200** | 60-day access + 365-day refresh; annual re-auth is the floor. |

## The limitation stack

Even setting the headline ban aside, a fetch-and-display feed hits a wall of independent blockers. Any one of the first four is, on its own, sufficient to stop the product.

1. **No Social Feeds restricted use** — the product category is banned for Community Management data; violation risks API revocation (and ClickSocial's posting with it).
2. **48-hour storage cap** — member social activity data can't be cached beyond 48 hours, which conflicts directly with a cache-in-WordPress architecture.
3. **Limited Audience rule** — data obtained to manage a Page may only be shown "to individuals associated with that Page." Public website visitors don't qualify, which alone blocks public display.
4. **`r_member_social` is closed** — personal feeds are impossible regardless of everything else.
5. **Standard-tier review gate** — the upgrade from dev tier requires a screencast of each use case, which would show LinkedIn the very product they prohibit.
6. **Token lifecycle & versioning tax** — annual re-auth per customer is the floor, and monthly API versions sunset at ~12 months, committing us to ongoing maintenance per shipped plugin.

## What access would we actually need — and can we buy it?

The natural follow-up: is there a higher tier, partnership, or enterprise API we could purchase that unlocks this? We researched LinkedIn's current programs. Short answer: **no purchasable tier removes either blocker.**

**To build personal feeds** — we would need `r_member_social`. It is a **closed permission**: not available on any tier, not requestable, and absent even from the permission table that approved Community Management partners receive. No contract, spend level, or partner status grants it today. This path is not open to anyone.

**To build company-page feeds** — the technical capability is already in hand. What we would need is *permission to display the data* — exactly what the "No Social Feeds" restriction denies. That restriction is a **platform-wide term of the Marketing API Program** (listed under "Unapproved Use Cases"), not a limit tied to a tier. It applies to all Marketing API partners; the documentation provides no exception for paid, enterprise, or partner status.

**What money actually buys** — LinkedIn does have partner-gated and paid programs (Marketing Developer Platform, Sales Navigator API) on custom enterprise contracts (industry estimates: ~$20k–$75k+/yr, selective approval-based onboarding). But those contracts buy *access to the same APIs at higher rate limits* — they do not exempt the holder from the restricted-use policy or open closed permissions. There is no "enterprise feed API" for sale.

**Conclusion on access:** the only thing that would change this verdict is a LinkedIn policy change — reopening `r_member_social` or carving out a social-feed exception — neither of which is purchasable or on any published roadmap.

## How competitors actually do it

None of the widely-marketed plugins (Elfsight, Juicer, Tagembed, EmbedSocial, SociableKIT) has found a compliant method we're missing. They fall into the two camps above. The diagnostic: the official API can only read a **company page you OAuth into as an admin** — it has no capability to read an arbitrary personal profile, a profile/page by pasted URL, a hashtag, or a group. Anything offering those is not using the official API for them.

- **Elfsight — scraping.** No API key, no OAuth; paste a public profile/company URL, 48-hour cache, works on personal profiles. Only possible by scraping; does not claim official-API use.
- **Juicer — scraping.** Sources include Personal (by profile name), Hashtag, and Group — all impossible via the official API. Positions itself as pulling LinkedIn "without the complexity of LinkedIn's official API."
- **Tagembed — mixed, overstated claim.** Markets "official API, no scraping," but also offers personal-profile and hashtag feeds the API cannot serve. Only its company-page feed could be official API — and that still displays a company feed on a website, the exact "No Social Feeds" prohibition.

Scraping is a worsening legal bet: LinkedIn sued Proxycurl (Jan 2025), which shut down July 2025; Apollo.io and Seamless.AI lost their LinkedIn pages in March 2025. Smash Balloon's brand and WordPress.org standing make scraping a non-starter.

## Recommendation

**Do not build a prototype.** Record this evaluation as the decision artifact. LinkedIn is structurally different from the networks we already support: the gap is legal, not technical, and it cannot be closed by buying access.

If leadership later wants a LinkedIn entry in the catalog, two adjacent products *are* buildable and ToS-defensible, but neither is "a feed like our other plugins" and each is a separate product decision: (a) a **publish-and-display loop** — the plugin posts to LinkedIn from WordPress and displays those posts from its own local copies; and (b) a **curated single-post embed showcase** using LinkedIn's official iframe embeds. Both avoid the read API and the social-feed ban entirely. Flagged only so the "couldn't we…" questions have an answer; recommend deferring unless separately prioritized.

## Sources

- [Restricted Uses of LinkedIn Marketing APIs and Data](https://learn.microsoft.com/en-us/linkedin/marketing/restricted-use-cases?view=li-lms-2026-05)
- [Community Management API — Overview (`r_member_social` closed)](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/community-management-overview?view=li-lms-2026-05)
- [Data Storage Requirements (48-hour cap)](https://learn.microsoft.com/en-us/linkedin/marketing/data-storage-requirements?view=li-lms-2026-05)
- [Additional Terms for the LinkedIn Marketing API Program](https://www.linkedin.com/legal/l/marketing-api-terms)

*Live probe logs and the full technical write-up: `FINDINGS.md` (this folder).*
