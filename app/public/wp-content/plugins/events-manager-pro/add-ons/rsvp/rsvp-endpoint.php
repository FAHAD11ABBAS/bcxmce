<?php
namespace EM\Bookings\RSVP;
use EM_Booking;

class Endpoint {
	
	public static $data = array();
	
	public static function init() {
		// rule flushes are handled by EM itself, we can just add our endpoints
		add_action( 'template_include', array( static::class, 'template_include' ) );
		add_action( 'init', array( static::class, 'add_endpoint' ) );
		add_filter( 'em_booking_output_placeholder', array( static::class, 'em_booking_output_placeholder'), 1, 4 ); //add booking placeholders
		add_action('em_rsvp_template_scripts', array( static::class, 'em_rsvp_template_scripts' ) );
	}
	
	public static function add_endpoint(){
		//define('EM_RSVP_ENDPOINT_ALIAS', array('em-bookings', 'em-admin')); // add an array of possible aliases, mainly useful/recommended if you change your mind on the alias used
		$endpoint = get_option('dbem_bookings_rsvp_endpoint_url');
		add_rewrite_endpoint($endpoint, EP_ROOT);
		if( defined('EM_RSVP_ENDPOINT_ALIAS') && is_array(EM_RSVP_ENDPOINT_ALIAS) ){
			foreach( EM_RSVP_ENDPOINT_ALIAS as $alias ){
				add_rewrite_endpoint($alias, EP_ROOT);
			}
		}
	}
	
	public static function get_endpoint_url( $path = null ){
		$endpoint = get_option('dbem_bookings_rsvp_endpoint_url');
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
		$endpoint = get_option('dbem_bookings_rsvp_endpoint_url');
		
		if ( (!is_home() && !is_front_page()) ) return $template;
		if( !isset( $wp_query->query_vars[$endpoint] ) ){
			if( !defined('EM_RSVP_ENDPOINT_ALIAS') ){
				return $template;
			}elseif( is_array(EM_RSVP_ENDPOINT_ALIAS) ){
				foreach( EM_RSVP_ENDPOINT_ALIAS as $alias ){
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
		
		// OK, we're here, let's get information about this booking or ticket
		$path = $wp_query->query_vars[$endpoint];
		if( preg_match('/^([a-zA-Z0-9\-]+)\/?/', $path, $match) ){
			static::$data['rsvp_id'] = $match[1];
			// get the booking by rsvp_id
			global $wpdb;
			$sql = $wpdb->prepare('SELECT booking_id FROM ' . EM_BOOKINGS_META_TABLE . ' WHERE meta_key=%s and meta_value=%s', 'rsvp_id', $match[1]);
			$booking_id = $wpdb->get_var( $sql );
			if ( $booking_id ) {
				$EM_Booking = new EM_Booking( $booking_id );
				
				// check user logged in
				if( get_option('dbem_bookings_rsvp_login') && $EM_Booking->person_id > 0 && !is_user_logged_in() ){
					$login_url = wp_login_url($_SERVER['REQUEST_URI']);
					wp_redirect($login_url);
					die();
				}
				
				// proceed with booking
				if( $EM_Booking->booking_id ) {
					static::$data['booking'] = $EM_Booking;
				}
			}
		}
		
		// Load template from either plugins directory or load our own core one
		$template = emp_locate_template('rsvp/template.php', true);
	}
	
	
	/**
	 * Adds extra placeholders to the booking email. Called by em_booking_output_placeholder filter, added in this object init() function.
	 *
	 * @param string $result
	 * @param EM_Booking $EM_Booking
	 * @param string $placeholder
	 * @param string $target
	 * @return string
	 */
	public static function em_booking_output_placeholder( $result, $EM_Booking, $placeholder, $target='html' ){
		if( $placeholder == "#_BOOKING_RSVP_URL" ){
			$result = static::get_rsvp_url( $EM_Booking );
		}
		return $result;
	}
	
	public static function get_rsvp_url( $EM_Booking ){
		return static::get_endpoint_url() . static::get_endpoint_id( $EM_Booking );
	}
	
	/**
	 * Get a unique ID for this booking, based on event_id and booking ID to avoid collisions
	 *
	 * @param \EM_Booking $EM_Booking
	 *
	 * @return void
	 */
	public static function get_endpoint_id( $EM_Booking ) {
		global $wpdb;
		// check if booking has an id already, if so return that already
		if( !empty($EM_Booking->booking_meta['rsvp_id']) ) {
			$id = $EM_Booking->booking_meta['rsvp_id'];
		} else {
			// create a random id until unique and save it to the db
			$length = defined('EM_RSVP_ID_LENGTH') ? EM_RSVP_ID_LENGTH : 8;
			$id = self::generate_unique_id( $length );
			do {
				$sql = $wpdb->prepare( 'SELECT booking_id FROM ' . EM_BOOKINGS_META_TABLE . ' WHERE meta_key=%s and meta_value=%s', 'rsvp_id', $id );
				$booking_id = $wpdb->get_var( $sql );
			} while ( $booking_id !== null );
			$EM_Booking->update_meta('rsvp_id', $id);
		}
		return $id;
	}
	
	public static function generate_unique_id ( $n ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-';
		$index = rand(0, strlen($characters) - 2); // omit the dash first time around
		$id = '';
		for ($i = 0; $i < $n && $index != '-'; $i++) {
			$id .= $characters[$index];
			$index = rand(0, strlen($characters) - 1);
		}
		return $id;
	}
	
	public static function em_rsvp_template_scripts(){
		\EM_Scripts_and_Styles::localize_script(); // get the localized script here, saved in the global below, WP's localization won't ever get hit
		global $em_localized_js;
		echo 'const EM = '.json_encode($em_localized_js) . ';'."\r\n";
		include('rsvp.js');
	}
	
}
Endpoint::init();