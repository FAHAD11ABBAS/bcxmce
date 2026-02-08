<?php
namespace EM\Automation\Actions;

/**
 * Sends a webhook with event or booking payload
 */
class Webhook extends Action {
	
	public static $type = 'webhook';
	public static $supported_contexts = array('event','booking');
	
	/**
	 * @var string URL to send webhook to
	 */
	public $url;
	/**
	 * @var array Array of headers to be sent
	 */
	public $headers = array();
	
	public function __construct($action = array()) {
		if( parent::__construct($action) && !empty($action['data']) ){
			$data = $action['data'];
			if( !empty($data['url']) ) $this->url = $data['url'];
		}
	}
	
	public static function init(){
		parent::init();
		// add ajax listener to test a specific webhook
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] === 'em_automation_action_webhook_test' ) {
			add_action('wp_ajax_em_automation_action_webhook_test', '\EM\Automation\Actions\Webhook::test');
		}
	}
	
	public static function test(){
		if( !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'em-automation-webhook-test') ){
			if( !wp_http_validate_url($_REQUEST['url']) ){
				esc_html_e('Please enter a valid URL for your webhook.', 'em-pro');
				die();
			}
			require_once( dirname(dirname(__FILE__)) .'/test-data.php');
			if( $_REQUEST['context'] == 'bookings' || $_REQUEST['context'] == 'booking' ){
				//We would send a booking
				$data = em_automation_get_test_data('booking');
			}elseif( $_REQUEST['context'] == 'event' ){
				$data = em_automation_get_test_data('event');
			}else{
				echo 'Not sure what we are meant to be sending... have you chosen the output object to pass onto actions?';
				die();
			}
			if( !empty($data) ) {
				$headers = array_merge(array('Content-Type' => 'application/json; charset=utf-8'));
				$remote_args = array('headers' => $headers, 'body' => json_encode($data), 'method' => 'POST', 'data_format' => 'body',);
				$response = wp_remote_post($_REQUEST['url'], $remote_args);
				if (is_wp_error($response)) {
					echo 'Error : ' . $response->get_error_message();
				} else {
					$result = wp_remote_retrieve_response_code($response);
					echo 'Success - Received response code ' . $result;
				}
			}
		}else{
			echo 'Cannot verify nonce and not attempting to test webhook, please reload and try agian.';
		}
		die();
	}
	
	public static function handle( $object, $action_data = array(), $runtime_data = array() ){
		global $wpdb;
		$action = new Webhook($action_data);
		$emails = array();
		if( $object instanceof \EM_Event ){
			$EM_Event = $object; /* @var \EM_Event $EM_Event */
			$data = $EM_Event->to_api();
		}elseif( $object instanceof \EM_Booking ) {
			$EM_Booking = $object;
			$data = $EM_Booking->to_api();
		}
		if( !empty($data) ){
			$headers = array_merge( $action->headers, array('Content-Type' => 'application/json; charset=utf-8'));
			$remote_args = array(
				'headers'     => $headers,
				'body'        => json_encode($data),
				'method'      => 'POST',
				'data_format' => 'body',
			);
			$response = wp_remote_post( $action->url, $remote_args );
			if( is_wp_error($response) ){
				\EM_Pro::log( array('Error: Remote webhook error to '.$action->url, $response), 'automation-action-webhook' );
			}
		}
	}
	
	public static function get_name(){
		return esc_html__('Webhook', 'em-pro');
	}
	
	public static function get_description(){
		return esc_html__('Sends a JSON-encoded payload to the supplied URL', 'em-pro');
	}
}
Webhook::init();