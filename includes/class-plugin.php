<?php
/**
 * Plugin bootstrap / singleton.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's pieces together and registers WordPress hooks.
 */
final class LinkedIn_Feeds_Plugin {

	/**
	 * Single instance.
	 *
	 * @var LinkedIn_Feeds_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Shortcode handler.
	 *
	 * @var LinkedIn_Feeds_Shortcode
	 */
	public $shortcode;

	/**
	 * Settings handler.
	 *
	 * @var LinkedIn_Feeds_Settings
	 */
	public $settings;

	/**
	 * Get the singleton.
	 *
	 * @return LinkedIn_Feeds_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register hooks.
	 */
	private function __construct() {
		$this->shortcode = new LinkedIn_Feeds_Shortcode();
		$this->shortcode->register();

		$this->settings = new LinkedIn_Feeds_Settings();
		$this->settings->register();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register front-end assets. They are only enqueued when the shortcode renders.
	 */
	public function register_assets() {
		wp_register_style(
			'linkedin-feeds',
			LINKEDIN_FEEDS_URL . 'assets/css/linkedin-feeds.css',
			array(),
			LINKEDIN_FEEDS_VERSION
		);

		wp_register_script(
			'linkedin-feeds',
			LINKEDIN_FEEDS_URL . 'assets/js/linkedin-feeds.js',
			array(),
			LINKEDIN_FEEDS_VERSION,
			true
		);
	}
}
