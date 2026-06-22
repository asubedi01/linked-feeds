<?php
/**
 * Image media partial.
 *
 * @var array $media { kind:'image', images: string[] }.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

$images = $media['images'];
$count  = count( $images );
?>
<div class="linkedin-post__media linkedin-post__images linkedin-post__images--<?php echo esc_attr( min( $count, 4 ) ); ?>">
	<?php foreach ( $images as $src ) : ?>
		<a class="linkedin-post__image" href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener nofollow" data-linkedin-lightbox>
			<img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy" />
		</a>
	<?php endforeach; ?>
</div>
