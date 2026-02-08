// handle check in/out buttons
document.querySelectorAll('.rsvp-action').forEach( function(btn){
	btn.addEventListener('click', function(e){
		let button = e.currentTarget;
		if ( button.classList.contains('selected') ) {
			return false;
		}
		document.querySelectorAll('.btn').forEach( el => el.classList.remove('selected') );
		let buttonData = Object.assign({}, button.dataset)
		button.classList.add('loading');

		return fetch( EM.rsvp.api_url, {
			method: "POST",
			headers : {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-WP-Nonce' : EM.api_nonce,
			},
			body: new URLSearchParams(buttonData).toString(),
		}).then( function( response ){
			button.classList.remove('loading');
			if( response.ok ) {
				return response.json();
			}
			return Promise.reject( response );
		}).then( function( data ){
			result( data.success, data.message );
		}).catch( function( err ){
			result( false, 'There was an error, see message console for more info.' );
			console.log('Error with RSVP API - %o', err);
		});
	});

	let result = function( success, message ){
		let result = document.getElementById('result');
		if( success ){
			result.querySelectorAll('.result-success').forEach( el => el.classList.remove('hidden') );
			result.querySelectorAll('.result-error').forEach( el => el.classList.add('hidden') );
			result.querySelectorAll('.btn').forEach( el => el.classList.add('btn-outline-success') );
		}else{
			result.querySelectorAll('.result-success').forEach( el => el.classList.add('hidden') );
			result.querySelectorAll('.result-error').forEach( el => el.classList.remove('hidden') );
			result.querySelectorAll('.btn').forEach( el => el.classList.add('btn-outline-secondary') );
		}
		document.getElementById('rsvp-result').innerHTML = message;
		document.getElementById('content').classList.add('hidden');
		result.classList.remove('hidden');
		result.firstElementChild.classList.add('show');
	}
});