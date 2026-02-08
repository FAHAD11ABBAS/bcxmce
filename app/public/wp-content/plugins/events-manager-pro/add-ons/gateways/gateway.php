<?php
namespace EM\Payments;
use EM_Pro, EM_Booking, EM_Event;
use WP_REST_Response, WP_REST_Request, WP_Error;

/**
 * This class is a parent class which gateways should extend. There are various variables and functions that are automatically taken care of by
 * EM_Gateway, which will reduce redundant code and unecessary errors across all gateways. You can override any function you want on your gateway,
 * but it's advised you read through before doing so.
 *
 */
class Gateway {
	/**
	 * Gateway reference, which is used in various places for referencing gateway info. Use lowercase characters/numbers and underscores.
	 * @var string
	 */
	public static $gateway = 'unknown';
	/**
	 * This will be what admins see as the gatweway name (e.g. Offline, PayPal, Authorize.net ...)
	 * @var string
	 */
	public static $title = 'Unknown';
	/**
	 * The default status value your gateway assigns this booking. Default is 0, i.e. pending 'something'.
	 * @var int
	 */
	public static $status = 0;
	/**
	 * Set this to any true value and this will trigger the em_my_bookings_booked_message function to override the status name of this booking when in progress.
	 * @var string
	 */
	public static $status_txt = '';
	/**
	 * If your gateway supports the ability to pay without requiring further fields (e.g. credit card info), then you can set this to true.
	 * 
	 * You will automatically have the ability to show buttons within your gateway. It's up to you to change what happens after by 
	 * overriding functions from EM_Gateway such as modifying booking_add or booking_form_feedback.
	 *  
	 * @var boolean
	 */
	public static $button_enabled = false;
	/**
	 * If your gateway is compatible with our Multiple Bookings Mode, then you can set this to true, otherwise your gateway won't be available for booking in this mode.
	 *  
	 * @var boolean
	 */
	public static $supports_multiple_bookings = false;
	/**
	 * If your gateway is apt for manual bookings (for example, PayPal Standard is not) so that admins can manually enter payment info, such as card info over phone, then set this to true
	 *
	 * @var boolean
	 */
	public static $supports_manual_bookings = false;
	/**
	 * Associative array of supported WP REST API endpoints which are automatically gnerated by this base class, the endpoint name is the array key.
	 * Value of each array item can either be a boolean value or a set of array items which are passed onto the register_rest_route() function, overriding the default arguments supplied by base class.
	 * Classes can always add their own alongside these by overriding the register_handle_payment_api() function.
	 *
	 * @var array
	 */
	public static $rest_api = array(
		'notify' => true,
		'cancel'=> true,
		'capture' => true,
	);
	/**
	 * Array of flow settings during the booking process that can automatically trigger/ignore PHP and JS functionality
	 * @var boolean[]
	 */
	public static $payment_flow = array(
		'intercept' => false, // if gateway would intercept the booking form before submission and handle the submission and result accodingly
		'loader' => false, // if gateway loads fields etc. when selected
		'custom-button' => false, // if gateway loads buttons (such as PayPal checkout)
		'redirect' => false,
		'redirect-success' => false,
		'redirect-cancel' => false,
	);
	/**
	 * Some external gateways (e.g. PayPal IPNs) return information back to your site about payments, which allow you to automatically track refunds made outside Events Manager.
	 * If you enable this to true, be sure to add an overriding handle_payment_return function to deal with the information sent by your gateway.
	 * @deprecated Use the Rest API instead via static::$rest_api and subsequently called functions
	 * @var boolean
	 */
	public static $payment_return = false;
	/**
	 * If payments made via this gateway time out after a certain time without payment, setting this to true will automatically cancel the booking if the specified time is reached, in minutes.
	 * The default time if set to true is used, which is defined in the general settings page, and can be overriden by individual gateways by assigning a number to this property.
	 * @var bool
	 */
	public static $has_timeout = false;
	/**
	 * Allows optional counting/reserving of bookings with pending payment status for availability, separate of whether reserved pending spaces are enabled in general EM settings.
	 * Setting this to true will allow users to choose whether these bookings can be considered reserved as pending spaces from the settings page of this gateway.
	 * This setting can be overriden and forced if $reserve_pending_spaces is set to true.
	 * @var boolean
	 */
	public static $count_pending_spaces = false;
	/**
	 * Blocks bookings with pending spaces for availability, even if approvals setting is disabled in general EM settings. This should be enabled for any payment gateway where online
	 * transactions are near-instantaneous, such as PayPal, Stripe, etc. otherwise double-bookings could occur if approvals or reserving pending spages is disabled.
	 *
	 * If set to false, $count_pending_spaces can be set to true so that bookings with pending spaces are optionally counted towards availability.
	 * @var boolean
	 */
	public static $reserve_pending_spaces = true;
	/**
	 * If gateway can have option to manually approve a booking after payment. Not always desirable, such as an offline payment essentially being manually approved already.
	 * @var boolean
	 */
	public static $can_manually_approve = true;
	/**
	 * If gateway requires SSL to operate (most will these days) this will check for SSL during is_active() and deactivate the gateway unless
	 * the constant named by static::$testing_constant is set to true.
	 *
	 * @var bool
	 */
	public static $requires_ssl = true;
	/**
	 * For security checks such as webhook signatures, SSL checks and other parts of the gateway, if this named constant is defined and set to true then certain security checks are skipped.
	 * ONLY FOR USE WHEN DEBUGGING! Do not define this constant and set to true in production unless temporarily testing.
	 * @var string
	 */
	public static $testing_constant = 'EM_GATEWAY_TESTING';
	/**
	 * Associated array containing counts for pending spaces of specific events, which can be reused when called again later on.
	 * @var array
	 */
	public static $event_pending_spaces = array();
	/**
	 * Multidimensional associated containing pending spaces for specific tickets, within eacy array item is an array of event id keys and corresponding counts. 
	 * @var array
	 */
	public static $ticket_pending_spaces = array();
	/**
	 * Unassociated array containing the url sprintable structure to a live transaction detail, test transaction detail and title service name for link.
	 * Example: array('https://test.com/transaction/%s', 'https://sandbox.test.com/transaction/%s', 'test.com');
	 * @var array
	 */
	public static $transaction_detail = array();
	
	/**
	 * JS property name for localized variables under the EM.Gateways JS variable.
	 * Default value is set to Gateway::$name upon init() unless specifically set by overriding classes.
	 * @var string;
	 */
	public static $js_localize;
	/**
	 * @var bool For legacy gateways.
	 */
	public static $legacy = false;
	/**
	 * Name of option storing the API credentials of this gatewaym default is em_{static::$gateway}_api, test creds always prefixed with _test
	 * @var string
	 */
	public static $api_option_name;
	/**
	 * New gateway updates should enable this to support the latest test modes, and account for test modes where relevant.
	 * @var bool
	 */
	public static $supports_test_mode = false;
	/**
	 * If set to 'live' or 'test' the mode of this gateway will be forced into Live Mode or Test Mode respectively.
	 * @var string
	 */
	public static $force_mode;
	
	// TODO create generic settings location and admin like booking form content text field, api settings, etc.
	// TODO create way for base gateways to hold admin settings etc.
	// TODO handle refunds
	// TODO handle initial deposit
	// TODO handle authorization
	// TODO handle storage for multiple payments
	// TODO figure out way to hide button depending on flow/method

	/**
	 * Adds some basic actions and filters to hook into the EM_Gateways class and Events Manager bookings interface. 
	 */
	public static function init() {
		$Gateway = static::class; /* @var Gateway $Gateway */
		Gateways::register( $Gateway );
		// Actions and Filters, only if gateway is active
		if( static::is_active() ){
			add_filter('em_booking_output_placeholder',array( static::class, 'em_booking_output_placeholder'),1,4); //add booking placeholders
			if( !empty(static::$rest_api) ){
				add_action('rest_api_init', array( static::class, 'register_api' ));
			}
			if( static::$payment_return ){
				// old-style
				add_action('em_handle_payment_return_' . static::$gateway, array( static::class, 'handle_payment_return') ); //handle return payment notifications
			}
			add_filter('em_manual_booking_success_' . static::$gateway, array( static::class, 'em_manual_booking_success' ) );
			if( !empty(static::$status_txt) ){
				//Booking UI
				add_filter('em_my_bookings_booked_message', array( static::class, 'em_my_bookings_booked_message'),10,2);
				add_filter('em_booking_get_status',array( static::class, 'em_booking_get_status'),10,2);
			}
			if( !empty(static::$transaction_detail) ){
				add_filter('em_gateways_transactions_table_gateway_id_'. static::$gateway, array( static::class, 'em_gateways_transactions_table_gateway_id'), 10, 2); //transaction list link
			}
			//warn admins about SSL
			if( static::$requires_ssl && !is_ssl() && static::is_live() && !em_constant(static::$testing_constant) ){
				if( current_user_can('activate_plugins') ){
					add_action('em_booking_form_before_tickets', function(){
						echo '<p><strong style="color:red !important;">'. esc_html( sprintf(__('%s will only work if your site is using SSL and has been disabled. Only admins will see this message.', 'em-pro'), static::$title) ) .'</strong></p>';
					});
				}
			}
		}
		if( !static::$js_localize ){
			static::$js_localize = static::$gateway;
		}
		
		// Modify spaces calculations, required even if inactive, due to previously made bookings whilst this may have been active
		if( static::$count_pending_spaces ) {
			// we reset this to true if settings for gateway are enabled to reserve spaces
			add_filter('em_bookings_get_pending_spaces', array( static::class, 'em_bookings_get_pending_spaces'),1,3);
			add_filter('em_ticket_get_pending_spaces', array( static::class, 'em_ticket_get_pending_spaces'),1,3);
		}
		if( static::$reserve_pending_spaces || get_option( 'em_' . static::$gateway . '_reserve_pending') ){
			add_filter('em_bookings_get_reserved_spaces', array( static::class, 'em_bookings_get_reserved_spaces'),1,3);
			add_filter('em_ticket_get_reserved_spaces', array( static::class, 'em_ticket_get_reserved_spaces'),1,3);
			add_filter('em_booking_is_reserved', array( static::class, 'em_booking_is_reserved'),1,2);
			add_filter('em_booking_is_pending', array( static::class, 'em_booking_is_pending'),1,2);
			static::$reserve_pending_spaces = true; // set this to true in event that it is false by default and user has enabled it in settings
		}
		
		// add default to static payment flow options
		static::$payment_flow = array_merge( self::$payment_flow, static::$payment_flow );
		
		// trigger action for overriding
		add_action('wp_loaded', [ static::class, 'wp_loaded' ] );
		do_action('em_gateway_'.static::$gateway.'_init');
	}

	public static function wp_loaded() {}
	
	/**
	 * If you would like to modify the default status message for this payment whilst in progress.
	 *
	 * This function is triggered if set static::$status_text to something and this will be called automatically. You can also override this function.
	 *
	 * @param string $message
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	public static function em_booking_get_status($message, $EM_Booking){
		if( !empty(static::$status_txt) && $EM_Booking->booking_status == static::$status && static::uses_gateway($EM_Booking) ){
			return static::$status_txt;
		}
		return $message;
	}
	
	
/* ------------------------------------------------------------------------------------------------------------------------------------------------------
* PAYMENT FORMS - Handle the payment form display and JS
* ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * @deprecated
	 * $see payment_form()
	 */
	public static function booking_form(){
		static::payment_form( 0 ); // no ID
	}
	
	/**
	 * Outputs extra custom content
	 * @return void
	 */
	public static function payment_form_header( $id ){}
	
	public static function payment_form_info( $id ){
		$test_mode = static::is_test_mode();
		if ( $test_mode ) {
			echo '<div class="em-notice em-notice-info">';
			if( is_array($test_mode) ){
				// limited test mode
				$reasons = array();
				if( !empty($test_mode['ip']) ) {
					$reasons[] = esc_html__('Your IP matches a Limited Test Mode filter.', 'em-pro');
				}
				if( !empty($test_mode['user']) ) {
					$reasons[] = esc_html__('Your user ID matches a Limited Test Mode filter.', 'em-pro');
				}
				if( !empty($test_mode['event']) ) {
					$reasons[] = esc_html__('This event matches a Limited Test Mode filter.', 'em-pro');
				}
				echo '<p>' . esc_html__('This gateway is in Limited Test Mode, meaning any visitors matching limit settings will see this message. You are currently in test mode because:', 'em-pro') . '</p>';
				echo '<ul style="margin-bottom:0; padding-bottom: 0;">';
				foreach( $reasons as $reason ){
					echo '<li>'.$reason.'</li>';
				}
				echo '</ul>';
				echo '<p>' . esc_html__('Visitors who do not match a limited condition will not see this message and will continue to use this gateway in Live Mode.', 'em-pro') . '</p>';
			} else {
				// regular test mode
				esc_html_e('This gateway is currently in Test Mode, only test payment methods can be used.', 'em-pro');
			}
			echo '</div>';
		}
		echo static::get_option('form'); // outputs html content defined in settings
	}
	
	/**
	 * Outputs extra custom content e.g. information about this gateway or extra form fields to be requested if this gateway is selected (not applicable with Quick Pay Buttons).
	 * Also outputs default booking info field in gateway setting (if set).
	 */
	public static function payment_form( $id ){}
	
	/**
	 * Outputs a skeleton loader
	 */
	public static function payment_form_loading( $id ){
		?>
		<div class="skeleton">
			<p>
				<div class="item label"></div>
				<div class="item input-line"></div>
			</p>
		</div>
		<?php
	}
	
	public static function payment_form_selector( $type, $id ){
		//  build data- properties
		$data_items = array();
		foreach( static::$payment_flow as $prop => $value ){
			if( $value === true || $value === false ){
				$value = $value ? 1 : 0;
			}
			$data_items[] = 'data-' . $prop . '="' . esc_attr($value) . '"';
		}
		if ( static::is_test_mode() ) {
			$data_items[] = 'data-test-mode="1"';
		}
		$props = 'value="' . static::$gateway . '" ' . implode( ' ', $data_items );
		$html = '';
		if( $type === 'select' ) {
			$html = '<option class="em-payment-gateway-option" ' . $props . '>' . get_option( 'em_' . static::$gateway . '_option_name' ) . '</option>';
		}elseif( $type === 'hidden' ){
			$html = '<input type="hidden" name="gateway" '. $props . ' class="em-payment-gateway em-payment-gateway-option" >';
		}elseif( $type === 'radio' ){
			ob_start();
			?>
			<div class="em-payment-gateway-option-radio">
				<input type="radio" name="gateway" <?php echo $props; ?> class="em-payment-gateway-option em-payment-gateway">
				<?php echo static::payment_form_selector_radio_label(); ?>
			</div>
			<?php
			$html = '';
			$html = ob_get_clean();
		}
		return $html;
	}
	
	public static function payment_form_selector_radio_label(){
		return '<label>'. get_option( 'em_' . static::$gateway . '_option_name' ) . '</label>';
	}
	
	/**
	 * Outputs any JS injected directly below the gateway selection HTML, within some script tags (not with jQuery though).
	 * @return void
	 */
	public static function payment_form_js( $id ){}
	
	/**
	 * @return void
	 */
	public static function payment_form_footer( $id ){}
	
	/**
	 * Function automatically called by Gateways which auto-localizes JS into the EM.Gateways var
	 * @return array|false
	 */
	public static function localize_js( $id ){
		return false;
	}
	
	
/* ------------------------------------------------------------------------------------------------------------------------------------------------------
* REST API - Listeners for notifications, cancellations, captures etc. and the functions used to generate these urls
* ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Registers the relevant API endpoitns for this gateway
	 * @return void
	 */
	public static function register_api(){
		// webhook notification listener
		if( !empty(static::$rest_api['notify']) ){
			$route = array(
				'methods'  => 'GET,POST',
				'callback' => array( static::class, 'handle_api_notify' ),
				'permission_callback' => '__return_true', // 5.5. compat
			);
			if( is_array( static::$rest_api['notify']) ){
				$route = array_merge( $route, static::$rest_api['notify'] );
			}
			register_rest_route( 'events-manager/v1', '/gateways/'.static::$gateway.'/notify', $route );
			
			if( static::$supports_test_mode ) {
				$route['callback'] = array( static::class, 'handle_api_notify_test' );
				register_rest_route( 'events-manager/v1', '/gateways/'.static::$gateway.'/notify_test', $route );
			}
		}
		// cancellation API
		if( !empty(static::$rest_api['cancel']) ){
			$route = array(
				'methods'  => 'GET,POST',
				'callback' => array( static::class, 'handle_api_cancel' ),
				'permission_callback' => '__return_true', // 5.5. compat
			);
			if( is_array( static::$rest_api['cancel']) ){
				$route = array_merge( $route, static::$rest_api['cancel'] );
			}
			register_rest_route( 'events-manager/v1', '/gateways/'.static::$gateway.'/cancel', $route );
		}
		// capture of payments API
		if( !empty(static::$rest_api['capture']) ){
			$route = array(
				'methods'  => 'GET,POST',
				'callback' => array( static::class, 'handle_api_capture' ),
				'permission_callback' => '__return_true', // 5.5. compat
			);
			if( is_array( static::$rest_api['capture']) ){
				$route = array_merge( $route, static::$rest_api['capture'] );
			}
			register_rest_route( 'events-manager/v1', '/gateways/'.static::$gateway.'/capture', $route );
		}
	}
	
	/**
	 * Return a WP REST result for handling a payment notification webhook
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public static function handle_api_notify( $request ) {
		$message = 'Missing POST variables. Identification is not possible. If you are not '.static::$title.' and are visiting this page directly in your browser, this error does not indicate a problem, but simply means Events Manager is correctly set up and ready to receive communication from '.static::$title.' only.';
		return new WP_REST_Response( array('message' => $message), 200 );
	}
	
	/**
	 * Return a WP REST result for handling a payment notification webhook in test mode
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public static function handle_api_notify_test( $request ) {
		$force_mode = static::force_mode('test');
		$return = static::handle_api_notify( $request );
		static::$force_mode = $force_mode;
		return $return;
	}
	
	/**
	 * Return a WP REST result for handling a payment cancellation via the EM Gateway form
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function handle_api_cancel( $request ){
		$payload = json_decode($request->get_body());
		if( !empty($payload->nonce) && !empty($payload->uuid) ){
			// nonce OK, get booking, amke sure it's an intent and then delete it
			$EM_Booking = em_get_booking($payload->uuid);
			if( wp_verify_nonce($payload->nonce, 'cancel-booking-intent-'.static::$gateway .'-' . $EM_Booking->id) ) {
				if ( $EM_Booking->booking_uuid === $payload->uuid && $EM_Booking->booking_status == static::$status ) {
					$result = $EM_Booking->delete();
					if ( $result ) {
						$response = array();
						$message = get_option( 'em_' . static::$gateway . '_booking_feedback_cancelled' );
						if( $message ) {
							$response['success'] = true;
							$response['type'] = 'info';
							$response['message'] = $message;
						}
						return new WP_REST_Response( $response, 200 );
					} else {
						return new WP_REST_Response( array( 'message' => 'Could not delete, please contact admins.' ), 500 );
					}
				} else {
					return new WP_REST_Response( array( 'message' => 'Booking not found.' ), 404 );
				}
			}
		}
		$message = 'Missing nonce, required for verification.';
		return new WP_REST_Response( array('message' => $message), 401 );
	}
	
	/**
	 * Return a WP REST result for handling a payment capture via payment via EM Gateway form
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function handle_api_capture( $request ){
		$payload = json_decode($request->get_body());
		if( !empty($payload->gateway) && !empty($payload->gateway->nonce) && !empty($payload->gateway->uuid) ){
			// nonce OK, get booking, amke sure it's an intent and then delete it
			$EM_Booking = em_get_booking($payload->gateway->uuid);
			if( wp_verify_nonce($payload->gateway->nonce, 'capture-booking-intent-'.static::$gateway .'-' . $EM_Booking->id) ) {
				if ( $EM_Booking->booking_uuid === $payload->gateway->uuid && $EM_Booking->booking_status == static::$status ) {
					$result = static::capture( $EM_Booking, $payload );
					return new WP_REST_Response( $result, 200 );
				} else {
					return new WP_REST_Response( array( 'message' => 'Booking not found.' ), 404 );
				}
			}
		}
		$message = 'Missing nonce, required for verification.';
		return new WP_REST_Response( array('message' => $message), 401 );
	}
	
	/**
	 * @deprecated Use Gateway::get_api_notify_url()
	 */
	public static function get_payment_return_api_url(){ return static::get_api_notify_url(); }
	
	/**
	 * Returns the REST API notification URL which gateways can send webhooks to, handled by handle_api_notify()
	 *
	 * @return string
	 */
	public static function get_api_notify_url(){
		$endpoint = static::is_test_mode() ? 'notify_test' : 'notify';
		$url = get_rest_url( get_current_blog_id(), 'events-manager/v1/gateways/'.static::$gateway.'/'.$endpoint );
		if( em_constant('EM_GATEWAY_API_DOMAIN') ){
			// localhost testing allows you to set up a tunnel like ngrok or localxpose
			$url = preg_replace('/https?:\/\/[^\/]+/', constant('EM_GATEWAY_API_DOMAIN'), $url);
		}
		return $url;
	}
	
	/**
	 * Returns the REST API notification URL where EM can notify iself of a cancelled booking attempt, handled by handle_api_cancel()
	 *
	 * @return string
	 */
	public static function get_api_cancel_url(){
		$url = get_rest_url( get_current_blog_id(), 'events-manager/v1/gateways/'.static::$gateway.'/cancel' );
		if( em_constant('EM_GATEWAY_API_DOMAIN') ){
			// localhost testing allows you to set up a tunnel like ngrok or localxpose
			$url = preg_replace('/https?:\/\/[^\/]+/', constant('EM_GATEWAY_API_DOMAIN'), $url);
		}
		return $url;
	}
	
	/**
	 * Returns the REST API notification URL where EM can notify iself of a booking to capture, handled by handle_api_capture()
	 *
	 * @return string
	 */
	public static function get_api_capture_url(){
		$url = get_rest_url( get_current_blog_id(), 'events-manager/v1/gateways/'.static::$gateway.'/capture' );
		if( em_constant('EM_GATEWAY_API_DOMAIN') ){
			// localhost testing allows you to set up a tunnel like ngrok or localxpose
			$url = preg_replace('/https?:\/\/[^\/]+/', constant('EM_GATEWAY_API_DOMAIN'), $url);
		}
		return $url;
	}

/* ----------------------------------------------------------------------------------------------------
* Booking Overrides
* ---------------------------------------------------------------------------------------------------- */
	
	public static function booking_validate( $EM_Booking ){
		return true;
	}

	/**
	 * Triggered by the em_booking_add_yourgateway action, modifies the booking status if the event isn't free and also adds a filter to modify user feedback returned.
	 * @param EM_Event $EM_Event
	 * @param EM_Booking $EM_Booking
	 * @param boolean $post_validation
	 */
	public static function booking_add($EM_Event,$EM_Booking, $post_validation = false){
		// handle redirect situations
		add_filter('em_action_booking_add',array( static::class, 'booking_form_feedback'),1,2);//modify the payment return
		add_filter('em_action_emp_checkout',array( static::class, 'booking_form_feedback'),1,2);//modify the payment return
		// add fallback if we're not in AJAX mode
		if( !em_constant('DOING_AJAX') && em_constant('REST_REQUEST') ){ //we aren't doing ajax (or REST Request) here, so we should provide a way to edit the $EM_Notices ojbect.
			add_action('option_dbem_booking_feedback', array(static::class, 'booking_form_feedback_fallback'));
		}
	}
	
	/**
	 * Called by Gateways, triggered by the em_booking_added function. This function can be overriden by gateways to perform an action once a book has been successfully added
	 * via a booking form submission using this gateway. No further checks are necessary to verify the booking should be intercepted by this gateway.
	 *
	 * @param EM_Booking $EM_Booking
	 */
	public static function booking_added( $EM_Booking ){}

	/**
	 * Intercepts return JSON and adjust feedback messages when booking with this gateway. This filter is added only when the em_booking_add function is triggered by the em_booking_add filter.
	 * @param array $return
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	public static function booking_form_feedback( $return, $EM_Booking = false ){
		return $return; //remember this, it's a filter!	
	}
	
	/**
	 * Called if AJAX isn't being used, i.e. a javascript script failed and forms are being reloaded instead.
	 * This function adds or overwrites the default feeback message defined in settings page, should there be a successful booking.
	 * Override if you can provide a button to proceed with booking, for example.
	 *
	 * @param string $feedback
	 * @return string
	 */
	public static function booking_form_feedback_fallback( $feedback ){
		global $EM_Booking;
		if( is_object($EM_Booking) ){
			$feedback .= "<br />" . __('Cannot proceed with booking without Javascript enabled.','emp-stripe');
		}
		return $feedback;
	}

	/**
	 * Adds extra placeholders to the booking email. Called by em_booking_output_placeholder filter, added in this object __construct() function.
	 * 
	 * You can override this function and just use this within your function:
	 * $result = parent::em_booking_output_placeholder($result);
	 * 
	 * @param string $result
	 * @param EM_Booking $EM_Booking
	 * @param string $placeholder
	 * @param string $target
	 * @return string
	 */
	public static function em_booking_output_placeholder($result,$EM_Booking,$placeholder,$target='html'){
		global $wpdb;
		if( ($placeholder == "#_BOOKINGTXNID" && !empty($EM_Booking->booking_meta['gateway'])) && $EM_Booking->booking_meta['gateway'] == static::$gateway ){
			if(empty($EM_Booking->BOOKINGTXNID)){
				$sql = $wpdb->prepare( "SELECT transaction_gateway_id FROM ".EM_TRANSACTIONS_TABLE." WHERE booking_id=%d AND transaction_gateway = %s", $EM_Booking->booking_id, static::$gateway );
				$txn_id = $wpdb->get_var($sql);
				if(!empty($txn_id)){
					$result = $EM_Booking->BOOKINGTXNID = $txn_id;
				}else{
				    $result = '';
				}
			}else{
				$result = $EM_Booking->BOOKINGTXNID;
			}
		}
		return $result;
	}
	
	public static function em_my_bookings_booking_actions( $actions, $EM_Booking ){
		return $actions;
	}
	
	/**
	 * @param EM_Booking $EM_Booking
	 * @param array $payload
	 *
	 * @return array
	 */
	public static function capture( $EM_Booking, $payload ){
		return array('result' => true );
	}
	
/* ------------------------------------------------------------------------------------------------------------------------------------------------------
* PENDING SPACE COUNTING - if static::$count_pending_spaces is true, depending on the gateway, bookings with this gateway status are considered pending and reserved
* ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Modifies pending spaces calculations to include gateway bookings, but only if gateway bookings are set to be reserved when pending.
	 * This is somewhat of a bug in EM, and may change in the future, so that pending spaces are not considered reserved.
	 * @param integer $count
	 * @param \EM_Bookings $EM_Bookings
	 *
	 * @return integer
	 */
	public static function em_bookings_get_pending_spaces($count, $EM_Bookings, $force_refresh = false){
		$pending_spaces = static::count_pending_bookings_spaces( $EM_Bookings, $force_refresh );
		return $count + $pending_spaces;
	}
	
	/**
	 * Checks number of pending spaces for bookings with this gateway and removes it from available spaces. Assumes that this function/hook is called only if this gateway reserves pending spaces regardless of EM settings.
	 * @param integer $count
	 * @param \EM_Bookings $EM_Bookings
	 *
	 * @return integer
	 */
	public static function em_bookings_get_reserved_spaces($count, $EM_Bookings, $force_refresh = false){
		$reserved_spaces = static::count_pending_bookings_spaces( $EM_Bookings, $force_refresh );
		return $count + $reserved_spaces;
	}
	
	/**
	 * Counts number of pending spaces for bookings with this gateway. Does not assume space is reserved.
	 * @param \EM_Bookings $EM_Bookings
	 * @param bool $force_refresh
	 * @return integer
	 */
	public static function count_pending_bookings_spaces($EM_Bookings, $force_refresh = false){
		global $wpdb;
		if( isset( self::$event_pending_spaces[static::$gateway][$EM_Bookings->event_id] ) && !$force_refresh ) {
			$pending_spaces = self::$event_pending_spaces[static::$gateway][$EM_Bookings->event_id];
		} else {
			$sql = "SELECT SUM(booking_spaces) FROM ".EM_BOOKINGS_TABLE. " b LEFT JOIN ". EM_BOOKINGS_META_TABLE . " bt ON b.booking_id=bt.booking_id AND meta_key='gateway' WHERE booking_status=%d AND event_id=%d AND meta_value=%s";
			$pending_spaces = $wpdb->get_var( $wpdb->prepare($sql, array(static::$status, $EM_Bookings->event_id, static::$gateway)) );
			if( empty(self::$event_pending_spaces[static::$gateway]) ) self::$event_pending_spaces[static::$gateway] = array();
			self::$event_pending_spaces[static::$gateway][$EM_Bookings->event_id] = $pending_spaces > 0 ? $pending_spaces : 0;
		}
		return $pending_spaces;
	}
	
	/**
	 * Changes EM_Booking::is_reserved() return value to true. Only called if static::$count_pending_spaces is set to true.
	 * @param boolean $result
	 * @param EM_Booking $EM_Booking
	 * @return boolean
	 */
	public static function em_booking_is_reserved( $result, $EM_Booking ){
		if( $EM_Booking->booking_status == static::$status && static::uses_gateway($EM_Booking) && ( static::$reserve_pending_spaces ) ){
			return true;
		}
		return $result;
	}
	
	/**
	 * Changes EM_Booking::is_pending() return value to true if booking is either reserved, or if gateway counts pending spaces (not necessraily set to reserve them though).
	 * @param $result
	 * @param $EM_Booking
	 *
	 * @return mixed|true
	 */
	public static function em_booking_is_pending( $result, $EM_Booking ){
		if( $EM_Booking->booking_status == static::$status  && static::uses_gateway($EM_Booking) && ( static::$reserve_pending_spaces || static::$count_pending_spaces )  ){
			return true;
		}
		return $result;
	}
	
	/**
	 * Modifies pending spaces calculations for individual tickets to include paypal bookings, but only if PayPal bookings are set to time-out (i.e. they'll get deleted after x minutes), therefore can be considered as 'pending' and can be reserved temporarily.
	 * @param integer $count
	 * @param \EM_Ticket $EM_Ticket
	 * @return integer
	 */
	public static function em_ticket_get_pending_spaces($count, $EM_Ticket, $force_refresh = false){
		$pending_spaces = static::count_pending_ticket_spaces( $EM_Ticket, $force_refresh );
		return $count + $pending_spaces;
	}
	
	/**
	 * Checks number of pending spaces for tickets with this gateway and removes it from available spaces. Assumes that this function/hook is called only if this gateway reserves pending spaces regardless of EM settings.
	 * @param integer $count
	 * @param \EM_Ticket $EM_Ticket
	 * @return integer
	 */
	public static function em_ticket_get_reserved_spaces($count, $EM_Ticket ){
		$reserved_spaces = static::count_pending_ticket_spaces( $EM_Ticket );
		return $count + $reserved_spaces;
	}
	
	public static function count_pending_ticket_spaces( $EM_Ticket, $force_refresh = false ){
		global $wpdb;
		if( !empty(static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id]) && isset(static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id][$EM_Ticket->event_id]) && !$force_refresh ) {
			$pending_spaces = static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id][$EM_Ticket->event_id];
		} else {
			if( empty(static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id]) ) static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id] = array();
			$booking_ids_sql = "SELECT b.booking_id FROM ".EM_BOOKINGS_TABLE. " b LEFT JOIN ". EM_BOOKINGS_META_TABLE . " bt ON b.booking_id=bt.booking_id AND meta_key='gateway' WHERE booking_status=%d AND event_id=%d AND meta_value=%s";
			$booking_ids_sql = $wpdb->prepare($booking_ids_sql, array(static::$status, $EM_Ticket->event_id, static::$gateway));
			$sql = 'SELECT SUM(ticket_booking_spaces) FROM '.EM_TICKETS_BOOKINGS_TABLE. ' WHERE ticket_id='.absint($EM_Ticket->ticket_id).' AND booking_id IN ('.$booking_ids_sql.')';
			$pending_spaces = $wpdb->get_var( $sql );
			static::$ticket_pending_spaces[static::$gateway][$EM_Ticket->ticket_id][$EM_Ticket->event_id] = $pending_spaces > 0 ? $pending_spaces : 0;
		}
		return $pending_spaces;
	}
	

/* --------------------------------------------------------------------------------------------------------
* REDIRECTION Functions - Thank you and cancel page handling for gateways with redirect functionality
* ------------------------------------------------------------------------------------------------------- */
	
	/**
	 * Detect if use was brought back from gateway checkout and needs to be served a thank you message. Adds hooks to thank user on MB checkout page, my bookings page, and event page.
	 */
	public static function handle_return_url(){
		//add actions for each page where a thank you might appear by default
		if( get_option('dbem_multiple_bookings') ){
			add_filter('pre_option_dbem_multiple_bookings_feedback_no_bookings', array( static::class, 'get_thank_you_message') );
		}
		add_action('em_template_my_bookings_header', array( static::class, 'thank_you_message'));
		add_action('em_booking_form_top', array( static::class, 'thank_you_message'));
		
		// load current event if booking_id or event_id supplied so that test mode can be determined
		add_action('em_template_my_bookings_header', array( '\EM\Payments\Gateways', 'load_current_event'), 9);
		add_action('em_booking_form_top', array( '\EM\Payments\Gateways', 'load_current_event'), 9);
		add_action('em_template_my_bookings_header', array( '\EM\Payments\Gateways', 'unload_current_event'), 11);
		add_action('em_booking_form_top', array( '\EM\Payments\Gateways', 'unload_current_event'), 11);
	}
	
	/**
	 * Outputs thank you message from gateway settings.
	 * @see EM_Gateway::get_thank_you_message()
	 */
	public static function thank_you_message(){
		echo static::get_thank_you_message();
	}
	
	/**
	 * Returns thank you message from gateway settings.
	 * @return string
	 */
	public static function get_thank_you_message(){
		return "<div class='em-notice em-notice-success em-booking-message em-booking-message-success'>" . static::get_option('booking_feedback_completed') . '</div>';
	}
	
	/**
	 * Outputs info or takes action on gateway transaction if applicable.
	 */
	public static function em_manual_booking_success(){
		static::handle_payment_return();
		return static::get_option('booking_feedback_completed');
	}
	
	/**
	 * Gets a return url where a thank you message can be displayed. If no return URL can be determined, the home page will be used even though a thank you message will not show by default.
	 * @param EM_Booking $EM_Booking If provided, and there is no other page to redirect to, the event page of this booking will be used.
	 * @return string
	 */
	public static function get_return_url( $EM_Booking = null ){
		if( get_option('em_'. static::$gateway . "_return" ) ){
			$return_url = get_option('em_'. static::$gateway . "_return" );
			if( !empty($EM_Booking) ){
				$return_url = add_query_arg( ['booking_id' => $EM_Booking->booking_id], $return_url );
			}
			return add_query_arg('payment_complete', static::$gateway, $return_url);
		}else{
			if( get_option('dbem_multiple_bookings') ){
				//if MB mode, redirect to checkout page
				$my_bookings_url = get_permalink(get_option('dbem_multiple_bookings_checkout_page'));
			}
			if( empty($my_bookings_url) && get_option('dbem_my_bookings_page') ){
				//if My Bookings Page exists, use that
				$my_bookings_url = get_permalink(get_option('dbem_my_bookings_page'));
			}
		}
		if( empty($my_bookings_url) ){
			if( $EM_Booking ){
				//otherwise, send back to original event page when booking is provided
				$my_bookings_url = $EM_Booking->get_event()->get_permalink();
			}else{
				//no thank you message, but we redirect anyway
				$my_bookings_url = get_home_url();
			}
		}
		if( !empty($EM_Booking) ){
			$my_bookings_url = add_query_arg( ['booking_id' => $EM_Booking->booking_id], $my_bookings_url );
		}
		//add the flag for displaying a message and return
		return add_query_arg('payment_complete', static::$gateway, $my_bookings_url);
	}
	
	public static function handle_cancel_url( $EM_Booking ){
		if( static::uses_gateway($EM_Booking) && $EM_Booking->booking_status == static::$status ){
			global $EM_Notices; /* @var \EM_Notices $EM_Notices */
			$EM_Booking->delete();
			$cancellation_message = get_option('em_'.$_REQUEST['payment_cancelled'].'_booking_feedback_cancelled');
			if( !empty($cancellation_message) ){
				$EM_Notices->add_confirm($cancellation_message);
			}
			if( get_class($EM_Booking) == 'EM_Multiple_Booking' ){
				// restore booking into session - ids can be preserved since they should all be deleted on auto-increment tables
				\EM_Multiple_Bookings::restore_cart();
			}
		}
	}
	
	/**
	 * Gets a cancellation url where a relevant you message can be displayed. If no cancellation URL has been set, the event page the booking was attempted for will be used.
	 * This cancellation URL will have certain query params added on to identify cancelled bookings by gateway and take appropriate action (i.e. delete the incomplete booking).
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	public static function get_cancel_url( $EM_Booking ){
		if( get_option('em_'. static::$gateway . "_cancel_return" ) ){
			$url = get_option('em_'. static::$gateway . "_cancel_return" );
		}else{
			if( get_class($EM_Booking) == 'EM_Multiple_Booking' ){
				$url = get_permalink(get_option('dbem_multiple_bookings_checkout_page'));
			}else{
				$url = $EM_Booking->get_event()->get_permalink();
			}
		}
		$query_args = array('payment_cancelled' => static::$gateway);
		if( !empty( $EM_Booking->booking_id ) ){
			$query_args['booking_id'] = $EM_Booking->booking_id;
			$query_args['n'] = wp_create_nonce('cancel_booking_'.static::$gateway.'_'.$EM_Booking->booking_id);
		}
		return add_query_arg($query_args, $url);
	}
	
	
/* --------------------------------------------------
* TRANSACTION FUNCTIONS
* --------------------------------------------------*/

	/**
	 * Records a transaction according to this booking and gateway type.
	 * @param EM_Booking $EM_Booking
	 * @param float $amount
	 * @param string $currency
	 * @param int $timestamp
	 * @param string $txn_id
	 * @param int $payment_status
	 * @param string $note
	 */
	public static function record_transaction($EM_Booking, $amount, $currency, $timestamp, $txn_id, $payment_status, $note, $payment_type = null, $payment_type_detail = null ) {
		global $wpdb;
		$data = array();
		$data['booking_id'] = $EM_Booking->booking_id;
		$data['transaction_gateway_id'] = $txn_id;
		$data['transaction_timestamp'] = $timestamp;
		$data['transaction_currency'] = $currency;
		$data['transaction_status'] = $payment_status;
		$data['transaction_total_amount'] = $amount;
		$data['transaction_note'] = $note;
		$data['transaction_gateway'] = static::$gateway;
		$data['transaction_payment_type'] = $payment_type;
		$data['transaction_payment_type_detail'] = $payment_type_detail;

		if( !empty($txn_id) ){
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT transaction_id, transaction_status, transaction_gateway_id, transaction_total_amount FROM ".EM_TRANSACTIONS_TABLE." WHERE transaction_gateway = %s AND transaction_gateway_id = %s AND transaction_status=%s", static::$gateway, $txn_id, $payment_status ) );
		}
		$table = EM_TRANSACTIONS_TABLE;
		if( is_multisite() && !EM_MS_GLOBAL && !empty($EM_Booking->get_event()->blog_id) && !is_main_site($EM_Booking->get_event()->blog_id) ){
			//we must get the prefix of the transaction table for this event's blog if it is not the root blog
			$table = $wpdb->get_blog_prefix($EM_Booking->get_event()->blog_id).'em_transactions';
		}
		if( !empty($existing->transaction_gateway_id) && $amount == $existing->transaction_total_amount ) {
			//Duplicate, so we log and ignore it.
			EM_Pro::log('Duplicate Transaction (ID '.$existing->transaction_id.') Received and Ignored - Booking ID '.$EM_Booking->booking_id, static::$gateway);
		}else{
			// As of EM Pro 2.6.5 we will not update previous transaction but create new ones, so that there's a fuller history of transaction operations
			if( is_numeric($timestamp) ){ //convert unix timestamps
				$data['transaction_timestamp'] = date('Y-m-d H:i:s', $timestamp);
			}
			EM_Pro::log('New Transaction - Gateway TXN ID '.$txn_id.' | Booking ID '.$EM_Booking->booking_id, static::$gateway);
			$wpdb->insert( $table, $data );
		}
	}
	
	/**
	 * Converts the transaction ID field in transaction admin tables into a clickable link to view the transaction on PayPal.
	 * @param $transaction_id
	 * @param $transaction
	 * @return string
	 */
	public static function em_gateways_transactions_table_gateway_id($transaction_id, $transaction ){
		$gateway_url = ( static::is_sandbox() ) ? static::$transaction_detail[1] : static::$transaction_detail[0];
		$title = sprintf( esc_attr__('View this transaction on %s', 'em-pro'), static::$transaction_detail[2]);
		$transaction_id = '<a href="'. esc_url(sprintf($gateway_url,$transaction->transaction_gateway_id)) .'" target="_blank" title="'.$title.'">'. $transaction->transaction_gateway_id .'</a>';
		return $transaction_id;
	}
	
	/**
	 * If this gateway $has_timeout is set to true, or has a custom timeout time, when there are bookings awaiting payment from this gateway that surpass the time limit
	 * this function will be executed and passed the relevant booking IDs. The gateway should double-check the status of the booking by querying the gateway and confiring
	 * somehow if the payment was made. If so, he approve, if not then cancel.
	 *
	 * For example, with PayPal this can be confirmed by supplying PayPal the unique invoice ID (via static::get_invoice_id() for reference when submitting payment, and then
	 * searching now with that ID. If a payment was in fact made but our site wasn't notified, we can double-check and prevent accidental deletions.
	 *
	 * @param array $booking_ids
	 *
	 * @return void
	 */
	public static function handle_booking_timeout( $booking_ids ){
		foreach( $booking_ids as $booking_id ) {
			$EM_Booking = em_get_booking( $booking_id ); //get first booking (we only handle one at a time for now, but this could be looped through if necessary
			static::handle_booking_timeout_action( $EM_Booking );
		}
	}
	
	/**
	 * @param \EM_Booking $EM_Booking
	 *
	 * @return void
	 */
	public static function handle_booking_timeout_action( $EM_Booking ){
		$EM_Booking->manage_override = true;
		if( get_option('em_'. static::$gateway . '_booking_timeout_action') === 'cancel' ) {
			$EM_Booking->cancel();
			EM_Pro::log('Booking ID '.$EM_Booking->booking_id.' Cancelled due to timeout', static::$gateway);
		} elseif( get_option('em_'. static::$gateway . '_booking_timeout_action') === 'delete' ){
			$EM_Booking->delete();
			EM_Pro::log('Booking ID '.$EM_Booking->booking_id.' Deleted due to timeout', static::$gateway);
		}
	}
	
	
/* --------------------------------------------------
 * HELPER FUNCTIONS
 * --------------------------------------------------*/
	
	/**
	 * Gets the API keys whether in live or test mode.
	 * @param $args
	 *
	 * @return false|array
	 */
	public static function get_api_keys( ...$args ){
		// handle passed $args, older PHP versions can pass array as argument for associative array, until PHP 8 is the norm and we can pass named variables
		if( !empty($args[0]) && is_array($args[0]) ) {
			$args = $args[0];
		}
		$mode = isset($args['mode']) ? $args['mode'] : false;
		$option_name = static::$api_option_name ?: 'em_' . static::$gateway . '_api';
		if ( $mode === 'live' || static::is_live_mode() ) {
			$keys = get_option( $option_name );
		} else {
			$keys = get_option( $option_name . '_test' );
		}
		return $keys;
	}
	
	/**
	 * Returns unique ID for use in transaction order meta for easy searching/linking of booking to transactions, combines uuid with a booking ID for extra uniqueness in event of gateway being used in other software
	 * @param EM_Booking $EM_Booking
	 *
	 * @return mixed|null
	 */
	public static function get_invoice_id( $EM_Booking ){
		$uuid = $EM_Booking->booking_uuid;
		if( !empty($EM_Booking->booking_meta['uuid']) ){
			// backwards compatible for old transactions since EM Pro > 3.1.3
			$uuid = $EM_Booking->booking_meta['uuid'];
		}
		$invoice_id = $uuid .'#'. $EM_Booking->booking_id;
		return apply_filters('em_gateway_'. static::$gateway. '_get_invoice_id', $invoice_id, $EM_Booking, static::class);
	}
	
	/**
	 * Gets the gateway option from the correct place. Does not require prefixing of em_gatewayname_
	 * Will be particularly useful when restricting possible gateway settings in MultiSite mode and sharing accross networks, use this and you're future-proof.
	 * @param string $name
	 * @return mixed
	 */
	public static function get_option( $name ){
		return get_option('em_'.static::$gateway.'_'.$name);
	}
	
	/**
	 * Updates the gateway option to the correct place. Does not require prefixing of em_gatewayname_
	 * Will be particularly useful when restricting possible gateway settings in MultiSite mode and sharing accross networks, use this and you're future-proof.
	 * @param string $name
	 * @param mixed $value
	 * @return boolean
	 */
	public static function update_option( $name, $value ){
		return update_option('em_'.static::$gateway.'_'.$name, $value);
	}
	
	/**
	 * Checks an EM_Booking object and returns whether or not this gateway is/was used in the booking.
	 * @param EM_Booking $EM_Booking
	 * @return boolean
	 */
	public static function uses_gateway($EM_Booking){
		return (!empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_meta['gateway'] == static::$gateway);
	}
	
	/**
	 * Returns whether gateway is active, in the particular context. The default context is bookings for backward compatibility.
	 * An additional object can be supplied in the event we have per-event selection of gateways (future-proof).
	 *
	 * @param $context
	 * @param $object
	 *
	 * @return bool
	 */
	public static function is_active( $context = 'bookings', $object = null ) {
		$active = Gateways::is_active( static::$gateway, $context, $object );
		if( $active && static::$requires_ssl && static::is_live() ) {
			return is_ssl() || em_constant( static::$testing_constant ); //stripe will not work out of SSL to adhere to their requirements
		}
		return $active;
	}
	
	public static function check_conditions( $check ) {
		$matches = true; // if no checks, then it's true
		if ( !empty($check['ips']) || !empty($check['users']) || !empty($check['events']) ) {
			// false until proven true
			$matches = array();
			// check against IP limits
			if( !is_array($check['ips']) ) $check['ips'] = empty($check['ips']) ? array() : explode(',', $check['ips']);
			if ( in_array( $_SERVER['REMOTE_ADDR'], $check['ips'] ) ) {
				$matches['ip'] = true;
			}
			// check against User limtis
			if( !is_array($check['users']) ) $check['users'] = empty($check['users']) ? array() : explode(',', $check['users']);
			if ( is_user_logged_in() ) {
				if ( in_array( get_current_user_id(), $check['users'] ) ) {
					$matches['user'] = true;
				}
			}
			// check against event type
			$current_event_id = Gateways::$current_event_id;
			if( !is_array($check['events']) ) $check['events'] = empty($check['events']) ? array() : explode(',', $check['events']);
			if ( $current_event_id && in_array( $current_event_id, $check['events'] ) ) {
				$matches['event'] = true;
			}
			if( empty($matches) ){
				$matches = false;
			}
		}
		return $matches;
	}
	
	/**
	 * If gateway supports sandbox/live mode, returns true if in live/production mode. Returns a boolean value if in limited test mode.
	 *
	 * @param string $context
	 * @param EM_Booking|EM_Event|null $object
	 * @return bool
	 */
	public static function is_displayable( $context = 'bookings', $object = null ) {
		$is_displayable = true;
		if( static::is_test_mode() ){
			// check against IP and User ID
			$check = array(
				'ips' => get_option('em_'. static::$gateway . '_test_hide_ips'),
				'users' => get_option('em_'. static::$gateway . '_test_hide_users'),
				'events' => get_option('em_'. static::$gateway . '_test_hide_events'),
			);
			$is_displayable = static::check_conditions( $check );
			if( is_array($is_displayable) ) {
				$is_displayable = in_array(true, $is_displayable);
			}
		}
		return $is_displayable;
	}
	
	/**
	 * If gateway supports sandbox/live mode, returns true if in sandbox/test mode.
	 *
	 * @deprecated
	 * @use static::is_test_mode()
	 * @return bool
	 */
	public static function is_sandbox(){
		return static::is_test_mode();
	}
	
	/**
	 * If gateway supports sandbox/live mode, returns true if in live/production mode.
	 *
	 * @deprecated
	 * @use static::is_live_mode()
	 * @return bool
	 */
	public static function is_live(){
		return static::is_live_mode();
	}
	
	/**
	 * If gateway supports sandbox/live mode, returns true if in live/production mode. Returns a boolean value or an array if in limited test mode.
	 *
	 * @return bool|array
	 */
	public static function is_test_mode( $check_limited = true ){
		if( static::$force_mode ) return static::$force_mode === 'test'; // forced mode is set, return whatever it says
		$is_test_mode = get_option('em_'. static::$gateway . "_mode" ) !== 'live';
		if( $is_test_mode && $check_limited && get_option('em_'. static::$gateway . '_test_limited') ) {
			// get all the checks and see if any are truthy, meaning limited mode is on
			$check = array(
				'ips' => explode( ',', str_replace(' ', '', get_option('em_'. static::$gateway . '_test_ips')) ),
				'users' => explode( ',', str_replace(' ', '', get_option('em_'. static::$gateway . '_test_users')) ),
				'events' => explode( ',', str_replace(' ', '', get_option('em_'. static::$gateway . '_test_events')) ),
			);
			$is_test_mode = static::check_conditions( $check );
		}
		return $is_test_mode;
	}
	
	public static function is_live_mode(  $check_limited = true  ){
		if( static::$force_mode ) return static::$force_mode === 'live'; // forced mode is set, return whatever it says
		// check if pure live mode, otherwise if limited test mode is active but not applicable in this instance
		return get_option('em_'. static::$gateway . "_mode" ) === 'live' || !self::is_test_mode( $check_limited );
	}
	
	public static function is_mode( $mode ){
		// get the mode, then test against $mode
		return static::get_mode() === $mode;
	}
	
	public static function get_mode(){
		$current_mode = get_option('em_'. static::$gateway . "_mode" ) === 'live' ? 'live' : 'test';
		if ( $current_mode === 'test' && static::$supports_test_mode ) {
			if ( get_option('em_'. static::$gateway . '_test_limited') ) {
				$ips = str_replace(' ', '', get_option('em_'. static::$gateway . '_test_ips'));
				$users = str_replace(' ', '', get_option('em_'. static::$gateway . '_test_users'));
				$events = str_replace(' ', '', get_option('em_'. static::$gateway . '_test_events'));
				if( !empty($ips) || !empty($users) || !empty($events) ) {
					$current_mode = 'limited';
				}
			}
		}
		return $current_mode;
	}
	
	/**
	 * Sets the gateway mode to either live or test strings provided in $object.
	 * If $object is an EM_Booking, it will set the gateway mode to the mode the booking was under at time of creation.
	 *
	 * @param mixed $object
	 *
	 * @return string
	 */
	public static function force_mode( $object ){
		$force_mode = static::$force_mode;
		if ( $object instanceof EM_Booking ) {
			$EM_Booking = $object;
			static::$force_mode = empty($EM_Booking->booking_meta['test']) ? 'live' : 'test';
		} elseif ( $object === 'live' || $object === 'test' ) {
			static::$force_mode = $object;
		}
		return $force_mode;
	}
	
	/* ------------------------------------------------------------------------------------------------------------------------------------------------------
	 * BUTTONS MODE Functions - i.e. booking doesn't require gateway selection, just button click, EMP adds gateway choice via JS to submission
	 * ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Shows button, not needed if using the new form display
	 * @return string
	 */
	public static function booking_form_button(){
		ob_start();
		if( preg_match('/https?:\/\//',get_option('em_'. static::$gateway . "_button")) ): ?>
			<input type="image" class="em-booking-submit em-gateway-button em-gateway-button-image" id="em-gateway-button-<?php echo static::$gateway; ?>" data-gateway="<?php echo esc_attr(static::$gateway); ?>" src="<?php echo get_option('em_'. static::$gateway . "_button"); ?>" alt="<?php echo static::$title; ?>" />
		<?php else: ?>
			<input type="submit" class="em-booking-submit em-gateway-button em-button" id="em-gateway-button-<?php echo static::$gateway; ?>" value="<?php echo get_option('em_'. static::$gateway . "_button",static::$title); ?>" />
		<?php endif;
		return ob_get_clean();
	}
	
	
/* --------------------------------------------------
 * ADMIN LOADERS
 * -------------------------------------------------- */

	/**
	 * Loads the gateway admin class parent and child classes and returns the child (or parent if not defined) \EM\Payments\Gateway_Name\Gateway_Admin class.
	 * @return Gateway_Admin
	 * @throws \Exception
	 */
	public static function admin(){
		// load default base class if not already loaded
		if( !class_exists( self::class . '_Admin' ) ){
			include_once('gateway-admin.php');
		}
		// get admin class and load
		return static::admin_load();
	}
	
	/**
	 * Loads and inits the admin gateway class if the child gateway has the same file prefixed '-admin.php'. For example, the gateway class EM\Payments\Offline\Gateway :
	 *
	 * /path/to/gateway.offline.php
	 *
	 * Will auto-load this file if it exists IN THE SAME DIRECTORY, which should also contain the EM\Payments\Offline\Gateway_Admin class:
	 *
	 * /path/to/gateway.offline-admin.php
	 *
	 * If you use a custom filename or custom class name, override this method and load/return your relevant class names.
	 *
	 * @return Gateway_Admin
	 * @throws \Exception
	 */
	public static function admin_load(){
		$admin_class = self::class .'_Admin'; /* @var Gateway_Admin $admin_class */
		if( static::class !== self::class ){
			$admin_class = static::class .'_Admin';
			if( !class_exists($admin_class) ){
				$filename = (new \ReflectionClass(static::class))->getFileName();
				$admin_filename = str_replace('.php', '-admin.php', $filename);
				if( file_exists($admin_filename) ){
					include_once($admin_filename);
				}
				if( !class_exists( $admin_class ) ) {
					throw new \Exception('An admin class must be defined for '. static::$gateway );
				}else{
					$admin_class::init();
				}
			}
			/* @var Gateway_Admin $admin_class */
			return $admin_class;
		}
		return $admin_class;
	}

/* ------------------------------------------------------------------------------------------------------------------------------------------------------
 * (DEPRECATED) NOTIFICATION LISTENER - Listeners for notifications sent by outdated protocols, use webhooks via REST APIs instead when possible
 * ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * If you set your gateway class $payment_return_ajax property to true, this function will be called when your external gateway sends a notification of payment.
	 *
	 * Override this in your function to catch payment returns and do something with this information, such as handling refunds.
	 */
	public static function handle_payment_return(){}
	
	/**
	 * Returns the notification URL which gateways sends return messages to, e.g. notifying of payment status.
	 *
	 * Your URL would correspond to http://yoursite.com/wp-admin/admin-ajax.php?action=em_payment&em_payment_gateway=gatewayname
	 * @return string
	 */
	public static function get_payment_return_url(){
		$return_url = admin_url('admin-ajax.php?action=em_payment&em_payment_gateway='.static::$gateway);
		if( em_constant('EM_GATEWAY_API_DOMAIN') ){
			// localhost testing allows you to set up a tunnel like ngrok or localxpose
			$return_url = preg_replace('/https?:\/\/[^\/]+/', EM_GATEWAY_API_DOMAIN, $return_url);
		}
		return $return_url;
	}
}
?>