# RapidAPI sample responses — data contract for the feed renderer

Live captures from **Fresh LinkedIn Scraper API** (`fresh-linkedin-scraper-api.p.rapidapi.com`), June 18, 2026, via `../rapidapi-probe.php`. These are the real payloads to build the shortcode renderer against — no live API call needed during development.

| File | Source call | Contents |
|---|---|---|
| `user-profile-williamhgates.json` | `user-profile williamhgates` | Profile header (name, headline, avatar[], cover[], location) + the `data.urn` used to fetch posts |
| `profile-posts-williamhgates.json` | `profile-posts <urn>` | **Personal feed** — 20 posts: 8 article, 5 video, 4 image, 1 document, plus text-only |
| `company-posts-1035.json` | `company-posts 1035` (Microsoft) | **Org feed** — 10 posts: document, image, video |
| `freshprofile-profile-williamhgates.json` | **2nd provider** `fresh-linkedin-profile-data` `/get-profile-posts?linkedin_url=…/in/williamhgates` | **Personal feed, alt provider** — 50 posts: 19 article, 14 video, 11 image, 3 document, 3 text. Verifies the `fresh-profile` provider mapping. |
| `freshprofile-company-microsoft.json` | **2nd provider** `fresh-linkedin-profile-data` `/get-company-posts?linkedin_url=…/company/microsoft` | **Org feed, alt provider** — 50 posts: 20 video, 17 image, 13 text. Verifies `fresh-profile` company mapping. **Posters logo-enriched locally** (see below). |
| `freshprofile-companydetails-microsoft.json` | `/get-company-by-linkedinurl?linkedin_url=…/company/microsoft` | **Company details** — `logo_url`, `tagline`, `follower_count`, `description`, HQ, industries, etc. Source of the company logo (not in the posts payload). |

#### Company logo requires a 2nd call on `fresh-linkedin-profile-data`

Its `/get-company-posts` payload omits the company logo (poster = `{ name, linkedin_url }`). The logo (`logo_url`) comes only from `/get-company-by-linkedinurl` — a **separate, cacheable call**. The provider (`class-provider-fresh-profile.php`) fetches it once per company (cached a week) and injects it into each post's `poster.image_url`. *(Contrast: `fresh-linkedin-scraper-api` embeds the logo directly in its company-posts response, so no extra call.)* The committed-locally `freshprofile-company-microsoft.json` sample has been logo-enriched so demo mode renders the real logo without a live call.

### `fresh-linkedin-profile-data` schema notes (differs from `fresh-linkedin-scraper-api`)

Mapped in `includes/providers/class-provider-fresh-profile.php`. Real shape (verified June 18 profile / June 22 company):
- Wrapper: `{ data:[…], message, paging:{count,pagination_token,start} }`.
- Post: `urn`, `text`, `share_urn`, `posted` (`"2026-06-17 18:30:19"`), `time` (relative), `num_likes/comments/reposts` + per-type `num_appreciations/empathy/interests/praises/entertainments`, `reshared`, `repost_*`.
- **Permalink field differs by feed type:** profile posts use **`post_url`**, company posts use **`url`**. The provider falls back `post_url → url`.
- `poster` shape differs by type: **profile** = `{ first, last, headline, image_url, linkedin_url, public_id, urn }` (present ~43/50; reshares fall back to top-level `poster_linkedin_url`); **company** = `{ name, linkedin_url }` only — **no avatar, no headline**. The provider prefers `poster.name`, else `first`+`last`, else a slug-derived name.
- Media: `video:{stream_url,duration}` (**no thumbnail** — video tiles render without a poster), `document:{title,page_count,url}`, **article fields are FLAT** (`article_title`, `article_subtitle`, `article_target_url`), `images:[{url}]`.
- Media URLs are signed/expiring here too.

**Both feeds share one post schema** — a renderer written for one handles the other. Only `author` differs (person vs company).

## Post object schema (the renderer's data contract)

Wrapper: `{ success, message, process_time, data: [ post… ], … }` (company also has `page`, `total`, `has_more`).

```
post.id              string   stable id (dedupe / cache key)
post.post_type       string   "ugc" | "activity"
post.text            string   body copy (may contain \n and URLs)
post.created_at      string   ISO 8601 → sort + "x ago"
post.url             string   permalink → "View on LinkedIn" / iframe embed target
post.share_urn       string   urn:li:ugcPost:… / urn:li:activity:…
post.activity.num_likes / num_comments / num_shares    int
post.activity.reaction_counts[]   { type: LIKE|APPRECIATION|EMPATHY|INTEREST|ENTERTAINMENT|PRAISE, count }
post.author          { id, urn?, url, name|full_name, public_identifier?, avatar[], account_type }
post.content.*       exactly ONE of the following is non-null (rest null):
```

### content variants (real shapes)

```
content.images   [ { image: [ {width,height,url,expires_at}, … multi-res … ] }, … ]
content.video    { thumbnail:[{w,h,url,expires_at}], duration(ms str), aspect_ratio, streams:[{url,width,height,bit_rate,expires_at}] }
content.document { title, total_page_count, manifest_url, transcribed_document_url(PDF), manifest_url_expires_at }
content.article  { title, subtitle, article_url }
content.poll / celebration / event   present in schema (all null in this sample — handle defensively)
```

## ⚠️ Engineering note that shapes the architecture

**Every `media.licdn.com` / `dms.licdn.com` URL is a signed CDN link with an `expires_at` (Unix ms), ~weeks out.** Avatars, post images, video thumbnails/streams, and document PDFs **all expire**. A cache-and-display plugin therefore cannot hotlink them — it must **download and re-host media locally** (or proxy through WP) at fetch time, and refresh on a schedule. Text/url/engagement fields don't expire; media does.

## How to refresh / extend the samples (46/50 requests left this month)

```bash
cd ..                                  # probe/
export RAPIDAPI_KEY=...                 # your RapidAPI key
export RAPIDAPI_HOST=fresh-linkedin-scraper-api.p.rapidapi.com
php rapidapi-probe.php user-profile  <username>      # step 1: get urn (1 req)
php rapidapi-probe.php profile-posts <urn> [page]    # step 2: personal feed (1 req)
php rapidapi-probe.php company-posts <company_id>    # org feed (1 req)
```

Responses auto-save here as `<cmd>-<id>.json`. A 403 "not subscribed" is free (proxy-rejected); only HTTP 200 spends quota.
