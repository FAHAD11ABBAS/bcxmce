<?php
if( is_admin() ){
	include('attendance-admin.php');
}
if( get_option('dbem_bookings_attendance') ){
	include('attendance.php');
	include('attendance-api.php');
	include('attendance-booking-admin.php');
}