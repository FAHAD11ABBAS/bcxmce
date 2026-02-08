<?php
namespace EM\Automation\Triggers;

class Event_Time extends Trigger {
	
	/**
	 * @var string Check per minute whether there's an event starting x minutes/hours/days before
	 */
	public static $is_cron = true;
	public static $listener = 'em_automation_cron_minute';
	public static $type = 'event-time';
	public static $context = 'events';
	
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
			// if global tables, we're only workin on blog-based automation (network-based could come after!)
			$multisite = '';
			if( EM_MS_GLOBAL && !$this->network_global ){
				$multisite = ' AND blog_id = '. get_current_blog_id();
			}
			// we do a search from the start time inwards, whichever side of the tense it is. We assume that the exact-ish time trigger is hit by checking the meta that's added after an event is triggered
			if( $this->trigger_data['time']['when'] == 'after' ){
				$end = date('Y-m-d H:i:00', time() - $seconds);
				$start = date('Y-m-d H:i:00', time() - $seconds - $window);
			}else{
				$end = date('Y-m-d H:i:00', time() + $seconds);
				$start = date('Y-m-d H:i:00', time() + $seconds - $window );
			}
			$timeframe = "event_start BETWEEN '$start' AND '$end'"; // find events that ended x time before for $window seconds
			// get events that match timeframe and haven't already been triggered
			$sql = "SELECT event_id FROM ". EM_EVENTS_TABLE ." WHERE $timeframe $multisite AND event_id NOT IN (SELECT object_id FROM ". EM_META_TABLE ." WHERE meta_key=%s AND meta_value=%s)";
			$sql = $wpdb->prepare($sql, 'triggered', $this->id);
			$this->run_data['sql'] = $sql;
			$event_ids = $wpdb->get_col( $sql );
			// go through the events, run actions and mark triggered
			foreach( $event_ids as $event_id ){
				// we'll mark this as triggered before running actions, because if something goes wrong we could end up with an endless loop of retriggering without completing, resulting in fired actions over and over again perpetually
				$insert_result = $wpdb->insert(EM_META_TABLE, array('object_id' => $event_id, 'meta_key' => 'triggered', 'meta_value' => $this->id), array('%d', '%s', '%d'));
				if( $insert_result !== false ){
					// fire the actions
					$EM_Event = em_get_event($event_id);
					if( $EM_Event->event_id ) {
						$result[$event_id] = $this->fire( $this->filter( $EM_Event ) );
					}
				}
			}
		}
		return $result;
	}
	
	public static function get_name(){
		return esc_html__("Event Times", 'em-pro');
	}
	
	public static function get_description(){
		return esc_html__('Triggered a specific amount of time before or after an event.', 'em-pro');
	}
}
Event_Time::init();