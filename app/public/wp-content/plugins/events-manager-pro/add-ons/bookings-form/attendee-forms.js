// remove attendee forms before DOMContentLoaded or jQuery is ready, so nothing is initialized
document.addEventListener("em_booking_form_init", function( e ) {
	let bookingForm = e.target;
	const attendeeTemplates = {};

	// remove attendee forms before DOMContentLoaded or jQuery is ready, so nothing is initialized
	let id = bookingForm.getAttribute('data-id');
	attendeeTemplates[id] = {};
	bookingForm.querySelectorAll('.em-ticket-booking-template').forEach( function( attendeeTemplate ){
		let ticket_id = attendeeTemplate.closest('[data-ticket-id]').getAttribute('data-ticket-id');
		if ( ticket_id === null ) { // fallback
			ticket_id = attendeeTemplate.closest('tbody').getAttribute('data-ticket-id');
		}
		// clear ui elements
		em_unsetup_ui_elements( attendeeTemplate );
		// save to map of templates
		attendeeTemplates[id][ticket_id] = attendeeTemplate;
		attendeeTemplate.remove(); // remove from DOM
	});

	const em_setup_attendee_forms = function ( spaces, fieldset = null, fieldset_container, fields_template ) {
		if ( fields_template.children.length === 0 ) return;
		//get the attendee form template and clone it
		const form = fields_template.cloneNode( true );
		form.classList.remove( 'em-ticket-booking-template' );
		form.classList.add( 'em-ticket-booking' );

		//add or subtract fields according to spaces
		const current_forms = fieldset_container.querySelectorAll( '.em-ticket-booking' );
		const new_forms = [];

		if ( current_forms.length < spaces ) {
			// we're adding new forms, so we clone our newly cloned and trimmed template form and keep adding it before the template, which is last
			for ( let i = current_forms.length; i < spaces; i++ ) {
				const new_form = form.cloneNode( true );
				fieldset_container.appendChild( new_form );
				new_form.style.display = 'block';
				new_form.innerHTML = new_form.innerHTML.replace( /#NUM#/g, i + 1 );
				new_form.querySelectorAll( '*[name]' ).forEach( el => {
					el.name = el.name.replace( '[%n]', '[' + i + ']' );
				} );
				new_forms.push( new_form );
			}
		} else if ( current_forms.length > spaces ) {
			for ( let i = current_forms.length - 1; i >= spaces; i-- ) {
				current_forms[i].remove();
			}
		}
		// clean up
		new_forms.forEach( container => em_setup_ui_elements( container ) );
		form.remove();
		return true;
	};

	let booking_form_attendees_listener = function ( el ) {
		const spaces = parseInt( el.value );
		const bookingFormId = el.closest( 'form' ).dataset.id;
		const ticket_id = el.dataset.ticketId;
		const fieldset_container = el.closest( 'form' ).querySelector( '.em-ticket-bookings-' + ticket_id );

		if ( typeof attendeeTemplates[bookingFormId][ticket_id] === 'object' ) {
			let fields_template = attendeeTemplates[bookingFormId][ticket_id];
			em_setup_attendee_forms( spaces, null, fieldset_container, fields_template );
			if ( spaces > 0 ) {
				fieldset_container.classList.remove( 'hidden' );
			} else {
				fieldset_container.classList.add( 'hidden' );
			}
		}
	}

	bookingForm.querySelectorAll( '.em-ticket-select' ).forEach( select => {
		select.addEventListener( 'change', function ( e ) {
			booking_form_attendees_listener( e.target );
		} );
		if ( select.value != 0 ) booking_form_attendees_listener( select );
	} );

	bookingForm.addEventListener( 'click', function ( e ) {
		if ( !e.target.matches( '.em-ticket-booking .em-ticket-booking-remove-trigger' ) ) return;

		const el = e.target;
		const wrapper = el.closest( '.em-ticket-bookings' );
		const ticket_id = wrapper.getAttribute( 'data-ticket-id' );
		el.closest( '.em-ticket-booking' ).remove();

		const select = wrapper.closest( '.em-booking-form' ).querySelector( '.em-ticket-' + ticket_id + ' .em-ticket-select' );
		select.value = parseInt( select.value ) - 1;

		wrapper.querySelectorAll( '.em-ticket-booking' ).forEach( ( el, i ) => {
			el.querySelectorAll( '[data-placeholder]' ).forEach( el => {
				const placeholder = el.getAttribute( 'data-placeholder' );
				el.innerHTML = placeholder.replace( /%d/g, i + 1 );
			} );
		} );
	} );
});