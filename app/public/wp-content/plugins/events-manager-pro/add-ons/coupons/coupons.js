document.addEventListener("em_booking_form_init", function( e ) {
	let booking_form = e.target;
	booking_form.addEventListener("em_booking_intent_updated", function( e ) {
		let intent = e.detail.intent;
		if ( booking_form.closest('.em-manual-booking') && 'amountOrig' in intent && intent.amountOrig > 0 ) return;
		if ( intent.amount > 0 && intent.spaces > 0 ) {
			booking_form.querySelectorAll('.em-booking-form-section-coupons').forEach( coupons => coupons.classList.remove('hidden') );
		} else {
			booking_form.querySelectorAll('.em-booking-form-section-coupons').forEach( coupons => coupons.classList.add('hidden') );
		}
	});

	booking_form.addEventListener('em_booking_form_hide_success', function( e ){
		booking_form.querySelectorAll('.em-booking-form-section-coupons').forEach( coupons => coupons.classList.add('hidden') );
	});

	booking_form.addEventListener('em_booking_form_unhide_success', function( e ){
		booking_form.querySelectorAll('.em-booking-form-section-coupons').forEach( coupons => coupons.classList.remove('hidden') );
	});

	booking_form.querySelectorAll('.em-coupon-code-button').forEach( function( el ){
		el.addEventListener('click', async function( e ){
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			let coupon_button = this;
			let coupon_input_wrap = this.previousElementSibling;
			let coupon_el = this.previousElementSibling.querySelector('input');
			let em_booking_form = coupon_el.closest('.em-booking-form');

			if ( coupon_button.getAttribute('data-remove') === '1' ){
				coupon_el.value = '';
				coupon_button.innerText = coupon_button.getAttribute('data-text-apply');
				coupon_button.setAttribute('data-remove', 0);
				coupon_el.readOnly = false;
				coupon_el.classList.remove('hidden');
				em_booking_form.querySelectorAll('.em-coupon-message').forEach( message => message.remove() );
				booking_form.dispatchEvent( new CustomEvent('em_booking_form_updated') );
			} else {
				let formdata = new FormData(em_booking_form);
				formdata.set('action','em_coupon_check'); //simple way to change action of form

				em_booking_form.querySelectorAll('.em-coupon-message').forEach( message => message.remove() );
				if( coupon_el.value == '' ){ return false; }
				coupon_el.classList.add('loading');

				await fetch( EM.ajaxurl, {
					method: "POST",
					body: formdata,
				}).then( function( response ){
					return response.json();
				}).then( function( response ){
					let message = document.createElement('span');
					if( response.result ){
						message.innerHTML = '<span class="em-coupon-message em-coupon-success"><span class="em-icon"></span> '+response.message+'</span>';
						coupon_input_wrap.prepend( message.firstElementChild );
						coupon_el.readOnly = true;
						coupon_button.innerText = coupon_button.getAttribute('data-text-remove');
						coupon_button.setAttribute('data-remove', 1);
						booking_form.dispatchEvent( new CustomEvent('em_booking_form_updated') );
					}else{
						message.innerHTML = '<span class="em-coupon-message em-coupon-error"><span class="em-icon"></span> '+response.message+'</span>';
						coupon_button.parentNode.insertBefore( message.firstElementChild, coupon_button.nextSibling );
						coupon_el.readOnly = false;
						coupon_button.innerText = coupon_button.getAttribute('data-text-apply');
						coupon_button.setAttribute('data-remove', 0);
					}
				}).finally( function(){
					coupon_el.classList.remove('loading');
				});
			}
		});
	});

	booking_form.querySelectorAll('.em-coupon-code').forEach( function( el ){
		el.addEventListener('keypress', function( e ) {
			if( e.which === 13 ) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				this.parentNode.nextElementSibling.click();
			}
		});
	});
} );