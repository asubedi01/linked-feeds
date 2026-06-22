<?php
/**
 * Provider: Fresh LinkedIn Scraper API (fresh-linkedin-scraper-api.p.rapidapi.com).
 *
 * Verified live June 18, 2026 (see probe/responses/README.md). Two-call workflow:
 * resolve a stable internal id (profile urn / numeric company_id), cached a week,
 * then fetch posts (cached separately by the feed source).
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fresh LinkedIn Scraper API provider.
 */
class LinkedIn_Feeds_Provider_Fresh_Scraper extends LinkedIn_Feeds_Provider {

	const HOST = 'fresh-linkedin-scraper-api.p.rapidapi.com';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'fresh-scraper';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function label() {
		return __( 'Fresh LinkedIn Scraper (rich media, ~0.4–1.4s)', 'linkedin-feeds' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_raw( $type, $handle, $page ) {
		$id = $this->resolve_id( $type, $handle );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( 'company' === $type ) {
			return $this->request(
				self::HOST,
				'/api/v1/company/posts',
				array( 'company_id' => $id, 'page' => $page, 'sort_by' => 'recent' )
			);
		}
		return $this->request( self::HOST, '/api/v1/user/posts', array( 'urn' => $id, 'page' => $page ) );
	}

	/**
	 * Resolve handle → stable internal id (urn / company_id), cached a week.
	 *
	 * @param string $type   'profile' | 'company'.
	 * @param string $handle Username / company slug.
	 * @return string|WP_Error
	 */
	private function resolve_id( $type, $handle ) {
		$cache_key = 'linkedin_feeds_fs_id_' . md5( $type . '|' . $handle );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( 'company' === $type ) {
			$res = $this->request( self::HOST, '/api/v1/company/profile', array( 'company' => $handle ) );
			$id  = ! is_wp_error( $res ) && isset( $res['data']['id'] ) ? (string) $res['data']['id'] : '';
		} else {
			$res = $this->request( self::HOST, '/api/v1/user/profile', array( 'username' => $handle ) );
			$id  = ! is_wp_error( $res ) && isset( $res['data']['urn'] ) ? (string) $res['data']['urn'] : '';
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( '' === $id ) {
			return new WP_Error( 'linkedin_feeds_no_id', __( 'Could not resolve LinkedIn id.', 'linkedin-feeds' ) );
		}

		set_transient( $cache_key, $id, WEEK_IN_SECONDS );
		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function normalize_post( array $p ) {
		$activity = isset( $p['activity'] ) && is_array( $p['activity'] ) ? $p['activity'] : array();

		return array(
			'id'        => isset( $p['id'] ) ? (string) $p['id'] : '',
			'type'      => isset( $p['post_type'] ) ? $p['post_type'] : '',
			'text'      => isset( $p['text'] ) ? (string) $p['text'] : '',
			'url'       => isset( $p['url'] ) ? esc_url_raw( $p['url'] ) : '',
			'share_urn' => isset( $p['share_urn'] ) ? (string) $p['share_urn'] : '',
			'timestamp' => isset( $p['created_at'] ) ? (int) strtotime( $p['created_at'] ) : 0,
			'author'    => $this->normalize_author( isset( $p['author'] ) ? (array) $p['author'] : array() ),
			'stats'     => array(
				'likes'     => isset( $activity['num_likes'] ) ? (int) $activity['num_likes'] : 0,
				'comments'  => isset( $activity['num_comments'] ) ? (int) $activity['num_comments'] : 0,
				'shares'    => isset( $activity['num_shares'] ) ? (int) $activity['num_shares'] : 0,
				'reactions' => isset( $activity['reaction_counts'] ) ? (array) $activity['reaction_counts'] : array(),
			),
			'media'     => $this->normalize_media( isset( $p['content'] ) ? (array) $p['content'] : array() ),
		);
	}

	/**
	 * Author block (person or company).
	 *
	 * @param array $a Raw author.
	 * @return array
	 */
	private function normalize_author( array $a ) {
		$is_company = isset( $a['account_type'] ) ? ( 'company' === $a['account_type'] ) : ! isset( $a['first_name'] );

		$name = '';
		if ( isset( $a['name'] ) ) {
			$name = $a['name'];
		} elseif ( isset( $a['full_name'] ) ) {
			$name = $a['full_name'];
		}

		$subtitle = isset( $a['description'] ) ? $a['description'] : ( isset( $a['headline'] ) ? $a['headline'] : '' );

		return array(
			'name'       => (string) $name,
			'subtitle'   => (string) $subtitle,
			'url'        => isset( $a['url'] ) ? esc_url_raw( $a['url'] ) : '',
			'avatar'     => $this->pick_image( isset( $a['avatar'] ) ? (array) $a['avatar'] : array(), 200 ),
			'is_company' => (bool) $is_company,
		);
	}

	/**
	 * content.* → typed media model.
	 *
	 * @param array $content Raw content.
	 * @return array
	 */
	private function normalize_media( array $content ) {
		if ( ! empty( $content['images'] ) && is_array( $content['images'] ) ) {
			$images = array();
			foreach ( $content['images'] as $entry ) {
				$url = $this->pick_image( isset( $entry['image'] ) ? (array) $entry['image'] : array(), 800 );
				if ( '' !== $url ) {
					$images[] = $url;
				}
			}
			if ( $images ) {
				return array( 'kind' => 'image', 'images' => $images );
			}
		}

		if ( ! empty( $content['video'] ) && is_array( $content['video'] ) ) {
			$v = $content['video'];
			return array(
				'kind'         => 'video',
				'poster'       => $this->pick_image( isset( $v['thumbnail'] ) ? (array) $v['thumbnail'] : array(), 800 ),
				'src'          => $this->pick_stream( isset( $v['streams'] ) ? (array) $v['streams'] : array() ),
				'aspect_ratio' => isset( $v['aspect_ratio'] ) ? (float) $v['aspect_ratio'] : 1.7777778,
			);
		}

		if ( ! empty( $content['document'] ) && is_array( $content['document'] ) ) {
			$d = $content['document'];
			return array(
				'kind'    => 'document',
				'title'   => isset( $d['title'] ) ? (string) $d['title'] : '',
				'pages'   => isset( $d['total_page_count'] ) ? (int) $d['total_page_count'] : 0,
				'pdf_url' => isset( $d['transcribed_document_url'] ) ? esc_url_raw( $d['transcribed_document_url'] ) : '',
			);
		}

		if ( ! empty( $content['article'] ) && is_array( $content['article'] ) ) {
			$a = $content['article'];
			return array(
				'kind'     => 'article',
				'title'    => isset( $a['title'] ) ? (string) $a['title'] : '',
				'subtitle' => isset( $a['subtitle'] ) ? (string) $a['subtitle'] : '',
				'url'      => isset( $a['article_url'] ) ? esc_url_raw( $a['article_url'] ) : '',
			);
		}

		return $this->no_media();
	}
}
