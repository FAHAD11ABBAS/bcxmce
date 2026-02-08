<?php
if( is_admin() ){
	include('manual-bookings-admin.php');
}
if( get_option('dbem_bookings_manual', true) ){
	include('manual-bookings.php');
}

/**
 * Shortcut that double-checks manual bookings are enabled as well and passes it onto the class.
 *
 * @param $new_registration
 * @see EM\Manual_Transactions\Bookings::is_manual_booking()
 * @uses EM\Manual_Transactions\Bookings::is_manual_booking()
 * @return bool
 */
function emp_is_manual_booking( $new_registration = false ){
	if( get_option('dbem_bookings_manual', true) ){
		return EM\Manual_Transactions\Bookings::is_manual_booking( $new_registration );
	}
	return false;
}