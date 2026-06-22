/**
 * LinkedIn Feeds — front-end behavior. No dependencies.
 * - Image lightbox (click an image → full-screen).
 * - Post-detail popup (click a card → enlarged post with full text + media).
 * - Carousel navigation (prev/next scroll).
 */
( function () {
	'use strict';

	/* ---------- Overlay helper ---------- */

	function openOverlay( className, buildContent ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'linkedin-overlay ' + className;

		var close = function () {
			overlay.remove();
			document.removeEventListener( 'keydown', onKey );
		};
		var onKey = function ( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		};

		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay || e.target.closest( '[data-linkedin-close]' ) ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', onKey );

		buildContent( overlay );
		document.body.appendChild( overlay );
		return overlay;
	}

	/* ---------- Image lightbox ---------- */

	function openImage( src ) {
		openOverlay( 'linkedin-lightbox', function ( overlay ) {
			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = '';
			overlay.appendChild( img );
		} );
	}

	/* ---------- Post-detail popup ---------- */

	function openDetail( card ) {
		openOverlay( 'linkedin-detail', function ( overlay ) {
			var panel = document.createElement( 'div' );
			panel.className = 'linkedin-detail__panel';

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'linkedin-detail__close';
			btn.setAttribute( 'data-linkedin-close', '' );
			btn.setAttribute( 'aria-label', 'Close' );
			btn.innerHTML = '&times;';
			panel.appendChild( btn );

			// Clone the card so the popup is a faithful, enlarged copy of the post.
			var clone = card.cloneNode( true );
			clone.removeAttribute( 'data-linkedin-detail' );
			clone.removeAttribute( 'role' );
			clone.removeAttribute( 'tabindex' );
			clone.classList.add( 'linkedin-post--detail' );
			panel.appendChild( clone );

			overlay.appendChild( panel );
		} );
	}

	/* ---------- Carousel ---------- */

	function scrollCarousel( btn ) {
		var feed = btn.closest( '.linkedin-feed--carousel' );
		if ( ! feed ) {
			return;
		}
		var track = feed.querySelector( '.linkedin-feed__track' );
		if ( ! track ) {
			return;
		}
		var card = track.querySelector( '.linkedin-post' );
		var step = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
		track.scrollBy( {
			left: 'prev' === btn.getAttribute( 'data-linkedin-carousel' ) ? -step : step,
			behavior: 'smooth',
		} );
	}

	/* ---------- Delegated click handling ---------- */

	document.addEventListener( 'click', function ( e ) {
		// Carousel arrows.
		var nav = e.target.closest( '[data-linkedin-carousel]' );
		if ( nav ) {
			scrollCarousel( nav );
			return;
		}

		// Image → lightbox (takes priority; stop it bubbling to the detail handler).
		var lb = e.target.closest( '[data-linkedin-lightbox]' );
		if ( lb ) {
			e.preventDefault();
			var img = lb.querySelector( 'img' );
			openImage( img ? img.src : lb.href );
			return;
		}

		// Real links and the native video player keep their default behavior.
		if ( e.target.closest( 'a, video, button' ) ) {
			return;
		}

		// Anywhere else on a card → post-detail popup.
		var card = e.target.closest( '[data-linkedin-detail]' );
		if ( card ) {
			openDetail( card );
		}
	} );

	// Keyboard: Enter/Space on a focused card opens its detail.
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' !== e.key && ' ' !== e.key ) {
			return;
		}
		var card = e.target.closest && e.target.closest( '[data-linkedin-detail]' );
		if ( card && e.target === card ) {
			e.preventDefault();
			openDetail( card );
		}
	} );
}() );
