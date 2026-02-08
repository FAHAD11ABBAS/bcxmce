document.querySelectorAll( '.em-cart-coupons-form' ).forEach( function ( form ) {
	form.addEventListener( 'submit', async function ( e ) {
		e.preventDefault();
		const coupon_form = this;
		const coupon_input = coupon_form.querySelector( 'input.em-coupon-code' );
		const coupon_button = coupon_form.querySelector( 'button' );

		document.querySelectorAll( '.em-coupon-message' ).forEach( msg => msg.remove() );
		if ( coupon_input.value === '' ) return false;
		coupon_button.classList.add( 'loading' );

		const formData = new FormData( coupon_form );

		fetch( EM.ajaxurl, {
			method: 'POST',
			body: formData
		} )
			.then( response => response.json() )
			.then( data => {
				if ( data.result ) {
					document.dispatchEvent( new CustomEvent( 'em_cart_refresh' ) );
				} else {
					const error = document.createElement( 'span' );
					error.className = 'em-coupon-message em-coupon-error';
					error.innerHTML = '<span class="em-icon em-icon-cross-circle"></span> ' + data.message;
					coupon_form.prepend( error );
				}
			} )
			.catch( error => {
				console.error( error );
			} )
			.finally( () => {
				coupon_button.classList.remove( 'loading' );
			} );
	} );
} );