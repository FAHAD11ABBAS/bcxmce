<?php
if( is_admin() ){
	include('waitlists-admin.php');
}
if( get_option('dbem_waitlists') ){
	include('em-waitlist-booking.php');
	include('waitlists-bookings.php');
	include('waitlists-events.php');
	include('waitlists-tickets.php');
	include('waitlists-manager.php');
	if( is_admin() ){
		include('waitlists-bookings-admin.php');
	}
}