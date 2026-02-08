<?php
namespace EM\Automation\Triggers;

class Booking_Time extends Trigger {
	
	public static $type = 'booking-time';
	public static $context = 'bookings';
	/**
	 * @var string Check per minute whether there's an event starting x minutes/hours/days before
	 */
	public static $is_cron = true;
	public static $listener = 'em_automation_cron_minute';
	
	public function run( $runtime_data = array() ) {
		$result = array();
		$window = 600; // 10-minute time window
		switch ( $this->trigger_data['time']['type'] ){
			case 'minutes':
				$seconds = $this->trigger_data['time']['amount'] * 60;
				$window = 300; // more precise since we're talking minutes
				break;
			case 'hours':
				$seconds = $this->trigger_data['time']['amount'] * 3600;
				break;
			case 'days':
				$seconds = $this->trigger_data['time']['amount'] * 86400;
				$window = 3600; // less precise, give 1 hour window
				break;
		}
		if( !empty($seconds) ){
			// finds all evens happening within this time frame, fire each one and tag it, so it's not fired twice
			global $wpdb;
			// if global tables, we're only workin on blog-based automation (network-based could come after!) so we need to fliter out bookings that aren't for this blog
			$multisite = '';
			if( EM_MS_GLOBAL && !$this->network_global ){
				$event_future = '';
				if( $this->trigger_data['future_events'] ){
					$now = date('Y-m-d H:i:s');
					$event_future = " AND event_start > '$now'";
				}
				$multisite = ' AND event_id IN (SELECT event_id FROM '. EM_EVENTS_TABLE.' WHERE blog_id='. get_current_blog_id().' . '. $event_future .')';
			}if( $this->trigger_data['future_events'] ){
				$now = date('Y-m-d H:i:s');
				$multisite = ' AND event_id IN (SELECT event_id FROM '. EM_EVENTS_TABLE. " WHERE event_start > '$now')";
			}
			$end = date('Y-m-d H:i:00', time() - $seconds);
			$start = date('Y-m-d H:i:00', time() - $seconds - $window);
			$timeframe = "booking_date BETWEEN '$start' AND '$end'"; // find events that ended x time before for $window seconds
			// get events that match timeframe and haven't already been triggered
			$sql = "SELECT booking_id FROM ". EM_BOOKINGS_TABLE ." WHERE $timeframe $multisite AND booking_id NOT IN (SELECT booking_id FROM ". EM_BOOKINGS_META_TABLE ." WHERE meta_key=%s AND meta_value=%s)";
			$sql = $wpdb->prepare($sql, 'triggered', $this->id);
			$bookings = $wpdb->get_col( $sql );
			// go through the events, run actions and mark triggered
			foreach( $bookings as $booking_id ){
				// we'll mark this as triggered before running actions, because if something goes wrong we could end up with an endless loop of retriggering without completing, resulting in fired actions over and over again perpetually
				$insert = $wpdb->insert(EM_BOOKINGS_META_TABLE, array('booking_id' => $booking_id, 'meta_key' => 'triggered', 'meta_value' => $this->id), array('%d', '%s', '%d'));
				if( $insert !== false ){
					// fire the actions
					$EM_Booking = em_get_booking($booking_id);
					if( $EM_Booking->booking_id ) {
						$result[$booking_id] = $this->fire( $this->filter( $EM_Booking ) );
					}
				}
			}
		}
		return $result;
	}
	
	public static function get_name(){
		return esc_html__("Booking Time", 'em-pro');
	}
	
	public static function get_description(){
		return esc_html__('Triggered when a booking was made after a certain amount of time.', 'em-pro');
	}
}
Booking_Time::init();