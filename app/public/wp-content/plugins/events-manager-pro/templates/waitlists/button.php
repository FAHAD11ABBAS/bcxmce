<?php
/**
 * @var EM_Event $EM_Event
 */
?>
<form class="em-ajax-form no-overlay-spinner no-inline-notice <?php em_template_classes('event-booking-form'); ?> input" style="display: inline-block; width: auto;" method='post' action=''>
	<input type='hidden' name='action' value='waitlist_booking'/>
	<input type='hidden' name='event_id' value='<?php echo $EM_Event->get_bookings()->event_id; ?>'/>
	<input type='hidden' name='_wpnonce' value='<?php echo wp_create_nonce('waitlist_booking'); ?>'/>
	<input type='hidden' name='waitlist_spaces' value='1'/>
	<button type="submit" class="button-secondary">
		<span class="em-icon em-icon-spinner loading-content"></span>
		<span class="loading-content"><?php esc_html_e('Joining', 'em-pro'); ?></span>
		<span class="loaded"><?php echo esc_attr(get_option('dbem_waitlists_submit_button')); ?></span>
		<span class="loaded-success"><?php esc_html_e('Waitlist Joined!', 'em-pro'); ?></span>
	</button>
</form>