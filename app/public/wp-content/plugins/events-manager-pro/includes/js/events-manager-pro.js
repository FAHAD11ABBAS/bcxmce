jQuery(document).ready(function($){
	//google maps
	$('.em-location-map-static.em-map-static-relative').each(function(){
		var e = $(this);
		var parent = e.closest('.em-location-map-container').css('background','');
		var url = e.data('gmap-url').replace('SIZE', parent.width() + 'x' + parent.height());
		var html = '<img src="'+ url +'" alt="'+ e.data('gmap-title')+'" class="em-location-static-map">';
		e.empty().append(jQuery(html));
	});
	$(document).on('click', '.em-location-map-container.em-map-static-load', function(){
		var e = $(this)
		if( e.data('gmap-embed') ){
			e.removeClass('em-map-static-embed').empty();
			e.append('<iframe style="width:100%; height:100%;" frameborder="0" style="border:0" src="'+ e.data('gmap-embed') +'" allowfullscreen></iframe>');
		}else{
			var map = e.removeClass('em-map-static-load').find('.em-location-map-static');
			map.addClass('em-location-map').removeClass('em-location-map-static').siblings('.em-map-overlay').remove();
			em_maps_loaded ? em_maps_load_location( map ) : em_maps_load();
		}
	});
	//offline approval link - listen on body to take precedence of default listener
	$('body').on('click', '.em-bookings-approve-5', function(e){
		if( !confirm(EM.offline_confirm) ){
			e.stopPropagation();
			e.stopImmediatePropagation();
			e.preventDefault();
			return false;
		}
	});
	//form tooltips
	var tooltip_vars = { theme : 'light-border', placement : 'right', allowHTML: true };
	$(document).trigger('emp-tippy-vars',[tooltip_vars]);
	tippy('form.em-booking-form span.form-tip, form#em-booking-form span.form-tip', tooltip_vars);
});