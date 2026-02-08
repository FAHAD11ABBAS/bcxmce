document.querySelectorAll('form.em-waitlist-booking-cancel').forEach( function(form){
    form.addEventListener('em_ajax_form_success_waitlist_cancel', function(e){
	    em_waitlist_success_cleanup( form );
    });
});

document.addEventListener("em_booking_form_init", function( e ) {
	let booking_form = e.target;
	booking_form.addEventListener('em_booking_success', function (e) {
		em_waitlist_success_cleanup( booking_form );
	});
});

function em_waitlist_success_cleanup( booking_form ){
    let wrapper = booking_form.closest('.em-waitlist-booking-approved');
    let parent = booking_form.closest('div');
    if( wrapper !== null && wrapper !== parent ){
        let others = wrapper.querySelectorAll(':scope > div');
        if( others !== null ){
            others.forEach( function( el ){
				if( el !== parent ) {
					el.remove();
				}
			});
        }
    }
}

document.querySelectorAll('form.em-waitlist-form').forEach( function( waitlist_form ){
	waitlist_form.addEventListener("submit", function( e ){
		e.preventDefault();
		em_booking_form_submit( e.target );
	});
});