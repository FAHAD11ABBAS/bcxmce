<?php
namespace EM\Payments\Paypal\Legacy;

use EM_Booking, EM_Event, EM_Multiple_Bookings, EM_Pro, EM;

class Gateway extends EM\Payments\Gateway {
	//change these properties below if creating a new gateway, not advised to change this for PayPal
	public static $legacy = true;
	public static $gateway = 'paypal';
	public static $title = 'PayPal (Payments Standard)';
	public static $status = 4;
	public static $status_txt = 'Awaiting PayPal Payment';
	public static $count_pending_spaces = true;
	
	public static $button_enabled = true;
	
	public static $rest_api = false;
	public static $payment_return = true;
	
	public static $payment_flow = array(
		'redirect' => true,
		'redirect-success' => true,
		'redirect-cancel' => true,
	);
	public static $has_timeout = true;
	public static $supports_multiple_bookings = true;
	
	public static $transaction_detail = array(
		'https://www.paypal.com/activity/payment/%s',
		'https://www.sandbox.paypal.com/activity/payment/%s',
		'paypal.com'
	);

	/**
	 * Sets up gateaway and adds relevant actions/filters 
	 */
	public static function init(){
		//Booking Interception
		static::$reserve_pending_spaces = get_option('em_'.static::$gateway.'_reserve_pending'); // default to true, overriden by option
		static::$has_timeout = (boolean) self::get_option('booking_timeout');
		parent::init();
		if(static::is_active()) {
			//Gateway-Specific
			add_filter('em_my_bookings_booking_actions', array(static::class,'em_my_bookings_booking_actions'),1,2);
		}
		add_filter('default_option_em_paypal_mode', function( $option ){ if( !$option ) return get_option('em_paypal_status', 'sandbox'); });
	}

	public static function wp_loaded(){
		static::$status_txt = __('Awaiting PayPal Payment','em-pro');
		parent::wp_loaded();
	}
	
	/* 
	 * --------------------------------------------------
	 * Booking Interception - functions that modify booking object behaviour
	 * --------------------------------------------------
	 */
	
	/**
	 * Intercepts return data after a booking has been made and adds paypal vars, modifies feedback message.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	public static function booking_form_feedback( $return, $EM_Booking = false ){
		//Double check $EM_Booking is an EM_Booking object and that we have a booking awaiting payment.
		if( is_object($EM_Booking) ){
			if( !empty($return['result']) && $EM_Booking->get_price() > 0 && $EM_Booking->booking_status == static::$status ){
				$return['message'] = get_option('em_'. static::$gateway . '_booking_feedback');	
				$paypal_url = static::get_paypal_url();	
				$paypal_vars = static::get_paypal_vars($EM_Booking);					
				$paypal_return = array('paypal_url'=>$paypal_url, 'paypal_vars'=>$paypal_vars);
				$return = array_merge($return, $paypal_return);
			}else{
				//returning a free message
				$return['message'] = get_option('em_'. static::$gateway . '_booking_feedback_free');
			}
		}
		return $return;
	}
	
	/**
	 * Adds the PayPal booking button, given the request should have been successful if the booking form feedback msg was called.	 *
	 * @param string $feedback
	 * @return string
	 */
	public static function booking_form_feedback_fallback( $feedback ){
		global $EM_Booking;
		if( is_object($EM_Booking) ){
			$feedback .= "<br />" . __('To finalize your booking, please click the following button to proceed to PayPal.','em-pro'). static::em_my_bookings_booking_actions('',$EM_Booking);
		}
		return $feedback;
	}
	
	/**
	 * Triggered by the em_booking_add_yourgateway action, hooked in EM_Gateway. Overrides EM_Gateway to account for non-ajax bookings (i.e. broken JS on site).
	 * @param EM_Event $EM_Event
	 * @param EM_Booking $EM_Booking
	 * @param boolean $post_validation
	 */
	public static function booking_add($EM_Event, $EM_Booking, $post_validation = false){
		parent::booking_add($EM_Event, $EM_Booking, $post_validation);
		if( !defined('DOING_AJAX') ){ //we aren't doing ajax here, so we should provide a way to edit the $EM_Notices ojbect.
			add_action('option_dbem_booking_feedback', array(static::class, 'booking_form_feedback_fallback'));
		}
	}
	
	/* 
	 * --------------------------------------------------
	 * Booking UI - modifications to booking pages and tables containing paypal bookings
	 * --------------------------------------------------
	 */
	
	/**
	 * Instead of a simple status string, a resume payment button is added to the status message so user can resume booking from their my-bookings page.
	 * @param string $message
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	public static function em_my_bookings_booking_actions( $message, $EM_Booking){
	    global $wpdb;
	    //if in multiple booking mode, switch the booking for the main booking and treat that as our booking
	    if( get_option('dbem_multiple_bookings') ){
	    	$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking($EM_Booking);
	    	if( $EM_Multiple_Booking !== false ) $EM_Booking = $EM_Multiple_Booking;
	    }
		if(static::uses_gateway($EM_Booking) && $EM_Booking->booking_status == static::$status){
		    //first make sure there's no pending payments
		    $pending_payments = $wpdb->get_var('SELECT COUNT(*) FROM '.EM_TRANSACTIONS_TABLE. " WHERE booking_id='".$EM_Booking->booking_id."' AND transaction_gateway='".static::$gateway."' AND transaction_status='Pending'");
		    if( $pending_payments == 0 ){
				//user owes money!
				$paypal_vars = static::get_paypal_vars($EM_Booking);
				$form = '<form action="'.static::get_paypal_url().'" method="post">';
				foreach($paypal_vars as $key=>$value){
					$form .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
				}
				$form .= '<input type="submit" value="'.__('Resume Payment','em-pro').'">';
				$form .= '</form>';
				$message .= $form;
		    }
		}
		return $message;
	}
	
	/**
	 * Outputs some JavaScript during the EM_Gateways::payment_form() function, which is run inside a script html tag, located in gateways/gateway.paypal.js
	 */
	public static function payment_form_js( $id ){
		include(dirname(__FILE__).'/gateway.paypal.js');		
	}
	
	/*
	 * --------------------------------------------------
	 * PayPal Functions - functions specific to paypal payments
	 * --------------------------------------------------
	 */
	
	/**
	 * Retreive the PayPal vars needed to send to the gatway to proceed with payment
	 * @param EM_Booking $EM_Booking
	 */
	public static function get_paypal_vars($EM_Booking){
		$notify_url = static::get_payment_return_url();
		$paypal_vars = array(
			'business' => trim(get_option('em_'. static::$gateway . "_email" )),
			'cmd' => '_cart',
			'upload' => 1,
			'currency_code' => get_option('dbem_bookings_currency', 'USD'),
			'notify_url' =>$notify_url,
			'invoice' => static::get_invoice_id($EM_Booking),
			'charset' => 'UTF-8',
		    'bn'=>'NetWebLogic_SP'
		);
		if( get_option('em_'. static::$gateway . "_lc" ) ){
		    $paypal_vars['lc'] = get_option('em_'. static::$gateway . "_lc" );
		}
		//address fields`and name/email fields to prefill on checkout page (if available)
		$paypal_vars['email'] = $EM_Booking->get_person()->user_email;
		$paypal_vars['first_name'] = $EM_Booking->get_person()->first_name;
		$paypal_vars['last_name'] = $EM_Booking->get_person()->last_name;
        if( \EM\Payments\Gateways::get_customer_field('address', $EM_Booking) != '' ) $paypal_vars['address1'] = \EM\Payments\Gateways::get_customer_field('address', $EM_Booking);
        if( \EM\Payments\Gateways::get_customer_field('address_2', $EM_Booking) != '' ) $paypal_vars['address2'] = \EM\Payments\Gateways::get_customer_field('address_2', $EM_Booking);
        if( \EM\Payments\Gateways::get_customer_field('city', $EM_Booking) != '' ) $paypal_vars['city'] = \EM\Payments\Gateways::get_customer_field('city', $EM_Booking);
        if( \EM\Payments\Gateways::get_customer_field('state', $EM_Booking) != '' ) $paypal_vars['state'] = \EM\Payments\Gateways::get_customer_field('state', $EM_Booking);
        if( \EM\Payments\Gateways::get_customer_field('zip', $EM_Booking) != '' ) $paypal_vars['zip'] = \EM\Payments\Gateways::get_customer_field('zip', $EM_Booking);
        if( \EM\Payments\Gateways::get_customer_field('country', $EM_Booking) != '' ) $paypal_vars['country'] = \EM\Payments\Gateways::get_customer_field('country', $EM_Booking);
        
		if( get_option('em_'. static::$gateway . "_return" ) != "" ){
			$paypal_vars['return'] = get_option('em_'. static::$gateway . "_return" );
		}
		if( get_option('em_'. static::$gateway . "_cancel_return" ) != "" ){
			$paypal_vars['cancel_return'] = get_option('em_'. static::$gateway . "_cancel_return" );
		}
		if( get_option('em_'. static::$gateway . "_format_logo" ) !== false ){
			$paypal_vars['image_url'] = get_option('em_'. static::$gateway . "_format_logo" );
		}
		if( get_option('em_'. static::$gateway . "_border_color" ) !== false ){
			$paypal_vars['cpp_cart_border_color'] = get_option('em_'. static::$gateway . "_format_border" );
		}
		$count = 1;
		//calculate discounts and surcharges if there are any
		$discount = $EM_Booking->get_price_adjustments_amount('discounts', 'pre') + $EM_Booking->get_price_adjustments_amount('discounts', 'post');
		$surcharges = $EM_Booking->get_price_adjustments_amount('surcharges', 'pre') + $EM_Booking->get_price_adjustments_amount('surcharges', 'post');
		/*
		 * IMPORTANT - If there's any adjustments to the price, we need to include one single price.
		 * The reason for this is because PayPal simply can't handle including prices per tickets without tax and provide 100% accuracy, 
		 * and if not excluding tax from item prices, pre tax adjustments aren't possible as separate items in the paypal checkout cart.
		 * Providing one item will avoid any issues, with the trade-off of a less detailed shopping cart checkout.
		 */
		if( $discount > 0 || $surcharges > 0 ){
			$description = $EM_Booking->get_event()->event_name;
			if( $EM_Booking->get_spaces() > 1 ){
				$description = $EM_Booking->get_spaces() . ' x ' . $description;
			}
			$paypal_vars['item_name_1'] = substr($description, 0, 126);
			$paypal_vars['amount_1'] = $EM_Booking->get_price();
		} else {
			if( $EM_Booking->get_price_taxes() > 0 && !get_option('em_'. static::$gateway . "_inc_tax" ) ){
				$paypal_vars['tax_cart'] = round($EM_Booking->get_price_taxes(), 2);
			}
			foreach( $EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Bookings ){ /* @public static $EM_Ticket_Bookings EM_Ticket_Bookings */
			    //divide price by spaces for per-ticket price by getting first ticket booking and getting the price that way
				$EM_Ticket_Booking = reset($EM_Ticket_Bookings->tickets_bookings); /* @var EM_Ticket_Booking $EM_Ticket_Booking */
				if( $EM_Ticket_Booking ){
				    //we divide this way rather than by $EM_Ticket because that can be changed by user in future, yet $EM_Ticket_Booking will change if booking itself is saved.
				    if( !get_option('em_'. static::$gateway . "_inc_tax" ) ){
				        $price = $EM_Ticket_Booking->get_price();
				    }else{
				        $price = $EM_Ticket_Booking->get_price_with_taxes();
				    }
					if( $price > 0 ){
						$ticket_name = wp_kses_data($EM_Ticket_Bookings->get_ticket()->name);
						$paypal_vars['item_name_'.$count] = substr($ticket_name, 0, 126);
						$paypal_vars['quantity_'.$count] = $EM_Ticket_Bookings->get_spaces();
						$paypal_vars['amount_'.$count] = round($price,2);
						$count++;
					}
				}
			}
		}
		return apply_filters('em_gateway_paypal_get_paypal_vars', $paypal_vars, $EM_Booking, static::class);
	}
	
	/**
	 * gets paypal gateway url (sandbox or live mode)
	 * @returns string 
	 */
	public static function get_paypal_url(){
		return ( static::is_sandbox() ) ? 'https://www.sandbox.paypal.com/cgi-bin/webscr':'https://www.paypal.com/cgi-bin/webscr';
	}
	/**
	 * Runs when PayPal sends IPNs to the return URL provided during bookings and EM setup. Bookings are updated and transactions are recorded accordingly. 
	 */
	public static function handle_payment_return() {
		// PayPal IPN handling code
		if ((isset($_POST['payment_status']) || isset($_POST['txn_type'])) && isset($_POST['custom'])) {
			
		    //Verify IPN request
			if (get_option( 'em_'. static::$gateway . "_status" ) == 'live') {
				$domain = 'https://www.paypal.com/cgi-bin/webscr';
			} else {
				$domain = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
			}

			$req = 'cmd=_notify-validate';
			foreach ($_POST as $k => $v) {
				$req .= '&' . $k . '=' . urlencode(stripslashes($v));
			}
			
			@set_time_limit(60);

			//add a CA certificate so that SSL requests always go through
			add_action('http_api_curl',array( static::class, 'payment_return_local_ca_curl'),10,1);
			//using WP's HTTP class
			$args = apply_filters('em_paypal_ipn_remote_get_args', array('httpversion'=>'1.1','user-agent'=>'EventsManagerPro/'.EMP_VERSION));
			$ipn_verification_result = wp_remote_get($domain.'?'.$req, $args);
			remove_action('http_api_curl',array( static::class, 'payment_return_local_ca_curl'),10,1);
			
			if ( !is_wp_error($ipn_verification_result) && $ipn_verification_result['body'] == 'VERIFIED' ) {
				//log ipn request if needed, then move on
				$status_log = "IPN successfully received & verified for {$_POST['mc_gross']} {$_POST['mc_currency']} - {$_POST['payment_status']} (TXN ID {$_POST['txn_id']}) - Invoice: {$_POST['invoice']}";
				if( !empty($_POST['custom']) ) $status_log .= " - Custom Info: {$_POST['custom']}";
				EM_Pro::log( $status_log, 'paypal');
			}else{
			    //log error if needed, send error header and exit
				EM_Pro::log( array('IPN Verification Error', 'WP_Error'=> $ipn_verification_result, '$_POST'=> $_POST, '$req'=>$domain.'?'.$req), 'paypal' );
			    header('HTTP/1.0 502 Bad Gateway');
			    exit;
			}
			//if we get past this, then the IPN went ok
			
			// handle cases that the system must ignore
			$new_status = false;
			//Common variables
			$timestamp = date('Y-m-d H:i:s', strtotime($_POST['payment_date']));
			if( !empty($_POST['invoice']) ){
			    $invoice_values = explode('#', $_POST['invoice']);
			    $booking_id = $invoice_values[1];
			    $EM_Booking = em_get_booking($booking_id);
			    $transaction_match = $_POST['invoice'] == static::get_invoice_id($EM_Booking);
			}else{
			    //legacy checking, newer bookings should have a unique invoice number
    			$custom_values = explode(':',$_POST['custom']);
    			$booking_id = $custom_values[0];
			    $EM_Booking = em_get_booking($booking_id);
			    $transaction_match = count($custom_values) == 2;
			}
			if( !empty($EM_Booking->booking_id) && !empty($transaction_match) ){
				//booking exists
				$EM_Booking->manage_override = true; //since we're overriding the booking ourselves.
				$user_id = $EM_Booking->person_id;
				
				// process PayPal response
				static::handle_payment_status($EM_Booking, $_POST['mc_gross'], $_POST['payment_status'], $_POST['mc_currency'], $_POST['txn_id'], $timestamp, $_POST);
				EM_Pro::log( array('Valid IPN Request Received & Processed', '$_POST'=> $_POST, '$booking_id' => $booking_id), 'paypal' );
			}else{
				if( is_numeric($booking_id) && ($_POST['payment_status'] == 'Completed' || $_POST['payment_status'] == 'Processed') ){
					$message = apply_filters('em_gateway_paypal_bad_booking_email',"
A Payment has been received by PayPal for a non-existent booking. 

It may be that this user's booking has timed out yet they proceeded with payment at a later stage. 
							
In some cases, it could be that other payments not related to Events Manager are triggering this error. If that's the case, you can prevent this from happening by changing the URL in your IPN settings to:

". get_home_url() ." 

To refund this transaction, you must go to your PayPal account and search for this transaction:

Transaction ID : %transaction_id%
Email : %payer_email%

When viewing the transaction details, you should see an option to issue a refund.

If there is still space available, the user must book again.

Sincerely,
Events Manager
					", $booking_id, 0);
					$message  = str_replace(array('%transaction_id%','%payer_email%'), array($_POST['txn_id'], $_POST['payer_email']), $message);
					wp_mail(get_option('em_'. static::$gateway . "_email" ), __('Unprocessed payment needs refund'), $message);
					EM_Pro::log( array('Payment received for non-existent booking.', '$_POST'=> $_POST, '$booking_id' => $booking_id), 'paypal' );
				}else{
					//header('Status: 404 Not Found');
					echo 'Error: Bad IPN request, custom ID does not correspond with any pending booking.';
					//echo "<pre>"; print_r($_POST); echo "</pre>";
					EM_Pro::log( array('Error: Bad IPN request, custom ID does not correspond with any pending booking.', '$_POST'=> $_POST), 'paypal' );
					exit;
				}
			}
			//fclose($log);
		} else {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			//header('Status: 404 Not Found');
			echo 'Error: Missing POST variables. Identification is not possible. If you are not PayPal and are visiting this page directly in your browser, this error does not indicate a problem, but simply means EM is correctly set up and ready to receive IPNs from PayPal only.';
			exit;
		}
	}
	
	/**
	 * Handles a payment status change in PayPal, as in a IPN notification, PDT callback or other lookup.
	 * @param EM_Booking $EM_Booking
	 * @param float $amount
	 * @param string $payment_status
	 * @param string $currency
	 * @param string $txn_id
	 * @param string $timestamp Expects a format of 'Y-m-d H:i:s' for DB storage
	 * @param array $args Associative array of values matching those expected from an IPN notification, in order to have these processed by this function convert accordingly. The current keys referenced are 'ReasonCode' and 'pending_reason' for pending or reversed payments.
	 */
	public static function handle_payment_status($EM_Booking, $amount, $payment_status, $currency, $txn_id, $timestamp, $args){
		$filter_args = array( 'amount' => $amount, 'payment_status' => $payment_status, 'payment_currency' => $currency, 'transaction_id' => $txn_id, 'timestamp' => $timestamp, 'args' => $args );
		switch ($payment_status) {
			case 'Completed':
			case 'Processed': // case: successful payment
				static::record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, '');
		
				if( $amount >= $EM_Booking->get_price() && (!get_option('em_'.static::$gateway.'_manual_approval', false) || !get_option('dbem_bookings_approval')) ){
					$EM_Booking->approve(true, true); //approve and ignore spaces
				}else{
					//TODO do something if pp payment not enough
					$EM_Booking->set_status(0); //Set back to normal "pending"
				}
				do_action('em_payment_processed', $EM_Booking, static::class, $filter_args);
				break;
		
			case 'Reversed':
			case 'Voided' :
				// case: charge back
				$note = 'Last transaction has been reversed. Reason: Payment has been reversed (charge back)';
				static::record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note);
		
				//We need to cancel their booking.
				$EM_Booking->cancel();
				do_action('em_payment_reversed', $EM_Booking, static::class, $filter_args);
		
				break;
		
			case 'Refunded':
				// case: refund
				$note = 'Payment has been refunded';
				static::record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note);
				$amount = $amount < 0 ? $amount * -1 : $amount; //we need to compare two positive numbers for refunds
				if( $amount >= $EM_Booking->get_price() ){
					$EM_Booking->cancel();
				}else{
					$EM_Booking->set_status(0, false); //Set back to normal "pending" but don't send email about it to prevent confusion
				}
				do_action('em_payment_refunded', $EM_Booking, static::class, $filter_args);
				break;
		
			case 'Denied':
				// case: denied
				$note = 'Last transaction has been reversed. Reason: Payment Denied';
				static::record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note);
		
				$EM_Booking->cancel();
				do_action('em_payment_denied', $EM_Booking, static::class, $filter_args);
				break;
		
			case 'In-Progress':
			case 'Pending':
				// case: payment is pending
				$pending_str = array(
					'address' => 'Customer did not include a confirmed shipping address',
					'authorization' => 'Funds not captured yet',
					'echeck' => 'eCheck that has not cleared yet',
					'intl' => 'Payment waiting for aproval by service provider',
					'multi-currency' => 'Payment waiting for service provider to handle multi-currency process',
					'unilateral' => 'Customer did not register or confirm his/her email yet',
					'upgrade' => 'Waiting for service provider to upgrade the PayPal account',
					'verify' => 'Waiting for service provider to verify his/her PayPal account',
					'paymentreview' => 'Paypal is currently reviewing the payment and will approve or reject within 24 hours',
					'*' => ''
				);
				$reason = @$args['pending_reason'];
				$note = 'Last transaction is pending. Reason: ' . (isset($pending_str[$reason]) ? $pending_str[$reason] : $pending_str['*']);

				static::record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note);

				do_action('em_payment_pending', $EM_Booking, static::class, $filter_args);
				break;
			case 'Canceled_Reversal':
				//do nothing, just update the transaction
				break;
			default:
				// case: various error cases
		}
	}
	
	public static function handle_booking_timeout( $booking_ids ){
		//get creds and check they exist before even trying this
		$api_options = get_option('em_paypal_api');
		if( empty($api_options['username']) || empty($api_options['password']) || empty($api_options['signature']) ) return false;
		//go through each booking and check if there's a matching payment on paypal already, in case there's problems with IPN callbacks
		foreach( $booking_ids as $booking_id ){
			$EM_Booking = em_get_booking($booking_id);
			//Verify if Payment has been made by searching for the Invoice ID, which would be EM-BOOKING#x were x is the booking id
			$domain = get_option( 'em_paypal_status' ) == 'live' ? 'https://api-3t.paypal.com/nvp' : $domain = 'https://api-3t.sandbox.paypal.com/nvp';
			$post_vars = array(
				'USER' => $api_options['username'], //CHANGE
				'PWD' => $api_options['password'], //CHANGE
				'SIGNATURE' => $api_options['signature'],
				'METHOD' => 'TransactionSearch',
				'VERSION' => '204',
				'STARTDATE' => date('Y-m-d', strtotime('-1 Month')).'T00:00:00Z', //1 month back just to be sure
				'INVNUM' => static::get_invoice_id($EM_Booking)
			);
			$nvp_vars = "";
			foreach($post_vars as $k => $v ){
				$nvp_vars .= ($nvp_vars ? "&" : "").$k.'='.urlencode($v);
			} unset($k, $v);
			@set_time_limit(60);
			//set request values
			$args = apply_filters('em_paypal_txn_search_remote_args', array(
				'httpversion'=>'1.1',
				'user-agent'=>'EventsManagerPro/'.EMP_VERSION,
				'method'=>'POST',
				'body'=>$nvp_vars
			));
			//add a CA certificate so that SSL requests always go through
			add_action('http_api_curl',array( static::class, 'payment_return_local_ca_curl'),10,1);
			//using WP's HTTP class
			$nvp_response = wp_remote_request($domain, $args);
			remove_action('http_api_curl',array( static::class, 'payment_return_local_ca_curl'),10,1);
			
			if ( !is_wp_error($nvp_response) ) {
				//we expect a single result from this search, since searching for a invoice ID should be unique
				$nvp_result_raw = explode('&', $nvp_response['body']);
				$nvp_results = array();
				foreach($nvp_result_raw as $v ){
					$nvp_result_raw = explode('=', $v);
					$nvp_results[$nvp_result_raw[0]] = urldecode($nvp_result_raw[1]);
				}
				if( !empty($nvp_results['ACK']) && $nvp_results['ACK'] == 'Success' ){
					//check response and see whether we have an actual pending booking
					$booking_expired = false; //conservatively decide not to delete a booking by default
					if( !empty($nvp_results['L_STATUS0']) ){
						//we received a result, so we shouldn't delete this payment and act as if we received an IPN
						$args = array('pending_reason' => $nvp_results['L_STATUS0']);
						$EM_DateTime = new \EM_DateTime(strtotime($nvp_results['L_TIMESTAMP0']));
						static::handle_payment_status($EM_Booking, $nvp_results['L_AMT0'], $nvp_results['L_STATUS0'], $nvp_results['L_CURRENCYCODE0'], $nvp_results['L_TRANSACTIONID0'], $EM_DateTime->getTimestamp(), $args);
						EM_Pro::log( array('Payment located via NVP for booking awaiting payment and status handled.', '$nvp_results' => $nvp_results), 'paypal');
					}else{
						//search produced no results, so we assume there's no payment made and just delete the booking
						$booking_expired = true;
					}
					//only if a payment hasn't been made do we delete the booking
					if( $booking_expired ){
						EM_Pro::log( array('Booking set to be deleted due to awaiting payment time out.', 'Booking Info' => $EM_Booking->to_array()), 'paypal');
						$EM_Booking->manage_override = true;
						static::handle_booking_timeout_action( $EM_Booking );
					}
				}else{
					//some sort of error, log if needed but we won't delete anything
					EM_Pro::log( array('TransactionSearchError', 'WP_Error'=> $nvp_response, '$_POST'=> $_POST, '$url'=>$domain, 'Booking ID'=> $EM_Booking->booking_id), 'paypal-timeout-delete' );
				}
			}else{
				//log error if needed, send error header and exit
				EM_Pro::log( array('TransactionSearchError', 'WP_Error'=> $nvp_response, '$_POST'=> $_POST, '$url'=>$domain, 'Booking ID'=> $EM_Booking->booking_id), 'paypal-timeout-delete' );
			}
		}
	}
	
	/**
	 * Fixes SSL issues with wamp and outdated server installations combined with curl requests by forcing a custom pem file, generated from - http://curl.haxx.se/docs/caextract.html
	 * @param resource $handle
	 */
	public static function payment_return_local_ca_curl( $handle ){
	    curl_setopt($handle, CURLOPT_CAINFO, dirname(__FILE__).DIRECTORY_SEPARATOR.'gateway.paypal.pem');
	}
}
Gateway::init();
?>