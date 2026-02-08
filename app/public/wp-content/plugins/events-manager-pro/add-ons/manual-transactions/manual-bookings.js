document.addEventListener("em_booking_form_init", function( e ) {
	let booking_form = e.target;
	if ( !booking_form.closest('.em-manual-booking') ) return false;
	// add check to see if matches need action
	let match_action = function( match ) {
		let booking_intent = booking_form.querySelector('input.em-booking-intent');
		if ( booking_intent.getAttribute('data-amount-orig') === null ) {
			booking_intent.setAttribute('data-amount-orig', booking_intent.getAttribute('data-amount') )
		}
		let amount = booking_intent.getAttribute('data-amount');
		if( match.name === 'payment_full' ){
			if( match.checked ) {
				booking_intent.setAttribute('data-amount', '0');
				booking_form.querySelector('.input-group.input-manual-amount').classList.add('hidden');
				booking_form.querySelector('[name="payment_amount"]').value = '';
			} else {
				booking_intent.setAttribute('data-amount', booking_intent.getAttribute('data-amount-orig'));
				booking_form.querySelector('.input-group.input-manual-amount').classList.remove('hidden');
			}
		} else if ( match.name === 'payment_amount' ) {
			if( match.value > 0 ) {
				booking_intent.setAttribute('data-amount', '0');
				if ( parseFloat(match.value) > parseFloat(booking_intent.getAttribute('data-amount-orig')) ) {
					match.value = '';
					match.closest('.input-group').classList.add('hidden');
					booking_form.querySelector('input[name="payment_full"]').checked = true;
				}
			} else {
				booking_intent.setAttribute('data-amount', booking_intent.getAttribute('data-amount-orig'));
			}
		}
		if( amount !== booking_intent.getAttribute('data-amount') ) {
			// something changed, trigger intent update
			em_booking_form_update_booking_intent(booking_form, booking_intent);
		}
	};
	// add listener to trigger update the booking_intent object if fully paid checkbox is clicked
	booking_form.addEventListener('change', function (e) {
		if ( e.target.matches('input[name="payment_full"], input[name="payment_amount"]') ) {
			match_action( e.target );
		}
	});
	booking_form.addEventListener("em_booking_intent_updated", function( e ){
		let fully_paid = booking_form.querySelector('input[name="payment_full"]');
		if ( fully_paid?.checked ){
			match_action( fully_paid );
		}
		let amount_paid = booking_form.querySelector('input[name="payment_amount"]');
		if ( amount_paid.value && parseFloat(amount_paid.value) > 0 ){
			match_action( amount_paid );
		}
	});

	// older functionality - jQuery dependent
	let $ = jQuery.noConflict();
	let user_fields = $('.em-booking-form p.input-user-field');
	$('select#person_id').on('change', function(e){
		let select = e.target;
		let person_id = select.value;
		let selectize = this.selectize;
		let option = selectize.getOption(person_id);

		person_id > 0 ? user_fields.addClass('hidden') : user_fields.removeClass('hidden');
		// handle consent checkboxes
		if( person_id > 0 ){
			$('.em-booking-form p.input-user-field').addClass('hidden');
			for ( const [type, consent] of Object.entries( EM.manual_booking.consents ) ) {
				if ( consent.enabled > 0 ) {
					let field = document.querySelector( consent.field );
					let checkbox = field.querySelector( 'input[type="checkbox"]' );
					checkbox.checked = false;
					var consented = Number( option.attr( 'data-' + type + '-consented' ) ) === 1;
					if ( consent.enabled === 1 ) {
						if ( consent.remember > 0 ) {
							checkbox.checked = consented;
							if ( consent.remember === 1 ) {
								field.classList.remove( 'hidden' );
								checkbox.disabled = consented;
							}
						}
					} else if ( consent.enabled === 2 ) {
						checkbox.checked = consented;
					}
				}
			}
		}else {
			$( '.em-booking-form p.input-user-field' ).removeClass( 'hidden' );
			for ( const [ type, consent ] of Object.entries( EM.manual_booking.consents ) ) {
				if ( consent.enabled > 0 ) {
					consent.field.querySelector( 'input[type="checkbox"]' ).checked = false;
					consent.field.classList.remove( 'hidden' );
				}
			}
		}
	});
});