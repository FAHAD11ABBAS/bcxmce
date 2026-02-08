//Select Submission
document.addEventListener("em_booking_form_init", function( e ) {
	let booking_form = e.target;
	let selected_gateway;
	let gateway_selectors = '.em-payment-gateways select.em-payment-gateway-options, .em-payment-gateways input[type="radio"].em-payment-gateway-option, input[type="hidden"].em-payment-gateway-option';
	let gateway_selectors_selected = '.em-payment-gateways select.em-payment-gateway-options option:checked, .em-payment-gateways input[type="radio"].em-payment-gateway-option:checked, input[type="hidden"].em-payment-gateway-option';
	// get currently selected gateway
	let selected_gateway_el = booking_form.querySelector( gateway_selectors_selected );
	if( selected_gateway_el ){
		selected_gateway = selected_gateway_el.value;
	}

	//Button Submission
	booking_form.querySelectorAll('input.em-gateway-button').forEach( function( button ){
		button.addEventListener("click", function( e ){
			//prevents submission in order to append a hidden field and bind to the booking form submission event
			e.preventDefault();
			//get gateway name
			let gateway = e.target.id.replace('em-gateway-button-', '');
			booking_form.querySelectorAll('input[name=gateway]').forEach( input => input.remove() );
			let input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'gateway';
			input.value = gateway;
			booking_form.append(input);
			booking_form.requestSubmit( button );
			return false;
		});
	});

	// Take over booking submission
	booking_form.addEventListener("submit", async function( e ){
		let booking_intent = booking_form.querySelector('input.em-booking-intent');
		let gateway = booking_form.querySelector('.em-payment-gateway-option:checked, input[type="hidden"].em-payment-gateway-option');
		if ( booking_intent && gateway ) {
			if ( booking_intent.dataset.amount > 0 && gateway.getAttribute('data-intercept') ) {
				// we have a paid booking with gateway selection, short-circuit process here entirely
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				let gateways = booking_form.querySelector('.em-payment-gateways');
				let validation = {
					success : true, // change to false and populate errors
					errors : {}, // errors to be displayed, with field id as key
					promises : [],
				};
				// run pre-validation, such as card number discrepencies. If errors added, abort here
				booking_form.dispatchEvent( new CustomEvent( 'em_gateway_payment_validate_' + selected_gateway, {
					detail : validation,
					bubbles : true,
					cancellable : true,
				}) );
				// execute any promises syncronously
				if ( validation.promises.length > 0 ) {
					await Promise.all(result.promises);
				}
				// continue if validation worked
				if ( validation.success ) {
					// get options to prevent handling successful booking before gateways intervene
					let options = {
						doFinally : false,
						doSuccess : false,
					};
					// submit booking, if all good, trigger gateway hook
					em_booking_form_submit( booking_form, options ).then( async function( result ){
						if( result.success ){
							// create and dispatch custom event
							let data = {
								result : result,
								promises: [],
								gateways : gateways,
							}
							let event = new CustomEvent( 'em_gateway_payment_' + selected_gateway, {
								detail: data,
								bubbles : true,
								cancellable : true,
							});
							booking_form.dispatchEvent( event );
							// execute any promises syncronously
							if( data.promises.length > 0 ) {
								await Promise.all(data.promises);
							}
							// check passed object for success or fail errors and handle accordingly
							em_booking_form_submit_success( booking_form, result, options );
						} else {
							// let EM handle errors etc.
							em_booking_form_submit_success( booking_form, result );
						}
					}).catch( function( error ){
						// do nothing
						console.log( error )
					}).finally( function(){
						em_booking_form_submit_finally( booking_form );
					});
				} else if( Object.keys( validation.errors ).length > 0 ) {
					em_booking_form_add_error( booking_form, validation.errors );
					em_booking_form_hide_spinner( booking_form );
				}
			}
		}
	});

	// trigger gateway event when selected
	booking_form.addEventListener("change", function( e ){
		if ( e.target.matches(gateway_selectors) ){
			let gateway;
			if( e.target instanceof HTMLSelectElement ) {
				gateway = e.target.options[ e.target.selectedIndex ];
			} else {
				gateway = e.target;
			}
			selected_gateway = gateway.value;
			selected_gateway_el = gateway;
			let gateways = gateway.closest('.em-payment-gateways');
			if( gateways ){
				// hide regular button if necessary
				if( gateway.getAttribute('data-custom-button') === "1" || gateway.getAttribute('data-custom-button') === 1 ){
					em_booking_form_disable_button( booking_form, true );
				}else{
					em_booking_form_enable_button( booking_form, true );
				}
				// remove lazy load when loaded
				booking_form.addEventListener( 'em_gateway_loaded_' + selected_gateway, function(){
					gateways.querySelectorAll('.em-payment-gateway-form-' + selected_gateway + ' .em-payment-gateway-form-loading').forEach( loader => loader.classList.add('hidden') );
					gateways.querySelectorAll('.em-payment-gateway-form-' + selected_gateway + ' .em-payment-gateway-form-data').forEach( loader => loader.classList.remove('hidden') );
				}, {once: true});
				// trigger event for loading
				let event = new CustomEvent( 'em_gateway_selected_' + selected_gateway, {
					detail: {
						gateway : gateway,
						booking_form : booking_form,
					},
					bubbles : true,
					cancellable : true,
				});
				booking_form.dispatchEvent( event );
				// show section
				gateways.querySelectorAll('.em-payment-gateway-form').forEach( button => button.classList.add('hidden') );
				gateways.querySelectorAll('.em-payment-gateway-form-' + selected_gateway).forEach( button => button.classList.remove('hidden') );
				// mark as initialized
				selected_gateway_el.setAttribute('data-initialized', '1');
			}
		}
	});

	// remove lazy load when loaded
	booking_form.addEventListener( 'em_gateway_loaded', function( e ){
		let gateway = e.detail.gateway;
		booking_form.querySelectorAll('.em-payment-gateway-form-' + gateway + ' .em-payment-gateway-form-loading').forEach( loader => loader.classList.add('hidden') );
		booking_form.querySelectorAll('.em-payment-gateway-form-' + gateway + ' .em-payment-gateway-form-data').forEach( loader => loader.classList.remove('hidden') );
	});

	// catch price change detection, showing gateway selection if free
	booking_form.addEventListener("em_booking_intent_updated", function( e ){
		let intent = e.detail.intent;
		if ( intent && intent.spaces > 0 ) {
			// there is an intent
			if ( intent.amount > 0 ) {
				booking_form.querySelectorAll('.em-payment-gateways').forEach( gateways => gateways.classList.remove('hidden') );
				booking_form.querySelectorAll('div.em-gateway-buttons').forEach( buttons => buttons.classList.remove('hidden') );
				// dispath events for selected gateway
				let detail = {
					form : booking_form,
					gateway : selected_gateway,
					intent : intent,
					type : 'booking',
				};
				// fire general event
				let general_event = new CustomEvent( 'em_gateway_intent_updated', {
					detail: detail,
					bubbles : true,
					cancellable : true,
				});
				// hide and disable regular button if necessary
				if( selected_gateway_el ) {
					if (selected_gateway_el.getAttribute('data-custom-button') === "1" || selected_gateway_el.getAttribute('data-custom-button') === 1) {
						em_booking_form_disable_button( booking_form, true );
					} else {
						em_booking_form_enable_button( booking_form, true );
					}
					booking_form.dispatchEvent( general_event );
					let event = new CustomEvent( 'em_gateway_intent_updated_' + selected_gateway, {
						detail: detail,
						bubbles : true,
						cancellable : true,
					});
					booking_form.dispatchEvent( event );
					// make sure gateway is initialized first time
					if ( selected_gateway_el.getAttribute('data-initialized') !== '1' ) {
						booking_form.querySelector(gateway_selectors).dispatchEvent( new Event('change', { bubbles: true, cancelable: true }) );
					}
				}
			} else {
				// it's a free booking
				booking_form.querySelectorAll('.em-payment-gateways').forEach( gateways => gateways.classList.add('hidden') );
				booking_form.querySelectorAll('div.em-gateway-buttons').forEach( buttons => buttons.classList.add('hidden') );
				em_booking_form_enable_button( booking_form, true );
			}
		}
	});

});