<?php
namespace EM_Pro;
use EM_Pro;

require('em-license.php');

class License extends EM_License {
	
	static $plugin_name = 'Events Manager Pro';
	static $plugin = 'events-manager-pro/events-manager-pro.php'; //set this on init in case someone changed the folder name
	static $slug = 'events-manager-pro';
	static $license_key_option_name = 'dbem_pro_api_key';
	static $lang = 'em-pro';
	static $constant_prefix = 'EMP_LICENSE';
	
	public static function init(){
		parent::init();
		static::$plugin = EMP_SLUG;
		static::$version = EMP_VERSION;
		static::$current_versions[static::$slug] = EMP_VERSION;
		static::$dev_updates =  (defined('EMP_DEV_UPDATES') && EMP_DEV_UPDATES) || get_option('dbem_pro_dev_updates') || get_option('em_check_dev_version');
	}
	
	public static function load_admin(){
		require_once('emp-license-admin.php');
		License_Admin::init();
	}
	
	public static function request( $args, $url = null ){
		$request = parent::request( $args, $url );
		if( is_wp_error($request) ){
			EM_Pro::log(array('error' => 'Failed Update API retrieval '.$url, 'response'=> $request));
		}
		return $request;
	}
}
add_action('plugins_loaded', '\EM_Pro\License::init', 1);