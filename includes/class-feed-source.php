<?php
/**
 * Feed source — turns a shortcode's intent into normalized posts, from either
 * saved sample JSON (demo mode) or the selected live provider (cached).
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves + caches feeds.
 */
class LinkedIn_Feeds_Feed_Source {

	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Get normalized posts for a parsed set of shortcode args.
	 *
	 * @param array $args { type, user, company, demo, provider, limit }.
	 * @return array[]|WP_Error
	 */
	public function get_posts( array $args ) {
		$posts = ! empty( $args['demo'] )
			? $this->load_sample( $args['type'] )
			: $this->load_live( $args );

		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		if ( $args['limit'] > 0 ) {
			$posts = array_slice( $posts, 0, $args['limit'] );
		}
		return $posts;
	}

	/**
	 * Load a saved sample payload (demo mode — no API call). Samples are the
	 * fresh-scraper captures, normalized with that provider's mapping; demo is a
	 * UI preview, independent of the live provider setting.
	 *
	 * @param string $type 'profile' | 'company'.
	 * @return array[]|WP_Error
	 */
	private function load_sample( $type ) {
		$map  = array(
			'profile' => 'profile-posts-williamhgates.json',
			'company' => 'company-posts-1035.json',
		);
		$file = LINKEDIN_FEEDS_DIR . 'probe/responses/' . ( isset( $map[ $type ] ) ? $map[ $type ] : $map['profile'] );

		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'linkedin_feeds_no_sample', __( 'Sample data file not found.', 'linkedin-feeds' ) );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled sample.
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'linkedin_feeds_bad_sample', __( 'Sample data is malformed.', 'linkedin-feeds' ) );
		}

		$provider = LinkedIn_Feeds_Provider::make( 'fresh-scraper' );
		return $provider->normalize_wrapper( $data );
	}

	/**
	 * Load a live feed via the selected provider, cached.
	 *
	 * @param array $args Parsed shortcode args.
	 * @return array[]|WP_Error
	 */
	private function load_live( array $args ) {
		$handle = 'company' === $args['type'] ? $args['company'] : $args['user'];
		if ( '' === $handle ) {
			return new WP_Error( 'linkedin_feeds_no_source', __( 'No LinkedIn profile or company specified.', 'linkedin-feeds' ) );
		}

		$provider = LinkedIn_Feeds_Provider::make( $args['provider'] );
		if ( ! $provider->has_key() ) {
			return new WP_Error( 'linkedin_feeds_no_key', __( 'Add a RapidAPI key (or use demo="1").', 'linkedin-feeds' ) );
		}

		// Cache keyed by provider + type + handle so switching providers re-fetches.
		$cache_key = 'linkedin_feeds_' . md5( $provider->id() . '|' . $args['type'] . '|' . $handle );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = $provider->get_feed( $args['type'], $handle );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		set_transient( $cache_key, $posts, self::CACHE_TTL );
		return $posts;
	}
}
