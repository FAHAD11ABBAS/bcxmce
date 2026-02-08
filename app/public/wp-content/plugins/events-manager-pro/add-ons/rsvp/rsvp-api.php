<?php
namespace EM\Bookings\RSVP;
use EM_Booking;
use WP_REST_Response, WP_REST_Request;

class API{
	
	public static function init(){
		add_action('rest_api_init', array( static::class, 'register_handler' ) );
		add_filter('em_wp_localize_script', array( static::class, 'em_wp_localize_script' ) , 10, 1);
	}
	
	public static function register_handler(){
		register_rest_route( 'events-manager/v1', '/bookings/rsvp', array(
			array(
				'methods'  => 'POST',
				'callback' => array( static::class, 'handler' ),
				'permission_callback' => '__return_true', // 5.5. compat
			)
		) );
	}
	
	/**
	 * @param WP_REST_Request $data
	 *
	 * @return WP_REST_Response
	 */
	public static function handler( $data ){
		$result = array( 'success' => false );
		// check action is valid by mapping actions of all statuses to the status key
		$statuses = EM_Booking::get_rsvp_statuses();
		$actions = array();
		foreach( $statuses as $status_key => $status_data ){
			$actions[$status_data->action] = $status_key;
		}
		$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : false;
		if ( $action && isset($actions[$action]) ) {
			if ( !empty( $_REQUEST['nonce'] ) && ( !empty( $_REQUEST['uuid'] ) || !empty( $_REQUEST['id'] ) ) ) {
				// get the ticket booking and check caps
				$identifier = !empty( $_REQUEST['uuid'] ) ? $_REQUEST['uuid'] : $_REQUEST['id'];
				$EM_Booking = em_get_booking( $identifier );
				if ( $EM_Booking->booking_id ) {
					if ( wp_verify_nonce( $_REQUEST['nonce'], 'rsvp_' . $action ) ) {
						$EM_Booking->manage_override = true;
						$result['id'] = $EM_Booking->booking_id;
						$result['uuid'] = $EM_Booking->booking_uuid;
						$result['success'] = $EM_Booking->set_rsvp_status( $actions[$action] ); // we'll set this back lower down
						$result['status'] = $EM_Booking->get_rsvp_status();
						$result['message'] = $EM_Booking->feedback_message;
						$EM_Booking->manage_override = false;
					} else {
						$result['message'] = 'You do not have permission to manage this booking.';
					}
				} else {
					$result['message'] = 'Booking not found.';
				}
			} else {
				$result['message'] = 'Missing POST variables. Identification is not possible. If you are visiting this page directly in your browser, this error does not indicate a problem, but simply means Events Manager is correctly set up and ready to receive communication for valid check-in requests.';
			}
		} else {
			$result['message'] = 'Invalid action for RSVP.';
		}
		return new WP_REST_Response( $result, 200 );
	}
	
	/**
	 * Add extra localized JS options to the em_wp_localize_script filter.
	 * @param array $vars
	 * @return array
	 */
	public static function em_wp_localize_script( $vars ){
		$vars['rsvp'] = array( 'api_url' => get_rest_url( get_current_blog_id(), 'events-manager/v1/bookings/rsvp' ) );
		return $vars;
	}
}
API::init();