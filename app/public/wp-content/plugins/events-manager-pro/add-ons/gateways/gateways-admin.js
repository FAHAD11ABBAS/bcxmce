document.addEventListener('DOMContentLoaded', function(){
	document.querySelectorAll('.gateway-status-togggle').forEach( function(el) {
		el.addEventListener('click', function( e ){
			e.preventDefault();
			if( !el.getAttribute('data-text') ){
				el.setAttribute('data-text', el.innerHTML);
			}
			el.innerHTML = this.dataset.loading;
			fetch( EM.ajaxurl, {
				method: "POST",
				headers : {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: new URLSearchParams({ action:'em_toggle_gateway_mode', gateway : el.dataset.gateway, nonce : el.dataset.nonce }).toString(),
			}).then( function( response ){
				if( response.ok ) {
					return response.json();
				}
				return Promise.reject( response );
			}).then( function( data ){
				if ( data.success ) {
					if ( data.mode === 'live' ) {
						// we're live mode, hide test, limited and show live mode info
						document.querySelectorAll('.gateway-status-info-test, .gateway-status-info-limited').forEach( el => el.classList.add('hidden') );
						document.querySelectorAll('.gateway-status-info-live').forEach( el => el.classList.remove('hidden') );
					} else if ( data.mode === 'test' ) {
						// we're test mode, hide limited, live and show test mode info
						document.querySelectorAll('.gateway-status-info-limited, .gateway-status-info-live').forEach( el => el.classList.add('hidden') );
						document.querySelectorAll('.gateway-status-info-test').forEach( el => el.classList.remove('hidden') );
					} else if ( data.mode === 'limited' ) {
						// we're test mode, hide live, test and show limited mode info
						document.querySelectorAll('.gateway-status-info-test, .gateway-status-info-live').forEach( el => el.classList.add('hidden') );
						document.querySelectorAll('.gateway-status-info-limited').forEach( el => el.classList.remove('hidden') );
					}
					document.querySelectorAll('.gateway-mode').forEach( el => el.setAttribute('data-mode', data.mode) );
				} else {
					alert( data.message );
				}
			}).catch( function( err ){
				alert( 'There was an error, see message console for more info.' );
				console.log('Error with Gateway Toggle - %o', err);
			}).finally( function(){
				el.innerHTML = el.getAttribute('data-text');
			});
		});
	});
	document.querySelectorAll('a[data-tab]').forEach( function(el){
		el.addEventListener('click', function(){
			document.getElementById( 'em-menu-' + el.dataset.tab ).click();
		});
	});

});