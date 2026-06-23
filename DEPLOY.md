# Deploying LinkedIn Feeds to a demo WP site

Goal: stand the plugin up on a demo instance so the team can play with feeds. The fastest, most reliable path uses **demo mode** (bundled sample data, no API key, zero quota). Live mode is optional.

---

## Step 1 — Build the installable zip (once, locally)

```bash
cd wp-content/plugins/linkedin-feeds
bash dev/package-plugin.sh        # → dist/linkedin-feeds.zip (~170 KB)
```

The zip bundles the plugin **and the demo-sample JSONs** (so `demo="1"` works out of the box on any site) and is scanned to **exclude all secrets** (`.env`, tokens) and internal strategy docs. *(Why a zip and not `git clone`: the demo samples are gitignored, so a clone has no demo data — the zip carries them.)*

## Step 2 — Get it onto the demo site (pick one)

- **A. WP admin (no shell needed — recommended):** *Plugins → Add New → Upload Plugin* → choose `dist/linkedin-feeds.zip` → Install → **Activate**.
- **B. SFTP/SSH:** unzip into `wp-content/plugins/` so you have `wp-content/plugins/linkedin-feeds/…`, then activate in *Plugins*.
- **C. WP-CLI:** `wp plugin install /path/to/linkedin-feeds.zip --activate` (add `--path=` / `ssh` as needed).

*(If the demo box is another Docksal/local site you control, you can instead `git clone` the repo into `wp-content/plugins/linkedin-feeds` and copy the `probe/responses/*.json` samples over manually — but the zip is simpler.)*

## Step 3 — Create feeds so people can play

**Easiest — paste shortcodes into pages** (any editor, no CLI). Create a page and drop in:

```
[linkedin_feed demo="1" type="profile"]
[linkedin_feed demo="1" type="company"]
[linkedin_feed demo="1" type="hashtag" tag="AI" show_source="1"]
[linkedin_feed demo="1" type="profile" layout="carousel" limit="10"]
[linkedin_feed demo="1" type="search" query="AI" layout="masonry"]
```

These render the bundled samples — personal, company, hashtag, search, all four layouts, the post-detail popup and image lightbox — **no key, no network, nothing to break.**

**Bulk option (WP-CLI)** — creates the five ready-made demo pages in one shot:

```bash
wp eval-file wp-content/plugins/linkedin-feeds/demo-pages/create-pages.php --path=<wp-root>
```
Produces: Profile (Grid), Company (Grid), Layouts (all 4), Content Scopes (all 4 scopes), Provider Comparison.

## Step 4 (optional) — enable live feeds

So people can point feeds at **any** profile/company/hashtag, add a RapidAPI key:

- *Settings → LinkedIn Feeds* → paste the **RapidAPI key** + pick a provider, **or** add to `wp-config.php`:
  ```php
  define( 'LINKEDIN_FEEDS_RAPIDAPI_KEY', 'your_rapidapi_key' );
  ```
- Then live shortcodes work:
  ```
  [linkedin_feed type="profile" user="williamhgates"]
  [linkedin_feed type="company" company="microsoft"]
  [linkedin_feed type="hashtag" tag="marketing"]
  ```

---

## What "everyone can play" looks like

- **Stable always-on playground →** use **demo mode**. It never hits the API, so it can't run out of quota or hit a provider hiccup. Give teammates Editor access + the shortcode list above so they can spin up their own example pages.
- **Let people try their own handles live →** set the key (Step 4). Note the cost/reliability caveats below.

## Caveats to set expectations (read before sharing widely)

1. **Demo media expires (~weeks).** The bundled samples carry signed LinkedIn CDN URLs that lapse — videos/PDFs in ~6–7 days, images in ~3 weeks. When tiles start breaking, **re-capture** on the demo box and re-package (or use live):
   ```bash
   cd wp-content/plugins/linkedin-feeds/probe
   export RAPIDAPI_KEY=...   # see probe/README.md
   php rapidapi-probe.php profile-posts <urn>     # etc.
   ```
   *(Production fix — auto re-hosting media locally — is the tracked build item; see RAPIDAPI-FINDINGS §7.)*
2. **Live mode costs quota.** Calls scale with feeds × refresh (1-hour cache), **not** page views. The free tier (~50–75/mo) suits a handful of feeds; a busy live playground will exhaust it. Demo mode is free.
3. **fresh-scraper search currently 429s.** Hashtag/search live runs on `fresh-profile`; the plugin auto-routes there. Personal/company work on either provider.
4. **It's a prototype on the third-party-scraper route** — fine for an internal demo; not a green-lit product. See the verdict before any external sharing.

---

## Troubleshooting

- **Shortcode prints as raw text** → plugin not active, or pasted into a code block. Use a Paragraph/Shortcode block.
- **"Sample data file not found"** (admins only) → the `probe/responses/*.json` didn't ship; re-build the zip with `dev/package-plugin.sh`.
- **"Add a RapidAPI key"** → you used a live shortcode without a key; either add the key (Step 4) or add `demo="1"`.
- **Blank where a feed should be** (non-admins) → an API error is hidden from visitors by design; log in as admin to see the message.
