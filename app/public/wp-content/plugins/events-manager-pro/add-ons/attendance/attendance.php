<?php
namespace EM_Pro\Attendance;
use EM_Ticket_Booking;

class Attendance {
	
	public static $status_cache = array();
	
	/**
	 * Handles a checkin and returns an array with result and message.
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @param string $action 'checkin' or 'checkout', defaults to 'checkin' if not supplied
	 * @return array
	 */
	public static function handle_action( $EM_Ticket_Booking, $action = null){
		$result = array();
		if( $action && $action == 'checkout' ){
			$action = static::checkout( $EM_Ticket_Booking );
			$result['result'] = true; // we'll set this back lower down
			$result['status'] = 0;
			if( $action === true ){
				$result['message'] = esc_html__('User successfully checked out.', 'em-pro');
			}elseif( $action === null ){
				$result['result'] = false;
				$result['message'] = esc_html__('User is already checked out.', 'em-pro');
			}
		}else{
			// we'll assume we're checking in
			$action = static::checkin( $EM_Ticket_Booking );
			$result['result'] = true; // we'll set this back lower down
			$result['status'] = 1;
			if( $action === true ){
				$result['message'] = $EM_Ticket_Booking->feedback_message;
			}elseif( $action === null ){
				$result['result'] = false;
				$result['message'] = $EM_Ticket_Booking->feedback_message ;
			}
		}
		if( $action === false ){
			$result['status'] = static::get_status($EM_Ticket_Booking);
			$result['result'] = false;
			$result['message'] = implode("\n", $EM_Ticket_Booking->get_errors());
		}
		$result['id'] = $EM_Ticket_Booking->ticket_booking_id;
		$result['uuid'] = $EM_Ticket_Booking->ticket_uuid;
		return $result;
	}
	
	/**
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @return array|null
	 */
	public static function get_status_data( $EM_Ticket_Booking ) {
		global $wpdb;
		if (!empty(static::$status_cache[$EM_Ticket_Booking->ticket_booking_id])) {
			return static::$status_cache[$EM_Ticket_Booking->ticket_booking_id];
		}
		$result = $wpdb->get_row($wpdb->prepare('SELECT checkin_status, checkin_ts FROM ' . EM_TICKETS_BOOKINGS_CHECKINS_TABLE . ' WHERE ticket_booking_id=%d ORDER BY checkin_ts DESC', $EM_Ticket_Booking->ticket_booking_id), ARRAY_A);
		if( $result ) {
			static::$status_cache[$EM_Ticket_Booking->ticket_booking_id] = $result;
			return $result;
		}
		return null;
	}
	
	
	/**
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @return int|null
	 */
	public static function get_status( $EM_Ticket_Booking ){
		$result = static::get_status_data( $EM_Ticket_Booking );
		if( $result === null ){
			return null;
		}
		return absint($result['checkin_status']);
	}
	
	public static function get_status_text( $EM_Ticket_Booking ){
		$status_text = array(
			1 => esc_html__('Checked In', 'em-pro'),
			0 => esc_html__('Checked Out', 'em-pro'),
			null => esc_html__('Not Attended', 'em-pro'),
		);
		$status = static::get_status( $EM_Ticket_Booking );
		return $status_text[$status];
	}
	
	/**
	 * Returns the timestamp in MySQL format for last action (i.e. check in/out) taken by supplied ticket booking. Returns null if no action previously taken.
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @return string|null
	 */
	public static function get_status_timestamp( $EM_Ticket_Booking ){
		$result = static::get_status_data( $EM_Ticket_Booking );
		if( $result === null ){
			return null;
		}
		return $result['checkin_ts'];
	}
	
	/**
	 * Checks in ticket booking, returns true on success, null if already checked in.
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @return bool|null
	 */
	public static function checkin($EM_Ticket_Booking ){
		global $wpdb;
		if( in_array($EM_Ticket_Booking->get_booking()->booking_status, array(2,3)) ) {
			$EM_Ticket_Booking->add_error( esc_html__('Could not complete action because booking is cancelled or rejected.', 'em-pro') );
			return false;
		} // rejected or cancelled
		if( static::get_status($EM_Ticket_Booking) == 1 ){
			$EM_Ticket_Booking->feedback_message = esc_html__('User is already checked in.', 'em-pro');
			return null;
		}
		$result = $wpdb->insert(EM_TICKETS_BOOKINGS_CHECKINS_TABLE, array('ticket_booking_id' => $EM_Ticket_Booking->ticket_booking_id, 'checkin_status' => 1));
		unset(static::$status_cache[$EM_Ticket_Booking->ticket_booking_id]);
		if ( $result !== false ) {
			$EM_Ticket_Booking->feedback_message = esc_html__('User successfully checked in.', 'em-pro');
		} else {
			$EM_Ticket_Booking->add_error( esc_html__('Could not complete action due to an error.', 'em-pro') );
		}
		return $result;
	}
	
	/**
	 * Checks in ticket booking, returns true on success, null if already checked in.
	 * @param EM_Ticket_Booking $EM_Ticket_Booking
	 * @return bool|null
	 */
	public static function checkout($EM_Ticket_Booking ){
		global $wpdb;
		if( static::get_status($EM_Ticket_Booking) === 0 ){
			return null;
		}
		$result = $wpdb->insert(EM_TICKETS_BOOKINGS_CHECKINS_TABLE, array('ticket_booking_id' => $EM_Ticket_Booking->ticket_booking_id, 'checkin_status' => 0));
		unset(static::$status_cache[$EM_Ticket_Booking->ticket_booking_id]);
		if ( $result !== false ) {
			$EM_Ticket_Booking->feedback_message = esc_html__('User successfully checked out.', 'em-pro');
		} else {
			$EM_Ticket_Booking->add_error( esc_html__('Could not complete action due to an error.', 'em-pro') );
		}
		return $result !== false;
	}
	
	public static function get_history( $EM_Ticket_Booking ){
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare('SELECT checkin_status, checkin_ts FROM '. EM_TICKETS_BOOKINGS_CHECKINS_TABLE .' WHERE ticket_booking_id=%d ORDER BY checkin_ts ASC', $EM_Ticket_Booking->ticket_booking_id), ARRAY_A );
		$timezone = $EM_Ticket_Booking->get_booking()->get_event()->get_timezone();
		$history = array();
		$status = array(
			1 => esc_html__('Checked In', 'em-pro'),
			0 => esc_html__('Checked Out', 'em-pro'),
		);
		foreach( $results as $result ){
			$EM_DateTime = new \EM_DateTime($result['checkin_ts'], $timezone);
			$history[] = array(
				'date' => $EM_DateTime->formatDefault(),
				'time' => $EM_DateTime->format('H:i A'),
				'datetime' => $EM_DateTime->i18n('M j Y @ h:i A'),
				'action' => $status[$result['checkin_status']],
				'status' => $result['checkin_status'],
			);
		}
		return $history;
	}
	
	public static function get_booking_ticket_ids_with_status( $EM_Booking, $status = 1, $ticket_id = false ){
		global $wpdb;
		if( $status === null ){
			// tickets that didn't attend will not have a record in the checkin history
			$results = array();
			$sql = 'SELECT ticket_booking_id FROM '. EM_TICKETS_BOOKINGS_TABLE .' WHERE booking_id='. absint($EM_Booking->booking_id) .' AND ticket_booking_id NOT IN (SELECT ticket_booking_id FROM '. EM_TICKETS_BOOKINGS_CHECKINS_TABLE .')';
			if( $ticket_id ){
				$sql .= 'AND ticket_id='.absint($ticket_id);
			}
			$ticket_booking_ids = $wpdb->get_col( $wpdb->prepare($sql, $EM_Booking->booking_id) );
			foreach( $ticket_booking_ids as $ticket_booking_id ){
				$results[] = array('ticket_booking_id' => $ticket_booking_id, 'checkin_ts' => null, 'checkin_status' => null);
			}
		}else{
			$sql = '
				SELECT t1.ticket_booking_id, t2.checkin_ts, t1.checkin_status
				FROM '. EM_TICKETS_BOOKINGS_CHECKINS_TABLE .' t1
				INNER JOIN (
					SELECT MAX(checkin_ts) checkin_ts, ticket_booking_id
					FROM '. EM_TICKETS_BOOKINGS_CHECKINS_TABLE .' t2
					WHERE ticket_booking_id IN (SELECT ticket_booking_id FROM '. EM_TICKETS_BOOKINGS_TABLE .' WHERE booking_id=%d)
				    GROUP BY ticket_booking_id
				) t2 ON t1.ticket_booking_id = t2.ticket_booking_id AND t1.checkin_ts = t2.checkin_ts
				WHERE checkin_status = '. absint($status);
			if( $ticket_id ){
				$sql .= $wpdb->prepare(' AND t1.ticket_booking_id IN (SELECT ticket_booking_id FROM '. EM_TICKETS_BOOKINGS_TABLE .' WHERE booking_id=%d AND ticket_id=%d)', $EM_Booking->booking_id, $ticket_id);
			}
			$results = $wpdb->get_results( $wpdb->prepare($sql, $EM_Booking->booking_id), ARRAY_A);
		}
		return $results;
	}
}