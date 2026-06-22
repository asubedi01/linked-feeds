<?php
/**
 * Single post card.
 *
 * @var array $post Normalized post model.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

$author = $post['author'];
$stats  = $post['stats'];
$media  = $post['media'];
?>
<article class="linkedin-post linkedin-post--<?php echo esc_attr( $media['kind'] ); ?>">

	<header class="linkedin-post__head">
		<?php if ( $author['avatar'] ) : ?>
			<a class="linkedin-post__avatar" href="<?php echo esc_url( $author['url'] ); ?>" target="_blank" rel="noopener nofollow">
				<img src="<?php echo esc_url( $author['avatar'] ); ?>" alt="<?php echo esc_attr( $author['name'] ); ?>" loading="lazy" width="48" height="48" />
			</a>
		<?php else : ?>
			<?php // No logo from this provider — render an initial-circle so the header grid stays aligned. ?>
			<span class="linkedin-post__avatar linkedin-post__avatar--placeholder" aria-hidden="true"><?php echo esc_html( strtoupper( mb_substr( $author['name'] ? $author['name'] : '?', 0, 1 ) ) ); ?></span>
		<?php endif; ?>
		<div class="linkedin-post__meta">
			<a class="linkedin-post__author" href="<?php echo esc_url( $author['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $author['name'] ); ?></a>
			<?php if ( $author['subtitle'] ) : ?>
				<span class="linkedin-post__subtitle"><?php echo esc_html( $author['subtitle'] ); ?></span>
			<?php endif; ?>
			<?php if ( $post['timestamp'] ) : ?>
				<time class="linkedin-post__time" datetime="<?php echo esc_attr( gmdate( 'c', $post['timestamp'] ) ); ?>"><?php echo esc_html( LinkedIn_Feeds_Shortcode::time_ago( $post['timestamp'] ) ); ?></time>
			<?php endif; ?>
		</div>
		<span class="linkedin-post__brand" aria-hidden="true">in</span>
	</header>

	<?php if ( '' !== $post['text'] ) : ?>
		<div class="linkedin-post__text"><?php echo wp_kses_post( LinkedIn_Feeds_Shortcode::format_text( $post['text'] ) ); ?></div>
	<?php endif; ?>

	<?php
	if ( 'none' !== $media['kind'] ) {
		echo LinkedIn_Feeds_Shortcode::render_template( 'media-' . $media['kind'], array( 'media' => $media, 'post' => $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial returns escaped HTML.
	}
	?>

	<footer class="linkedin-post__foot">
		<div class="linkedin-post__stats">
			<span title="<?php esc_attr_e( 'Reactions', 'linkedin-feeds' ); ?>">&#128077; <?php echo esc_html( LinkedIn_Feeds_Shortcode::abbrev( $stats['likes'] ) ); ?></span>
			<span title="<?php esc_attr_e( 'Comments', 'linkedin-feeds' ); ?>">&#128172; <?php echo esc_html( LinkedIn_Feeds_Shortcode::abbrev( $stats['comments'] ) ); ?></span>
			<span title="<?php esc_attr_e( 'Reposts', 'linkedin-feeds' ); ?>">&#128257; <?php echo esc_html( LinkedIn_Feeds_Shortcode::abbrev( $stats['shares'] ) ); ?></span>
		</div>
		<?php if ( $post['url'] ) : ?>
			<a class="linkedin-post__permalink" href="<?php echo esc_url( $post['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'View on LinkedIn', 'linkedin-feeds' ); ?></a>
		<?php endif; ?>
	</footer>

</article>
