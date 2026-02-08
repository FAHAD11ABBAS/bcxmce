{
	let refreshCartWidget = function() {
		document.querySelectorAll( '.em-cart-widget' ).forEach( function ( el ) {
			let form = el.querySelector( 'form' );
			let formData = new FormData( form );
			let loading_text = el.querySelector( '.em-cart-widget-contents' );
			loading_text.textContent = form.querySelector( 'input[name="loading_text"]' ).value;

			fetch( EM.ajaxurl + '?' + new URLSearchParams( formData ) )
				.then( response => response.text() )
				.then( response => {
					loading_text.innerHTML = response;
				} );
		} );
	}
	document.addEventListener( 'em_booking_success', refreshCartWidget );
	document.addEventListener( 'em_cart_refresh', refreshCartWidget );
	document.addEventListener( 'em_cart_widget_refresh', refreshCartWidget );

	document.addEventListener( 'em_booking_button_response', function ( e ) {
		let response = e.detail.response;
		let button = e.detail.button;

		if ( response.result ) {
			document.dispatchEvent( new CustomEvent( 'em_cart_widget_refresh' ) );
			button.classList.add( 'em-booked-button' );
			button.classList.remove( 'em-booking-button' );

			if ( typeof response.redirect === 'string' ) {
				button.setAttribute( 'href', response.redirect );
			}
			if ( typeof response.text === 'string' ) {
				button.textContent = response.text;
			}
		}
	} );
}