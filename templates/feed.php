<?php
/**
 * Feed wrapper template.
 *
 * @var array[] $posts Normalized posts.
 * @var array   $args  Parsed shortcode args.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $posts ) ) {
	return;
}
?>
<div class="linkedin-feed linkedin-feed--<?php echo esc_attr( $args['layout'] ); ?> linkedin-feed--<?php echo esc_attr( $args['type'] ); ?>">
	<?php
	foreach ( $posts as $post ) {
		echo LinkedIn_Feeds_Shortcode::render_template( 'post', array( 'post' => $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template returns escaped HTML.
	}
	?>
</div>
