<?php
/**
 * RapidAPI LinkedIn scraper probe — LinkedIn Feeds evaluation (June 2026)
 *
 * Companion to linkedin-probe.php (which probes LinkedIn's OFFICIAL API).
 * This one probes THIRD-PARTY scraper APIs sold on the RapidAPI marketplace —
 * the route that can fetch arbitrary public profile posts + company posts by
 * URL/id with only an API key (no OAuth). See FINDINGS.md §7 for why this is a
 * scraping route and the compliance caveats around it.
 *
 * Run locally (your machine can reach *.rapidapi.com; the AI sandbox cannot).
 *
 * Setup:
 *   1. Sign up at rapidapi.com, subscribe to a LinkedIn scraper API (free tier
 *      is enough to capture response shapes). Two are pre-wired below:
 *        - fresh-linkedin-scraper-api  (Fresh LinkedIn Scraper API)
 *        - linkedin-data-api           (RockApis Real-Time LinkedIn Scraper)
 *   2. export RAPIDAPI_KEY=your_key_here
 *   3. Pick a provider:  export RAPIDAPI_HOST=fresh-linkedin-scraper-api.p.rapidapi.com
 *
 * Personal-feed workflow (Fresh LinkedIn Scraper) is TWO calls:
 *   1. user-profile <username>   → returns profile incl. data.urn (e.g. ACoAAA...)
 *   2. profile-posts <urn>       → that account's posts
 * Org feed is ONE call: company-posts <numeric-company-id>.
 *
 * Every response is also written to responses/<cmd>-<id>.json for building the
 * shortcode renderer against real data.
 *
 * Usage:
 *   php rapidapi-probe.php user-profile  <username>          # step 1 for personal feed
 *   php rapidapi-probe.php profile-posts <urn> [page]        # step 2 (urn from step 1)
 *   php rapidapi-probe.php company-posts <company-id> [page] # org feed
 *   php rapidapi-probe.php raw <path-with-query>             # e.g. /api/v1/user/posts?urn=...
 *
 * IMPORTANT: exact endpoint paths and param names vary per provider and change
 * over time. The paths below reflect docs as of June 2026 — if you get a 404,
 * open the provider's RapidAPI "Endpoints" tab and adjust PATHS[] accordingly.
 * The point of this probe is to capture the live RESPONSE FIELDS so we can
 * confirm a feed is buildable; getting the exact path is a 2-minute lookup.
 */

$key  = getenv( 'RAPIDAPI_KEY' ) ?: '';
$host = getenv( 'RAPIDAPI_HOST' ) ?: 'fresh-linkedin-scraper-api.p.rapidapi.com';

if ( '' === $key ) {
	fwrite( STDERR, "Missing RAPIDAPI_KEY env var. export RAPIDAPI_KEY=...\n" );
	exit( 1 );
}

/**
 * Per-provider endpoint paths. {id} and {page} are substituted.
 * Verify/adjust against the provider's RapidAPI Endpoints tab if a call 404s.
 */
const PATHS = array(
	'fresh-linkedin-scraper-api.p.rapidapi.com' => array(
		'user-profile'  => '/api/v1/user/profile?username={id}',
		'profile-posts' => '/api/v1/user/posts?urn={id}&page={page}',
		'company-posts' => '/api/v1/company/posts?company_id={id}&page={page}&sort_by=recent',
	),
	'linkedin-data-api.p.rapidapi.com'          => array(
		'user-profile'  => '/get-profile-data-by-url?url={id}',
		'profile-posts' => '/get-profile-posts?username={id}',
		'company-posts' => '/get-company-posts?username={id}',
	),
);

function probe( string $host, string $key, string $path, string $saveAs = '' ): void {
	$url = "https://{$host}{$path}";
	$ch  = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_HTTPHEADER     => array(
			"x-rapidapi-key: {$key}",
			"x-rapidapi-host: {$host}",
		),
		CURLOPT_TIMEOUT        => 30,
	) );
	$response = curl_exec( $ch );
	if ( false === $response ) {
		fwrite( STDERR, 'cURL error: ' . curl_error( $ch ) . "\n" );
		exit( 1 );
	}
	$status     = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$headerSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );

	$rawHeaders = substr( $response, 0, $headerSize );
	$payload    = substr( $response, $headerSize );

	echo "=== GET {$url}\n";
	echo "HTTP {$status}\n";
	// Quota / rate-limit headers RapidAPI returns — note these for capacity planning.
	foreach ( explode( "\r\n", $rawHeaders ) as $h ) {
		if ( preg_match( '/^(x-ratelimit|x-rapidapi|retry-after)/i', $h ) ) {
			echo "  {$h}\n";
		}
	}
	$json = json_decode( $payload, true );
	$pretty = ( null !== $json ? json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $payload );

	// Persist FIRST (before the large echo) so piping output through head/less,
	// which can SIGPIPE this process mid-print, never loses the saved file.
	$saved = '';
	if ( '' !== $saveAs && 200 === $status ) {
		$dir = __DIR__ . '/responses';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}
		$saved = "{$dir}/{$saveAs}.json";
		file_put_contents( $saved, $pretty );
	}

	if ( '' !== $saved ) {
		echo "[saved] {$saved}\n";
	}
	echo $pretty . "\n";

	// Help map fields: print the key set of the first post object found.
	if ( is_array( $json ) ) {
		$posts = $json['data'] ?? $json['posts'] ?? $json['response'] ?? null;
		if ( is_array( $posts ) && isset( $posts[0] ) && is_array( $posts[0] ) ) {
			echo "\n--- first-post field keys (for FINDINGS field map) ---\n";
			echo implode( ', ', array_keys( $posts[0] ) ) . "\n";
		}
	}
}

$cmd = $argv[1] ?? 'help';

switch ( $cmd ) {
	case 'user-profile':
	case 'profile-posts':
	case 'company-posts':
		$id   = $argv[2] ?? exit( "Missing id/username/urn\n" );
		$page = $argv[3] ?? '1';
		$tmpl = PATHS[ $host ][ $cmd ] ?? exit( "No path template for {$host} / {$cmd}; add one to PATHS[] or use `raw`.\n" );
		$path = str_replace( array( '{id}', '{page}' ), array( rawurlencode( $id ), $page ), $tmpl );
		// Sanitize id for filename.
		$slug = preg_replace( '/[^A-Za-z0-9_-]+/', '_', $id );
		probe( $host, $key, $path, "{$cmd}-{$slug}" );
		break;

	case 'raw':
		$path = $argv[2] ?? exit( "Missing path (e.g. /api/v1/user/posts?urn=foo)\n" );
		probe( $host, $key, $path, 'raw-' . preg_replace( '/[^A-Za-z0-9_-]+/', '_', ltrim( $path, '/' ) ) );
		break;

	default:
		echo "Commands: user-profile <username> | profile-posts <urn> [page] | company-posts <id> [page] | raw <path>\n";
		echo "Env: RAPIDAPI_KEY (required), RAPIDAPI_HOST (default fresh-linkedin-scraper-api.p.rapidapi.com)\n";
}
