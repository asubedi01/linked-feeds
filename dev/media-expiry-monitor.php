<?php
/**
 * Media URL expiry monitor.
 *
 * LinkedIn CDN media URLs (images, video streams/thumbnails, document PDFs,
 * avatars) are SIGNED and carry an `e=<unix>` expiry. This tool answers the
 * exploration question: "how long do captured media URLs keep working?" — by
 * (1) reading the embedded expiry from each captured response, and (2) optionally
 * doing a live HTTP check to confirm whether the URL still serves (200) or has
 * been revoked (403/expired). Append-logs to dev/media-expiry-log.csv so you can
 * run it daily and watch the real expiry vs. the advertised `e=` value.
 *
 * Usage:
 *   php dev/media-expiry-monitor.php            # parse + live-check, log a row per media type
 *   php dev/media-expiry-monitor.php --no-http  # parse only (offline; just the e= windows)
 *
 * @package LinkedIn_Feeds
 */

$do_http = ! in_array( '--no-http', $argv, true );
$dir     = dirname( __DIR__ ) . '/probe/responses';
$log     = __DIR__ . '/media-expiry-log.csv';
$now     = time();

/** Extract the e= expiry (unix seconds) embedded in a signed LinkedIn URL. */
function url_expiry( $url ) {
	return preg_match( '/[?&]e=(\d{10})/', (string) $url, $m ) ? (int) $m[1] : 0;
}

/** HEAD/GET a URL, return HTTP status (0 on transport error). */
function http_status( $url ) {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_NOBODY         => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 LinkedInFeeds-monitor',
	) );
	curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	return $code;
}

/**
 * Pull one representative URL per media type out of a decoded response.
 * Handles both provider shapes (fresh-scraper nested + fresh-profile flat-ish).
 *
 * @return array<string,string> type => url
 */
function sample_urls( array $wrapper ) {
	$out  = array();
	$data = $wrapper['data'] ?? array();
	foreach ( $data as $p ) {
		$c = $p['content'] ?? $p; // fresh-scraper nests under content; fresh-profile is flatter.

		if ( empty( $out['avatar'] ) ) {
			$av = $p['author']['avatar'][0]['url'] ?? ( $p['poster']['image_url'] ?? '' );
			if ( $av ) { $out['avatar'] = $av; }
		}
		if ( empty( $out['image'] ) ) {
			$img = $c['images'][0]['image'][0]['url'] ?? ( $p['images'][0]['url'] ?? '' );
			if ( $img ) { $out['image'] = $img; }
		}
		if ( empty( $out['video'] ) ) {
			$vid = $c['video']['streams'][0]['url'] ?? ( $p['video']['stream_url'] ?? '' );
			if ( $vid ) { $out['video'] = $vid; }
		}
		if ( empty( $out['vthumb'] ) ) {
			$vt = $c['video']['thumbnail'][0]['url'] ?? '';
			if ( $vt ) { $out['vthumb'] = $vt; }
		}
		if ( empty( $out['document'] ) ) {
			$doc = $c['document']['transcribed_document_url'] ?? ( $p['document']['url'] ?? '' );
			if ( $doc ) { $out['document'] = $doc; }
		}
	}
	return $out;
}

if ( ! is_dir( $dir ) ) {
	fwrite( STDERR, "No probe/responses dir — capture samples first.\n" );
	exit( 1 );
}

$new_log = ! file_exists( $log );
$fh      = fopen( $log, 'a' );
if ( $new_log ) {
	fputcsv( $fh, array( 'checked_at_utc', 'capture_file', 'media_type', 'expiry_utc', 'days_to_expiry', 'http_status', 'live' ), ',', '"', '' );
}

printf( "Checked %s UTC%s\n\n", gmdate( 'Y-m-d H:i', $now ), $do_http ? '' : ' (offline, --no-http)' );
printf( "%-40s %-9s %-12s %-8s %s\n", 'capture', 'type', 'expiry', 'days', $do_http ? 'http' : '' );

foreach ( glob( $dir . '/*.json' ) as $file ) {
	$wrapper = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $wrapper ) ) {
		continue;
	}
	foreach ( sample_urls( $wrapper ) as $type => $url ) {
		$exp    = url_expiry( $url );
		$days   = $exp ? round( ( $exp - $now ) / 86400, 1 ) : '';
		$status = $do_http ? http_status( $url ) : '';
		$live   = '' === $status ? '' : ( 200 === $status ? 'yes' : 'no' );

		printf(
			"%-40s %-9s %-12s %-8s %s\n",
			substr( basename( $file ), 0, 40 ),
			$type,
			$exp ? gmdate( 'Y-m-d', $exp ) : 'n/a',
			'' === $days ? '' : sprintf( '%+.1f', $days ),
			$do_http ? ( $status . ( 'yes' === $live ? ' ✓' : ' ✗' ) ) : ''
		);

		fputcsv( $fh, array( gmdate( 'c', $now ), basename( $file ), $type, $exp ? gmdate( 'c', $exp ) : '', $days, $status, $live ), ',', '"', '' );
	}
}
fclose( $fh );
echo "\nLogged to dev/media-expiry-log.csv\n";
