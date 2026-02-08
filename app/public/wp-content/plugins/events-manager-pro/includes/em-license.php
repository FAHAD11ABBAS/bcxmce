<?php
namespace EM_Pro;
use EM_Admin_Notice;
use EM_Admin_Notices;
use EM_Notices;
require('pxl-license.php');

class EM_License extends PXL_License {
	
	static $updates_url = 'https://eventsmanagerpro.com/api/%s/';
	static $activation_url = 'https://eventsmanagerpro.com/account/licenses/activate/';
	static $deactivation_url = 'https://eventsmanagerpro.com/account/licenses/deactivate/';
	static $purchase_url = 'https://eventsmanagerpro.com/gopro/';
	
	public static function init(){
		parent::init();
		static::$dev_updates =  get_option('em_check_dev_version');
		if( defined('EM_VERSION') ) static::$current_versions['events-manager'] = EM_VERSION;
	}
	
	public static function load_admin(){
		require_once('em-license-admin.php');
		EM_License_Admin::init();
	}
	
	public static function get_license_admin_url() {
		$tabs_enabled = defined('EM_SETTINGS_TABS') && EM_SETTINGS_TABS;
		if( is_multisite() ){
			if( $tabs_enabled ){
				return network_admin_url('admin.php?page=events-manager-options&em_tab=license');
			}
			return network_admin_url('admin.php?page=events-manager-options#license');
		}else{
			if( $tabs_enabled ){
				return admin_url('edit.php?post_type=event&page=events-manager-options&em_tab=license#license');
			}
			return admin_url('edit.php?post_type=event&page=events-manager-options#license');
		}
	}
	
	public static function load_em_admin_notices(){
		if( !class_exists('EM_Admin_Notices') ){
			if( defined('EM_DIR') && file_exists(EM_DIR.'/classes/em-admin-notices.php') && file_exists(EM_DIR.'/classes/em-admin-notice.php') ){
				$admin_notices = include_once(EM_DIR.'/classes/em-admin-notices.php');
				$admin_notice = include_once(EM_DIR.'/classes/em-admin-notice.php');
				return $admin_notice && $admin_notices;
			}
			return false;
		}
		return true;
	}
	
	public static function get_request_args($action) {
		$return = parent::get_request_args($action);
		return $return;
	}
	
	public static function license_activated(){
		if( !static::load_em_admin_notices() ) return false;
		EM_Admin_Notices::remove(static::$slug.'-deactivation');
		$EM_Notices = new EM_Notices();
		$EM_Notices->add_confirm( sprintf(esc_html__('Your %s license has now been activated on this site and plugin features are now enabled.', 'em-pro'), static::$plugin_name), true );
	}
	
	public static function license_saved(){
		if( !static::load_em_admin_notices() ) return false;
		//remove previous notices to be added again
		EM_Admin_Notices::remove(static::$slug.'-activation', is_multisite());
		EM_Admin_Notices::remove(static::$slug.'-license', is_multisite());
		EM_Admin_Notices::remove(static::$slug.'-error', is_multisite());
		EM_Admin_Notices::remove('pxl-dev-license', is_multisite());
		if( !static::is_active() ){
			EM_Admin_Notices::remove(static::$slug.'-activated', is_multisite());
			EM_Admin_Notices::add(static::$slug.'-activation', is_multisite());
		}elseif( static::$license->error ){
			EM_Admin_Notices::add(static::$slug.'-error', is_multisite());
		}elseif( static::get_license()->dev ){
			EM_Admin_Notices::add('pxl-dev-license', is_multisite());
		}
	}
}