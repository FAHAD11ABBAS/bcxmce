<?php
namespace EM\Automation\Actions;

class Action {
	
	public static $type = 'action';
	public static $supported_contexts = array();
	
	public function __construct($action = array() ){
		// overriding classes should save data accoringly
		return !empty($action['type']) && static::$type === $action['type'];
	}
	
	/**
	 * Initializes action and hooks into the automation class to register itself.
	 * @return void
	 */
	public static function init(){
		add_filter('em_automation_register_actions', array(get_called_class(), 'register') );
	}
	
	public static function register( $actions ){
		$actions[static::$type] = get_called_class();
		return $actions;
	}
	
	/**
	 * @param mixed $object         The object being actioned upon, such as an event, location, booking etc.
	 * @param array $action_data    Trigger object containing the trigger that was fired and relevant data about the specific data including action data
	 * @param array $runtime_data   Array of values that may have been returned in a filter or action for example, data that can't be predicted until runtime and trigger is executed
	 * @return bool
	 */
	public static function handle( $object, $action_data = array(), $runtime_data = array() ){
		return true;
	}
	
	public static function get_name(){
		return 'Action';
	}
	
	public static function get_description(){
		return 'Fires when a user does X or when an event is X time away/after.';
	}
	
	public static function load_admin( $base_dir = '' ){
		include_once('admin/action.php');
		if( !$base_dir ){
			$base_dir = dirname(__FILE__).'/';
		} else {
			$base_dir = trailingslashit($base_dir);
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
		return 'Automation\Actions\Admin\Trigger';
	}
}

// include natively-included triggers
include('email.php');
include('webhook.php');