<?php
/**
 * Settings — Settings → LinkedIn Feeds. Stores the active provider + RapidAPI key.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers options and renders the settings screen.
 */
class LinkedIn_Feeds_Settings {

	const GROUP = 'linkedin_feeds_settings';

	/**
	 * Hook in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_linkedin_feeds_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_post_linkedin_feeds_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
	}

	/**
	 * Re-capture the demo feeds (fresh media URLs) + clear caches. Admin action.
	 */
	public function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'linkedin-feeds' ) );
		}
		check_admin_referer( 'linkedin_feeds_refresh' );
		$results = LinkedIn_Feeds_Feed_Source::refresh_demo_data();
		$this->clear_cache();
		set_transient( 'linkedin_feeds_notice', array( 'kind' => 'refresh', 'results' => $results ), 60 );
		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Clear cached live feeds so the next view refetches (fresh media URLs).
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'linkedin-feeds' ) );
		}
		check_admin_referer( 'linkedin_feeds_clear_cache' );
		$n = $this->clear_cache();
		set_transient( 'linkedin_feeds_notice', array( 'kind' => 'clear', 'count' => $n ), 60 );
		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Delete all linkedin_feeds_* transients (feed + resolve caches).
	 *
	 * @return int Rows deleted.
	 */
	private function clear_cache() {
		global $wpdb;
		return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_linkedin\_feeds\_%' OR option_name LIKE '\_transient\_timeout\_linkedin\_feeds\_%'"
		);
	}

	/**
	 * Render the result notice after a refresh / clear action.
	 */
	public function maybe_notice() {
		$notice = get_transient( 'linkedin_feeds_notice' );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'linkedin_feeds_notice' );
		echo '<div class="notice notice-info is-dismissible"><p><strong>LinkedIn Feeds:</strong> ';
		if ( 'clear' === $notice['kind'] ) {
			/* translators: %d: number of cache entries cleared. */
			echo esc_html( sprintf( __( 'Cleared %d cache entries — live feeds will refetch on next view.', 'linkedin-feeds' ), (int) $notice['count'] ) );
		} else {
			echo esc_html__( 'Demo data refresh:', 'linkedin-feeds' ) . ' ';
			$parts = array();
			foreach ( (array) $notice['results'] as $scope => $status ) {
				$parts[] = esc_html( $scope . ' — ' . $status );
			}
			echo wp_kses_post( implode( ' &middot; ', $parts ) );
		}
		echo '</p></div>';
	}

	/**
	 * Settings page URL.
	 *
	 * @return string
	 */
	private function settings_url() {
		return admin_url( 'options-general.php?page=linkedin-feeds' );
	}

	/**
	 * Add the options page under Settings.
	 */
	public function add_page() {
		add_options_page(
			__( 'LinkedIn Feeds', 'linkedin-feeds' ),
			__( 'LinkedIn Feeds', 'linkedin-feeds' ),
			'manage_options',
			'linkedin-feeds',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the two options.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			'linkedin_feeds_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_provider' ),
				'default'           => 'fresh-scraper',
			)
		);
		register_setting(
			self::GROUP,
			'linkedin_feeds_rapidapi_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Constrain the provider to a registered id.
	 *
	 * @param string $value Submitted value.
	 * @return string
	 */
	public function sanitize_provider( $value ) {
		$choices = LinkedIn_Feeds_Provider::choices();
		return isset( $choices[ $value ] ) ? $value : 'fresh-scraper';
	}

	/**
	 * Render the settings form.
	 */
	public function render_page() {
		$provider = LinkedIn_Feeds_Provider::default_id();
		$key       = (string) get_option( 'linkedin_feeds_rapidapi_key', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LinkedIn Feeds', 'linkedin-feeds' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="linkedin_feeds_provider"><?php esc_html_e( 'Data provider', 'linkedin-feeds' ); ?></label></th>
						<td>
							<select name="linkedin_feeds_provider" id="linkedin_feeds_provider">
								<?php foreach ( LinkedIn_Feeds_Provider::choices() as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $provider, $id ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Which RapidAPI LinkedIn API to fetch from. A shortcode can override this with provider="…".', 'linkedin-feeds' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="linkedin_feeds_rapidapi_key"><?php esc_html_e( 'RapidAPI key', 'linkedin-feeds' ); ?></label></th>
						<td>
							<input type="password" name="linkedin_feeds_rapidapi_key" id="linkedin_feeds_rapidapi_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Your RapidAPI key (works across providers — subscribe to the selected API on RapidAPI). Or define LINKEDIN_FEEDS_RAPIDAPI_KEY in wp-config.php.', 'linkedin-feeds' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Keep the demo fresh', 'linkedin-feeds' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Demo-mode media (images/video) is served from saved samples whose LinkedIn URLs expire in ~1–3 weeks. Re-capture them (needs a key — uses a few API calls) to keep demo feeds live. Live feeds self-refresh hourly and don\'t need this.', 'linkedin-feeds' ); ?>
			</p>
			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="linkedin_feeds_refresh" />
					<?php wp_nonce_field( 'linkedin_feeds_refresh' ); ?>
					<?php submit_button( __( 'Refresh demo data', 'linkedin-feeds' ), 'secondary', 'submit', false ); ?>
				</form>
				&nbsp;
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="linkedin_feeds_clear_cache" />
					<?php wp_nonce_field( 'linkedin_feeds_clear_cache' ); ?>
					<?php submit_button( __( 'Clear feed cache', 'linkedin-feeds' ), 'secondary', 'submit', false ); ?>
				</form>
			</p>

			<h2><?php esc_html_e( 'Usage', 'linkedin-feeds' ); ?></h2>
			<p><code>[linkedin_feed type="profile" user="williamhgates"]</code> &middot; <code>[linkedin_feed type="company" company="microsoft"]</code></p>
			<p><?php esc_html_e( 'Preview without a key:', 'linkedin-feeds' ); ?> <code>[linkedin_feed demo="1" type="profile"]</code></p>
			<p><?php esc_html_e( 'Per-shortcode provider override:', 'linkedin-feeds' ); ?> <code>[linkedin_feed type="company" company="microsoft" provider="fresh-profile"]</code></p>
		</div>
		<?php
	}
}
