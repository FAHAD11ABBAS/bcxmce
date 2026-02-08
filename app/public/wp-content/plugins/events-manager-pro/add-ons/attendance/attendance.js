// handle check in/out buttons
document.querySelectorAll('.attendance-action').forEach( function(btn){
	btn.addEventListener('click', function(e){
		let button = e.currentTarget;
		let buttonData = {
			action : button.getAttribute('data-action'),
		};
		if( button.hasAttribute('data-uuid') ){
			buttonData.uuid = button.getAttribute('data-uuid');
		}else{
			buttonData.id = button.getAttribute('data-id');
		}
		button.classList.add('loading');

		var request = new XMLHttpRequest();
		request.open('POST', EM.attendance_api_url, true);
		request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		request.setRequestHeader('X-WP-Nonce', EM.api_nonce);

		request.onload = function() {
			button.classList.remove('loading');
			if (this.status >= 200 && this.status < 400) {
				// Success!
				try {
					var data = JSON.parse(this.response);
					if( data.result ){
						let attendanceStatus;
						if( data.status === 1 ) attendanceStatus = '1';
						if( data.status === 0 ) attendanceStatus = '0';
						if( data.status === null ) attendanceStatus = 'null';
						let selectString = '.em-ticket-booking-'+ data.id +' [class*="attendance-status-"], .em-ticket-booking-'+ data.uuid +' [class*="attendance-status-"]';
						document.querySelectorAll(selectString).forEach( function( statusItem ){
							if( statusItem.classList.contains('attendance-status-'+attendanceStatus) ){
								statusItem.classList.remove('hidden');
							}else{
								statusItem.classList.add('hidden');
							}
						})
					}else{
						alert( data.message );
					}
				} catch(e) {
					alert( 'Error Encountered : ' + e);
				}
			} else {
				alert('Error encountered... please see debug logs or contact support.');
			}
		};

		request.onerror = function() {
			alert('Connection error encountered... please see debug logs or contact support.');
		};

		let body = new URLSearchParams(buttonData).toString();
		request.send( body );
	});
});

// deal with showing history in admin interface
jQuery(document).ready( function($){
    $('.em-booking-single-info .attendance-history-toggle').on('click', function(){
        el = $(this);
        if( this.classList.contains('display') ){
            this.classList.remove('display');
            el.closest('.em-booking-single-info').find('.attendance-history').slideUp();
        }else{
            this.classList.add('display');
            el.closest('.em-booking-single-info').find('.attendance-history').slideDown();
        }
    });
});