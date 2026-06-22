<?php
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

require LINKEDIN_FEEDS_DIR."includes/class-media.php";
require LINKEDIN_FEEDS_DIR."includes/class-provider.php";
require LINKEDIN_FEEDS_DIR."includes/providers/class-provider-fresh-scraper.php";
require LINKEDIN_FEEDS_DIR."includes/providers/class-provider-fresh-profile.php";
require LINKEDIN_FEEDS_DIR."includes/class-feed-source.php";
require LINKEDIN_FEEDS_DIR."includes/class-shortcode.php";
