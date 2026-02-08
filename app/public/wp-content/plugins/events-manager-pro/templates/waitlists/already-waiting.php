<?php
// since we're displaying only a message, we'll pass it to the cancel button template and let it display there.
$EM_Booking = \EM\Waitlist\Bookings::$booking;
$message = get_option('dbem_waitlists_text_already_waiting');
include(emp_locate_template('waitlists/button-cancel.php'));
?>