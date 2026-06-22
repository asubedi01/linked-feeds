<?php
/**
 * Video media partial.
 *
 * @var array $media { kind:'video', poster, src, aspect_ratio }.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

$ratio = $media['aspect_ratio'] > 0 ? $media['aspect_ratio'] : 1.7777778;
?>
<div class="linkedin-post__media linkedin-post__video" style="aspect-ratio: <?php echo esc_attr( $ratio ); ?>;">
	<?php if ( $media['src'] ) : ?>
		<video controls preload="none" playsinline
			<?php if ( $media['poster'] ) : ?>poster="<?php echo esc_url( $media['poster'] ); ?>"<?php endif; ?>>
			<source src="<?php echo esc_url( $media['src'] ); ?>" type="application/vnd.apple.mpegurl" />
			<source src="<?php echo esc_url( $media['src'] ); ?>" type="video/mp4" />
		</video>
	<?php elseif ( $media['poster'] ) : ?>
		<img src="<?php echo esc_url( $media['poster'] ); ?>" alt="" loading="lazy" />
	<?php endif; ?>
</div>
