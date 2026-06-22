# LinkedIn API Probe Toolkit

Standalone PHP CLI for testing the ClickSocial app's actual read capabilities. No WordPress required — just PHP with curl.

> **Two probes here:**
> - `linkedin-probe.php` — LinkedIn's **official** API (OAuth, ClickSocial app). Documented below.
> - `rapidapi-probe.php` — **third-party RapidAPI scraper** APIs (api-key only, any public profile/company). See FINDINGS.md §7. Quick start: `export RAPIDAPI_KEY=...` then `php rapidapi-probe.php company-posts <id>` / `profile-posts <username>`. Captures live response fields to confirm feed-buildability.

## Quick start

```bash
cd probe

# 1. Generate the authorization URL (uses LinkedIn's own redirect tool, already authorized on the app)
php linkedin-probe.php auth-url

# 2. Open the URL, log in, authorize. Copy the ?code=... value from the redirect URL.
php linkedin-probe.php token PASTE_CODE_HERE
# → returns JSON with access_token / refresh_token (values redacted here):
{
    "access_token": "<redacted>",
    "expires_in": 5183999,
    "refresh_token": "<redacted>",
    "refresh_token_expires_in": 31536059,
    "scope": "r_basicprofile,r_member_postAnalytics,r_organization_social,r_organization_social_feed,rw_organization_admin"
}

Note: token TTL is 60 days. If no refresh_token is returned, the app
isn't enabled for programmatic refresh (MDP partners only).
Never paste real tokens into this file — they are credentials.

# 3. Probe
php linkedin-probe.php me        $TOKEN                  # your person id
php linkedin-probe.php orgs      $TOKEN                  # org pages you admin
php linkedin-probe.php org-posts $TOKEN <org_id>         # ✅ expected to work (r_organization_social)
php linkedin-probe.php member-posts $TOKEN <person_id>   # ❌ expected 403 — THE key test
php linkedin-probe.php post-analytics $TOKEN             # metrics-only member analytics
```

Alternative: generate a token via LinkedIn's [OAuth token generator](https://www.linkedin.com/developers/tools/oauth) and skip steps 1–2.

## What each probe answers

| Probe | Question | Expected (per current docs) |
|---|---|---|
| `org-posts` | Can we read company-page posts with the app's existing scopes? | **Yes** — 200 with post JSON (`commentary`, `content`, timestamps) |
| `member-posts` | Can a member's own posts be read with ANY granted scope, esp. `r_member_postAnalytics`? | **No** — 403; needs `r_member_social`, a closed permission. **If this returns 200, that changes the evaluation — flag it immediately.** |
| `post-analytics` | What does `r_member_postAnalytics` actually return? | Metrics only (`metricType`, `count`) — no post content |
| `token` | Does the app get a `refresh_token`? | Only if enabled as MDP partner — verifies whether annual-reauth is the floor |
| `orgs` | Org discovery for a source picker | List of orgs where the member is ADMINISTRATOR |

Responses print throttle/diagnostic headers (`x-li-*`, `retry-after`) — paste interesting ones into FINDINGS.md.

## Notes

- `LinkedIn-Version` is pinned to `202605` in the script; bump if LinkedIn rejects it.
- Test-account hygiene (from ClickSocial findings): accounts can get blocked ~24h after creation; UK IPs avoid phone verification; remove the app at `linkedin.com/mypreferences/d/data-sharing-for-permitted-services` to re-test consent.
- ⚠️ The client secret was shared in chat during this evaluation — **rotate it** in the developer console afterward and pass via `LI_CLIENT_SECRET` env var.
