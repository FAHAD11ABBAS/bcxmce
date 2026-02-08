<?php
namespace EM\Bookings\RSVP;
use EM_DateTime;

class Policies {
	
	public static $available_hook = true;
	
	public static $invalid_spaces_cache = array();
	
	public static function init(){
		// add hooks to calculate 'real' available spaces
		add_filter('em_bookings_get_available_spaces', array( static::class, 'em_bookings_get_available_spaces'), 10, 3 );
		add_filter('em_ticket_get_available_spaces', array( static::class, 'em_ticket_get_available_spaces'), 10, 2 );
		// when a booking is confirmed, cancel the latest unconfirmed booking(s) until enough space is available
		add_action('em_booking_status_changed', array( static::class, 'em_booking_status_changed'), 10, 1 );
		add_action('em_bookings_added', array( static::class, 'em_booking_status_changed'), 10, 1 );
		// output info if event is in policy enforcement
		if ( !empty($_REQUEST['page']) && $_REQUEST['page'] === 'events-manager-bookings' && !empty($_REQUEST['event_id']) && empty($_REQUEST['booking_id']) ) {
			add_action( 'admin_notices', array( static::class, 'admin_notices' ), 10, 2 );
		}
		include( 'rsvp-policies-events.php' );
		include( 'rsvp-policies-manager.php' );
	}
	
	public static function admin_notices(){
		global $EM_Notices; /* @var \EM_Notices $EM_Notices */
		$EM_Event = em_get_event( $_REQUEST['event_id'] );
		$notice = static::get_policy_notice( $EM_Event );
		if( $notice ) {
			$EM_Notices->add_info( $notice );
		}
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 *
	 * @return string|false
	 */
	public static function get_policy_notice( $EM_Event ) {
		static::$available_hook = false;
		$msg = false;
		if( static::has_policy($EM_Event) ) {
			$policy = static::get_policy( $EM_Event );
			if ( static::is_policy_active( $EM_Event ) ) {
				$EM_Bookings = $EM_Event->get_bookings();
				if ( $EM_Bookings->has_open_time() ) {
					if ( $EM_Bookings->has_space( true ) ) {
						$msg = sprintf( esc_html__( 'This event has a %s RSVP policy which will activate once fully booked.', 'em-pro' ), '<strong>' . $policy->label . '</strong>' );
						$msg .= ' ' . $policy->description;
					} else {
						$msg = sprintf( esc_html__( 'This event is currently fully booked and enforcing a %s RSVP policy.', 'em-pro' ), '<strong>' . $policy->label . '</strong>' );
						$msg .= ' ' . $policy->description;
					}
				}
			} else {
				$EM_DateTime = self::get_policy_deadline( $EM_Event );
				$msg = esc_html__( 'This event has a %s RSVP policy which will activate on %s once fully booked.', 'em-pro' );
				$msg = sprintf( $msg, '<strong>' . $policy->label . '</strong>', '<strong>' . $EM_DateTime->formatDefault() . ' (' . $EM_DateTime->getTimezone()->getName() . ')</strong>' );
			}
		}
		static::$available_hook = true;
		return $msg;
	}
	
	public static function em_bookings_get_available_spaces( $available_spaces, $EM_Bookings, $force_refresh ) {
		if( !static::$available_hook ) return $available_spaces; // let functions deactivate this temporarily
		// check if cut-off time is reached
		$EM_Event = $EM_Bookings->get_event();
		if( static::is_policy_active( $EM_Event ) ) {
			$spaces = static::count_invalid_bookings( $EM_Event, $force_refresh );
			if( $spaces ) {
				$available_spaces = $available_spaces + (int) $spaces;
			}
		}
		return $available_spaces;
	}
	
	/**
	 * @param $available_spaces
	 * @param \EM_Ticket $EM_Ticket
	 * @param array $vars;
	 *
	 * @return int|mixed
	 */
	public static function em_ticket_get_available_spaces( $available_spaces, $EM_Ticket, $vars = array() ){
		if( !static::$available_hook ) return $available_spaces; // let functions deactivate this temporarily
		// check if cut-off time is reached
		$EM_Event = $EM_Ticket->get_event();
		if( static::is_policy_active( $EM_Event ) ) {
			if ( !isset($vars['event_spaces']) ){
				$event_available_spaces = $EM_Ticket->get_event()->get_bookings()->get_available_spaces();
				$ticket_available_spaces = $EM_Ticket->get_spaces() - $EM_Ticket->get_booked_spaces();
			} else {
				$event_available_spaces = $vars['event_spaces'];
				$ticket_available_spaces = $vars['ticket_spaces'];
			}
			$spaces = static::count_invalid_booking_tickets( $EM_Ticket );
			if( $spaces ) {
				$ticket_available_spaces = $ticket_available_spaces + (int) $spaces;
			}
			$available_spaces = ($ticket_available_spaces <= $event_available_spaces) ? $ticket_available_spaces:$event_available_spaces;
		}
		return $available_spaces;
	}
	
	/**
	 * @param \EM_Booking $EM_Booking
	 * @param $args
	 *
	 * @return void
	 */
	public static function em_booking_status_changed( $EM_Booking ){
		// if booking is confirmed and policy in force, check if we're overbooked and if so start cancelling unconfirmed bookings
		$ok_booking_statuses = apply_filters('em_booking_rsvp_policy_overridable_statuses', array(1), $EM_Booking);
		if ( in_array($EM_Booking->booking_status, $ok_booking_statuses) && static::is_policy_active( $EM_Booking->get_event() ) ) {
			// cut-off time has passed, so any bookings unconfirmed become available
			// get overbooked spaces we need (if needed)
			static::$available_hook = false;
			$available_spaces = $EM_Booking->get_event()->get_bookings()->get_available_spaces();
			static::$available_hook = true;
			if( $available_spaces < $EM_Booking->get_spaces() ) {
				$booking_ids = static::get_invalid_booking_ids( $EM_Booking->get_event() );
				remove_action('em_booking_status_changed', array( static::class, 'em_booking_status_changed') );
				foreach( $booking_ids as $booking_id ){
					$booking = em_get_booking($booking_id);
					if( $booking->get_rsvp_status() !== 1 && $booking->booking_id !== $EM_Booking->booking_id ) {
						$booking->manage_override = true;
						$booking->cancel();
						$available_spaces =+ $booking->booking_spaces;
					}
					if ( $available_spaces >= $EM_Booking->get_spaces() ) break;
				}
				add_action('em_booking_status_changed', array( static::class, 'em_booking_status_changed'), 10, 2 );
			}
		}
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 *
	 * @return bool
	 */
	public static function has_policy( $EM_Event ) {
		$has_policy = get_option( 'dbem_bookings_rsvp_policy_default' ) == true;
		if ( get_option('dbem_bookings_rsvp_policy_events') ) {
			// if not site default
			if ( isset( $EM_Event->event_attributes['rsvp_policy'] ) ) {
				$has_policy = (int) $EM_Event->event_attributes['rsvp_policy'] === 1;
			}
		}
		return $has_policy;
	}
	
	/**
	 * Returns the policy type this event applies to. A policy is returned even if policies are disabled for this event, careful!
	 * @param \EM_Event $EM_Event
	 *
	 * @return object
	 */
	public static function get_policy( $EM_Event = null ) {
		$policy_type = get_option( 'dbem_bookings_rsvp_policy_type', 'open' );
		if( $EM_Event && get_option('dbem_bookings_rsvp_policy_events') ) {
			// if not site default
			if ( isset( $EM_Event->event_attributes['rsvp_policy_type'] ) ) {
				$policy_type = $EM_Event->event_attributes['rsvp_policy_type'];
			}
		}
		if ( $policy_type == 'strict' ) {
			$policy = array(
				'type' => 'strict',
				'label' => esc_html__( 'Strict', 'em-pro' ),
				'description' =>  sprintf( esc_html__( 'Any unconfirmed or declined bookings will be cancelled automatically on %s.', 'em-pro' ), static::get_policy_deadline($EM_Event)->i18n() ),
			);
		} elseif( $policy_type == 'flexible' ) {
			$policy = array(
				'type' => 'flexible',
				'label' => esc_html__( 'Flexible', 'em-pro' ),
				'description' => sprintf(  esc_html__( 'Any unconfirmed bookings made before %s will be cancelled if any new bookings are made.', 'em-pro' ), static::get_policy_deadline($EM_Event)->i18n() ),
			);
		} else {
			$policy = array(
				'type' => 'open',
				'label' => esc_html__( 'Open', 'em-pro' ),
				'description' =>  esc_html__( 'RSVPing attendance is optional, but no action is taken in any case.', 'em-pro' ),
			);
		}
		return (object) $policy;
	}
	
	public static function is_policy_active( $EM_Event ) {
		if( static::has_policy( $EM_Event ) ) {
			$EM_DateTime = self::get_policy_deadline( $EM_Event );
			return !empty( $EM_DateTime ) && !empty( $EM_DateTime->valid ) && $EM_DateTime->getTimestamp() < time();
		}
		return false;
	}
	
	public static function get_policy_deadline( $EM_Event = null ){
		$time = get_option('dbem_bookings_rsvp_policy_deadline');
		/* @var \EM_DateTime $EM_DateTime */
		$EM_DateTime = new EM_DateTime();
		$EM_DateTime->valid = false; // if we don't have a valid policy, EM_DateTime is now but invalid
		if( $EM_Event !== null ) {
			if ( is_numeric( $time ) ) {
				$EM_DateTime = $EM_Event->start()->copy()->sub( 'PT' . absint( $time ) . 'H' );
			} elseif ( \EM_Booking::is_dateinterval_string( $time ) ) {
				$EM_DateTime = $EM_Event->start()->copy()->sub( $time );
			}
		} else {
			if ( is_numeric( $time ) ) {
				$EM_DateTime->sub( 'PT' . absint( $time ) . 'H' );
			} elseif ( \EM_Booking::is_dateinterval_string( $time ) ) {
				$EM_DateTime->sub( $time );
			}
		}
		return $EM_DateTime;
	}
	
	public static function count_invalid_bookings( $EM_Event, $force_refresh = false ) {
		global $wpdb;
		if ( !isset(static::$invalid_spaces_cache[$EM_Event->event_id]['spaces']) || $force_refresh ) {
			$where = static::get_invalid_bookings_sql_where( $EM_Event );
			$sql = "SELECT SUM(booking_spaces) FROM ".EM_BOOKINGS_TABLE . ' WHERE ' . $where;
			$spaces = $wpdb->get_var( $sql );
			if( empty(static::$invalid_spaces_cache[$EM_Event->event_id]) ) {
				static::$invalid_spaces_cache[$EM_Event->event_id] = array();
			}
			static::$invalid_spaces_cache[$EM_Event->event_id]['spaces'] = ( $spaces ) ? absint( $spaces ) : false ;
		}
		return static::$invalid_spaces_cache[$EM_Event->event_id]['spaces'];
	}
	
	public static function count_invalid_booking_tickets( $EM_Ticket, $force_refresh = false ) {
		global $wpdb;
		$EM_Event = $EM_Ticket->get_event();
		if ( !isset(static::$invalid_spaces_cache[$EM_Event->event_id][$EM_Ticket->ticket_id]) || $force_refresh ) {
			$where = static::get_invalid_bookings_sql_where( $EM_Event );
			$sub_sql = "SELECT booking_id FROM ".EM_BOOKINGS_TABLE . ' WHERE ' . $where;
			$sql = 'SELECT COUNT(*) FROM '. EM_TICKETS_BOOKINGS_TABLE . " WHERE ticket_id = %d AND booking_id IN ($sub_sql)";
			$spaces = $wpdb->get_var( $wpdb->prepare($sql, $EM_Ticket->ticket_id) );
			if( empty(static::$invalid_spaces_cache[$EM_Event->event_id]) ) {
				static::$invalid_spaces_cache[$EM_Event->event_id] = array();
			}
			static::$invalid_spaces_cache[$EM_Event->event_id][$EM_Ticket->ticket_id] = ( $spaces ) ? absint( $spaces ) : false ;
		}
		return static::$invalid_spaces_cache[$EM_Event->event_id][$EM_Ticket->ticket_id];
	}
	
	public static function get_invalid_booking_ids( $EM_Event ) {
		global $wpdb;
		$where = static::get_invalid_bookings_sql_where( $EM_Event );
		$sql = "SELECT booking_id FROM ".EM_BOOKINGS_TABLE . ' WHERE ' . $where;
		return $wpdb->get_col( $sql );
	}
	
	public static function get_invalid_bookings_sql_where( $EM_Event ){
		global $wpdb;
		// cut-off time has passed, so any bookings unconfirmed become available
		$status_cond = !get_option('dbem_bookings_approval_reserved') ? 'booking_status IN (0,1)' : 'booking_status = 1';
		$date_order = get_option('dbem_bookings_rsvp_policy_order', 'ASC') == 'ASC' ? 'ASC' : 'DESC';
		$rsvp_order = 'CASE
			WHEN booking_rsvp_status = 0 THEN 1
			WHEN booking_rsvp_status = 2 THEN 2
			WHEN booking_rsvp_status IS NULL THEN 3
			ELSE 4 END ASC';
		$overconfirmed_date = static::get_policy_deadline( $EM_Event )->getDateTime( true );
		$sql = '(booking_rsvp_status != 1 OR booking_rsvp_status IS NULL) AND event_id=%d AND booking_date < %s AND ' . $status_cond .' ORDER BY ' . $rsvp_order . ', booking_date ' . $date_order;
		$sql = $wpdb->prepare( $sql, $EM_Event->event_id, $overconfirmed_date );
		return $sql;
	}
}
Policies::init();