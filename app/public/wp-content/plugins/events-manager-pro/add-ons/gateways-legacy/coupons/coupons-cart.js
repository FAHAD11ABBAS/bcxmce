jQuery(document).ready( function($){
	$(document).on('submit', '.em-cart-coupons-form', function(e){
		e.preventDefault();
		var coupon_form = $(this);
		var coupon_input = coupon_form.find('input.em-coupon-code');
		var coupon_button = coupon_form.find('button');
		$.ajax({
			url: EM.ajaxurl,
			data: coupon_form.serializeArray(),
			dataType: 'jsonp',
			type:'post',
			beforeSend: function(formData, jqForm, options) {
				$('.em-coupon-message').remove();
				if( coupon_input.val() == ''){ return false; }
		        coupon_button.addClass('loading');
			},
			success : function(response, statusText, xhr, $form) {
				if(response.result){
					$(document).trigger('em_checkout_page_refresh');
					$(document).trigger('em_cart_page_refresh');
				}else{
					coupon_form.prepend('<span class="em-coupon-message em-coupon-error"><span class="em-icon em-icon-cross-circle"></span> '+response.message+'</span>');
				}
			},
			complete : function() {
		        coupon_button.removeClass('loading');
			}
		});
	});
});