/**
 * LinkedIn Feeds — front-end behavior (scaffold).
 * Minimal image lightbox; no dependencies.
 */
( function () {
	'use strict';

	function openLightbox( src ) {
		var box = document.createElement( 'div' );
		box.className = 'linkedin-lightbox';
		box.innerHTML = '<img src="" alt="" />';
		box.querySelector( 'img' ).src = src;
		box.addEventListener( 'click', function () {
			box.remove();
		} );
		document.body.appendChild( box );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-linkedin-lightbox]' );
		if ( ! trigger ) {
			return;
		}
		e.preventDefault();
		var img = trigger.querySelector( 'img' );
		openLightbox( img ? img.src : trigger.href );
	} );
}() );
