<?php
/**
 * Standalone renderer harness — verifies templates + both providers WITHOUT a
 * WordPress install. Shims the WP functions the render path touches, then:
 *   1. renders both demo feeds (real fresh-scraper captures) → dev/out-*.html
 *   2. asserts the fresh-profile normalizer maps a synthetic fdocs-shaped payload
 *
 * Usage:  php dev/render-sample.php
 *
 * @package LinkedIn_Feeds
 */

error_reporting( E_ALL & ~E_DEPRECATED );

// --- Minimal WP shims ------------------------------------------------------- //
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'LINKEDIN_FEEDS_VERSION', 'dev' );
define( 'LINKEDIN_FEEDS_DIR', dirname( __DIR__ ) . '/' );
define( 'LINKEDIN_FEEDS_URL', '' );

class WP_Error { public $msg; public function __construct( $c = '', $m = '' ) { $this->msg = $m; } public function get_error_message() { return $this->msg; } }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_url_raw( $s ) { return (string) $s; }
function esc_html_e( $s, $d = '' ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = '' ) { echo esc_attr( $s ); }
function __( $s, $d = '' ) { return $s; }
function _n( $s, $p, $n, $d = '' ) { return 1 === (int) $n ? $s : $p; }
function wp_kses_post( $s ) { return $s; }
function make_clickable( $s ) { return preg_replace( '#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $s ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function human_time_diff( $from, $to ) { $d = max( 1, $to - $from ); if ( $d < 3600 ) { return round( $d / 60 ) . ' mins'; } if ( $d < 86400 ) { return round( $d / 3600 ) . ' hours'; } return round( $d / 86400 ) . ' days'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function apply_filters( $h, $v ) { return $v; }
function add_query_arg( $args, $url ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args ); }
function shortcode_atts( $defaults, $atts, $tag = '' ) { $atts = (array) $atts; $out = array(); foreach ( $defaults as $k => $v ) { $out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v; } return $out; }
function locate_template( $name ) { return ''; }
function add_shortcode( $t, $cb ) {}
function add_action( $h, $cb, $p = 10, $a = 1 ) {}
function get_option( $k, $d = '' ) { return $d; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t ) {}
function current_user_can( $c ) { return true; }
function wp_enqueue_style( $h ) {}
function wp_enqueue_script( $h ) {}

// --- Load real plugin classes ----------------------------------------------- //
require LINKEDIN_FEEDS_DIR . 'includes/class-media.php';
require LINKEDIN_FEEDS_DIR . 'includes/class-provider.php';
require LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-scraper.php';
require LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-profile.php';
require LINKEDIN_FEEDS_DIR . 'includes/class-feed-source.php';
require LINKEDIN_FEEDS_DIR . 'includes/class-shortcode.php';

// --- 1. Render both demo feeds (fresh-scraper real captures) ---------------- //
$shortcode = new LinkedIn_Feeds_Shortcode();
$css       = file_get_contents( LINKEDIN_FEEDS_DIR . 'assets/css/linkedin-feeds.css' );

foreach ( array( 'profile', 'company' ) as $type ) {
	$html = $shortcode->render( array( 'demo' => '1', 'type' => $type, 'layout' => 'grid' ) );
	file_put_contents(
		LINKEDIN_FEEDS_DIR . "dev/out-{$type}.html",
		"<!doctype html><meta charset=utf-8><title>LinkedIn Feed — {$type}</title><style>body{font-family:system-ui;background:#f4f2ee;padding:24px}{$css}</style>{$html}"
	);

	$posts = ( new LinkedIn_Feeds_Feed_Source() )->get_posts( array( 'type' => $type, 'user' => '', 'company' => '', 'demo' => true, 'limit' => 0, 'provider' => null ) );
	$kinds = array();
	foreach ( $posts as $p ) {
		$kinds[ $p['media']['kind'] ] = ( $kinds[ $p['media']['kind'] ] ?? 0 ) + 1;
	}
	printf( "[fresh-scraper demo:%s] %d posts → media kinds %s\n", $type, count( $posts ), json_encode( $kinds ) );
}

// --- 2. Assert the fresh-profile normalizer against the REAL captured sample -- //
$ok      = true;
$sample  = LINKEDIN_FEEDS_DIR . 'probe/responses/freshprofile-profile-williamhgates.json';
if ( ! is_readable( $sample ) ) {
	echo "[fresh-profile] no live sample captured yet — skipping (run the live capture first)\n";
} else {
	$fp    = LinkedIn_Feeds_Provider::make( 'fresh-profile' );
	$raw   = json_decode( file_get_contents( $sample ), true );
	$posts = $fp->normalize_wrapper( $raw );

	$kinds = array();
	foreach ( $posts as $p ) { $kinds[ $p['media']['kind'] ] = ( $kinds[ $p['media']['kind'] ] ?? 0 ) + 1; }
	printf( "[fresh-profile real] %d posts → media kinds %s\n", count( $posts ), json_encode( $kinds ) );

	// Visual artifact: render the fresh-profile sample through the templates.
	$html = LinkedIn_Feeds_Shortcode::render_template( 'feed', array( 'posts' => $posts, 'args' => array( 'layout' => 'grid', 'type' => 'profile' ) ) );
	file_put_contents(
		LINKEDIN_FEEDS_DIR . 'dev/out-freshprofile.html',
		"<!doctype html><meta charset=utf-8><title>LinkedIn Feed — fresh-profile</title><style>body{font-family:system-ui;background:#f4f2ee;padding:24px}{$css}</style>{$html}"
	);

	// All four media kinds must appear, and core fields must populate.
	foreach ( array( 'image', 'video', 'document', 'article' ) as $kind ) {
		$present = ! empty( $kinds[ $kind ] );
		$ok      = $ok && $present;
		printf( "    media kind %-9s present: %s\n", $kind, $present ? 'OK' : 'FAIL' );
	}
	$withAuthor = 0; $withTs = 0;
	foreach ( $posts as $p ) {
		if ( '' !== $p['author']['name'] ) { $withAuthor++; }
		if ( $p['timestamp'] > 0 ) { $withTs++; }
	}
	$ok = $ok && ( $withAuthor >= 45 ) && ( $withTs === count( $posts ) );
	printf( "    posts with author name: %d/%d (want >=45) %s\n", $withAuthor, count( $posts ), $withAuthor >= 45 ? 'OK' : 'FAIL' );
	printf( "    posts with timestamp:   %d/%d %s\n", $withTs, count( $posts ), $withTs === count( $posts ) ? 'OK' : 'FAIL' );
	$first = $posts[0];
	printf( "    first: author=%s | media=%s | likes=%d | reactions=%d types\n", $first['author']['name'], $first['media']['kind'], $first['stats']['likes'], count( $first['stats']['reactions'] ) );
}

echo $ok ? "\nAll provider assertions passed.\n" : "\nASSERTIONS FAILED.\n";
exit( $ok ? 0 : 1 );
