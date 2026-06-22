<?php
/**
 * Provider: Fresh LinkedIn Profile Data (fresh-linkedin-profile-data.p.rapidapi.com).
 *
 * One-call workflow: posts are fetched directly by `linkedin_url` (no resolve
 * step). Slower (~7s, synchronous scrape) but rich schema.
 *
 * Field mapping VERIFIED against a live response June 18, 2026
 * (probe/responses/freshprofile-profile-williamhgates.json, 50 posts). Notable
 * shape vs. the published docs: `poster` uses first/last/image_url/linkedin_url;
 * article fields are FLAT (article_title/subtitle/target_url), not a nested object;
 * `video` has no thumbnail; reactions include num_entertainments.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fresh LinkedIn Profile Data provider.
 */
class LinkedIn_Feeds_Provider_Fresh_Profile extends LinkedIn_Feeds_Provider {

	const HOST = 'fresh-linkedin-profile-data.p.rapidapi.com';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'fresh-profile';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function label() {
		return __( 'Fresh LinkedIn Profile Data (1-call by URL, ~7s)', 'linkedin-feeds' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * One call, keyed by the LinkedIn URL built from the handle. Pagination is
	 * offset-based (`start`): page 1 = 0, page 2 = 50, ...
	 */
	protected function get_raw( $type, $handle, $page ) {
		$start = max( 0, ( (int) $page - 1 ) * 50 );

		if ( 'company' === $type ) {
			$url = 'https://www.linkedin.com/company/' . rawurlencode( $handle );
			return $this->request(
				self::HOST,
				'/get-company-posts',
				array( 'linkedin_url' => $url, 'start' => $start, 'sort_by' => 'recent' )
			);
		}

		$url = 'https://www.linkedin.com/in/' . rawurlencode( $handle );
		return $this->request(
			self::HOST,
			'/get-profile-posts',
			array( 'linkedin_url' => $url, 'start' => $start, 'type' => 'posts' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function normalize_post( array $p ) {
		return array(
			'id'        => isset( $p['urn'] ) ? (string) $p['urn'] : '',
			'type'      => ! empty( $p['reshared'] ) ? 'repost' : 'ugc',
			'text'      => isset( $p['text'] ) ? (string) $p['text'] : '',
			'url'       => isset( $p['post_url'] ) ? esc_url_raw( $p['post_url'] ) : '',
			'share_urn' => isset( $p['share_urn'] ) ? (string) $p['share_urn'] : '',
			'timestamp' => isset( $p['posted'] ) ? (int) strtotime( $p['posted'] ) : 0,
			'author'    => $this->normalize_author( $p ),
			'stats'     => array(
				'likes'     => isset( $p['num_likes'] ) ? (int) $p['num_likes'] : 0,
				'comments'  => isset( $p['num_comments'] ) ? (int) $p['num_comments'] : 0,
				'shares'    => isset( $p['num_reposts'] ) ? (int) $p['num_reposts'] : 0,
				'reactions' => $this->reactions( $p ),
			),
			'media'     => $this->normalize_media( $p ),
		);
	}

	/**
	 * Reaction-type breakdown from the per-type counts.
	 *
	 * @param array $p Raw post.
	 * @return array<array{type:string,count:int}>
	 */
	private function reactions( array $p ) {
		$map = array(
			'LIKE'          => 'num_likes',
			'APPRECIATION'  => 'num_appreciations',
			'EMPATHY'       => 'num_empathy',
			'INTEREST'      => 'num_interests',
			'PRAISE'        => 'num_praises',
			'ENTERTAINMENT' => 'num_entertainments',
		);
		$out = array();
		foreach ( $map as $type => $key ) {
			if ( ! empty( $p[ $key ] ) ) {
				$out[] = array( 'type' => $type, 'count' => (int) $p[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * Author block. `poster` is present on most posts; reshares without one fall
	 * back to the top-level `poster_linkedin_url` (often a company page).
	 *
	 * @param array $p Raw post.
	 * @return array
	 */
	private function normalize_author( array $p ) {
		$poster = isset( $p['poster'] ) && is_array( $p['poster'] ) ? $p['poster'] : array();
		$url    = isset( $poster['linkedin_url'] ) ? $poster['linkedin_url'] : ( isset( $p['poster_linkedin_url'] ) ? $p['poster_linkedin_url'] : '' );

		$name = trim( ( $poster['first'] ?? '' ) . ' ' . ( $poster['last'] ?? '' ) );
		$is_company = false !== strpos( (string) $url, '/company/' );
		if ( '' === $name && $is_company ) {
			// Derive a readable name from the company slug.
			$slug = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			$slug = preg_replace( '#^company/#', '', $slug );
			$name = ucwords( str_replace( '-', ' ', $slug ) );
		}

		return array(
			'name'       => $name,
			'subtitle'   => isset( $poster['headline'] ) ? (string) $poster['headline'] : '',
			'url'        => esc_url_raw( $url ),
			'avatar'     => isset( $poster['image_url'] ) ? LinkedIn_Feeds_Media::localize( $poster['image_url'] ) : '',
			'is_company' => $is_company,
		);
	}

	/**
	 * Map media to the shared typed model. Priority: video > document > article >
	 * image (an article-share also carries a thumbnail image, so article wins).
	 *
	 * @param array $p Raw post.
	 * @return array
	 */
	private function normalize_media( array $p ) {
		// Video: nested { stream_url, duration } — no thumbnail provided.
		if ( ! empty( $p['video']['stream_url'] ) ) {
			return array(
				'kind'         => 'video',
				'poster'       => '',
				'src'          => LinkedIn_Feeds_Media::localize( $p['video']['stream_url'] ),
				'aspect_ratio' => 1.7777778,
			);
		}

		// Document (PDF): nested { title, page_count, url }.
		if ( ! empty( $p['document']['url'] ) ) {
			$d = $p['document'];
			return array(
				'kind'    => 'document',
				'title'   => isset( $d['title'] ) ? (string) $d['title'] : '',
				'pages'   => isset( $d['page_count'] ) ? (int) $d['page_count'] : 0,
				'pdf_url' => esc_url_raw( $d['url'] ),
			);
		}

		// Article (link preview): FLAT fields.
		if ( ! empty( $p['article_target_url'] ) ) {
			return array(
				'kind'     => 'article',
				'title'    => isset( $p['article_title'] ) ? (string) $p['article_title'] : '',
				'subtitle' => isset( $p['article_subtitle'] ) ? (string) $p['article_subtitle'] : '',
				'url'      => esc_url_raw( $p['article_target_url'] ),
			);
		}

		// Images: array of { url }.
		if ( ! empty( $p['images'] ) && is_array( $p['images'] ) ) {
			$images = array();
			foreach ( $p['images'] as $img ) {
				$url = is_array( $img ) ? ( $img['url'] ?? '' ) : (string) $img;
				if ( $url ) {
					$images[] = LinkedIn_Feeds_Media::localize( $url );
				}
			}
			if ( $images ) {
				return array( 'kind' => 'image', 'images' => $images );
			}
		}

		return $this->no_media();
	}
}
