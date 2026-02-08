<?php
use EM\Waitlist\Booking;
/* @var $EM_Event EM_Event */
?>
<div class="<?php em_template_classes('event-booking-form'); ?> input">
	<div class="em-booking-message">
		<?php
		echo $EM_Event->output( get_option('dbem_waitlists_text_form') );
		?>
	</div>
	<?php if( !is_user_logged_in() ) include( em_locate_template('forms/bookingform/login.php') ); ?>
	<form class="em-waitlist-form" name='waitlist-form' method='post' action='<?php echo apply_filters('em_booking_form_action_url',''); ?>#em-booking'>
		<input type='hidden' name='action' value='waitlist_booking'>
		<input type='hidden' name='event_id' value='<?php echo $EM_Event->get_bookings()->event_id; ?>'>
		<input type='hidden' name='_wpnonce' value='<?php echo wp_create_nonce('waitlist_booking'); ?>'>
		<input type='hidden' name='intent' value='<?php echo wp_create_nonce('waitlist_booking'); ?>'>
		<?php if( !is_user_logged_in() ): ?>
		<p>
			<label><?php esc_html_e_emp('Email'); ?></label>
			<input type='email' name='user_email' placeholder="info@example.com" value='' />
		</p>
		<p>
			<label><?php esc_html_e_emp('Name'); ?></label>
			<input type='text' name='user_name' placeholder="<?php esc_html_e('Enter your full name', 'em-pro') ?>" value='' />
		</p>
		<?php endif; ?>
		<p>
			<label><?php esc_html_e_emp('Spaces'); ?></label>
			<select name="waitlist_spaces">
				<option>1</option>
				<?php
					$allowed_spaces = Booking::get_max_spaces( $EM_Event );
					for( $i = 2; $i <= $allowed_spaces; $i++ ){
						echo "<option>$i</option>";
					}
				?>
			</select>
		</p>
		<input type="submit" class="em-booking-submit button-secondary" id="em-booking-submit" value="<?php echo esc_attr(get_option('dbem_waitlists_submit_button')); ?>" />
	</form>
</div>