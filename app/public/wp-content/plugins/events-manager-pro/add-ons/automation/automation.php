<?php
namespace EM;

class Automation {
	
	public static $triggers = array();
	public static $actions = array();
	
	public static function init(){
		
		// load default actions and triggers
		include('actions/action.php');
		include('triggers/trigger.php');
		
		// allow triggers and actions to load themselves here
		do_action('em_automation_loaded');
		
		// register triggers and actions available
		static::$triggers = apply_filters('em_automation_register_triggers', static::$triggers);
		static::$actions = apply_filters('em_automation_register_actions', static::$actions);
		
		// go through triggers and register them into the relevant hooks etc.
		foreach( static::$triggers as $trigger ){
			$trigger::listen();
		}
		
		// register a heartbeat, triggers should add action to listen to em_automatio_heartbeat
		if( !wp_next_scheduled('em_automation_cron_minute') && has_action('em_automation_cron_minute') ){
			wp_schedule_event( time(), 'em_minute', 'em_automation_cron_minute');
		}
		//set up cron for addint to email queue
		if( !wp_next_scheduled('em_automation_cron_hourly') && has_action('em_automation_cron_hourly') ){
			wp_schedule_event( time(), 'em_minute', 'em_automation_cron_hourly');
		}
		//set up cron for addint to email queue
		if( !wp_next_scheduled('em_automation_cron_daily') && has_action('em_automation_cron_daily') ){
			wp_schedule_event( static::get_daily_cron_time(), 'daily', 'em_automation_cron_daily');
		}
		//set up cron for addint to email queue
		if( !wp_next_scheduled('em_automation_cron_twicedaily') && has_action('em_automation_cron_twicedaily') ){
			wp_schedule_event( static::get_daily_cron_time(), 'twicedaily', 'em_automation_cron_twicedaily');
		}
	}
	
	public static function get_daily_cron_time(){
		$todays_time_to_run = strtotime(date('Y-m-d', current_time('timestamp')).' '.  get_option('dbem_em_automation_cron_daily_time'), current_time('timestamp'));
		$tomorrows_time_to_run = strtotime(date('Y-m-d', current_time('timestamp')+(86400)).' '. get_option('dbem_em_automation_cron_daily_time'), current_time('timestamp'));
		$time = $todays_time_to_run > current_time('timestamp') ? $todays_time_to_run:$tomorrows_time_to_run;
		$time -= ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ); //offset time to run at UTC time for WP Cron
		return $time;
	}
	
	public static function trigger_exists($type ){
		return !empty(static::$triggers[$type]);
	}
	
	public static function get_trigger( $type_or_id ){
		if( is_numeric($type_or_id) ){
			// get trigger instance
			global $wpdb;
			$trigger_row = $wpdb->get_row('SELECT * FROM '.EM_AUTOMATION_TABLE.' WHERE id='. absint($type_or_id));
			if( $trigger_row && static::trigger_exists($trigger_row->type) ){
				$trigger = static::$triggers[$trigger_row->type];
				return new $trigger($trigger_row);
			}
		}elseif( static::trigger_exists($type_or_id) ){
			return static::$triggers[$type_or_id];
		}
		return false;
	}
	
	public static function action_exists( $action_type ){
		return !empty(static::$actions[$action_type]);
	}
	
	public static function get_action( $action_type ){
		if( static::action_exists($action_type) ){
			return static::$actions[$action_type];
		}
		return false;
	}
}
Automation::init();