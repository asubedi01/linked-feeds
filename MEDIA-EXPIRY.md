# Media URL Expiry — findings & monitoring

**Question:** When you capture a feed, how long do the image / video / document URLs keep working before LinkedIn's CDN revokes them? This sets expectations for demos and confirms why production must re-host media.

## How it works

Every media URL returned by the RapidAPI providers is a **signed LinkedIn CDN URL** (`media.licdn.com` / `dms.licdn.com`) carrying an `e=<unix-seconds>` expiry parameter (and the captured JSON also stores `expires_at` in ms). After that moment the URL returns **403** — the bytes are gone, the post text/links/counts are not (those don't expire). Both providers (`fresh-scraper`, `fresh-profile`) return LinkedIn's own signed URLs, so the windows are identical across providers.

## Empirical windows (captured June 17–18, 2026)

Measured from the `e=` parameter, all confirmed **live (HTTP 200)** at the June 22 check:

| Media type | Expiry window from capture | Notes |
|---|---|---|
| **Video streams** (`dms.licdn.com`) | **~6–7 days** | shortest-lived |
| **Video thumbnails** | **~6–7 days** | same batch as the stream |
| **Documents** (PDF) | **~6–7 days** | same short tier |
| **Images** (feedshare) | **~21 days** | longer tier |
| **Avatars / company logos** | **~21 days** | longer tier |

**Two-tier pattern:** video + documents expire in about a **week**; images + avatars last about **three weeks**. So a captured demo loses its videos/PDFs first (feed still renders, those tiles just break), then images a couple weeks later.

> ⚠️ These are observed values from one capture batch, not a documented LinkedIn SLA — treat as a working estimate and re-confirm with the monitor. LinkedIn can change signing windows at any time.

## Practical expectations

- **For a demo:** record within **~5 days** of capturing samples to be safe on video/PDF tiles; image-only feeds are fine for ~2–3 weeks. Or re-capture right before recording (`probe/rapidapi-probe.php`, ~3–4 free-tier calls).
- **For production:** you **cannot** store-and-serve these URLs. Media must be **downloaded and re-hosted locally** at fetch time and refreshed on a schedule — scaffolded via the `linkedin_feeds_localize_media` filter in `includes/class-media.php` (currently pass-through). This is the main remaining build item.

## Monitoring

`dev/media-expiry-monitor.php` reads every capture in `probe/responses/`, extracts one URL per media type, records the embedded expiry, and (by default) does a live HTTP check to see whether each is still serving. It appends a timestamped row per type to `dev/media-expiry-log.csv` (gitignored) so you can watch advertised-vs-actual expiry over time.

```bash
php dev/media-expiry-monitor.php            # parse + live-check, append to log
php dev/media-expiry-monitor.php --no-http  # offline: just show the e= windows
```

Run it daily during the exploration to confirm whether URLs actually die exactly at `e=` or linger/expire early. The log columns: `checked_at_utc, capture_file, media_type, expiry_utc, days_to_expiry, http_status, live`.
