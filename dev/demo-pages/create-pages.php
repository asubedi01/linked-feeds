<?php
/**
 * Create/refresh the LinkedIn Feeds demo pages.
 * Run inside the container:  fin wp eval-file <this> --path=/var/www/docroot
 * Idempotent: matches by slug and updates instead of duplicating.
 */

$pages = array(
	'linkedin-demo-profile-grid' => array( 'LinkedIn Feed - Profile (Grid)', __DIR__ . '/profile-grid.html' ),
	'linkedin-demo-company-grid' => array( 'LinkedIn Feed - Company (Grid)', __DIR__ . '/company-grid.html' ),
	'linkedin-demo-layouts'      => array( 'LinkedIn Feed - Layouts',        __DIR__ . '/layouts.html' ),
	'linkedin-demo-comparison'   => array( 'LinkedIn Feed - Provider Comparison', __DIR__ . '/comparison.html' ),
);

foreach ( $pages as $slug => $cfg ) {
	list( $title, $file ) = $cfg;
	$content = file_get_contents( $file );

	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$postarr  = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}

	$id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $id ) ) {
		echo "FAIL {$slug}: " . $id->get_error_message() . "\n";
		continue;
	}
	echo str_pad( $title, 34 ) . " id={$id}  " . get_permalink( $id ) . "\n";
}
