<?php
/**
 * Media localizer.
 *
 * IMPORTANT (see FINDINGS.md §7.3): every media.licdn.com / dms.licdn.com URL the
 * API returns is a SIGNED CDN link that EXPIRES (weeks). A production feed must
 * download and re-host media locally so embedded <img>/<video> don't 403 once the
 * signature lapses. This scaffold ships a pass-through with the hook in place;
 * implementing the sideload is a tracked TODO.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns expiring LinkedIn CDN URLs into durable local URLs.
 */
class LinkedIn_Feeds_Media {

	/**
	 * Localize a single media URL.
	 *
	 * Scaffold behavior: pass-through (returns the signed URL unchanged), so the
	 * renderer works against fresh samples. Filter `linkedin_feeds_localize_media`
	 * lets the production media store swap in a rehosted URL without touching the
	 * normalizer or templates.
	 *
	 * @param string $url Remote signed URL.
	 * @return string Local (or, for now, original) URL.
	 */
	public static function localize( $url ) {
		/**
		 * Filter the URL used for a piece of LinkedIn media.
		 *
		 * Production: hook here, sideload via media_sideload_image()/wp_upload_bits()
		 * keyed by a hash of the URL path (ignore the expiring query string), cache the
		 * attachment id, and return the local URL. Refresh on the feed's cron cadence.
		 *
		 * @param string $url Original signed CDN URL.
		 */
		return (string) apply_filters( 'linkedin_feeds_localize_media', $url );
	}
}
