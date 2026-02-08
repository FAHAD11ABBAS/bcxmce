<?php
use EM\Waitlist\Bookings;
/**
 * Displays the booking form on a page where the waitlist booking object has been requested.
 */
?>
<div class="<?php em_template_classes('event-booking-form'); ?> input em-waitlist-booking-approved">
	<?php
	// show information letting user know of waiting status
	echo '<div class="em-notice">';
	echo Bookings::$booking->output( get_option('dbem_waitlists_text_booking_form') );
	echo '</div>';
	// add cancel button
	include(emp_locate_template('waitlists/button-cancel.php'));
	// output form
	echo Bookings::$booking->get_event()->output('#_BOOKINGFORM');
	?>
</div>
<?php