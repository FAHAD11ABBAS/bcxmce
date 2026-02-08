const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

// monitor the checked cookie
  let auto = Cookies.get('em_manual_checkin');
  let checkInToggle = document.getElementById('auto-checkin');
  if( checkInToggle ){
	checkInToggle.checked = auto != 1;
	checkInToggle.addEventListener('change', function(e){
		let checked = !e.target.checked ? 1:0;
		Cookies.set('em_manual_checkin', checked);
	});
}