<?php
/**
 * Plugin Name:       LinkedIn Feeds
 * Plugin URI:        https://smashballoon.com/
 * Description:       Display LinkedIn personal-profile and company-page post feeds via the [linkedin_feed] shortcode. Renders against the RapidAPI (Fresh LinkedIn Scraper) data shape; ships with a demo mode that renders saved sample payloads with no API key.
 * Version:           0.1.0-scaffold
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Smash Balloon
 * License:           GPL-2.0-or-later
 * Text Domain:       linkedin-feeds
 *
 * SCAFFOLD STATUS: this is an exploratory prototype built against live RapidAPI
 * samples in probe/responses/. The data layer + renderer are real; the live API
 * path is gated behind a key. See FINDINGS.md §7 for the compliance context —
 * shipping this commits to the third-party-scraper route, a stakeholder decision.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

define( 'LINKEDIN_FEEDS_VERSION', '0.1.0-scaffold' );
define( 'LINKEDIN_FEEDS_FILE', __FILE__ );
define( 'LINKEDIN_FEEDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LINKEDIN_FEEDS_URL', plugin_dir_url( __FILE__ ) );

require_once LINKEDIN_FEEDS_DIR . 'includes/class-media.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/class-provider.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-scraper.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/providers/class-provider-fresh-profile.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/class-feed-source.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/class-settings.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/class-shortcode.php';
require_once LINKEDIN_FEEDS_DIR . 'includes/class-plugin.php';

/**
 * Boot the plugin on plugins_loaded.
 */
function linkedin_feeds() {
	return LinkedIn_Feeds_Plugin::instance();
}
add_action( 'plugins_loaded', 'linkedin_feeds' );
