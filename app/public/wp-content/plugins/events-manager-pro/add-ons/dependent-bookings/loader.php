<?php
if( get_option('dbem_bookings_dependent_events') ) {
	include( 'dependent-bookings.php' );
}
if( is_admin() ){
	include('dependent-bookings-admin.php');
}