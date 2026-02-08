<?php
namespace EM\Bookings\RSVP\Policies;
use EM\Bookings\RSVP\Policies;
use EM_Booking;

class Manager {
	
	public static function init() {
		//set up cron for clearing email queue
		if( !wp_next_scheduled('emp_cron_rsvp_policies') ){
			wp_schedule_event( time(),'em_minute','emp_cron_rsvp_policies');
		}
		add_action('emp_cron_minimum_capacity', array(static::class,'scan') );
		add_filter('em_event_load_postdata_other_attributes', array(static::class,'event_other_attributes') );
		add_filter('em_event_save_post_meta_other_attributes', array(static::class,'event_other_attributes') );
	}
	
	public static function scan(){
		global $wpdb;
		// get event ids that start x time from now
		$EM_DateTime = Policies::get_policy_deadline();
		$deadline_start = $EM_DateTime->getDateTime();
		$deadline_min = $EM_DateTime->copy()->sub('PT15M');
		$sql = "
			SELECT event_id AS id, meta_value AS started
			FROM ". EM_EVENTS_TABLE . "
			LEFT JOIN ". EM_META_TABLE . " ON object_id = event_id AND meta_key='rsvp_policy_started'
			WHERE event_start < '$deadline_start'
				AND event_start > '$deadline_min'
		";
		$events = $wpdb->get_results( $sql );
		foreach ( $events as $event ) {
			// check if event started, if so trigger what needs to be triggered
			if ( !$event->started ) {
				$wpdb->insert( EM_META_TABLE, array('meta_key' => 'rsvp_policy_started', 'meta_value' => 1, 'object_id' => $event->id ));
				// get the policy type to decide how to proceed
				if ( get_option('dbem_bookings_rsvp_policy_events') ) {
					$EM_Event = em_get_event($event->id);
					$policy = Policies::get_policy( $EM_Event );
					$policy_type = $policy->type;
				} else {
					$policy_type = get_option('dbem_bookings_rsvp_policy_type');
				}
				// if policy is strict, cancel all applicable bookings, otherwise trigger a fake booking to initiate other notification/booking features
				if( $policy_type === 'strict' ) {
					// load event for sure in this case
					if( empty($EM_Event) ){
						$EM_Event = em_get_event( $event->id );
					}
					// go through each booking
					foreach( Policies::get_invalid_booking_ids( $EM_Event ) as $booking_id ){
						$EM_Booking = em_get_booking($booking_id);
						$EM_Booking->cancel();
					}
				} elseif( $policy_type === 'flexible' ) {
					// trigger a booking status cancellation so things like waitlists can come into force
					$EM_Booking = new EM_Booking( array('event_id' => $event->id, 'booking_spaces' => 1, 'booking_status' => 3 ) );
					do_action('em_booking_deleted', $EM_Booking);
				}
			}
		}
	}
	
	/**
	 * Get waitlist meta loaded in the EM_Event->load_postdata() function
	 * @param array $array
	 * @return array
	 */
	public static function event_other_attributes( $array ){
		return array_merge($array, array('rsvp_policy_started', 'rsvp_policy_started'));
	}
}
Manager::init();