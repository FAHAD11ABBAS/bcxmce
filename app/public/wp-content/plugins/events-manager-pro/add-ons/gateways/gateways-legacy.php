<?php

if( !class_exists('EM_Gateways') ) {
	/**
	 * @deprecated
	 */
	class EM_Gateways {
		// empty, just so we can call EM_Gateways and avoid namespace issues introduced in EM Pro 3.2 and also include some functions that were moved into the Admin classes
		
		public function __get( $prop ){
			return null;
		}
		
		public function __call( $name, $args ){
			return null;
		}
		
		public static function __callStatic( $name, $args ){
			return null;
		}
		
		public static function register_gateway( $gateway, $class ){
			_doing_it_wrong('EM_Gateways::register_gateway', 'This function is now deprecated, ensure your gateway ' . $gateway . ' called by '. $class .' is compatible with EM Pro 3.2 and calls its own static init() function directly instead', '3.2');
		}
		
		public static function is_manual_booking( $new_registration = false ){
			return emp_is_manual_booking( $new_registration );
		}
	}
}


if( !class_exists('EM_Gateway') ) {
	/**
	 * @deprecated
	 */
	class EM_Gateway {
		// empty, just so we can call EM_Gateways and avoid namespace issues introduced in EM Pro 3.2 and also include some functions that were moved into the Admin classes
		
		public function __construct(){
			return null;
		}
		
		public function __get( $prop ){
			return null;
		}
		
		public function __call( $name, $args ){
			return null;
		}
		
		public static function __callStatic( $name, $args ){
			return null;
		}
		
	}
}

if( !function_exists('emp_register_gateway') ){
	function emp_register_gateway( $gateway, $class ) {
		_doing_it_wrong('emp_register_gateway', 'This function is now deprecated, ensure your gateway ' . $gateway . ' called by '. $class .' is compatible with EM Pro 3.2 and calls its own static init() function directly instead', '3.2');
	} //compatibility, use \EM\Payments\Gateways directly
}