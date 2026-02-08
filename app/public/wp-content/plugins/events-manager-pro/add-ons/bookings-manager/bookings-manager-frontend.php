<?php
namespace EM_Pro;
use EM_Ticket_Booking, EM_Booking;

class Bookings_Manager_Frontend {
	
	public static $data = array();
	
	public static function init() {
		// rule flushes are handled by EM itself, we can just add our endpoints
		add_action( 'template_include', array(static::class, 'template_include'), 1 );
		add_action( 'init', array(static::class, 'add_endpoint') );
	}
	
	public static function add_endpoint(){
		//define('EM_FE_ENDPOINT_ALIAS', array('em-bookings', 'em-admin')); // add an array of possible aliases, mainly useful/recommended if you change your mind on the alias used
		$endpoint = get_option('dbem_bookings_manager_endpoint');
		add_rewrite_endpoint($endpoint, EP_ROOT);
		if( defined('EM_FE_ENDPOINT_ALIAS') && is_array(EM_FE_ENDPOINT_ALIAS) ){
			foreach( EM_FE_ENDPOINT_ALIAS as $alias ){
				add_rewrite_endpoint($alias, EP_ROOT);
			}
		}
	}
	
	public static function get_endpoint_url( $path = null ){
		$endpoint = get_option('dbem_bookings_manager_endpoint');
		$url = trailingslashit(get_home_url( null, $endpoint ));
		if( !empty($path) ){
			$path = preg_replace('/^\//', '', $path);
			$url .= $path;
		}
		return $url;
	}
	
	public static function template_include( $template ) {
		// if this is not a request for json or a singular object then bail
		global $wp_query;
		//echo '<pre>'.print_r($wp_query, true).'</pre>'; die();
		$endpoint = get_option('dbem_bookings_manager_endpoint');
		// check if we're on the endpoint, if not bail
		if ( !is_home() && !is_front_page() )  return $template;
		if( !isset( $wp_query->query_vars[$endpoint] ) ){
			if( !defined('EM_FE_ENDPOINT_ALIAS') ){
				return $template;
			}elseif( is_array(EM_FE_ENDPOINT_ALIAS) ){
				foreach( EM_FE_ENDPOINT_ALIAS as $alias ){
					if( isset( $wp_query->query_vars[$alias] ) ){
						$endpoint = $alias;
						$found = true;
						break;
					}
				}
				if( empty($found) ){
					return $template;
				}
			}
		}
		
		
		// check user logged in
		if( !is_user_logged_in() ){
			$login_url = wp_login_url($_SERVER['REQUEST_URI']);
			wp_redirect($login_url);
			die();
		}
		
		// OK, we're here, let's get information about this booking or ticket
		$path = $wp_query->query_vars[$endpoint];
		if( preg_match('/^(ticket|booking)\/([a-zA-Z0-9]{32})\/?/', $path, $match) ){
			$item = $match[1];
			static::$data['id'] = $match[2];
			if( $item == 'ticket' ){
				$EM_Ticket_Booking = new EM_Ticket_Booking(static::$data['id']);
				if( $EM_Ticket_Booking->can_manage('manage_bookings', 'manage_others_bookings') ){
					static::$data['ticket_booking'] = $EM_Ticket_Booking;
					static::$data['view'] = 'ticket_booking';
				}
			}elseif( $item == 'booking' ){
				$EM_Booking = new EM_Booking(static::$data['id']);
				if( $EM_Booking->can_manage('manage_bookings', 'manage_others_bookings') ){
					static::$data['booking'] = $EM_Booking;
					static::$data['view'] = 'booking';
				}
			}
		}
		
		// Load template from either plugins directory or load our own core one
		$template = emp_locate_template('bookings-manager/template.php', false);
		if( !file_exists( $template ) ) {
			$template = dirname( __FILE__ ) . '/template/template.php';
		}
		include($template);
		die();
	}
}
Bookings_Manager_Frontend::init();