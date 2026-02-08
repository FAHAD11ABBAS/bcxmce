<?php
namespace EM\Waitlist;

/**
 * Manages waitlists and waitlist booking capacities, cleans things up, crons etc.
 */
class Manager {
	
	public static $disable_triggers = false;
	
	public static function init(){
		// deal with status changes of bookings that can open up new spaces for waitlist bookings
		add_filter('em_booking_set_status', '\EM\Waitlist\Manager::em_booking_set_status', 10, 2);
		add_filter('em_booking_save', '\EM\Waitlist\Manager::em_booking_save', 10, 2);
		add_action('em_booking_deleted', '\EM\Waitlist\Manager::em_booking_deleted', 10, 1);
		add_action('em_bookings_deleted', '\EM\Waitlist\Manager::em_bookings_deleted', 10, 3);
		add_filter('em_event_save', '\EM\Waitlist\Manager::em_event_save', 10, 2);
		// manage expired bookings - set up cron for clearing email queue
		if( !wp_next_scheduled('emp_cron_waitlist_check_expired') ){
			wp_schedule_event( time(),'em_minute', 'emp_cron_waitlist_check_expired');
		}
		add_action('emp_cron_waitlist_check_expired', '\EM\Waitlist\Manager::check_expired');
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_save( $result, $EM_Event ){
		static::check_waitlist( $EM_Event );
		return $result;
	}
	
	/**
	 * Checks if a space has freed up due to booking update, also associates and cleans up waitlists that have ben changed into a real booking.
	 * @param bool $result
	 * @param \EM_Booking $EM_Booking
	 * @return bool
	 */
	public static function em_booking_save( $result, $EM_Booking ){
		// trigger a check because booking may have changed number of spaces during an edit
		if( !static::$disable_triggers ){
			static::check_waitlist( $EM_Booking->get_event() );
		}
		return $result;
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Booking $EM_Booking
	 * @return bool
	 */
	public static function em_booking_set_status( $result, $EM_Booking ){
		// we can ignore confirmed and waitlist-approved bookings since they only take away availability
		if( !in_array($EM_Booking->booking_status, array(1,7)) && !static::$disable_triggers ){
			static::check_waitlist( $EM_Booking->get_event() );
		}
		return $result;
	}
	
	public static function em_booking_deleted( $EM_Booking ){
		// we can ignore confirmed and waitlist-approved bookings since they only take away availability
		if( !static::$disable_triggers ){
			static::check_waitlist( $EM_Booking->get_event() );
		}
		remove_action('em_bookings_deleted', '\EM\Waitlist\Manager::em_bookings_deleted', 10);
	}
	
	public static function em_bookings_deleted( $result, $booking_ids, $event_ids ){
		// we can ignore confirmed and waitlist-approved bookings since they only take away availability
		foreach( $event_ids as $event_id ){
			$EM_Event = em_get_event($event_id);
			if( !static::$disable_triggers ){
				static::check_waitlist( $EM_Event );
			}
		}
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 */
	public static function check_waitlist($EM_Event ){
		global $wpdb;
		Bookings::$ignore_waitlisted = true;
		// check that we have any available spaces - force a refresh
		$spaces = $EM_Event->get_bookings()->get_available_spaces(true);
		if( $spaces > 0 ){
			$candidates = array();
			// check if we have any waitlisted people
			$sql = 'SELECT SUM(booking_spaces) FROM '.EM_BOOKINGS_TABLE. ' WHERE booking_status=6 AND event_id=%d ORDER BY booking_date ASC LIMIT 10';
			$waitlist_count = $wpdb->get_var( $wpdb->prepare($sql, $EM_Event->event_id) );
			if( $waitlist_count ){
				// prep guest ticket types to check here and avoid potentially unecessary repetitions
				$ticket_types = array('guest' => array(), 'member' => array());
				foreach( $EM_Event->get_bookings()->get_tickets() as $EM_Ticket ){
					if( get_option('dbem_waitlists_events') && get_option('dbem_waitlists_events_tickets') && !empty($EM_Ticket->ticket_meta['waitlist_excluded']) ){
						continue;
					}
					// check if ticket is available without member restrictions, then split for guest/member specific tickets
					if( $EM_Ticket->is_available(true, true) ){
						if( $EM_Ticket->is_available_to(true) ){
							$ticket_types['member'][] = $EM_Ticket;
						}
						if ($EM_Ticket->is_available_to(false) ){
							$ticket_types['guest'][] = $EM_Ticket;
						}
					}
				}
				if( !empty($ticket_types['member']) || !empty($ticket_types['guest']) ){
					// go through waitlist, first come first served
					$limit = 20;
					$sql = 'SELECT * FROM '.EM_BOOKINGS_TABLE. ' WHERE booking_status=6 AND event_id=%d ORDER BY booking_date ASC LIMIT '. $limit;
					$waitlist = $wpdb->get_results( $wpdb->prepare($sql, $EM_Event->event_id), ARRAY_A );
					for( $offset = 0; !empty($waitlist); $offset += $limit ){
						foreach( $waitlist as $waitee ){
							// check if user is guest or member and if they have any applicable tickets
							if( $waitee['person_id'] == 0 ){
								// get user info and check all ticket types they could book
								if( !empty($ticket_types['guest']) ){
									// this person is next!
									if( $waitee['booking_spaces'] <= $spaces ) {
										$candidates[] = $waitee;
										$spaces -= $waitee['booking_spaces'];
									}else{
										// next waitee needs more spaces so we put it on hold for them until spaces free up
										$candidate_blocker = true;
										break;
									}
								}
							}else{
								// check for guest/unrestricted tickets they could beok
								foreach( $ticket_types['member'] as $EM_Ticket ){
									if( $EM_Ticket->is_available_to($waitee['person_id']) ){
										// this person is next!
										if( $waitee['booking_spaces'] <= $spaces ) {
											$candidates[] = $waitee;
											$spaces -= $waitee['booking_spaces'];
											break;
										}else{
											// next waitee needs more spaces so we put it on hold for them until spaces free up
											$candidate_blocker = true;
											break;
										}
									}
								}
								if( !empty($candidate_blocker) ) break;
							}
							if( $spaces == 0 ){
								break;
							}
						}
						// keep going until we find a candidate or run out of waitees
						$waitlist = false;
						if( empty($candidate_blocker) && $spaces > 0 ){
							$waitlist = $wpdb->get_results( $wpdb->prepare($sql . " OFFSET $offset", $EM_Event->event_id), ARRAY_A );
						}
					}
					// if we have a candidate at this point, approve their waitlist
					if( !empty($candidates) ) {
						static::$disable_triggers = true;
						foreach( $candidates as $candidate ){
							$EM_Booking = new Booking($candidate);
							// set booking expiry before setting status
							$expiry = Events::get_var('expiry', $EM_Event);
							if( $expiry > 0 ){
								$EM_Booking->update_meta('waitlist_expiry', time() + ($expiry * 3600));
							}
							// approve waitlist booking
							$EM_Booking->set_status(7);
						}
						static::$disable_triggers = false;
					}
				}
			}
		}
		Bookings::$ignore_waitlisted = false;
	}
	
	public static function check_expired(){
		global $wpdb;
		// get any expired bookings
		$booking_ids = $wpdb->get_col('SELECT booking_id FROM '. EM_BOOKINGS_META_TABLE ." WHERE meta_key='waitlist_expiry' AND CAST(meta_value as UNSIGNED) < ".time());
		if( !empty($booking_ids) ){
			// disable trigger check
			static::$disable_triggers = true;
			$event_ids = array();
			foreach( $booking_ids as $booking_id ){
				// create an object and set status so email is triggered
				$EM_Booking = new Booking($booking_id); // in theory this should always and only be a waitlist booking
				if( $EM_Booking->booking_status == 7 && $EM_Booking->booking_meta['waitlist_expiry'] < time() ){ // quick check just in case!
					$EM_Booking->manage_override = true;
					$EM_Booking->set_status(8, true, true);
					if( empty($event_ids[$EM_Booking->event_id]) ) {
						$event_ids[$EM_Booking->event_id] = $EM_Booking->get_event();
					}
				}
				// let's clean this up regardless and remove the expiry record, it doesn't belong in the booking, change it to past tense so we have a record of when it expired
				$EM_Booking->update_meta('waitlist_expired', $EM_Booking->booking_meta['waitlist_expiry']);
				$EM_Booking->update_meta('waitlist_expiry', null);
			}
			// now go through all affected events and trigger a waitlist check
			if( !empty($event_ids) ){
				foreach( $event_ids as $EM_Event ){
					static::check_waitlist($EM_Event);
				}
			}
			// done! reset the trigger check for status changes
			static::$disable_triggers = false;
		}
	}
}
Manager::init();
