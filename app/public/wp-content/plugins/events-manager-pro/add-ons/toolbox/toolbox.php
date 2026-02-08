<?php

/**
 * This add-on adds powerful features to EM, such as extending cancellation options.
 */
include('cancellation.php');

// Event Submission Limits
if( get_option('dbem_event_submission_limits_enabled') ){
	include('limits.php');
}
// Past Events
if( get_option('dbem_past_events') !== 'published' ) {
	include('past-events/past-events.php');
}

if( get_option('dbem_minimum_capacity') ) {
	include('minimum-capacity/minimum-capacity.php');
}

// Admin Settings
if( is_admin() ){
	include('limits-admin.php');
	include('past-events/past-events-admin.php');
	include('minimum-capacity/minimum-capacity-admin.php');
}

include('move-booking.php');