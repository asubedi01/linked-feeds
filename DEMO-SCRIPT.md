# LinkedIn Feeds — Demo & Findings Script

**Use:** narration for the stakeholder/video walkthrough. **Length:** ~8–10 min. **Format:** `[SHOW: …]` = stage direction; plain text = spoken. Times are cumulative.
**Live demo site:** `http://smash.docksal.site/` — pages: `linkedin-demo-profile-grid`, `…-company-grid`, `…-layouts`, `…-content-scopes`, `…-comparison`.
**Companion docs:** `LinkedIn-Feeds-Verdict.md` (verdict), `RAPIDAPI-FINDINGS.md` (technical), `API-COMPARISON.md`, `SHORTCODE-DEMO.md`, `DEPLOY.md`.

---

## 0 · One-line verdict (0:00–0:30)

> "Short version: a LinkedIn feed plugin on the **official API is a no** — one feed type is technically blocked, the other is contractually banned, and no amount of money unlocks it. But we proved the product is **fully buildable on a third-party (RapidAPI) route** — here's a working prototype. That route is gated by two things that are leadership/legal calls, not engineering ones: **is scraped data acceptable**, and **who pays for the API**. My ask today is a legal consult plus a cost decision; if both clear, we can ship this in parallel while we pursue official access."

---

## 1 · Scoping — what we set out to answer (0:30–1:30)

- "The brief: can we build an Instagram-Feeds-style product for LinkedIn — connect a source, fetch posts, display them on a WordPress site — across the content scopes we care about: **personal profile posts, company-page posts, and hashtag/search feeds**."
- "Two buckets were defined up front: **Bucket 1** — if it's not viable, document why and stop; **Bucket 2** — if we can build even a basic feed like our other plugins, build a prototype."
- "We did both. The *official* API lands in Bucket 1. Exploring the *alternative* produced a Bucket-2 prototype. I'll keep those two tracks clearly separate."

---

## 2 · Findings, Part A — the Official LinkedIn API: NO (1:30–3:30)

"Everything here is from **live API calls**, not just reading docs."

**Personal feeds — technically impossible.**
- "Reading a member's own posts needs one scope, `r_member_social`. LinkedIn has **closed it** — their words: *'we're not accepting access requests at this time.'*"
- "Live test returned **HTTP 400 — 'Member permissions must be used when using member as author.'** And to prove that's a permission wall and not just an empty account: the member-*analytics* endpoint returned **200 with 670 impressions**. So the posts exist, member reads work — LinkedIn gives us the *metrics* but refuses the *content*."

**Company feeds — works technically, banned contractually.**
- "Company-page posts *did* return **200 with full content**. So capability isn't the blocker. The blocker is LinkedIn's Restricted Uses policy, which names our exact product, verbatim:" [SHOW: the quote]
  > **No Social Feeds:** none of the data provided via our Community Management APIs can be used in a social feed use case (e.g. to display a feed of LinkedIn company updates on the company's website).
- "Backed by a **48-hour storage cap** that kills caching, and a **limited-audience rule** — the data may only be shown to people associated with the Page, not public visitors."

**Can we buy our way out? No.**
- "`r_member_social` isn't on any tier, paid or partner. The 'No Social Feeds' rule is a platform-wide term with no enterprise exception. Paid programs buy higher rate limits on the *same* APIs — not an exemption."
- "This only flips if LinkedIn changes policy, **or** we obtain written permission **and pass their Standard-tier review** with an honest feed use case. Until then: **No.**"

**And the legal climate is why scraping isn't a quiet workaround** (keep this on the record):
- "LinkedIn **sued Proxycurl in Jan 2025; it shut down July 2025.** Apollo and Seamless had their LinkedIn pages removed in March 2025. Their User Agreement has an explicit anti-scraping clause."

---

## 3 · Findings, Part B — the RapidAPI alternative + prototype (3:30–4:30)

- "Since the official door is closed, we evaluated the **RapidAPI marketplace** — third-party services that return LinkedIn data by API key, no OAuth. This is the mechanism behind every URL-paste competitor."
- "We went far enough to **build a working WordPress plugin** — so this is a running product, not a slide."
- "Two interchangeable providers behind one normalizer — **Fresh LinkedIn Scraper** and **Fresh LinkedIn Profile Data** — one RapidAPI key covers both. Swappable from a setting."
- "**All three content scopes work**: personal, company, and hashtag/search. Verified live — e.g. a `#AI` search returned 49 posts."
- "Cost per feed is low at steady state — **about one API call per refresh** (IDs and company logos are cached). Page views cost nothing; everything renders from cache."

---

## 4 · LIVE DEMO (4:30–7:00)

> Tip: lead with **demo mode** (offline, no quota, can't break mid-talk). Keep one live example in reserve.

1. **Personal + company feeds.** [SHOW: `linkedin-demo-profile-grid`, then `…-company-grid`]
   "Real posts — Bill Gates' profile, Microsoft's page. Each card: author, text, media, engagement counts, and a 'View on LinkedIn' link."

2. **Rich LinkedIn-native media — our differentiation.** [SHOW: scroll a feed with image, video, a PDF/document card, and an article link-preview]
   "Images, native video, **document/PDF carousels, and article link-previews**. This matters: LinkedIn feeds are dominated by article shares and PDF posts — and **most competitors don't render those well.** That's our clearest place to win."

3. **Four layouts.** [SHOW: `linkedin-demo-layouts` — scroll grid → list → masonry → carousel; use the carousel arrows]
   "Grid, list, masonry, and a carousel — pure CSS, responsive, theme-agnostic."

4. **Interactions.** [SHOW: click an image → lightbox; click a card → post-detail popup]
   "Click an image for a lightbox; click a card for an enlarged post-detail popup. Dependency-free, keyboard-accessible."

5. **All four content scopes on one page.** [SHOW: `linkedin-demo-content-scopes`]
   "Personal, company, **hashtag (#AI), and keyword search** — same UI, four different sources."

6. **Two providers, identical UI.** [SHOW: `linkedin-demo-comparison`]
   "Same source through both providers, side by side. The card design is identical by design — swap the data provider, the UI doesn't change. The little 'Source' badges show which is which."

7. **Keeping it live.** [SHOW: Settings → LinkedIn Feeds → "Refresh demo data" button]
   "LinkedIn's media URLs are signed and expire — videos in about a week, images in about three. This button re-captures fresh data so the demo stays live while you decide. Live feeds self-heal automatically every hour."

---

## 5 · The two real constraints (7:00–8:30)

**Constraint 1 — Compliance (a legal call).**
- "Every RapidAPI provider is **scraping LinkedIn underneath** — the official API can't read arbitrary profiles by URL, so anything that does is scraping. That triggers the anti-scraping User Agreement and the Proxycurl/Apollo/Seamless risk, plus reseller risk — a provider can vanish or be de-listed overnight."
- "And **hashtag/search is the most exposed scope** — it pulls arbitrary third parties' posts by topic, and the official API has **no hashtag/search capability at all**, so there's no future official fallback. If we do it, it's scraping-only, permanently — enable it last."

**Constraint 2 — Cost (the free tier won't sustain a product).**
- "Cost scales with **feeds × refresh × installs**, not traffic. One feed refreshed hourly is ~720 calls a month — about **14× the free tier**. The free tier realistically covers **one feed refreshed daily**."
- "At scale it forces a choice: **we host one key** — recurring per-call cost that grows with adoption (~10k installs on a daily refresh ≈ 300k calls/month, landing in the $200–$500+/month tiers) — turning a one-time-license plugin into ongoing COGS; **or each customer brings their own key** — a signup-and-monthly-bill friction our official-API plugins don't have. Either way, **decide before building.**"

---

## 6 · Recommendation / the ask (8:30–9:30)

1. "**Pursue the official API** as the durable goal — file the permission/exception applications, use the partner channel — but treat it as a **No until won**."
2. "**Before any RapidAPI build, clear two gates:** first, a **legal consult** on the scraping posture — this is counsel's call, not ours; second, **decide the cost model** — who pays, refresh cadence, caching."
3. "**If — and only if — legal clears it and the cost model closes**, we harden this prototype and ship it as an **interim / parallel offering while we work on obtaining official access.** The official API stays the long-term foundation; RapidAPI is the bridge that gets us in-market sooner."

> "In one line: **official API — no until permissions and review are won; RapidAPI — proven by this prototype, but consult legal and solve cost first, then we can run it in parallel.**"

---

## 7 · Q&A prep — anticipated questions

- **"How do competitors do it then?"** — Scraping (Elfsight, Juicer) or the gray-zone 'No Social Feeds' use case (Tagembed). None found a compliant method we're missing; vendor 'official API / compliant' claims don't hold up.
- **"Can't we just pay LinkedIn for access?"** — No purchasable tier removes either blocker; the closed scope and the social-feed ban are policy, not pricing.
- **"How reliable is the data?"** — fresh-profile is solid (verified). fresh-scraper's *search* is currently returning 429s; the plugin auto-routes search to the reliable provider and surfaces failures cleanly. Scrapers are inherently flaky — factor it in.
- **"What's left to build before shipping?"** — Mainly **local media re-hosting** (so feeds don't break when URLs expire) plus polish (load-more, presets). Data and core UI are done.
- **"Is the prototype safe to show externally?"** — It's an internal prototype on the scraper route. Don't represent it as a shipping product until the verdict's gates are cleared.
- **"What did this cost to explore?"** — A free-tier RapidAPI key (~50 calls/mo); the whole prototype was built within it.

---

## Appendix — key facts to have ready

| Fact | Number / detail |
|---|---|
| Personal feed (official) | `r_member_social` **closed**; live **400** |
| Member analytics control | **200**, 670 impressions (proves permission wall) |
| Company feed (official) | **200** content, but **"No Social Feeds"** ban + 48h cap + limited-audience |
| Scraping precedent | Proxycurl sued 1/2025 → shut 7/2025; Apollo/Seamless removed 3/2025 |
| Providers | fresh-scraper + fresh-profile; **one key**; switchable |
| Calls/feed | scraper 2 (resolve+posts); profile 1 (+1 company logo); **~1/refresh steady state** |
| Posts per call | scraper ~10–20; profile 50 |
| Content scopes | personal ✅ · company ✅ · hashtag/search ✅ (search 429s on scraper) |
| Media expiry | video/PDF ~6–7 days; images/logos ~21 days |
| Layouts / UI | grid·list·masonry·carousel; post-detail popup; image lightbox |
| Cost math | 1 feed hourly ≈ 720 calls/mo (~14× free 50/mo); ~10k installs daily ≈ 300k/mo |
| Recommendation | Official = No until permission+review; RapidAPI = legal consult + cost model → then parallel ship |
