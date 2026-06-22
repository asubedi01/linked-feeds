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
<?php if ( ! empty( $args['show_source'] ) && ! empty( $args['source_label'] ) ) : ?>
	<p class="linkedin-feed__source">
		<span class="linkedin-feed__source-badge"><?php esc_html_e( 'Source', 'linkedin-feeds' ); ?></span>
		<?php echo esc_html( $args['source_label'] ); ?>
		<?php if ( ! empty( $args['demo'] ) ) : ?><em>(<?php esc_html_e( 'demo data', 'linkedin-feeds' ); ?>)</em><?php endif; ?>
	</p>
<?php endif; ?>
<div class="linkedin-feed linkedin-feed--<?php echo esc_attr( $args['layout'] ); ?> linkedin-feed--<?php echo esc_attr( $args['type'] ); ?>">
	<?php
	foreach ( $posts as $post ) {
		echo LinkedIn_Feeds_Shortcode::render_template( 'post', array( 'post' => $post ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template returns escaped HTML.
	}
	?>
</div>
