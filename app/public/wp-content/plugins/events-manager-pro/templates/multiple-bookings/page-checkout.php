<?php
/*
* WARNING -This is a recently added template (2013-01-30), and is likly to change as we fine-tune things over the coming weeks/months, if at all possible try to use our hooks or CSS/jQuery to acheive your customizations 
* This page displays a checkout page when 'Multiple Bookings Mode' is in effect.
* You can override the default display settings pages by copying this file to yourthemefolder/plugins/events-manager-pro/multiple-bookings/ and modifying it however you need.
* For more information, see http://wp-events-plugin.com/documentation/using-template-files/
*/
$EM_Multiple_Booking = EM_Multiple_Bookings::get_multiple_booking();
$id = 0;
?>
<div class="em-booking <?php em_template_classes('event-booking-form'); ?> input">
	<?php
	global $EM_Notices;
	echo $EM_Notices;
	if( empty($EM_Multiple_Booking->bookings) ){
		echo get_option('dbem_multiple_bookings_feedback_no_bookings');
	} else {
		do_action('em_checkout_form_before_summary', $EM_Multiple_Booking); //do not delete
		?>
		<div class="em-cart-table-contents">
			<?php emp_locate_template('multiple-bookings/cart-table.php',true); ?>
		</div>
		<?php do_action('em_checkout_form_after_summary', $EM_Multiple_Booking); //do not delete ?>
	
		<form class="em-booking-form" name='booking-form' method='post' action='<?php echo apply_filters('em_checkout_form_action_url',''); ?>#em-booking' id="em-booking-form-<?php echo $id; ?>" data-id="<?php echo $id; ?>">
			<input type='hidden' name='action' value='emp_checkout'/>
			<input type='hidden' name='_wpnonce' value='<?php echo wp_create_nonce('emp_checkout'); ?>'>
			<?php echo $EM_Multiple_Booking->output_intent_html(); ?>
			
			<?php do_action('em_checkout_form_before_registration_info', $EM_Multiple_Booking); // do not delete ?>
			<section class="em-booking-form-section-details" id="em-checkout-form-section-details">
				<?php if( get_option('dbem_bookings_header_reg_info') ): ?>
					<h3 class="em-booking-section-title em-booking-form-details-title"><?php echo get_option('dbem_bookings_header_reg_info'); ?></h3>
				<?php endif; ?>
				<div class="em-booking-form-details em-booking-section">
					<?php if( !is_user_logged_in() && get_option('dbem_bookings_login_form') ): ?>
						<div class="em-login-trigger">
							<?php echo sprintf(esc_html__('Do you already have an account with us? %s','events-manager'), '<a href="#">'. esc_html__('Sign In', 'events-manager') .'</a>'); ?>
						</div>
					<?php endif; ?>
					<?php
					do_action('em_checkout_form_before_user_details', $EM_Multiple_Booking); // do not delete
					echo EM_Booking_Form::get_form( false, $EM_Multiple_Booking );
					do_action('em_checkout_form_after_user_details', $EM_Multiple_Booking); // do not delete
					?>
				</div>
			</section>
			<?php do_action('em_checkout_form_after_registration_info', $EM_Multiple_Booking); // do not delete ?>
			
			<?php
			/*
			 * BOOKING CONFIRMATION/PAYMENT
			 */
			?>
			<?php do_action('em_checkout_form_before_confirm', $EM_Multiple_Booking); // do not delete	?>
			<section class="em-booking-form-section-confirm" id="em-checkout-form-section-confirm">
				<?php if( get_option('dbem_bookings_header_confirm') ): ?>
					<h3 class="em-booking-section-title em-booking-form-confirm-title em-booking-form-confirm-title-paid <?php if ( $EM_Multiple_Booking->get_spaces() == 0 || $EM_Multiple_Booking->get_price() == 0 ) echo 'hidden'; ?>"><?php echo esc_html( get_option('dbem_bookings_header_confirm') ); ?></h3>
				<?php endif; ?>
				<?php if( get_option('dbem_bookings_header_confirm_free') ): ?>
					<h3 class="em-booking-section-title em-booking-form-confirm-title em-booking-form-confirm-title-free <?php if ( $EM_Multiple_Booking->get_spaces() == 0 || $EM_Multiple_Booking->get_price() > 0 ) echo 'hidden'; ?>"><?php echo esc_html( get_option('dbem_bookings_header_confirm_free') ); ?></h3>
				<?php endif; ?>
				<?php do_action('em_checkout_form_confirm_header', $EM_Multiple_Booking); // do not delete ?>
				<?php if( has_action('em_checkout_form_confirm') ): ?>
					<div class="em-booking-form-confirm em-booking-section">
						<?php do_action('em_checkout_form_confirm', $EM_Multiple_Booking); // do not delete ?>
					</div>
				<?php endif; ?>
				<?php do_action('em_checkout_form_confirm_footer', $EM_Multiple_Booking); // do not delete ?>
				<?php
				if( apply_filters('em_checkout_form_show_button', true, $EM_Multiple_Booking ) ){
					emp_locate_template('multiple-bookings/page-checkout-button.php', true, array( 'EM_Multiple_Booking' => $EM_Multiple_Booking ));
				}
				?>
			</section>
			<?php do_action('em_checkout_form_after_confirm', $EM_Multiple_Booking); // do not delete ?>
			
			<?php do_action('em_checkout_form_footer', $EM_Multiple_Booking); //do not delete ?>
			<?php do_action('em_checkout_form_footer_after_buttons', $EM_Multiple_Booking); //do not delete ?>
		</form>
		<?php
	}
	?>
</div>