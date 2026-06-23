#!/usr/bin/env bash
#
# Build a self-contained, installable plugin zip for a demo WP site.
# Bundles the plugin + the captured demo-sample JSONs (so demo mode works with
# NO API key) but NEVER ships secrets (.env, asmitasubedi.json, .git) or the
# internal strategy docs.
#
# Usage:  bash dev/package-plugin.sh
# Output: dist/linkedin-feeds.zip  (top-level folder: linkedin-feeds/)
#
set -euo pipefail

cd "$(dirname "$0")/.."          # plugin root
ROOT="$(pwd)"
STAGE="$(mktemp -d)/linkedin-feeds"
OUT="$ROOT/dist/linkedin-feeds.zip"
mkdir -p "$STAGE" "$ROOT/dist"

# --- What ships (explicit allow-list — only what's named is included) --------
# Plugin core
cp "$ROOT/linkedin-feeds.php" "$STAGE/"
cp -R "$ROOT/includes"   "$STAGE/"
cp -R "$ROOT/templates"  "$STAGE/"
cp -R "$ROOT/assets"     "$STAGE/"

# Demo data (real captures → demo mode works offline; media expires ~weeks)
mkdir -p "$STAGE/probe/responses"
cp "$ROOT"/probe/responses/*.json    "$STAGE/probe/responses/" 2>/dev/null || true
cp "$ROOT/probe/responses/README.md" "$STAGE/probe/responses/" 2>/dev/null || true

# Probe tools to re-capture fresh samples on the demo box (secrets are env-only)
cp "$ROOT/probe/rapidapi-probe.php" "$STAGE/probe/"
cp "$ROOT/probe/README.md"          "$STAGE/probe/"

# Bulk demo-page creator + usage docs
cp -R "$ROOT/dev/demo-pages" "$STAGE/"        # placed at linkedin-feeds/demo-pages/
cp "$ROOT/README.md"         "$STAGE/" 2>/dev/null || true
cp "$ROOT/SHORTCODE-DEMO.md" "$STAGE/" 2>/dev/null || true

# --- Safety: never ship secrets ---------------------------------------------
rm -f "$STAGE/.env" "$STAGE/probe/asmitasubedi.json"
# Generic patterns (no literal secrets in this file): RapidAPI key shape
# (…msh…jsn…), LinkedIn app-secret prefix (WPL_), OAuth tokens (AQ…).
if grep -rIlE "[A-Za-z0-9]{8,}msh[A-Za-z0-9]+jsn[A-Za-z0-9]+|WPL_[A-Za-z0-9]|AQ[WTV][A-Za-z0-9_-]{40}" "$STAGE" 2>/dev/null; then
	echo "❌ ABORT: a secret was found in the staging tree (see paths above)."
	exit 1
fi

# --- Zip ---------------------------------------------------------------------
rm -f "$OUT"
( cd "$(dirname "$STAGE")" && zip -rq "$OUT" "linkedin-feeds" -x '*.DS_Store' )
rm -rf "$(dirname "$STAGE")"

echo "✅ Built $OUT"
echo "   $(du -h "$OUT" | cut -f1) — upload via Plugins → Add New → Upload Plugin"
echo "   $(unzip -l "$OUT" | grep -c '\.') files"
