<?php
/**
 * Article (link preview) media partial.
 *
 * @var array $media { kind:'article', title, subtitle, url }.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;
?>
<a class="linkedin-post__media linkedin-post__article" href="<?php echo esc_url( $media['url'] ); ?>" target="_blank" rel="noopener nofollow">
	<span class="linkedin-post__article-title"><?php echo esc_html( $media['title'] ); ?></span>
	<?php if ( $media['subtitle'] ) : ?>
		<span class="linkedin-post__article-subtitle"><?php echo esc_html( $media['subtitle'] ); ?></span>
	<?php endif; ?>
	<span class="linkedin-post__article-host"><?php echo esc_html( wp_parse_url( $media['url'], PHP_URL_HOST ) ); ?></span>
</a>
