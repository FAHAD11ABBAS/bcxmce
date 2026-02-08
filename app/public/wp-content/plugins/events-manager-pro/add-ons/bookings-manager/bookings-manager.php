<?php
if( is_admin() ){
	include('bookings-manager-admin.php');
}
if( get_option('dbem_bookings_manager') ){
	include('bookings-manager-frontend.php');
	if( get_option('dbem_bookings_qr') ) {
		include('qr.php');
	}
}