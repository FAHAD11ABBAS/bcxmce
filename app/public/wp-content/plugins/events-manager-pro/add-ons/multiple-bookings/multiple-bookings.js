//checkout cart
document.addEventListener( 'click', function ( e ) {
	if ( e.target.matches( '.em-cart-table a.em-cart-table-details-show' ) ) {
		e.preventDefault();
		let event_id = e.target.classList.add( 'hidden' ) && e.target.getAttribute( 'rel' );
		document.querySelector( '#em-cart-table-details-hide-' + event_id ).classList.remove( 'hidden' );
		document.querySelectorAll( '.em-cart-table-event-details-' + event_id ).forEach( el => el.style.display = 'block' );
		document.querySelectorAll( '#em-cart-table-event-summary-' + event_id + ' .em-cart-table-spaces span' ).forEach( el => el.style.display = 'none' );
		document.querySelectorAll( '#em-cart-table-event-summary-' + event_id + ' .em-cart-table-price span' ).forEach( el => el.style.display = 'none' );
	}

	if ( e.target.matches( '.em-cart-table a.em-cart-table-details-hide' ) ) {
		e.preventDefault();
		let event_id = e.target.classList.add( 'hidden' ) && e.target.getAttribute( 'rel' );
		document.querySelector( '#em-cart-table-details-show-' + event_id ).classList.remove( 'hidden' );
		document.querySelectorAll( '.em-cart-table-event-details-' + event_id ).forEach( el => el.style.display = 'none' );
		document.querySelectorAll( '#em-cart-table-event-summary-' + event_id + ' .em-cart-table-spaces span' ).forEach( el => el.style.display = 'block' );
		document.querySelectorAll( '#em-cart-table-event-summary-' + event_id + ' .em-cart-table-price span' ).forEach( el => el.style.display = 'block' );
	}

	if ( e.target.matches( '.em-cart-table a.em-cart-table-actions-remove' ) ) {
		e.preventDefault();
		let event_id = e.target.getAttribute( 'rel' );
		let container = e.target.closest( '.em-cart-table' ).parentElement;

		document.querySelectorAll( '.em-booking-message' ).forEach( el => el.remove() );
		let spinner = document.createElement('div');
		spinner.classList.add('em-loading');
		container.parentElement.append( spinner );

		fetch( EM.ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: 'action=emp_checkout_remove_item&event_id=' + event_id
		} )
			.then( response => response.json() )
			.then( response => {
				spinner.remove();
				if ( response.result ) {
					document.dispatchEvent( new CustomEvent( 'em_cart_refresh' ) );
				} else {
					em_booking_form_add_error( container, response.message, {} );
					window.scrollTo( {
						top: container.parentElement.offsetTop - 30,
						behavior: 'smooth'
					} );
				}
			} );
	}

	if ( e.target.matches( '.em-cart-actions-empty' ) ) {
		if ( !confirm( EM.mb_empty_cart ) ) return false;
		e.preventDefault();
		let container = e.target.parentElement;

		document.querySelectorAll( '.em-booking-message' ).forEach( el => el.remove() );
		let spinner = document.createElement('div');
		spinner.classList.add('em-loading');
		container.parentElement.append( spinner );

		fetch( EM.ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: 'action=emp_empty_cart'
		} )
			.then( () => {
				spinner.remove();
				em_mb_booking_form_page_refresh();
			} );
	}
} );

let em_mb_booking_form_page_refresh = function ( part = 'cart') {
	let loader = document.querySelector( '.em-' + part + '-page-contents' );
	if ( loader ) {
		let spinner = document.createElement('div');
		spinner.classList.add('em-loading');
		loader.append(spinner);

		fetch( EM.ajaxurl + '?action=em_' + part + '_page_contents' )
			.then( response => response.text() )
			.then( response => {
				loader.innerHTML = response;
			} );
	}
};
document.addEventListener( 'em_cart_page_refresh', () => em_mb_booking_form_page_refresh('cart') );
document.addEventListener( 'em_checkout_page_refresh', () => em_mb_booking_form_page_refresh('checkout') );
document.addEventListener( 'em_cart_refresh', () => {
	em_mb_booking_form_page_refresh('checkout');
	em_mb_booking_form_page_refresh('cart');
} );

if ( EM.cache ) {
	document.dispatchEvent( new CustomEvent( 'em_cart_refresh' ) );
}