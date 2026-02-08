<?php
namespace EM_Pro;
use \WP_Error;

class PXL_License {
	
	static $updates_url = false;
	static $activation_url = false;
	static $deactivation_url = false;
	static $purchase_url = false;
	static $plugin = false; //set this on init
	static $version = '0.0';
	static $slug = 'plugin-name';
	static $plugin_name = 'Plugin Name';
	static $dev_updates = false;
	static $license_key_option_name;
	static $current_versions = array();
	static $home_url_filter; //temporary filter holding
	static $pre_option_home_option_filter; //temporary filter holding
	static $option_home_option_filter; //temporary filter holding
	static $constant_prefix = 'PXL_LICENSE';
	/**
	 * @var PXL_License_Token
	 */
	static $license;
	static $lang;
	static $depends_on;
	protected static $ms_switched = false;
	
	public static function init(){
		global $wp_version;
		static::$current_versions['wp'] = $wp_version;
		//Set URLs
		$self = get_called_class();
		if( !static::$license_key_option_name ){
			static::$license_key_option_name = str_replace('-', '_', static::$slug) . '_license_key';
		}
		// Hook into the plugin update check
		add_filter('pre_set_site_transient_update_plugins', array($self, 'check'));
		// Recheck hook
		add_action( 'wp_ajax_pxl_recheck_'.static::$slug, array($self,'recheck') );
		// Reset hook
		add_action( 'wp_ajax_pxl_reset_'.static::$slug, array($self,'reset') );
		// Hook into the plugin details screen
		add_filter('plugins_api', array($self,'info'), 10, 3);
		// Load Admin
		if( is_admin() && !static::$depends_on ){ //dependent plugins don't need to load themselves into admin
			static::load_admin();
		}
	}
	
	public static function switch_blog(){
		//sort out filters that may interfere with home url
		global $wp_filter;
		if( !empty($wp_filter['home_url']) ){
			static::$home_url_filter = $wp_filter['home_url'];
			unset( $wp_filter['home_url'] );
		}
		if( !empty($wp_filter['option_home']) ){
			static::$option_home_option_filter = $wp_filter['option_home'];
			unset( $wp_filter['option_home'] );
		}
		if( !empty($wp_filter['pre_option_home']) ){
			static::$pre_option_home_option_filter = $wp_filter['pre_option_home'];
			unset( $wp_filter['pre_option_home'] );
		}
		//multisite switch
		if( is_multisite() && !is_main_site() ){
			static::$ms_switched = true;
			switch_to_blog( get_main_site_id() );
		}
	}
	
	public static function restore_blog(){
		//restore home url interfering filters
		global $wp_filter;
		if( !empty(static::$home_url_filter) ) {
			$wp_filter['home_url'] = static::$home_url_filter;
			static::$home_url_filter = null;
		}
		if( !empty(static::$option_home_option_filter) ){
			$wp_filter['option_home'] =  static::$option_home_option_filter;
			static::$option_home_option_filter = null;
		}
		if( !empty(static::$pre_option_home_option_filter) ){
			$wp_filter['pre_option_home'] =  static::$pre_option_home_option_filter;
			static::$pre_option_home_option_filter = null;
		}
		//multisite switch
		if( is_multisite() && static::$ms_switched ){
			restore_current_blog();
			static::$ms_switched = false;
		}
	}
	
	public static function load_admin(){
		require_once('pxl-license-admin.php');
		PXL_License_Admin::init();
	}

	public static function get_license_admin_url(){
		return admin_url();
	}
	
	public static function license_saved(){
		return true;
	}
	
	public static function license_activated(){
		return true;
	}
	
	public static function is_active(){
		$license = static::get_license();
		if( $license->key ){
			//we also check if there's a definitive active response (no connection error), otherwise we assume it's active
			if( $license->activated !== null ){
				return $license->activated;
			}
			return true;
		}else{
			//no license key, so it's not activated in any case
			return false;
		}
	}
	
	public static function is_valid(){
		$license = static::get_license();
		return $license->valid && $license->until > time();
	}
	
	/* request types */
	public static function check_license_key( $force = false ){
		$license = static::get_license( $force );
		return $license->valid;
	}
	
	public static function get_license_key(){
		return static::get_license()->key;
	}
	
	public static function get_request_args( $action ){
		static::switch_blog();
		$args = array(
			'action' => $action,
			'v' => static::$current_versions,
			'key' => static::get_license()->key,
			'site' => get_bloginfo('url'),
			'ms' => is_multisite(),
		);
		if( defined(static::$constant_prefix.'_SITE') && defined(static::$constant_prefix.'_SITE_KEY') && defined(static::$constant_prefix.'_NODE_SITE') && defined(static::$constant_prefix.'_NODE_SITE_KEY') ){
			// load balancing servers sharing the same DB can define thes constants in wp-config.php so that license is still recognized and doesn't trip changing server IP issues
			$args['site'] = constant(static::$constant_prefix.'_SITE');
			$args['key'] = constant(static::$constant_prefix.'_SITE_KEY');
			$args['node_site'] = constant(static::$constant_prefix.'_NODE_SITE');
			$args['node_key'] = constant(static::$constant_prefix.'_NODE_SITE_KEY');
		}
		static::restore_blog();
		//request the latest dev version
		if( static::$dev_updates ){
			$args['dev_version'] = 1;
		}
		//get dependencies
		$dependencies = apply_filters('pxl_updates_depend_on_'.static::$slug, array());
		if( !empty($dependencies) ){
			$args['plugins'] = array();
			foreach( $dependencies as $plugin_slug => $plugin_info ){
				$args['plugins'][] = $plugin_slug;
				$args['v'][$plugin_slug] = $plugin_info['version'];
			}
		}
		return $args;
	}
	
	// Send a request to the alternative API, return an object
	public static function request( $args, $url = null ) {
		static::switch_blog();
		//URL can be hard-coded or contain a placeholder for the slug so sprintf can do its thing
		if( !$url ) $url = sprintf(static::$updates_url, static::$slug);
		// Send request
		$request = wp_remote_post( $url, array( 'body' => $args ) );
		static::restore_blog();
		if( is_wp_error( $request ) ){
			return $request;
		}elseif( wp_remote_retrieve_response_code( $request ) != 200 ){
			$code = wp_remote_retrieve_response_code( $request );
			$msg = wp_remote_retrieve_response_message( $request );
			// Request failed
			return new WP_Error('pxl-license-request', "$code - $msg", $request);
		}
		// Read server response, which should be an object
		$response = json_decode( wp_remote_retrieve_body( $request ) );
		if( is_object( $response ) ) {
			$response->site = $args['site']; // add site so we can double-check odd site changes
			return $response;
		} else {
			// Unexpected response
			$response_text = is_string($response) ? $response : json_encode($response);
			return new WP_Error('pxl-license-request', "Unexpected Error : $response_text", $request);
		}
	}
	
	public static function check_response($response){
		// Make sure the request was successful
		if( is_wp_error( $response ) ){ //wp erro object
			return false;
		}elseif( is_object( $response ) ){ //we got our object - whatever it is
			return true;
		}
		return false;
	}
	
	/**
	 * @param bool $force
	 * @return PXL_License_Token
	 */
	public static function get_license( $force = false ){
		if( static::$license && !$force ) return static::$license; //only one lookup needed per run
		static::$license = new PXL_License_Token( static::$license_key_option_name );
		//get license if not an object, or we need a forced update
		if( $force && (static::$license->key || defined(static::$constant_prefix.'_SITE_KEY')) ){
			// POST data to send to your API
			$args = static::get_request_args('verify');
			// Send request checking for an update
			$previously_active = static::$license->activated;
			static::$license->load( static::request( $args ) );
			static::$license->save();
			static::license_saved();
			if( static::get_license()->activated && $previously_active !== static::$license->activated ){
				static::license_activated();
			}
		}elseif( $force ){
			static::license_saved();
		}
		return static::$license;
	}
	
	public static function recheck(){
		if( !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'recheck-license-'.static::$plugin) ){
			if( !empty($_REQUEST['key']) ){
				static::get_license()->key = wp_unslash($_REQUEST['key']);
				static::get_license()->save();
			}
			static::get_license(true);
			wp_redirect( static::get_license_admin_url() );
			exit();
		}
	}
	
	public static function reset(){
		if( !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'reset-license-'.static::$plugin) ){
			static::get_license()->delete();
			static::license_saved();
			wp_redirect( static::get_license_admin_url() );
			exit();
		}
	}
	
	//latest version
	public static function check( $transient ) {
		// Check if the transient contains the 'checked' information
		// If no, just return its value without hacking it
		if( empty( $transient->checked ) ) return $transient;
		// The transient contains the 'checked' information
		if( empty($transient->checked[static::$plugin]) ){
			$transient->checked[static::$plugin] = static::$version;
		}
		// POST data to send to your API
		$args = static::get_request_args('check');
		
		// Send request checking for an update
		$response = static::request( $args );
		
		// If response is false, don't alter the transient
		if( static::check_response($response) ){
			$the_response = clone($response);
			$the_response->slug = static::$slug;
			if( !empty($the_response->license) ) unset($the_response->license);
			if( !empty($the_response->new_version) && version_compare($transient->checked[static::$plugin], $the_response->new_version) < 0) {
				if( empty($response->license->valid) ){
					$the_response->package = '';
				}
				$the_response->plugin = static::$plugin;
				$transient->response[static::$plugin] = $the_response;
			}else{
				$transient->no_update[static::$plugin] = $the_response;
			}
			//check dependencies
			$dependencies = apply_filters('pxl_updates_depend_on_'.static::$slug, array());
			if( !empty($dependencies) && !empty($response->other_plugins) ){
				foreach( $response->other_plugins as $plugin_slug => $plugin_info ){
					$plugin_path = $dependencies[$plugin_slug]['plugin'];
					if( empty($transient->checked[$plugin_path]) ) $transient->checked[$plugin_path] = $dependencies[$plugin_slug]['version'];
					if( !empty($the_response->new_version) && version_compare($transient->checked[$plugin_path], $plugin_info->new_version) < 0) {
						if( empty($response->license->valid) ){
							$plugin_info->package = '';
						}
						$plugin_info->slug = $plugin_slug;
						$plugin_info->plugin = $plugin_path;
						$transient->response[$plugin_path] = $plugin_info;
					}else{
						$transient->no_update[$plugin_path] = $plugin_info;
					}
				}
			}
			//check if our license has been updated in some way
			if( !empty($response->license) ){
				$previously_active = static::get_license()->activated;
				if( isset($response->license->key) ){
					static::get_license()->load($response->license)->save();
				}elseif( !empty($response->license->error) ){
					static::get_license()->error = $response->license->error;
					static::get_license()->error_response = $response;
					static::get_license()->save();
				}
				static::license_saved();
				if( static::get_license()->activated && $previously_active !== static::get_license()->activated ){
					static::license_activated();
				}
			}
		}
		//echo "<pre>"; print_r($response); echo "</pre>"; die();
		return $transient;
	}
	
	//plugin info pane
	public static function info( $false, $action, $info_args ) {
		// Check if this plugins API is about this plugin
		$dependencies = apply_filters('pxl_updates_depend_on_'.static::$slug, array());
		
		if( empty($info_args->slug) || ($info_args->slug != static::$slug && empty($dependencies[$info_args->slug])) ) {
			return $false;
		}
		
		// The transient contains the 'checked' information
		$args = static::get_request_args('info');
		$args['slug'] = $info_args->slug;

		$url = false;
		if( !empty($dependencies[$info_args->slug]) ){
			unset($args['plugins']);
			$url = sprintf( static::$updates_url, $info_args->slug );
		}
		
		// Send request for detailed information
		$response = static::request( $args, $url );
		$response = ( static::check_response($response) ) ? $response:false;
		
		// Convert sections into array, since we got back a JSON response which converted assoc arrays into objects
		if( !empty($response->sections) ){
			$response->sections = (array) $response->sections;
		}
		return $response;
	}
}

class PXL_License_Token {
	public $key;
	public $activated;
	public $deactivated = false;
	public $valid;
	public $checked;
	public $error;
	public $error_response;
	public $update_notices;
	public $until = 0;
	public $dev = false;
	public $option_name;
	public $wp_error_code;
	public $site;
	public $previous_site;
	
	public function __construct( $option_name ){
		$this->option_name = $option_name;
		$this->get();
	}
	
	public function get(){
		$license = get_site_option($this->option_name);
		$this->load( $license );
		return $this;
	}
	
	public function save(){
		$vars = get_object_vars($this);
		unset($vars['option_name']);
		foreach($vars as $k => $v){
			if( $v === null ) unset($vars[$k]);
		}
		if( is_multisite() ){
			update_site_option( $this->option_name, $vars );
		}else{
			update_option( $this->option_name, $vars );
		}
		return $this;
	}
	
	public function load( $response = false, $touch = false ){
		if( !$response ) return $this;
		if( is_wp_error($response) ){ /* @var WP_Error $response */
			$this->error = $response->get_error_message();
			if( $response->get_error_data() == 'pxl-license-request' && $response->get_error_data('pxl-license-request') ){
				$this->error_response = $response->get_error_data('pxl-license-request');
			}else{
				$this->error_response = $response;
			}
			$this->wp_error_code = $response->get_error_code();
		}elseif( $response !== false ){
			if( is_array($response) ) $response = (object) $response;
			if( is_object($response) ){
				// activate or deal with sudden url changes softly
				if( $this->activated && empty($response->activated) && ($this->site !== $this->previous_site || $response->site !== $this->site) ){
					// save previous site info here in event of an unexpected site change, and do not deactivate unless specifically told to
					if( $response->site !== $this->site ) {
						$this->previous_site = $this->site;
					}
					$this->activated = empty($response->deactivated);
					$this->valid = false;
					$response->until = time() - 86400;
				}else{
					$this->activated = !empty($response->activated);
					$this->valid = !empty($response->valid);
				}
				$this->deactivated = !empty($response->deactivated);
				$this->dev = !empty($response->dev);
				$this->error = !empty($response->error) ? $response->error : false;
				$this->error_response = !empty($response->error_response) ? $response->error_response : false;
				$this->site = !empty($response->site) ? $response->site : get_bloginfo('url');
				// previous site check/cancellation
				if( !empty($response->previous_site) ) $this->previous_site = $response->previous_site;
				if( $this->previous_site == $this->site ) $this->previous_site = null;
				//key and until don't need to get overwritten if $response is empty
				if( !empty($response->key) ) $this->key = $response->key;
				if( !empty($response->until) ) $this->until = absint($response->until);
			}elseif( is_string($response) && $response && empty($this->key) ){
				//we're loading the key for the first time, or using legacy option data which was previously just a key
				$this->key = str_pad($response, 40, '0', STR_PAD_LEFT); //old format padded for consistency
			}
		}
		if( $touch ) $this->checked = time();
		return $this;
	}
	
	public function delete(){
		delete_option( $this->option_name );
		$this->key = $this->activated = $this->valid = null;
	}
}