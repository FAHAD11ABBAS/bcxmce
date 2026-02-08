<p>
	<?php esc_html_e('You are currently on a waiting list and are now elegible to book the following event:', 'em-pro'); ?>
	
</p>
<p>
	<strong><?php esc_html_e('Waitlist Reservation Details:', 'em-pro'); ?></strong>

</p>
<p>
	<strong><?php esc_html_e_emp('Event') ?> :</strong> #_EVENTNAME<br>
	<strong><?php esc_html_e_emp('Date/Time'); ?> :</strong> #_EVENTDATES @ #_EVENTTIMES<br>
	<strong><?php esc_html_e('Reserved Spaces', 'em-pro'); ?> :</strong> #_BOOKINGSPACES
</p>
{has_waitlist_expiry}
<p>
	<?php echo sprintf( esc_html_x('Your requested spaces will be reserved for %s.', 'A specified amount of time like x hours, y minutes.', 'em-pro'), '#_WAITLIST_BOOKING_EXPIRY' ); ?>
	
</p>
{/has_waitlist_expiry}
<p>
	<?php esc_html_e('If you do not want to attend this event anymore, please cancel your reservation so others can have an opportunity to attend this event.', 'em-pro'); ?>
	<?php esc_html_e('You can also cancel your booking by clicking the button below.', 'em-pro'); ?>
	
</p>