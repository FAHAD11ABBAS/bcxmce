<?php

namespace EM\Automation\Triggers;
use EM\Automation;
use EM\Bookings\RSVP\Policies;

class Trigger {
	
	/**
	 * @var string Type of trigger, such as a trigger for a specific event time or when a certain booking action is performed
	 */
	public static $type = 'trigger';
	/**
	 * @var boolean Denotes that this is a cron dependent trigger. If set to true the static::$listener should be set to any cron action, preferably a value that corresponsds to an em_automation_cron_[interval] hook, such as 'minute', 'daily', 'hourly', 'twicedaily'
	 */
	public static $is_cron;
	/**
	 * Single action/filter name or alternatively an associative array of action/filter => callable sets allowing triggers to listen to multiple hooks and handle them individually.
	 * The associative array value can take three forms;
	 *      - Nested associative array containing three keys corresponding to the same arguments passed onto add_filter - 'callback', 'priority', 'accepted_arguments'.
	 *      - Single callable callback value which will assume the default priority and accepted_argument values.
	 *      - Integer containing the number of accepted arguments, which will be passed onto the handle() method of this trigger instance.
	 * @var string|array
	 */
	public static $listener;
	/**
	 * @var string The context this trigger can be fired under, which could be an event, a booking or anything else so that only actions that support this context are associated with it
	 */
	public static $context = 'events';
	/**
	 * @var int Unique identifier of the trigger in the automation table
	 */
	public $id;
	/**
	 * @var string  Name of the trigger, reference for the trigger to admins.
	 */
	public $name = 'Unnamed Trigger';
	/**
	 * @var int ID of object to be acted upon, depending on the trigger, such as an event time for a specific event then the object_id would be an event id.
	 */
	public $object_id;
	/**
	 * @var int Unix timestamp for when to trigger this automation, relevant when specific to objects, such as a specific event a certain time before that event starts would have a specific date when to be fired
	 */
	public $ts;
	/**
	 * @var array[]   Array of associative arrays containing data about each action to be taken, sequentially.
	 */
	public $trigger_data = array();
	/**
	 * @var array[]   Array of associative arrays containing data about each action to be taken, sequentially.
	 */
	public $action_data = array();
	/**
	 * @var bool True if trigger is enabled, false if not.
	 */
	public $status;
	/**
	 * @var bool Flag whether the trigger is currently underway during a cron job, in which case it should be ignored by following cron calls until completed and flag is unset
	 */
	public $doing_cron;
	/**
	 * @var bool If set to true, trigger will apply for ALL events on the network when in MS Global mode, otherwise it'll only go for events triggered in the current blog ID
	 * @note We suggest you don't use this yet on your own implementations. We need to test this more whilst in beta stages, assume each event is searched and automated within its own network.
	 */
	public $network_global = false;
	/**
	 * @var array Log data that would be added to the automation logging table under the 'data' serialized array. Extending classes can add extra runtime data to this via the run() method, which is executed before a record is added ot the DB
	 */
	public $run_data = array();
	/**
	 * @var Trigger[]   Array of triggers currently active
	 */
	public static $triggers = array();
	
	public function __construct( $trigger = null ){
		global $wpdb;
		if( is_numeric($trigger) ){
			$trigger = $wpdb->get_col( $wpdb->prepare('SELECT * FROM '.EM_AUTOMATION_TABLE.' WHERE id=%d') );
		}
		if( is_object($trigger) ){
			$this->id = $trigger->id;
			$this->name = $trigger->name;
			$this->object_id = $trigger->object_id;
			$this->trigger_data = maybe_unserialize($trigger->trigger_data);
			$this->action_data = maybe_unserialize($trigger->action_data);
			$this->status = !empty($trigger->status);
			$this->doing_cron = !empty($trigger->doing_cron);
			$this->ts = $trigger->ts;
		}
		do_action('em_trigger', $this, $trigger);
	}
	
	/**
	 * Handles an event that set this trigger off, whether a cron action, or other hook.
	 * @param array ...$runtime_data  Data supplied by action/filter
	 * @return mixed|void
	 */
	public function handle( ...$runtime_data ){
		// catch for logging
		add_filter( 'wp_php_error_message', array( &$this, 'log_error' ), 10, 2 );
		$this->run_data = array('runtime_data' => $runtime_data);
		// run it
		if( static::$is_cron ) {
			// get script max execution time
			$runtime = ini_get('max_execution_time');
			if( !$this->doing_cron || strtotime($this->ts) < time() - $runtime ){
				$this->cron_underway();
				$result = $this->run( $runtime_data );
				$this->cron_complete();
			}
		}else{
			$result = $this->run( $runtime_data );
		}
		// log it (currently experimental)
		if( isset($result) && defined('EM_AUTOMATION_LOGS') ) {
			if( ( constant('EM_AUTOMATION_LOGS') === 1 && count($result) > 0 ) || constant('EM_AUTOMATION_LOGS') === 2 ) {
				$this->log_error( $result );
			}
		}
		$this->run_data = array();
		// for now, we're just returning first arg if it exists, so that filters always pass. We won't modify filters at this time, maybe later for more advanced trigger types
		// we're currently focusing on automation rather than hook manipulation
		if( isset($runtime_data[0]) ){
			return $runtime_data[0];
		}
	}
	
	public function log_error ( ...$args ) {
		global $wpdb;
		if( count($args) > 1 ) {
			// this was a WP Error, log if set to verbose since user will likely have gotten an admin email about the fatal error
			if( constant('EM_AUTOMATION_LOGS') === 2 ) {
				$this->run_data['error'] = $args[1];
				$result = null;
			} else {
				return;
			}
		} else {
			$this->run_data['results'] = $args[0];
			$result = count($args[0]);
		}
		$insert_data = array(
			'automation_id' => $this->id,
			'affected' => $result,
			'data' => serialize($this->run_data),
		);
		$wpdb->insert( EM_AUTOMATION_LOGS_TABLE, $insert_data );
	}
	
	/**
	 * Checks the conditions the trigger requires run the fire() method on applicable objects, for example all events happening within x time will fire.
	 * @param array $runtime_data       Data passed on by hook, if any.
	 * @return array                    Returns result from fire() for each object fired on, or an empty array if not fired at all.
	 */
	public function run( $runtime_data = array() ){
		// when overriding this function, each trigger cah check its conditions and whether to fire the trigger which in turn will
		return array();
	}
	
	public function filter( $result ){
		if( static::$context === 'events' ){
			$EM_Event = $result; /* @var \EM_Event $EM_Event */
			// filter could request to get all bookings and then of a certain status or condition
			if( !empty($this->trigger_data['output']) && $this->trigger_data['output'] === 'bookings' ){
				global $wpdb;
				// first check RSVP filter, if event isn't matching then no point continuing
				if( !$this->filter_event_rsvp_policy( $EM_Event ) ) {
					return false;
				}
				// otherwise get all bookings with an SQL filter
				$sql = 'SELECT * FROM '.EM_BOOKINGS_TABLE.' WHERE event_id='. absint($EM_Event->event_id);
				// get all bookings as per the status filter
				if( !empty($this->trigger_data['filters']['booking_status']) ){
					// filter it in
					$booking_status = array();
					foreach( $this->trigger_data['filters']['booking_status'] as $status ){
						if( is_numeric($status) ) {
							$booking_status[] = absint($status);
						}
					}
					if( !empty($booking_status) ){
						$exclude = !empty($this->trigger_data['filters']['booking_status_include']) && $this->trigger_data['filters']['booking_status_include'] === 'exclude' ? 'NOT':'';
						$sql .= ' AND booking_status '. $exclude .' IN ('. implode(',', $booking_status).')';
					}
				}
				// get all bookings as per the status filter
				if( !empty($this->trigger_data['filters']['gateways']) ){
					// filter it in
					$gateways = array();
					foreach( $this->trigger_data['filters']['gateways'] as $gateway ){
						$gateways[$gateway] = '%s';
					}
					if( !empty($gateways) ){
						$exclude = !empty($this->trigger_data['filters']['gateways_include']) && $this->trigger_data['filters']['gateways_include'] === 'exclude' ? 'NOT':'';
						$sql .= 'AND booking_id '. $exclude .' IN ( SELECT booking_id FROM '. EM_BOOKINGS_META_TABLE . ' WHERE meta_key=\'gateway\' AND meta_value IN ('. $wpdb->prepare(implode(',', $gateways), array_keys($gateways)).'))';
					}
				}
				$the_bookings = $wpdb->get_results($sql, ARRAY_A);
				if( empty($the_bookings) ) return false;
				$EM_Bookings = new \EM_Bookings( $EM_Event );
				$bookings = array();
				foreach( $the_bookings as $booking ){
					$bookings[$booking['booking_id']] = new \EM_Booking($booking);
				}
				$EM_Bookings->bookings = $bookings;
				return $EM_Bookings; // return the array
			}else{
				// check if the event matches filters, if not return
				if( !$this->filter_event($EM_Event) ){
					return false;
				}
				return $EM_Event;
			}
		}elseif( static::$context === 'bookings' ){
			$EM_Booking = $result; /* @var \EM_Booking $EM_Booking */
			// filter could get the event of that booking and pass it onto actions
			if( !empty($this->trigger_data['output']) && $this->trigger_data['output'] === 'event' ){
				$EM_Event = $EM_Booking->get_event();
				if( !$this->filter_event($EM_Event) ){
					return false;
				}
				return $EM_Event;
			}else{
				// filter here, return null if filter is set and doesn't match object, overriding classes could in theory unset the trigger_data['output'] filter options if they incorporate them in an SQL statement to optimize
				if( !empty($this->trigger_data['filters']['booking_status']) ){
					// only specific statuses
					$exclude = !empty($this->trigger_data['filters']['booking_status_include']) && $this->trigger_data['filters']['booking_status_include'] === 'exclude';
					if( in_array($EM_Booking->booking_status, $this->trigger_data['filters']['booking_status']) === $exclude ){
						return false;
					}
				}
				if( !empty($this->trigger_data['filters']['gateways']) ){
					// get all bookings as per the status filter
					$exclude = !empty($this->trigger_data['filters']['gateways_include']) && $this->trigger_data['filters']['gateways_include'] === 'exclude';
					if( in_array($EM_Booking->booking_meta['gateway'], $this->trigger_data['filters']['gateways']) === $exclude ){
						return false;
					}
				}
				if( !$this->filter_event_rsvp_policy( $EM_Booking->get_event() ) ) {
					return false;
				}
				return $EM_Booking;
			}
		}elseif( static::$context === 'locations' ){
			// return some sort of contect for locations - TBD
			return $result;
		}
		return $result;
	}
	
	public function filter_event( $EM_Event ){
		if( !empty($this->trigger_data['filters']['categories']) ){
			$exclude = !empty($this->trigger_data['filters']['categories_include']) && $this->trigger_data['filters']['categories_include'] === 'exclude';
			if( has_term($this->trigger_data['filters']['categories'], EM_TAXONOMY_CATEGORY, $EM_Event->post_id) === $exclude ){
				return false;
			}
		}
		if( !empty($this->trigger_data['filters']['tags']) ){
			$exclude = !empty($this->trigger_data['filters']['tags_include']) && $this->trigger_data['filters']['tags_include'] === 'exclude';
			if( has_term($this->trigger_data['filters']['tags'], EM_TAXONOMY_TAG, $EM_Event->post_id) === $exclude ){
				return false;
			}
		}
		if( !$this->filter_event_rsvp_policy( $EM_Event ) ) {
			return false;
		}
		return true;
	}
	
	public function filter_event_rsvp_policy( $EM_Event ) {
		if( !empty($this->trigger_data['filters']['rsvp_policy']) && class_exists('\EM\Bookings\RSVP\Policies') ) {
			$has_policy = Policies::has_policy( $EM_Event );
			$event_policy =  Policies::get_policy( $EM_Event )->type;
			$filter_policy = $this->trigger_data['filters']['rsvp_policy'];
			if ( $has_policy ) {
				if( $filter_policy !== $event_policy ) {
					return false;
				}
			} elseif ( $filter_policy !== 'none' ) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Fires or 'pulls' the trigger to perform the relevant actions and then returns whether all the actions were fired.
	 * In this process, overriding functions should also leave markers to make sure they don't run at the same time in cron or saving the id to an object (e.g. event meta) so it isn't run twice on the same object.
	 * @param mixed $object             The object to be actioned on, generally would be an event, booking or location
	 * @param array $runtime_data    Any data that may have been passed on by a trigger, for example filter/action data
	 * @return bool[]                   Array containing results of each action.
	 */
	public function fire( $object, $runtime_data = array() ){
		$i = 1;
		$results = array();
		if( !empty($object) ){
			foreach( $this->action_data as $action_data ){
				if( !isset($action_data['enabled']) || !empty($action_data['enabled']) ){
					$action_type = $action_data['type'];
					$action = Automation::get_action( $action_type ); /* @var Automation\Actions\Action $action */
					if( is_array($object) || $object instanceof \Iterator ){
						$results[$i.'_'.$action_type] = array();
						foreach( $object as $item ){
							$results[$i.'_'.$action_type][] = $action::handle( $item, $action_data, $runtime_data );
						}
					}elseif( class_exists($action) ){
						$results[$i.'_'.$action_type] = $action::handle( $object, $action_data, $runtime_data );
					}
				} else {
					$results[ $i . '_' . $action_data['type'] ] = null;
				}
				$i++;
			}
		}
		return $results;
	}
	
	/**
	 * Sets the doing_cron flag in the database for this trigger so that overlapping crons won't result in firing the same trigger twice for the same event.
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public function cron_underway(){
		global $wpdb;
		$this->doing_cron = true;
		return $wpdb->update(EM_AUTOMATION_TABLE, array('doing_cron' => 1, 'ts' => date('Y-m-d H:i:s')), array('id' => $this->id));
	}
	
	/**
	 * Sets the doing_cron flag in the database for this trigger so that overlapping crons won't result in firing the same trigger twice for the same event.
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public function cron_complete(){
		global $wpdb;
		$this->doing_cron = false;
		return $wpdb->update(EM_AUTOMATION_TABLE, array('doing_cron' => null, 'ts' => null), array('id' => $this->id));
	}
	
	/**
	 * Static functionality, handling all triggers of this type
	 */
	
	/**
	 * Initializes trigger and hooks into the automation class to register itself.
	 * @return void
	 */
	public static function init(){
		add_filter('em_automation_register_triggers', array(get_called_class(), 'register') );
	}
	
	public static function register( $triggers ){
		$triggers[static::$type] = get_called_class();
		return $triggers;
	}
	
	/**
	 * Fired on Automation::init() after all triggers and actions have been registered. A trigger can listen to anything, be it a cron, or a specific event.
	 * When a trigger type is 'triggered', the static::handle() function is called which should in turn fire the specific triggers of this type that have been enabled by the site, user, etc.
	 * @return void
	 */
	public static function listen(){
		// hook or do what you need to do so that triggers will be triggered
		foreach( static::get_triggers() as $trigger ) {
			if( !is_array(static::$listener) ) {
				// a simple trigger with only 1 argument passed in priority 10, send straight to Trigger->handle()
				add_filter(static::$listener, array($trigger, 'handle'));
			}else{
				foreach( static::$listener as $action => $callable ){
					if( is_array($callable) && !empty($callable['callback']) && is_callable($callable['callback']) ){
						$accepted_arguments = !empty($callable['accepted_arguments']) ? $callable['accepted_arguments'] : 1;
						$priority = !empty($callable['priority']) ? $callable['priority'] : 10;
						add_filter($action, $callable['callback'], $priority, $accepted_arguments);
					}else{
						$priority = !empty($callable['priority']) ? $callable['priority'] : 10;
						$accepted_arguments = 1;
						$callback = array($trigger, 'handle');
						if( is_integer($callable) ) $accepted_arguments = $callable;
						elseif( !empty($callable['accepted_arguments']) ) $accepted_arguments = absint($callable['accepted_arguments']);
						elseif( is_callable($callable) ) $callback = $callable;
						add_filter($action, $callback, $priority, $accepted_arguments);
					}
				}
			}
		}
	}
	
	/**
	 * @return Trigger[]
	 */
	public static function get_triggers( $force_refresh = false ){
		$class = get_called_class();
		if( empty(static::$triggers[$class]) || $force_refresh ){
			if( $force_refresh ) static::$triggers[$class] = array();
			$trigger_data = static::get_trigger_data();
			foreach( $trigger_data as $trigger ){
				static::$triggers[$class][$trigger->id] = new $class( $trigger );
			}
		}
		if( !empty(static::$triggers[$class]) ) {
			return static::$triggers[$class];
		}else{
			return array();
		}
	}
	
	public static function get_trigger_data(){
		global $wpdb;
		// load the triggers created
		$triggers = $wpdb->get_results( $wpdb->prepare('SELECT * FROM '.EM_AUTOMATION_TABLE.' WHERE type=%s AND status=1', static::$type ));
		if( $triggers === false ){
			return array();
		}
		return $triggers;
	}
	
	public static function get_name(){
		return 'Trigger';
	}
	
	public static function get_description(){
		return 'Fires when a user does X or when an event is X time away/after.';
	}
	
	public static function load_admin( $base_dir = '' ){
		include_once('admin/trigger.php');
		if( !$base_dir ){
			$base_dir = dirname(__FILE__).'/';
		}
		if( file_exists( $base_dir . 'admin/'. static::$type.'.php') ){
			include_once( $base_dir . 'admin/'. static::$type.'.php');
		}elseif( file_exists( $base_dir . static::$type.'-admin.php') ){
			include_once($base_dir . static::$type.'-admin.php');
		}
		// get class name of admin
		$classpath = explode('\\', get_called_class());
		$classname = array_pop($classpath);
		$classpath = '\\'. implode('\\', $classpath);
		$admin_classpath =  $classpath . '\\' . 'Admin';
		if( class_exists($admin_classpath . '\\' . $classname) ){
			return $admin_classpath . '\\' . $classname;
		}elseif( class_exists($classpath . '\\' . $classname) ){
			return $admin_classpath . '\\' . $classname;
		}
		return 'Automation\Triggers\Admin\Trigger';
	}
}

// include native triggers already pre-loaded
include('event-time.php');
include('booking-status.php');
include('booking-time.php');