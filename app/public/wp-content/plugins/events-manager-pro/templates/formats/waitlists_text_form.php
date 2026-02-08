<p>
	<?php esc_html_e('This event is fully booked. You can join a waitlist and if an elegible ticket becomes available, you will be notified by email to make a booking.', 'em-pro'); ?>
	
</p>
{has_waitlist_limit}
<p>
	<?php echo sprintf(esc_html__('There are %s spaces left on the waitlist.', 'em-pro'), '#_WAITLIST_AVAILABLE'); ?>

</p>
{/has_waitlist_limit}
{has_waitlist_booking_limit}
<p>
	<?php echo sprintf(esc_html__('You can book up to %s spaces on this waitlist.', 'em-pro'), '#_WAITLIST_BOOKING_LIMIT'); ?>

</p>
{/has_waitlist_booking_limit}
{has_waitlist_expiry}
<p>
	<?php esc_html_e('Please remember that you have #_WAITLIST_EXPIRY hours to book reserved spaces once they become available, you will be notified immediately when an elegible ticket becomes available.', 'em-pro'); ?>

</p>
{/has_waitlist_expiry}