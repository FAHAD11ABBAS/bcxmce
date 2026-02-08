<?php
	if( !class_exists('\EM\Bookings\RSVP') ){
		if( is_admin() ){
			include('rsvp-admin.php');
		}
		if( get_option('dbem_bookings_rsvp') ){
			include('rsvp.php');
			include( 'rsvp-api.php' );
			if( get_option('dbem_bookings_rsvp_endpoint') ) {
				include( 'rsvp-endpoint.php' );
			}
			if( get_option('dbem_bookings_rsvp_policy') ) {
				include( 'rsvp-policies.php');
			}
		}
	}