<?php
/**
 * LinkedIn API probe toolkit — ClickSocial app (LinkedIn Feeds evaluation, June 2026)
 *
 * Run locally (your machine can reach api.linkedin.com; the AI sandbox cannot).
 *
 * Usage:
 *   php linkedin-probe.php auth-url [comma,separated,scopes]
 *   php linkedin-probe.php token <authorization_code>
 *   php linkedin-probe.php refresh <refresh_token>
 *   php linkedin-probe.php userinfo <access_token>
 *   php linkedin-probe.php me <access_token>
 *   php linkedin-probe.php orgs <access_token>
 *   php linkedin-probe.php org-posts <access_token> <organization_id>
 *   php linkedin-probe.php member-posts <access_token> <person_id>
 *   php linkedin-probe.php post-analytics <access_token>
 *
 * Typical flow:
 *   1. php linkedin-probe.php auth-url        → open URL in browser, authorize
 *   2. Copy ?code=... from the redirect URL   → php linkedin-probe.php token <code>
 *   3. Use the access_token with the other commands.
 *
 * Alternatively, generate a token with LinkedIn's OAuth token generator:
 * https://www.linkedin.com/developers/tools/oauth (the tool's redirect URL is
 * already authorized on this app).
 *
 * Credentials come from env vars (LI_CLIENT_ID, LI_CLIENT_SECRET) — never
 * hardcode or commit real secrets.
 */

const LI_VERSION = '202605'; // bump monthly; versions sunset ~12 months after release

// Provide credentials via env vars — never commit real secrets:
//   export LI_CLIENT_ID=...  LI_CLIENT_SECRET=...
$clientId     = getenv('LI_CLIENT_ID') ?: '';
$clientSecret = getenv('LI_CLIENT_SECRET') ?: '';

if ('' === $clientId || '' === $clientSecret) {
	fwrite(STDERR, "Set LI_CLIENT_ID and LI_CLIENT_SECRET env vars (see README).\n");
}
$redirectUri  = getenv('LI_REDIRECT_URI') ?: 'https://www.linkedin.com/developers/tools/oauth/redirect';

// Scopes currently active on the ClickSocial app that matter for read probing.
// NOTE: r_member_social (read personal posts) is NOT on the app and is a closed
// permission — LinkedIn is not accepting access requests.
$defaultScopes = [
	'r_basicprofile',
	'r_organization_social',
	'r_organization_social_feed',
	'rw_organization_admin',
	'r_member_postAnalytics',
];

$cmd = $argv[1] ?? 'help';

function request( string $method, string $url, array $headers = [], ?string $body = null ): void {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, [
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_TIMEOUT        => 30,
	] );
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
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

	echo "=== {$method} {$url}\n";
	echo "HTTP {$status}\n";
	// Throttle / diagnostic headers are the interesting bit for rate-limit observation.
	foreach ( explode( "\r\n", $rawHeaders ) as $h ) {
		if ( preg_match( '/^(x-li-|x-restli|retry-after|ratelimit)/i', $h ) ) {
			echo "  {$h}\n";
		}
	}
	$json = json_decode( $payload, true );
	echo ( null !== $json ? json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $payload ) . "\n";
}

function api_headers( string $token ): array {
	return [
		"Authorization: Bearer {$token}",
		'LinkedIn-Version: ' . LI_VERSION,
		'X-Restli-Protocol-Version: 2.0.0',
	];
}

switch ( $cmd ) {

	case 'auth-url':
		$scopes = isset( $argv[2] ) ? explode( ',', $argv[2] ) : $defaultScopes;
		$url    = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query( [
			'response_type' => 'code',
			'client_id'     => $clientId,
			'redirect_uri'  => $redirectUri,
			'state'         => bin2hex( random_bytes( 8 ) ),
			'scope'         => implode( ' ', $scopes ),
		], '', '&', PHP_QUERY_RFC3986 ); // RFC3986: spaces as %20, not '+'
		echo "Open in browser, authorize, then copy ?code= from the redirect URL:\n\n{$url}\n";
		break;

	case 'token':
		$code = $argv[2] ?? exit( "Missing authorization code\n" );
		request( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken',
			[ 'Content-Type: application/x-www-form-urlencoded' ],
			http_build_query( [
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'client_id'     => $clientId,
				'client_secret' => $clientSecret,
				'redirect_uri'  => $redirectUri,
			] )
		);
		echo "\nNote: token TTL is 60 days. If no refresh_token is returned, the app\nisn't enabled for programmatic refresh (MDP partners only).\n";
		break;

	case 'refresh':
		$rt = $argv[2] ?? exit( "Missing refresh token\n" );
		request( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken',
			[ 'Content-Type: application/x-www-form-urlencoded' ],
			http_build_query( [
				'grant_type'    => 'refresh_token',
				'refresh_token' => $rt,
				'client_id'     => $clientId,
				'client_secret' => $clientSecret,
			] )
		);
		break;

	case 'userinfo': // OIDC userinfo — only works if openid/profile scopes granted
		$t = $argv[2] ?? exit( "Missing access token\n" );
		request( 'GET', 'https://api.linkedin.com/v2/userinfo', [ "Authorization: Bearer {$t}" ] );
		break;

	case 'me': // legacy profile endpoint, works with r_basicprofile
		$t = $argv[2] ?? exit( "Missing access token\n" );
		request( 'GET', 'https://api.linkedin.com/v2/me', [ "Authorization: Bearer {$t}" ] );
		break;

	case 'orgs': // org pages the member administers (needs rw_organization_admin)
		$t = $argv[2] ?? exit( "Missing access token\n" );
		request( 'GET',
			'https://api.linkedin.com/rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED',
			api_headers( $t )
		);
		break;

	case 'org-posts': // THE core org-feed probe (needs r_organization_social)
		$t  = $argv[2] ?? exit( "Missing access token\n" );
		$id = $argv[3] ?? exit( "Missing organization id (numeric)\n" );
		$author = rawurlencode( "urn:li:organization:{$id}" );
		request( 'GET',
			"https://api.linkedin.com/rest/posts?author={$author}&q=author&count=10&sortBy=LAST_MODIFIED",
			api_headers( $t )
		);
		break;

	case 'member-posts':
		// Tests whether ANY granted scope (esp. r_member_postAnalytics) unlocks
		// reading the member's own posts. Docs say this needs r_member_social
		// (closed permission) — expected result: 403 ACCESS_DENIED. If this
		// returns 200, that's a significant finding — document it.
		$t  = $argv[2] ?? exit( "Missing access token\n" );
		$id = $argv[3] ?? exit( "Missing person id (from `me` command, the `id` field)\n" );
		$author = rawurlencode( "urn:li:person:{$id}" );
		request( 'GET',
			"https://api.linkedin.com/rest/posts?author={$author}&q=author&count=10",
			api_headers( $t )
		);
		break;

	case 'post-analytics':
		// Member Post Analytics API (r_member_postAnalytics). Metrics only — no
		// post content. Aggregated across the authenticated member's posts.
		// Param is `queryType` (NOT metricType); dateRange omitted = lifetime.
		// Optional 2nd arg: queryType (IMPRESSION, MEMBERS_REACHED, RESHARE,
		// REACTION, COMMENT, POST_SAVE, POST_SEND, LINK_CLICKS, ...).
		$t  = $argv[2] ?? exit( "Missing access token\n" );
		$qt = $argv[3] ?? 'IMPRESSION';
		request( 'GET',
			"https://api.linkedin.com/rest/memberCreatorPostAnalytics?q=me&queryType={$qt}&aggregation=TOTAL",
			api_headers( $t )
		);
		echo "\nDocs: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/members/post-statistics\n";
		break;

	case 'snapshot':
		// Member Data Portability (3rd Party) — Member Snapshot API.
		// Only works AFTER the "Member Data Portability API (3rd Party)" product
		// is added to the app (Products tab; business verification required) and
		// the token was authorized with scope r_dma_portability_3rd_party by an
		// EEA member (UK is NOT EEA). Returns the member's archived data per
		// domain; MEMBER_SHARE_INFO = posts.
		$t      = $argv[2] ?? exit( "Missing access token\n" );
		$domain = $argv[3] ?? 'MEMBER_SHARE_INFO';
		request( 'GET',
			"https://api.linkedin.com/rest/memberSnapshotData?q=criteria&domain={$domain}",
			api_headers( $t )
		);
		echo "\nDocs: https://learn.microsoft.com/en-us/linkedin/dma/member-data-portability/shared/member-snapshot-api\n";
		break;

	default:
		echo "Commands: auth-url | token | refresh | userinfo | me | orgs | org-posts | member-posts | post-analytics | snapshot\n";
		echo "See header comment for usage.\n";
}
