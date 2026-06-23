<?php
/**
 * Provider abstraction.
 *
 * Each provider encapsulates ONE RapidAPI LinkedIn source: its host, how it
 * fetches profile/company posts, and how it maps its JSON onto the shared post
 * model the templates render. Adding/swapping a provider touches only a provider
 * class — the shortcode, feed source, and templates are provider-agnostic.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class + registry for feed providers.
 */
abstract class LinkedIn_Feeds_Provider {

	/**
	 * RapidAPI key (shared across providers — a key is per RapidAPI account).
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Constructor.
	 *
	 * @param string $key Optional explicit key; otherwise resolved from constant/option.
	 */
	public function __construct( $key = '' ) {
		if ( '' === $key && defined( 'LINKEDIN_FEEDS_RAPIDAPI_KEY' ) ) {
			$key = LINKEDIN_FEEDS_RAPIDAPI_KEY;
		}
		if ( '' === $key ) {
			$key = (string) get_option( 'linkedin_feeds_rapidapi_key', '' );
		}
		$this->key = $key;
	}

	/* ---------- Registry ---------- */

	/**
	 * Map of provider id => class name.
	 *
	 * @return array<string,string>
	 */
	public static function registry() {
		return array(
			'fresh-scraper' => 'LinkedIn_Feeds_Provider_Fresh_Scraper',
			'fresh-profile' => 'LinkedIn_Feeds_Provider_Fresh_Profile',
		);
	}

	/**
	 * Id => human label, for settings dropdowns.
	 *
	 * @return array<string,string>
	 */
	public static function choices() {
		$out = array();
		foreach ( array_keys( self::registry() ) as $id ) {
			$class      = self::registry()[ $id ];
			$out[ $id ] = $class::label();
		}
		return $out;
	}

	/**
	 * The configured default provider id.
	 *
	 * @return string
	 */
	public static function default_id() {
		$id = (string) get_option( 'linkedin_feeds_provider', 'fresh-scraper' );
		return isset( self::registry()[ $id ] ) ? $id : 'fresh-scraper';
	}

	/**
	 * Factory.
	 *
	 * @param string|null $id  Provider id (null = configured default).
	 * @param string      $key Optional key override.
	 * @return LinkedIn_Feeds_Provider
	 */
	public static function make( $id = null, $key = '' ) {
		$registry = self::registry();
		if ( null === $id || ! isset( $registry[ $id ] ) ) {
			$id = self::default_id();
		}
		$class = $registry[ $id ];
		return new $class( $key );
	}

	/* ---------- Contract ---------- */

	/**
	 * Stable provider id (slug).
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human label. Static so the registry can list it without a key/instance.
	 *
	 * @return string
	 */
	abstract public static function label();

	/**
	 * Fetch the raw response wrapper for a feed.
	 *
	 * @param string $type   'profile' | 'company'.
	 * @param string $handle Username / company slug.
	 * @param int    $page   Page number.
	 * @return array|WP_Error Decoded wrapper or error.
	 */
	abstract protected function get_raw( $type, $handle, $page );

	/**
	 * Map one raw post object onto the shared model.
	 *
	 * @param array $raw Raw post.
	 * @return array Normalized post model.
	 */
	abstract public function normalize_post( array $raw );

	/* ---------- Shared behavior ---------- */

	/**
	 * Whether a key is configured.
	 *
	 * @return bool
	 */
	public function has_key() {
		return '' !== $this->key;
	}

	/**
	 * Fetch + normalize a feed.
	 *
	 * @param string $type   'profile' | 'company'.
	 * @param string $handle Username / company slug.
	 * @param int    $page   Page number.
	 * @return array[]|WP_Error Normalized posts or error.
	 */
	public function get_feed( $type, $handle, $page = 1 ) {
		$raw = $this->get_raw( $type, $handle, $page );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		return $this->normalize_wrapper( $raw );
	}

	/**
	 * Normalize a full response wrapper into a list of post models.
	 * Tolerant of which key holds the array.
	 *
	 * @param array $wrapper Decoded payload.
	 * @return array[]
	 */
	public function normalize_wrapper( array $wrapper ) {
		$items = array();
		foreach ( array( 'data', 'posts', 'response' ) as $key ) {
			if ( isset( $wrapper[ $key ] ) && is_array( $wrapper[ $key ] ) ) {
				$items = $wrapper[ $key ];
				break;
			}
		}
		$posts = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$posts[] = $this->normalize_post( $item );
			}
		}
		return $posts;
	}

	/**
	 * Perform a GET request against a RapidAPI host and decode JSON.
	 *
	 * @param string $host  RapidAPI host.
	 * @param string $path  Path beginning with a slash.
	 * @param array  $query Query args.
	 * @return array|WP_Error
	 */
	protected function request( $host, $path, array $query ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'linkedin_feeds_no_key', __( 'No RapidAPI key configured.', 'linkedin-feeds' ) );
		}

		// WP's add_query_arg() does NOT URL-encode values (build_query uses
		// urlencode=false), so values must be pre-encoded here.
		$url      = add_query_arg( array_map( 'rawurlencode', $query ), 'https://' . $host . $path );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'x-rapidapi-key'  => $this->key,
					'x-rapidapi-host' => $host,
				),
			)
		);
		return $this->handle_response( $response );
	}

	/**
	 * Perform a POST request with a JSON body against a RapidAPI host (some
	 * endpoints — e.g. post search — are POST).
	 *
	 * @param string $host RapidAPI host.
	 * @param string $path Path beginning with a slash.
	 * @param array  $body JSON body.
	 * @return array|WP_Error
	 */
	protected function request_post( $host, $path, array $body ) {
		if ( ! $this->has_key() ) {
			return new WP_Error( 'linkedin_feeds_no_key', __( 'No RapidAPI key configured.', 'linkedin-feeds' ) );
		}
		$response = wp_remote_post(
			'https://' . $host . $path,
			array(
				'timeout' => 25,
				'headers' => array(
					'x-rapidapi-key'  => $this->key,
					'x-rapidapi-host' => $host,
					'Content-Type'    => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		return $this->handle_response( $response );
	}

	/**
	 * Decode + validate a wp_remote_* response.
	 *
	 * @param array|WP_Error $response Raw response.
	 * @return array|WP_Error
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : wp_remote_retrieve_response_message( $response );
			return new WP_Error(
				'linkedin_feeds_http_' . $code,
				/* translators: 1: HTTP status code, 2: error message. */
				sprintf( __( 'LinkedIn API request failed (HTTP %1$d): %2$s', 'linkedin-feeds' ), $code, $message )
			);
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'linkedin_feeds_bad_json', __( 'Malformed API response.', 'linkedin-feeds' ) );
		}
		// Some providers return 200 with an in-band failure flag (e.g. a
		// decommissioned service). Surface that as an error.
		if ( isset( $data['success'] ) && false === $data['success'] && empty( $data['data'] ) ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'Provider returned an error.', 'linkedin-feeds' );
			return new WP_Error( 'linkedin_feeds_provider_error', $message );
		}
		return $data;
	}

	/**
	 * Whether this provider supports keyword/hashtag post search.
	 * Override in providers that implement it.
	 *
	 * @return bool
	 */
	public function supports_search() {
		return false;
	}

	/**
	 * Pick the smallest image variant >= target width (else the largest), then
	 * localize. For variant arrays shaped { width, height, url }.
	 *
	 * @param array $variants Variants.
	 * @param int   $target   Target width.
	 * @return string URL ('' if none).
	 */
	protected function pick_image( array $variants, $target ) {
		$best   = '';
		$best_w = -1;
		$fb     = '';
		$fb_w   = PHP_INT_MAX;
		foreach ( $variants as $v ) {
			if ( empty( $v['url'] ) ) {
				continue;
			}
			$w = isset( $v['width'] ) ? (int) $v['width'] : 0;
			if ( $w >= $best_w ) {
				$best_w = $w;
				$best   = $v['url'];
			}
			if ( $w >= $target && $w < $fb_w ) {
				$fb_w = $w;
				$fb   = $v['url'];
			}
		}
		$url = '' !== $fb ? $fb : $best;
		return $url ? LinkedIn_Feeds_Media::localize( $url ) : '';
	}

	/**
	 * Pick the highest-resolution video stream, then localize.
	 *
	 * @param array $streams Streams shaped { url, width, height, bit_rate }.
	 * @return string
	 */
	protected function pick_stream( array $streams ) {
		$chosen = '';
		$best_h = -1;
		foreach ( $streams as $s ) {
			if ( empty( $s['url'] ) ) {
				continue;
			}
			$h = isset( $s['height'] ) ? (int) $s['height'] : 0;
			if ( $h >= $best_h ) {
				$best_h = $h;
				$chosen = $s['url'];
			}
		}
		return $chosen ? LinkedIn_Feeds_Media::localize( $chosen ) : '';
	}

	/**
	 * Empty media model.
	 *
	 * @return array
	 */
	protected function no_media() {
		return array( 'kind' => 'none' );
	}
}
