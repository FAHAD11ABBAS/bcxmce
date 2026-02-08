<?php
namespace EM\Waitlist;
$EM_Booking = Bookings::$booking;
// provide the template
if( $EM_Booking->booking_status == 6 ){
	include( emp_locate_template('waitlists/already-waiting.php') );
}elseif($EM_Booking->booking_status == 7 ){
	// disable flags so bookings can be done
	Bookings::display_booking_form();
}elseif($EM_Booking->booking_status == 3 ){
	include( emp_locate_template('waitlists/cancelled.php') );
}elseif($EM_Booking->booking_status == 8 ){
	include( emp_locate_template('waitlists/expired.php') );
}
echo "<script>";
include( emp_locate_template('waitlists/waitlists.js') );
echo "</script>";