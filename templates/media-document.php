<?php
/**
 * Document (PDF carousel) media partial.
 *
 * @var array $media { kind:'document', title, pages, pdf_url }.
 *
 * @package LinkedIn_Feeds
 */

defined( 'ABSPATH' ) || exit;
?>
<a class="linkedin-post__media linkedin-post__document" href="<?php echo esc_url( $media['pdf_url'] ); ?>" target="_blank" rel="noopener nofollow">
	<span class="linkedin-post__document-icon" aria-hidden="true">&#128196;</span>
	<span class="linkedin-post__document-meta">
		<span class="linkedin-post__document-title"><?php echo esc_html( $media['title'] ); ?></span>
		<?php if ( $media['pages'] ) : ?>
			<span class="linkedin-post__document-pages">
				<?php
				/* translators: %d: number of pages. */
				echo esc_html( sprintf( _n( '%d page', '%d pages', $media['pages'], 'linkedin-feeds' ), $media['pages'] ) );
				?>
			</span>
		<?php endif; ?>
	</span>
</a>
