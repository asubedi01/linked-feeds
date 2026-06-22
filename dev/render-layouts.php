<?php
// Render every layout/type/limit combo to confirm what's demo-able.
require __DIR__ . '/_shims.php';
$sc  = new LinkedIn_Feeds_Shortcode();
$css = file_get_contents( LINKEDIN_FEEDS_DIR . 'assets/css/linkedin-feeds.css' );
$combos = [
  ['grid','profile',6], ['list','profile',4], ['masonry','profile',9],
  ['grid','company',6],
];
foreach ( $combos as [$layout,$type,$limit] ) {
  $html = $sc->render( ['demo'=>'1','type'=>$type,'layout'=>$layout,'limit'=>$limit] );
  $n = substr_count( $html, 'linkedin-post linkedin-post--' );
  printf("layout=%-8s type=%-7s limit=%d → %d cards\n", $layout, $type, $limit, $n);
}
echo "OK\n";
