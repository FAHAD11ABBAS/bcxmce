<?php
namespace EM_Pro\Attendance;
use WP_REST_Response, EM_Ticket_Booking;

class API{
	
	public static function init(){
		add_action('rest_api_init', '\EM_Pro\Attendance\API::register_handler');
		add_filter('em_wp_localize_script', '\EM_Pro\Attendance\API::em_wp_localize_script',10,1);
	}
	
	public static function register_handler(){
		register_rest_route( 'events-manager/v1', '/attendance', array(
			array(
				'methods'  => 'GET,POST',
				'callback' => '\EM_Pro\Attendance\API::handler',
				'permission_callback' => '__return_true', // 5.5. compat
			)
		) );
	}
	
	public static function handler(){
		$result = array( 'result' => false );
		if( !empty($_REQUEST['uuid']) || !empty($_REQUEST['id']) ){
			// get the ticket booking and check caps
			$identifier = !empty($_REQUEST['uuid']) ? $_REQUEST['uuid'] : $_REQUEST['id'];
			$EM_Ticket_Booking = new EM_Ticket_Booking( $identifier );
			if( $EM_Ticket_Booking->ticket_booking_id ){
				if( $EM_Ticket_Booking->can_manage() ){
					$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : 'checkin';
					$result = Attendance::handle_action( $EM_Ticket_Booking, $action );
				}else {
					$result['message'] = 'You do not have permission to manage this ticket.';
				}
			}else{
				$result['message'] = 'Ticket not found.';
			}
		}else{
			$result['message'] = 'Missing POST variables. Identification is not possible. If you are visiting this page directly in your browser, this error does not indicate a problem, but simply means Events Manager is correctly set up and ready to receive communication for valid check-in requests.';
		}
		return new WP_REST_Response( $result, 200 );
	}
	
	/**
	 * Add extra localized JS options to the em_wp_localize_script filter.
	 * @param array $vars
	 * @return array
	 */
	public static function em_wp_localize_script( $vars ){
		$vars['attendance_api_url'] = get_rest_url( get_current_blog_id(), 'events-manager/v1/attendance' );
		return $vars;
	}
}
API::init();