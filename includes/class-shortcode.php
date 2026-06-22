<?php
/**
 * [linkedin_feed] shortcode + render helpers.
 *
 * Examples:
 *   [linkedin_feed demo="1" type="profile"]            Render saved personal-feed sample.
 *   [linkedin_feed demo="1" type="company" layout="list"]
 *   [linkedin_feed type="profile" user="williamhgates" limit="6"]   Live (needs key).
 *   [linkedin_feed type="company" company="microsoft"]
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the feed shortcode.
 */
class LinkedIn_Feeds_Shortcode {

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( 'linkedin_feed', array( $this, 'render' ) );
	}

	/**
	 * Render handler.
	 *
	 * @param array $atts Raw shortcode attributes.
	 * @return string HTML.
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'     => 'profile', // profile | company.
				'user'     => '',        // public username for profile feeds.
				'company'  => '',        // company slug for company feeds.
				'demo'     => '',        // "1" → render bundled sample, no API call.
				'layout'   => 'grid',    // grid | list | masonry.
				'limit'    => 0,         // 0 = all returned.
				'provider' => '',        // override the configured provider for this feed.
			),
			$atts,
			'linkedin_feed'
		);

		$provider = sanitize_text_field( $atts['provider'] );
		$choices  = LinkedIn_Feeds_Provider::choices();

		$args = array(
			'type'     => in_array( $atts['type'], array( 'profile', 'company' ), true ) ? $atts['type'] : 'profile',
			'user'     => sanitize_text_field( $atts['user'] ),
			'company'  => sanitize_text_field( $atts['company'] ),
			'demo'     => filter_var( $atts['demo'], FILTER_VALIDATE_BOOLEAN ),
			'layout'   => in_array( $atts['layout'], array( 'grid', 'list', 'masonry' ), true ) ? $atts['layout'] : 'grid',
			'limit'    => max( 0, (int) $atts['limit'] ),
			'provider' => isset( $choices[ $provider ] ) ? $provider : null,
		);

		$source = new LinkedIn_Feeds_Feed_Source();
		$posts  = $source->get_posts( $args );

		if ( is_wp_error( $posts ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="linkedin-feed-error">LinkedIn Feeds: ' . esc_html( $posts->get_error_message() ) . '</div>';
			}
			return '';
		}

		wp_enqueue_style( 'linkedin-feeds' );
		wp_enqueue_script( 'linkedin-feeds' );

		return $this->render_template( 'feed', array( 'posts' => $posts, 'args' => $args ) );
	}

	/**
	 * Render a template file with the given variables, returning its output.
	 * Themes can override any template by placing it in a `linkedin-feeds/` dir.
	 *
	 * @param string $name Template slug (no extension).
	 * @param array  $vars Variables exposed to the template.
	 * @return string
	 */
	public static function render_template( $name, array $vars = array() ) {
		$file = locate_template( 'linkedin-feeds/' . $name . '.php' );
		if ( '' === $file ) {
			$file = LINKEDIN_FEEDS_DIR . 'templates/' . $name . '.php';
		}
		if ( ! is_readable( $file ) ) {
			return '';
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, local template vars.
		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/* ---------- Template helpers ---------- */

	/**
	 * Human-friendly relative time.
	 *
	 * @param int $timestamp Unix time.
	 * @return string
	 */
	public static function time_ago( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}
		$diff = time() - $timestamp;
		if ( $diff < 0 ) {
			$diff = 0;
		}
		/* translators: %s: human time diff, e.g. "3 days". */
		return sprintf( __( '%s ago', 'linkedin-feeds' ), human_time_diff( $timestamp, time() ) );
	}

	/**
	 * Abbreviate engagement counts (1234 → 1.2K).
	 *
	 * @param int $n Number.
	 * @return string
	 */
	public static function abbrev( $n ) {
		$n = (int) $n;
		if ( $n >= 1000000 ) {
			return round( $n / 1000000, 1 ) . 'M';
		}
		if ( $n >= 1000 ) {
			return round( $n / 1000, 1 ) . 'K';
		}
		return (string) $n;
	}

	/**
	 * Convert raw post text to safe HTML: escape, linkify URLs, keep line breaks.
	 *
	 * @param string $text Raw text.
	 * @return string Safe HTML.
	 */
	public static function format_text( $text ) {
		$safe = esc_html( $text );
		$safe = make_clickable( $safe );
		return nl2br( $safe );
	}
}
