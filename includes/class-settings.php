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

			<h2><?php esc_html_e( 'Usage', 'linkedin-feeds' ); ?></h2>
			<p><code>[linkedin_feed type="profile" user="williamhgates"]</code> &middot; <code>[linkedin_feed type="company" company="microsoft"]</code></p>
			<p><?php esc_html_e( 'Preview without a key:', 'linkedin-feeds' ); ?> <code>[linkedin_feed demo="1" type="profile"]</code></p>
			<p><?php esc_html_e( 'Per-shortcode provider override:', 'linkedin-feeds' ); ?> <code>[linkedin_feed type="company" company="microsoft" provider="fresh-profile"]</code></p>
		</div>
		<?php
	}
}
