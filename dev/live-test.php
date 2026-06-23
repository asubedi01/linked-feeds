<?php
/**
 * LIVE provider test — drives the real provider classes over the network via curl
 * shims for wp_remote_*. Confirms the refactored fetch+resolve+normalize path works
 * against the live API. Run locally with the key in the environment:
 *
 *   LINKEDIN_FEEDS_RAPIDAPI_KEY=xxxx php dev/live-test.php
 *
 * Spends quota only on subscribed providers (a 403 "not subscribed" is free).
 *
 * @package LinkedIn_Feeds
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'LINKEDIN_FEEDS_DIR', dirname( __DIR__ ) . '/' );

class WP_Error { public $code; public $msg; public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; } public function get_error_message() { return $this->msg; } }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = '' ) { return $s; }
function esc_url_raw( $s ) { return (string) $s; }
function get_option( $k, $d = '' ) { return 'linkedin_feeds_rapidapi_key' === $k ? (string) getenv( 'LINKEDIN_FEEDS_RAPIDAPI_KEY' ) : $d; }
$GLOBALS['_t'] = array();
function get_transient( $k ) { return $GLOBALS['_t'][ $k ] ?? false; }
function set_transient( $k, $v, $t ) { $GLOBALS['_t'][ $k ] = $v; }
// Match WP: add_query_arg does NOT url-encode values (provider pre-encodes them).
function add_query_arg( $args, $url ) { $pairs = array(); foreach ( $args as $k => $v ) { $pairs[] = $k . '=' . $v; } return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . implode( '&', $pairs ); }
function apply_filters( $h, $v ) { return $v; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_remote_get( $url, $args = array() ) {
	$ch      = curl_init( $url );
	$headers = array();
	foreach ( ( $args['headers'] ?? array() ) as $k => $v ) { $headers[] = "{$k}: {$v}"; }
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $args['timeout'] ?? 25 ) );
	$body = curl_exec( $ch );
	if ( false === $body ) { return new WP_Error( 'http', curl_error( $ch ) ); }
	$code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	return array( 'code' => $code, 'body' => $body );
}
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_remote_post( $url, $args = array() ) {
	$ch      = curl_init( $url );
	$headers = array();
	foreach ( ( $args['headers'] ?? array() ) as $k => $v ) { $headers[] = "{$k}: {$v}"; }
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $args['body'] ?? '', CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $args['timeout'] ?? 25 ) );
	$body = curl_exec( $ch );
	if ( false === $body ) { return new WP_Error( 'http', curl_error( $ch ) ); }
	$code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	return array( 'code' => $code, 'body' => $body );
}
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? $r['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? $r['body'] : ''; }
function wp_remote_retrieve_response_message( $r ) { return ''; }

require LINKEDIN_FEEDS_DIR . 'includes/class-media.php';
require LINKEDIN_FEEDS_DIR . 'includes/class-provider.php';
require LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-scraper.php';
require LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-profile.php';

if ( '' === (string) getenv( 'LINKEDIN_FEEDS_RAPIDAPI_KEY' ) ) {
	fwrite( STDERR, "Set LINKEDIN_FEEDS_RAPIDAPI_KEY env var.\n" );
	exit( 1 );
}

function summarize( $label, $posts ) {
	if ( is_wp_error( $posts ) ) {
		printf( "%-28s → ERROR: %s\n", $label, $posts->get_error_message() );
		return;
	}
	$kinds = array();
	foreach ( $posts as $p ) { $kinds[ $p['media']['kind'] ] = ( $kinds[ $p['media']['kind'] ] ?? 0 ) + 1; }
	printf( "%-28s → %d posts | kinds %s\n", $label, count( $posts ), json_encode( $kinds ) );
	if ( $posts ) {
		$p = $posts[0];
		printf( "    first: author=%s | media=%s | likes=%d | %s\n", $p['author']['name'], $p['media']['kind'], $p['stats']['likes'], substr( $p['text'], 0, 60 ) );
	}
}

// 1. fresh-scraper (subscribed) — full resolve+fetch+normalize, spends 2 calls.
$fs = LinkedIn_Feeds_Provider::make( 'fresh-scraper' );
summarize( 'fresh-scraper company/microsoft', $fs->get_feed( 'company', 'microsoft' ) );

// 2. fresh-profile — profile feed.
$fp = LinkedIn_Feeds_Provider::make( 'fresh-profile' );
summarize( 'fresh-profile profile/williamhgates', $fp->get_feed( 'profile', 'williamhgates' ) );

// 3. Hashtag/search (POST /search-posts on fresh-profile; GET /api/v1/search/posts on
//    fresh-scraper — the latter returned upstream 429 in testing).
summarize( 'fresh-profile hashtag/#AI', $fp->get_feed( 'hashtag', 'AI' ) );
summarize( 'fresh-scraper hashtag/#AI', $fs->get_feed( 'hashtag', 'AI' ) );
