<?php
namespace EM\Payments\Offline;
use EM_Booking, EM_Bookings, EM_Object, EM_Multiple_Bookings, EM_Multiple_Booking, EMP_Logs, EM_Pro, EM;

/**
 * This class is a parent class which gateways should extend. There are various variables and functions that are automatically taken care of by
 * EM_Gateway, which will reduce redundant code and unecessary errors across all gateways. You can override any function you want on your gateway,
 * but it's advised you read through before doing so.
 *
 */
class Gateway extends \EM\Payments\Gateway {

	public static $gateway = 'offline';
	public static $title = 'Offline';
	public static $status = 5;
	public static $button_enabled = true;
	public static $count_pending_spaces = true;
	public static $reserve_pending_spaces = false; // user can select this in settings
	public static $can_manually_approve = false;
	public static $supports_multiple_bookings = true;
	public static $supports_manual_bookings = true;

	/**
	 * Sets up gateway and registers actions/filters
	 */
	public static function init() {
		parent::init();
		add_action('init',array(static::class, 'actions'),10);
		
		//Booking Interception
		add_filter('em_booking_set_status',array(static::class,'em_booking_set_status'),1,2);
		add_filter('em_bookings_pending_count', array(static::class, 'em_bookings_pending_count'),1,1);
		add_filter('em_wp_localize_script', array(static::class,'em_wp_localize_script'),1,1);
		add_action('em_bookings_single_metabox_footer', array(static::class, 'add_payment_form'),1,1); //add payment to booking
		
		// add confirmation messages for booking tables
		add_filter('em_bookings_table_booking_actions_data_5', array( static::class, 'em_bookings_table_booking_actions_data_5' ), 10, 2);
		add_filter('em_bookings_table_action_messages', array( static::class, 'em_bookings_table_action_messages' ), 10, 2);
		add_filter('em_bookings_table_get_bulk_action_message', array( static::class, 'em_bookings_table_get_bulk_action_message' ), 10, 3);
		
		
	}
	
	/**
	 * Run on init, actions that need taking regarding offline bookings are caught here, e.g. registering manual bookings and adding payments 
	 */
	public static function actions(){
		global $EM_Notices, $EM_Booking;
		//Check if manual payment has been added
		if( !empty($_REQUEST['booking_id']) && !empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce'])){
			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
			if( $_REQUEST['action'] == 'gateway_add_payment' && is_object($EM_Booking) && wp_verify_nonce($_REQUEST['_wpnonce'], 'gateway_add_payment') ){
				if( !empty($_REQUEST['transaction_total_amount']) && is_numeric($_REQUEST['transaction_total_amount']) ){
					static::record_transaction($EM_Booking, $_REQUEST['transaction_total_amount'], get_option('dbem_bookings_currency'), current_time('mysql', true), '', 'Completed', $_REQUEST['transaction_note']);
					$string = __('Payment has been registered.','em-pro');
					$total = $EM_Booking->get_total_paid();
					if( $total >= $EM_Booking->get_price() ){
						$EM_Booking->approve();
						$string .= " ". __('Booking is now fully paid and confirmed.','em-pro');
					}
					$EM_Notices->add_confirm($string,true);
					do_action('em_payment_processed', $EM_Booking, static::class);
					wp_redirect(em_wp_get_referer());
					exit();
				}else{
					$EM_Notices->add_error(__('Please enter a valid payment amount. Numbers only, use negative number to credit a booking.','em-pro'));
					unset($_REQUEST['action']);
					unset($_POST['action']);
				}
			}
		}
	}
	
	/**
	 * Adds offline localized message for use in JS
	 * @param array $vars
	 * @return array
	 */
	public static function em_wp_localize_script($vars){
		if( is_user_logged_in() && get_option('dbem_rsvp_enabled') ){
			$vars['offline_confirm'] = __('Be aware that by approving a booking awaiting payment, a full payment transaction will be registered against this booking, meaning that it will be considered as paid.','em-pro');
		}
		return $vars;
	}
	
	public static function is_live_mode ( $check_limited = true ) {
		return true;
	}
	
	public static function is_test_mode ( $check_limited = true ) {
		return false;
	}
	
	/*
	 * --------------------------------------------------
	 * Bookings Table
	 * --------------------------------------------------
	 */
	
	/**
	 * @param string[] $messages
	 * @param \EM_Bookings_Table $EM_Bookings_Table
	 *
	 * @return string[]
	 */
	public static function em_bookings_table_action_messages ( $messages, $EM_Bookings_Table ) {
		$messages['approve_offline'] = __('Be aware that by approving a booking awaiting payment, a full payment transaction will be registered against this booking, meaning that it will be considered as paid.','em-pro');
		return $messages;
	}
	
	public static function em_bookings_table_get_bulk_action_message ( $message, $action, $context ) {
		if( $action === 'approve' ) {
			if( $message ) $message .= "\n\n";
			$message .= __('If you have selected any bookings awaiting manual payment, a full payment transaction will be registered against this booking, meaning that it will be considered as paid.','em-pro');
		}
		return $message;
	}

	public static function em_bookings_table_booking_actions_data_5( $actions, $EM_Booking ){
		$gateway_used = $EM_Booking->booking_meta['gateway'] ?? false;
		if ( $gateway_used === 'offline' && !empty($actions['approve']) ) {
			$actions['approve']['data']['confirmation'] = 'approve_offline';
		}
		return $actions;
	}
	
	/* 
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */
	
	
	/**
	 * Intercepts return JSON and adjust feedback messages when booking with this gateway.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	public static function booking_form_feedback( $return, $EM_Booking = false ){
		if( !empty($return['result']) && !empty($EM_Booking->booking_meta['gateway']) && !empty($EM_Booking->booking_status) ){ //check emtpies
			if( $EM_Booking->booking_status == 5 && static::uses_gateway($EM_Booking) ){ //check values
				$return['message'] = get_option('em_'.static::$gateway.'_booking_feedback');
				if( !empty($EM_Booking->email_not_sent) ){
					$return['message'] .=  ' '.get_option('dbem_booking_feedback_nomail');
				}
				return apply_filters('em_gateway_offline_booking_add', $return, $EM_Booking->get_event(), $EM_Booking);
			}
		}						
		return $return;
	}
	
	/**
	 * Sets booking status and records a full payment transaction if new status is from pending payment to completed. 
	 * @param int $status
	 * @param EM_Booking $EM_Booking
	 */
	public static function em_booking_set_status($result, $EM_Booking){
		if( !emp_is_manual_booking() && $EM_Booking->booking_status == 1 && $EM_Booking->previous_status == static::$status && static::uses_gateway($EM_Booking) && (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], array('gateway_add_payment')) ) ){
			static::record_transaction($EM_Booking, $EM_Booking->get_price(false,false,true), get_option('dbem_bookings_currency'), current_time('mysql'), '', 'Completed', '');
		}
		return $result;
	}
	
	public static function em_bookings_pending_count($count){
		return $count + EM_Bookings::count(array('status'=>'5'));
	}
	
	/* 
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing offline bookings
	 * --------------------------------------------------
	 */
	
	/**
	 * Adds a payment form which can be used to submit full or partial offline payments for a booking. 
	 */
	public static function add_payment_form() {
		?>
		<div id="em-gateway-payment" class="stuffbox">
			<h3>
				<?php _e('Add Offline Payment', 'em-pro'); ?>
			</h3>
			<div class="inside">
				<div>
					<form method="post" action="" style="padding:5px;">
						<table class="form-table">
							<tbody>
							  <tr valign="top">
								  <th scope="row"><?php _e('Amount', 'em-pro') ?></th>
									  <td><input type="text" name="transaction_total_amount" value="<?php if(!empty($_REQUEST['transaction_total_amount'])) echo esc_attr($_REQUEST['transaction_total_amount']); ?>" />
									  <br />
									  <em><?php _e('Please enter a valid payment amount (e.g. 10.00). Use negative numbers to credit a booking.','em-pro'); ?></em>
								  </td>
							  </tr>
							  <tr valign="top">
								  <th scope="row"><?php _e('Comments', 'em-pro') ?></th>
								  <td>
										<textarea name="transaction_note"><?php if(!empty($_REQUEST['transaction_note'])) echo esc_attr($_REQUEST['transaction_note']); ?></textarea>
								  </td>
							  </tr>
							</tbody>
						</table>
						<input type="hidden" name="action" value="gateway_add_payment" />
						<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('gateway_add_payment'); ?>" />
						<input type="hidden" name="redirect_to" value="<?php echo (!empty($_REQUEST['redirect_to'])) ? $_REQUEST['redirect_to']:em_wp_get_referer(); ?>" />
						<input type="submit" class="<?php if( is_admin() ) echo 'button-primary'; ?>" value="<?php _e('Add Offline Payment', 'em-pro'); ?>" />
					</form>
				</div>					
			</div>
		</div> 
		<?php
	}
	
	/* 
	 * --------------------------------------------------
	 * Settings pages and functions
	 * --------------------------------------------------
	 */
	
	/**
	 * Checks an EM_Booking object and returns whether or not this gateway is/was used in the booking.
	 * @param EM_Booking $EM_Booking
	 * @return boolean
	 */
	public static function uses_gateway($EM_Booking){
	    //for all intents and purposes, if there's no gateway assigned but this booking status matches, we assume it's offline
		return parent::uses_gateway($EM_Booking) || ( empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_status == static::$status );
	}
}
Gateway::init();
?>