<?php
namespace EM\Payments;
use EM_Gateways_Transactions, EM_Booking, EM_Multiple_Bookings;

class Gateways {
	
	/**
	 * Array of registered gateways that can be used on this site.
	 * @var Gateway[]
	 */
	protected static $registered = array();

	public static $customer_fields = array (
		'address' => 'Address',
		'address_2' => 'Address Line 2',
		'city' => 'City',
		'state' => 'State/County',
		'zip' => 'Zip/Post Code',
		'country' => 'Country',
		'phone' => 'Phone',
		'fax' => 'Fax',
		'company' => 'Company'
	);

	/**
	 * The current event ID being acted on, used in situations such as determining Limited Test Mode for a specific event.
	 * @var int
	 */
	public static $current_event_id;
	public static $previous_event_id = array();
	
	
	public static function init(){
		//add to booking interface (menu options, booking statuses)
		add_action('em_bookings_table',array( static::class, 'em_bookings_table'),10,1);
		// Payment return
		add_action('wp_ajax_em_payment', array( static::class, 'handle_payment_gateways'), 10 );
		add_action('wp_ajax_nopriv_em_payment', array( static::class, 'handle_payment_gateways'), 10 );
		add_action('init', array( static::class, 'handle_payment_redirections')); //handle successful and cancelled payments via redirection from gateways
		//Booking Tables UI
		add_filter('em_bookings_sql_fields_orderby_booking_meta', array( static::class, 'em_bookings_sql_fields_orderby_booking_meta'), 10, 2);
		add_filter('em_bookings_table_get_sortable_columns', array( static::class, 'em_bookings_table_get_sortable_columns'), 10, 2);
		add_filter('em_bookings_table_rows_col', array( static::class, 'em_bookings_table_rows_col'),10,5);
		add_filter('em_bookings_table_cols_template', array( static::class, 'em_bookings_table_cols_template'),10,2);
		add_filter('em_bookings_table_cols_template_groups', array( static::class, 'em_bookings_table_cols_template_groups'), 10, 1);
		//Booking interception
		if( get_option('dbem_multiple_bookings') && empty($_REQUEST['manual_booking']) ) {
		    //Multiple bookings mode (and not doing a manual booking)
			add_filter('em_multiple_booking_validate', array( static::class, 'em_booking_validate'), 10, 2);
			add_action('em_multiple_booking_add', array( static::class, 'em_booking_add'), 10, 3);
			add_filter('em_multiple_booking_get_post',array( static::class, 'em_booking_get_post'), 10, 2);
			add_filter('em_action_emp_checkout', array( static::class, 'em_action_booking_add'),10,2); //adds gateway var to feedback
			//Booking Form Modifications
			add_action('em_checkout_form_confirm_footer', array( static::class, 'mb_payment_form'),10,2);
			add_action('em_manual_booking_form_confirm_footer', array( static::class, 'event_booking_payment_form'),10,2);
		} else {
		    //Normal Bookings mode, or manual booking
			add_filter('em_booking_validate', array( static::class, 'em_booking_validate'), 10, 2);
			add_action('em_booking_add', array( static::class, 'em_booking_add'), 10, 3);
			add_filter('em_booking_get_post',array( static::class, 'em_booking_get_post'), 100, 2);
			add_filter('em_booking_added', array( static::class, 'em_booking_added'), 10, 2);
			add_filter('em_action_booking_add', array( static::class, 'em_action_booking_add'),10,2); //adds gateway var to feedback
			//Booking Form Modifications
				//new way, with payment selector
				add_action('em_booking_form_confirm_footer', array( static::class, 'event_booking_payment_form'),10,2);
				// back-compat for the above action in case templates are overriden but outdated
				add_action('em_booking_form_footer', array(static::class, 'em_booking_form_footer'),10,2);
		}

		//data privacy - transaction history
        add_filter('em_data_privacy_export_bookings_items_after_item', array( static::class, 'data_privacy_export'), 10, 3);
		
		// Now Include things
		if( is_admin() ){
			include('gateways-admin.php');
		}
		include('gateway.php');
		include('gateways.transactions.php');
		include('gateways.transactions-table.php');
		include('gateways-legacy.php'); // prevent major errors
		
		// load native gateways
		include('gateway.offline.php');
		include('paypal-legacy-standard/gateway.paypal.php');
		include('authorize-aim/gateway.authorize.aim.php');
		
		//set up cron
		$timestamp = wp_next_scheduled('em_gateways_cron');
		if( absint( get_option('dbem_gateway_payment_timeout', 15)) > 0 && !$timestamp ){
			$result = wp_schedule_event(time(),'em_minute','em_gateways_cron');
		}elseif( !$timestamp ){
			wp_unschedule_event($timestamp, 'em_gateways_cron');
		}
		add_action('em_gateways_cron', array( static::class, 'check_timeouts'));
		
		// init hook
		add_action('wp_loaded', [ static::class, 'wp_loaded'] );
		do_action('em_gateways_init');
	}

	public static function wp_loaded() {
		//booking gateways JS
		static::$customer_fields = array(
			'address' => __('Address','em-pro'),
			'address_2' => __('Address Line 2','em-pro'),
			'city' => __('City','em-pro'),
			'state' => __('State/County','em-pro'),
			'zip' => __('Zip/Post Code','em-pro'),
			'country' => __('Country','em-pro'),
			'phone' => __('Phone','em-pro'),
			'fax' => __('Fax','em-pro'),
			'company' => __('Company','em-pro')
		);
	}
	
	public static function em_bookings_table( $EM_Bookings_Table ){
		$EM_Bookings_Table->statuses['awaiting-online'] = array('label'=>__('Awaiting Online Payment','em-pro'), 'search'=>4);
		$EM_Bookings_Table->statuses['awaiting-payment'] = array('label'=>__('Awaiting Offline Payment','em-pro'), 'search'=>5);
		if( !get_option('dbem_bookings_approval') ){
			$EM_Bookings_Table->statuses['needs-attention']['search'] = array(5);
		}else{
			$EM_Bookings_Table->statuses['needs-attention']['search'] = array(0,5);
		}
		if ( empty($EM_Bookings_Table->filters['status']) ) {
			// tweak default status filter if not already set
			$EM_Bookings_Table->status = ( !empty($_REQUEST['status']) && array_key_exists($_REQUEST['status'], $EM_Bookings_Table->statuses) ) ? $_REQUEST['status']:get_option('dbem_default_bookings_search','needs-attention');
		}
	}
	
  /* ------------------------------------------------------------------------------------------------------------------------------------------------------
   * REGISTRATON AND UTLITY FUNCTIONS - Registering gateways, deregistering, getting, lists, checking if active etc.
   * ------------------------------------------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Deregister a gateway from being used on site.
	 * @param Gateway $class
	 * @return bool
	 */
	public static function register( $class ) {
		if( class_exists($class) ){
			static::$registered[$class::$gateway] = $class;
			return true;
		}
		return false;
	}
	
	/**
	 * Deregister a gateway from being used on site.
	 * @param $gateway
	 * @return bool
	 */
	public static function deregister( $gateway ){
		if( !empty( static::$registered[$gateway] ) ){
			unset( static::$registered[$gateway] );
			return true;
		}
		return false;
	}
		
	/**
	 * Returns an array of active gateway objects
	 * @return Gateway[]
	 */
	public static function active_gateways( $context = 'bookings', $object = null ) {
		$active_gateways = get_option('em_payment_gateways', array());
		$gateways = array();
		foreach( $active_gateways as $gateway => $active ){
			if( $active && static::is_registered($gateway) && static::is_active_for( $gateway, $context, $object ) ){
				$gateways[$gateway] = self::$registered[$gateway];
			}
		}
		return $gateways;
	}
	
	/**
	 * Returns an array of all registered gateway object names with gateway name/key as keys.
	 * @return string[]
	 */
	public static function gateways_list() {
		$gateways = array();
		foreach( static::$registered as $Gateway){
			$gateways[$Gateway::$gateway] = $Gateway::$title;
		}
		return $gateways;
	}
	
	public static function list(){
		return static::$registered;
	}
	
	/**
	 * Returns the Gateway static class with supplied name
	 * @param string $gateway
	 * @return string|Gateway
	 */
	public static function get( $gateway ){
		//check for array key first
		if( !empty(static::$registered[$gateway]) && static::$registered[$gateway]::$gateway == $gateway ) return static::$registered[$gateway];
		//otherwise we loop through the gateways array in case the gateway key registered doesn't match the actual gateway name
		foreach(static::$registered as $Gateway){
			if( $Gateway::$gateway == $gateway ) return $Gateway;
		}
		return Gateway::class; //returns a blank EM\Payments\Gateway regardless to avoid fatal errors
	}
	
	/**
	 * @deprecated
	 * @use Gateways::get();
	 * @see Gateways::get();
	 * @param $gateway
	 * @return Gateway|string
	 */
	public static function get_gateway( $gateway ){
		return static::get( $gateway );
	}
	
	/**
	 * Checks whether supplied gateway key name or class name is registered.
	 * @param string|Gateway $gateway
	 *
	 * @return bool
	 */
	public static function is_registered( $gateway ){
		if( isset( static::$registered[$gateway] ) ){
			return true;
		} elseif ( class_exists($gateway) ) {
			$gateway = $gateway::$gateway;
			return isset( static::$registered[$gateway] );
		}
		return false;
	}
	
	/**
	 * Checks if a gateway is registered and active.
	 *
	 * @param string $gateway   The gateway name/key.
	 * @param string $context   What context is this gateway active under, e.g. regular bookings, multiple bookings, event registration payments, etc.
	 * @param mixed $object     The object specifically being checked against, e.g. an event taking bookings (for future implementation of per-event gateways)
	 *
	 * @return bool
	 */
	public static function is_active( $gateway, $context = null, $object = null ){
		$active_gateways = get_option('em_payment_gateways', array());
		$is_active = array_key_exists( $gateway, $active_gateways ) && $active_gateways[$gateway] && static::is_registered( $gateway );
		if( $context !== null && $is_active ) {
			$is_active = static::is_active_for( $gateway, $context, $object );
		}
		return $is_active;
	}
	
	/**
	 * Checks if gateway is active for a specific context or object, assumes that the gateway is generally active
	 * @param string|Gateway $gateway   The gateway string or class name.
	 * @param string $context           What context is this gateway active under, e.g. regular bookings, multiple bookings, event registration payments, etc. Supports 'bookings' and 'events'
	 * @param $object                   A booking or event object to check against if necessary
	 *
	 * @return array|bool
	 */
	public static function is_active_for( $gateway, $context = null, $object = null ){
		if( $context === 'bookings' ){
			if( get_option('dbem_multiple_bookings') ){
				$supports_mb = static::get( $gateway )::$supports_multiple_bookings;
				return $supports_mb && static::get( $gateway )::is_displayable($context, $object);
			}else{
				return static::get( $gateway )::is_displayable($context, $object);
			}
		}
		return $context === null;
	}
	
	/**
	 * Useful for setting and resetting the current event ID being worked on. Returns the current event_id before updated value.
	 * Ideally should be used at start and end of a function, getting the old id at start and resetting it again at the end.
	 * @param \EM_Event|\EM_Booking|\EM_Object|int $object Any object that has event_id property, or the event ID directly
	 *
	 * @return int|null
	 */
	public static function switch_current_event( $object ) {
		$previous_event_id = static::$current_event_id;
		if( is_numeric($object) ) {
			static::$current_event_id = $object;
		} elseif ( is_object($object) && !empty($object->event_id) ) {
			static::$current_event_id = $object->event_id;
		} elseif ( !$object ) {
			static::$current_event_id = null;
		}
		static::$previous_event_id[] = $previous_event_id;
		return $previous_event_id;
	}
	
	public static function restore_current_event() {
		static::$current_event_id = array_pop( static::$previous_event_id );
	}
	
	public static function load_current_event() {
		if( !empty($_REQUEST['booking_id']) ) {
			$EM_Booking = em_get_booking( absint($_REQUEST['booking_id']) );
			self::switch_current_event( $EM_Booking );
		} elseif( !empty($_REQUEST['event_id']) ) {
			self::switch_current_event( absint($_REQUEST['event_id']) );
		}
	}
	
	public static function unload_current_event() {
		if( !empty($_REQUEST['booking_id']) ) {
			$EM_Booking = em_get_booking( absint($_REQUEST['booking_id']) );
			if( $EM_Booking->event_id == Gateways::$current_event_id ) {
				self::restore_current_event();
			}
		} elseif( !empty($_REQUEST['event_id']) ) {
			if( $_REQUEST['event_id'] == Gateways::$current_event_id ) {
				self::restore_current_event();
			}
		}
	}
	
	
  /* ------------------------------------------------------------------------------------------------------------------
   * Booking Interception - functions that modify booking object behaviour
   * ------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Hooks into em_booking_get_post filter and makes sure that if there's an active gateway for new bookings, if no $_REQUEST['gateway'] is supplied (i.e. hacking, spammer, or js problem with booking button mode).
	 * @param boolean $result
	 * @param EM_Booking $EM_Booking
	 * @return boolean
	 */
	public static function em_booking_get_post($result, $EM_Booking){
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] === 'booking_form_summary' ) {
			// only proceed if we're not asking for a booking form summary
			return $result;
		}
	    if( get_option('dbem_multiple_bookings') && get_class($EM_Booking) == 'EM_Booking' && !emp_is_manual_booking() ){
			//we only deal with the EM_Multiple_Booking class if we're in multi booking mode unless it's a manual booking
	        return $result;
	    }
		static::switch_current_event( $EM_Booking );
		// hard intercept booking process if gateway not selected or unrecognized
		if( empty($EM_Booking->booking_id) && (empty($_REQUEST['gateway']) || !static::is_active($_REQUEST['gateway'], 'bookings')) && $EM_Booking->get_price() > 0 && count(static::active_gateways('bookings', $EM_Booking)) > 0 ) {
	        //spammer or hacker trying to get around no gateway selection
	    	$error = __('Choice of payment method not recognized. If you are seeing this error and selecting a method of payment, we apologize for the inconvenience. Please contact us and we\'ll help you make a booking as soon as possible.','em-pro');
	    	$EM_Booking->add_error($error);
	    	$result = false;
	    	if( em_constant('DOING_AJAX') ){
	    		$return = array('result'=>false, 'message'=>$error, 'errors'=>$error);
	    		echo \EM_Object::json_encode($return);
	    		die();
	    	}
	    }elseif( !empty($_REQUEST['gateway']) ) {
			// if the booking isn't free, set gateway and other relevant values
			if ( $EM_Booking->get_price() > 0 ) {
				$Gateway = static::get( $_REQUEST['gateway'] );
				// set booking gateway
				$EM_Booking->booking_meta['gateway'] = $Gateway::$gateway;
				// set booking status out the door
				$EM_Booking->booking_status = $Gateway::$status; // e.g. status 4 = awaiting online payment
				// check if this booking is in test mode, if so, we add it right now and we know onwards that we're in test mode for this booking
				if ( $Gateway::is_test_mode() ) {
					$EM_Booking->booking_meta['test'] = true;
				}
			}
		}
		static::restore_current_event();
	    return $result;
	}
	
	/**
	 * Hooks into the em_booking_validate filter and runs optional gateway validation before it is saved, such as card details.
	 * @param bool $result
	 * @param EM_Booking $EM_Booking
	 *
	 * @return bool
	 */
	public static function em_booking_validate( $result, $EM_Booking ){
		if( get_option('dbem_multiple_bookings') && get_class($EM_Booking) == 'EM_Booking' && !emp_is_manual_booking() ){ //we only deal with the EM_Multiple_Booking class if we're in multi booking mode
			return $result;
		}
		if( !empty($EM_Booking->booking_meta['gateway']) && !empty($_REQUEST['gateway']) && static::is_active($_REQUEST['gateway']) ){
			$Gateway = static::get($EM_Booking->booking_meta['gateway']);
			static::switch_current_event( $EM_Booking ); // for Test Mode
			$result = $result && $Gateway::booking_validate( $EM_Booking );
			static::restore_current_event(); // for Test Mode
		}
		return $result;
	}
	
	/**
	 * Intercepted when a booking is about to be added and saved, calls the relevant booking gateway action provided gateway is provided in submitted request variables.
	 * @param \EM_Event $EM_Event the event the booking is being added to
	 * @param EM_Booking $EM_Booking the new booking to be added
	 * @param boolean $post_validation
	 */
	static function em_booking_add($EM_Event, $EM_Booking, $post_validation = false){
		if( !empty($EM_Booking->booking_meta['gateway']) && static::is_active($EM_Booking->booking_meta['gateway']) ){
			$Gateway = static::get($EM_Booking->booking_meta['gateway']);
			//Individual gateways will hook into this function
			static::switch_current_event( $EM_Booking ); // for Test Mode
			$Gateway::booking_add($EM_Event, $EM_Booking, $post_validation);
			static::restore_current_event(); // for Test Mode
		}
	}
	
	/**
	 * When a booking is added via form submission, this will fire the subsequent booking_added() function of the relevant gateway class, for easier integration.
	 * @param EM_Booking $EM_Booking the new booking to be added
	 * @param boolean $post_validation
	 */
	static function em_booking_added( $EM_Booking ){
		if( !empty($_REQUEST['gateway']) && static::is_active($_REQUEST['gateway']) ){
			$Gateway = static::get($_REQUEST['gateway']);
			if( $Gateway::uses_gateway($EM_Booking) ){
				//Individual gateways will hook into this function
				static::switch_current_event( $EM_Booking ); // for Test Mode
				$Gateway::booking_added( $EM_Booking );
				static::restore_current_event(); // for Test Mode
			}
		}
	}
	
	/**
	 * Backwards compatible hook in case templates are overriden but outdated
	 * @param $EM_Event
	 *
	 * @return void
	 */
	public static function em_booking_form_footer ( $EM_Event ){
		// if firing hook via the back-compat mode then don't proceed, since we'll also likely have the new hook above
		if( !did_action('em_booking_form_confirm_footer') ){
			static::event_booking_payment_form( $EM_Event );
		}
	}
	
	public static function event_booking_payment_form( $EM_Event ){
		static::switch_current_event( $EM_Event ); // for Test Mode
	    self::payment_form( $EM_Event->event_id );
		static::restore_current_event(); // for Test Mode
	}
	
	public static function mb_payment_form(){
	    $EM_Multiple_Booking = EM_Multiple_Bookings::get_multiple_booking();
		static::switch_current_event( 0 ); // for Test Mode
	    if( $EM_Multiple_Booking->get_price() > 0 ){
	        self::payment_form( 0 );
	    }
		static::restore_current_event(); // for Test Mode
	}
	
	public static function em_action_booking_add($return){
		if( !empty($_REQUEST['gateway']) ){
			$return['gateway'] = $_REQUEST['gateway'];
		}
		return $return;
	}
	
	
  /* ------------------------------------------------------------------------------------------------------------------
   * PAYMENT FORM - Generate a payment form for anything
   * ------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Gets called at the bottom of the form before the submit button. 
	 * Outputs a gateway selector and allows gateways to hook in and provide their own payment information to be submitted.
	 * By default each gateway is wrapped with a div with id em-booking-gateway-x where x is the gateway for JS to work.
	 * 
	 * To prevent this from firing, call this function after the init action:
	 * remove_action('em_booking_form_footer', array('\EM\Payments\Gateways','payment_form'),1,2);
	 * 
	 * You'll have to ensure a gateway value is submitted in your booking form in order for paid bookings to be processed properly.
	 */
	public static function payment_form( $id = null ){
		$id = $id === null ? rand() : absint($id);
		$active_gateways = static::active_gateways();
		// filter out non-displayable gateways
		foreach ( $active_gateways as $gateway => $Gateway ) {
			if( !$Gateway::is_displayable() ) {
				unset($active_gateways[$gateway]);
			}
		}
		//Check if we can user quick pay buttons
		if( get_option('dbem_gateway_use_buttons', 1) && static::buttons_mode_possible() ){ //backward compatability
			echo static::booking_form_buttons( '', $id );
			do_action('em_gateways_payment_form_footer');
		}else{
			//Continue with payment gateway selection
			// add hooks before
			foreach( $active_gateways as $Gateway ){
				$Gateway::payment_form_header( $id );
			}
			//Add gateway selector
			?>
			<div class="em-booking-section em-booking-gateway em-payment-gateways hidden" id="em-payment-gateways-<?php echo $id; ?>" data-id="<?php echo $id; ?>">
				<?php
				if( count($active_gateways) > 1 ){
					$payment_form_type = get_option('dbem_gateways_form_selector', 'select');
					?>
					<p class="em-payment-gateway-selector">
						<?php if( $payment_form_type === 'select' ): ?>
						<label for="em-payment-gateways-<?php echo $id; ?>"><?php echo get_option('dbem_gateway_label'); ?></label>
						<select id="em-payment-gateways-<?php echo $id; ?>" name="gateway" class="em-payment-gateway-options">
							<?php
							foreach($active_gateways as $gateway => $Gateway){
								echo $Gateway::payment_form_selector( $payment_form_type, $id );
								$selected = (!empty($selected)) ? $selected:$gateway;
							}
							?>
						</select>
						<?php elseif( $payment_form_type === 'radio' ): ?>
						<fieldset id="em-payment-gateways-<?php echo $id; ?>" name="gateway" class="em-payment-gateway-options">
							<?php
							foreach($active_gateways as $gateway => $Gateway){
								echo $Gateway::payment_form_selector( $payment_form_type, $id );
								$selected = (!empty($selected)) ? $selected:$gateway;
							}
							?>
						</fieldset>
						<?php endif; ?>
					</p>
					<?php
				}elseif( count($active_gateways) == 1 ){
					$Gateway = current($active_gateways);
					$selected = (!empty($selected)) ? $selected: $Gateway::$gateway;
					echo $Gateway::payment_form_selector( 'hidden', $id );
				}
				foreach( $active_gateways as $gateway => $Gateway ){
					$hidden = ($selected == $gateway) ? '' : ' hidden';
					$data_hidden = ( empty($Gateway::$payment_flow['loader']) ) ? '' : ' hidden';
					$skeleton_hidden = $data_hidden ? '' : ' hidden';
					echo '<div class="em-payment-gateway-form em-payment-gateway-form-' . $gateway . $hidden . '" id="em-payment-gateway-' . $gateway . '-' . $id . '">';
					echo '<div class="em-payment-gateway-form-info">';
					$Gateway::payment_form_info( $id );
					echo "</div>";
					echo '<div class="em-payment-gateway-form-data ' . $data_hidden . '">';
					$Gateway::payment_form( $id );
					echo "</div>";
					if ( ( !empty($Gateway::$payment_flow['loader']) ) ) {
						echo '<div class="em-payment-gateway-form-loading">';
						$Gateway::payment_form_loading( $id );
						echo "</div>";
					}
					echo "</div>";
				}
				// add hooks after
				foreach( $active_gateways as $Gateway ){
					$Gateway::payment_form_footer( $id );
				}
				do_action('em_gateways_payment_form_footer');
				?>
			</div>
			<?php
		}
		?>
		<script type="text/javascript">
			<?php
			$gateways_localized = apply_filters('em_gateways_localize_script', array() );
			foreach( $active_gateways as $Gateway ){
				$gateway_vars = $Gateway::localize_js( $id );
				if ( !empty($gateway_vars) ) {
					if ( !empty($gateways_localized[$Gateway::$js_localize]) ) {
						$gateways_localized[$Gateway::$js_localize] = array_merge( $gateways_localized[$Gateway::$js_localize], $gateway_vars );
					} else {
						$gateways_localized[$Gateway::$js_localize] = $gateway_vars;
					}
				}
			}
			if( !empty($gateways_localized) ){
				// output like wp_localize_js
				echo "EM.Gateways = " . wp_json_encode( $gateways_localized ) . ';';
			}
			foreach( $active_gateways as $Gateway ){
				$Gateway::payment_form_js( $id );
			}
			include( dirname(__FILE__).'/gateways.js' );
			?>
		</script>
		<?php
		return $id; //for filter compatibility
	}
	
  /* ------------------------------------------------------------------------------------------------------------------
   * Payment Notification Listeners e.g. for PayPal IPNs or similar postbacks
   * ------------------------------------------------------------------------------------------------------------------ */
	
	/**
	 * Gateways that redirect back will be processed here first if using default links
	 * @return void
	 */
	public static function handle_payment_redirections(){
		if( !empty($_REQUEST['payment_cancelled']) && !empty($_REQUEST['booking_id']) && !empty($_REQUEST['n']) && wp_verify_nonce($_REQUEST['n'], 'cancel_booking_'.$_REQUEST['payment_cancelled'].'_'.$_REQUEST['booking_id']) ){
			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
			if( $EM_Booking->booking_id ) {
				$Gateway = static::get( $_REQUEST['payment_cancelled'] );
				$force_mode = $Gateway::force_mode( $EM_Booking ); // for Test Mode
				$Gateway::handle_cancel_url( $EM_Booking );
				$Gateway::force_mode( $force_mode ); // for Test Mode
				
			}
		}
		if( !empty($_GET['payment_complete']) && static::is_active($_GET['payment_complete']) ){
			$Gateway = static::get($_GET['payment_complete']);
			$Gateway::handle_return_url();
		}
	}

	/**
	 * Checks whether em_payment_gateway is passed via WP_Query, GET or POST and fires the appropriate gateway filter.
	 * yoursite.com/wp-admin/admin-ajax.php?action=em_payment&em_payment_gateway=gatewayname
	 *
	 * @deprecated Use REST APIs instead wherever possible
	 */
	public static function handle_payment_gateways() {
	    //Listen on admin-ajax.php
		if( !empty($_REQUEST['em_payment_gateway']) ) {
			do_action( 'em_handle_payment_return_' . $_REQUEST['em_payment_gateway']);
			exit();
		}
	}
	
  /* ----------------------------------------------------------
   * Booking Table and CSV Export
   * ---------------------------------------------------------- */
	
	public static function em_bookings_table_rows_col($value, $col, $EM_Booking, $EM_Bookings_Table, $csv){
		if( $col == 'gateway' ){
			//get latest transaction with an ID
			if( !empty( $EM_Booking->booking_meta['gateway'] ) ){
				$Gateway = static::get_gateway( $EM_Booking->booking_meta['gateway'] );
				$value = $Gateway::$title;
			}else{
				$value = __( 'None', 'em-pro' );
			}
		}
		return $value;
	}
	
	public static function em_bookings_table_cols_template_groups( $template_groups ) {
		$template_groups['payment']['fields'][] = 'gateway';
		return $template_groups;
	}
	
	public static function em_bookings_table_cols_template( $template = array() ){
		$template['gateway'] = __( 'Gateway Used', 'em-pro' );
		return $template;
	}
	
	public static function em_bookings_table_get_sortable_columns( $sortable_columns, $EM_Bookings_Table ) {
		$sortable_columns[ 'gateway' ] = [ 'gateway', false ];
		return $sortable_columns;
	}
	
	public static function em_bookings_sql_fields_orderby_booking_meta( $orderby ) {
		$orderby['gateway'] = 'gateway';
		return $orderby;
	}

	
  /* ----------------------------------------------------------------------------------------------------
   * USER FIELDS - Adds user details link for use by gateways and options to form editor
   * ---------------------------------------------------------------------------------------------------- */
	
	/**
	 * Returns value of a customer field, which are common fields for payment gateways linked to custom user fields in the forms editor.
	 * @param string $field_name
	 * @param EM_Booking $EM_Booking
	 * @param string $user_or_id
	 * @return string
	 */
	static function get_customer_field($field_name, $EM_Booking = false, $user_or_id = false){
		//get user id
		if( is_numeric($user_or_id) ){
			$user_id = (int) $user_or_id; 
		}elseif(is_object($user_or_id)){
			$user_id = $user_or_id->ID;
		}elseif( !empty($EM_Booking->person_id) ){
			$user_id = $EM_Booking->person_id;		
		}else{
			$user_id = get_current_user_id();
		}
		//get real field id
		if( array_key_exists($field_name, self::$customer_fields) ){
			$associated_fields = get_option('emp_gateway_customer_fields');
			$form_field_id = $associated_fields[$field_name];
		}
		if( empty($form_field_id) ) return '';
		//determine field value
		if( $user_id === 0 && !empty($EM_Booking) ){ //no-user mode is assumed since id is exactly 0
			//get meta from booking if user meta isn't available
			if( !empty($EM_Booking->booking_meta['registration'][$form_field_id])){
				return $EM_Booking->booking_meta['registration'][$form_field_id];
			}
		}elseif( !empty($user_id) ){
			//get corresponding user meta field, the one in $EM_Booking takes precedence as it may be newer
			if( !empty($EM_Booking->booking_meta['registration'][$form_field_id]) ){
				return $EM_Booking->booking_meta['registration'][$form_field_id];
			}else{
    			$value = get_user_meta($user_id, $form_field_id, true);
				return $value;
			}			
		}
		return '';
	}

  /* ----------------------------------------------------------------------------------------------------
   * BUTTONS MODE Functions - i.e. booking doesn't require gateway selection, just button click
   * ---------------------------------------------------------------------------------------------------- */
	
	/**
	 * Determines whether bookings mode can be displayed, returns false if any of the gateways supplied in $gateways are not button-enabled.
	 * @param Gateway[] $gateways Default is all gateways that are active.
	 *
	 * @return bool
	 */
	public static function buttons_mode_possible ( $gateways = array() ) {
		if( empty($gateways) ) {
			$gateways = static::active_gateways();
		}
		foreach ( $gateways as $Gateway ) {
			if ( !$Gateway::$button_enabled ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * This gets called when a booking form created using the old buttons API, and calls subsequent gateways to output their buttons.
	 * @param string $button
	 * @param int $id
	 * @param \EM_Event $EM_Event
	 * @return string
	 */
	public static function booking_form_buttons($button = '', $id = 0){
		$gateway_buttons = array();
		$active_gateways = static::active_gateways();
		if( !empty($active_gateways) ){
			foreach( $active_gateways as $gateway => $Gateway ){
				if( $Gateway::$button_enabled) {
					$gateway_button = $Gateway::booking_form_button( $id );
					if(!empty($gateway_button)){
						$gateway_buttons[$gateway] = $gateway_button;
					}
				}
			}
			//$gateway_buttons = apply_filters('em_gateway_buttons', $gateway_buttons, $EM_Event);
			if( count($gateway_buttons) > 0 ){
				ob_start();
				?>
				<div class="em-gateway-buttons em-booking-section hidden">
					<?php echo implode('', $gateway_buttons); ?>
				</div>
				<?php
				$button = ob_get_clean();
			}
			if( count($gateway_buttons) > 1 ){
				$button .= '<input type="hidden" name="gateway" value="offline" class="em-payment-gateway-option" data-custom-button="1">';
			}else{
				$button .= '<input type="hidden" name="gateway" value="'.$gateway.'" class="em-payment-gateway-option" data-custom-button="1">';
			}
		}
		return apply_filters('em_gateway_booking_form_buttons', $button, $gateway_buttons);
	}
	
	/**
	 * OTHER FUNCTIONS
	 */

	/**
	 * Modifies exported multiple booking items
	 * @param array $export_item
	 * @param EM_Booking $EM_Booking
	 * @return array
	 */
	public static function data_privacy_export($export_items, $export_item, $EM_Booking ){
		if( get_option('dbem_multiple_bookings') ){
			$EM_MB_Booking = EM_Multiple_Bookings::get_main_booking($EM_Booking);
			if( $EM_Booking->booking_id != $EM_MB_Booking->booking_id ) return $export_items; //we don't need to export bookings with an MB parent
        }
        //get the transaction
		global $EM_Gateways_Transactions; /* @var EM_Gateways_Transactions $EM_Gateways_Transactions */
		$transactions = $EM_Gateways_Transactions->get_transactions( $EM_Booking );
        if( $EM_Gateways_Transactions->total_transactions > 0 ){
		    foreach( $transactions as $transaction ){
			    $transactions_item = array(
				    'group_id' => 'events-manager-booking-transactions',
				    'group_label' => __('Booking Transactions', 'events-manager'),
				    'item_id' => 'booking-transaction-'.$transaction->transaction_id, //replace ID with txn ID
				    'data' => array() // replace this with assoc array of name/value key arrays
			    );
			    if( get_class($EM_Booking) == 'EM_Multiple_Booking' ){
			        $events = array();
				    foreach( $EM_MB_Booking->get_bookings() as $EM_Booking ){ /* @var EM_Booking $EM_Booking */
				        //handle potentially deleted events in a MB booking
					    $events[] = !empty($EM_Booking->get_event()->post_id) ? $EM_Booking->get_event()->output('#_EVENTLINK - #_EVENTDATES @ #_EVENTTIMES') : __('Deleted Event', 'em-pro');
				    }
				    $transactions_item['data'][] = array('name' => __('Events','em-pro'), 'value' => implode('<br>', $events) );
                }else{
				    $EM_Event = $EM_Booking->get_event(); //handle potentially deleted events in a MB booking
				    $event_string = !empty($EM_Event->post_id) ? $EM_Event->output('#_EVENTLINK - #_EVENTDATES @ #_EVENTTIMES') : __('Deleted Event', 'em-pro');
				    $transactions_item['data'][] = array('name' => __('Event','em-pro'), 'value' => $event_string );
                }
			    $transactions_item['data'][] = array('name' => __('Status','em-pro'), 'value' => $transaction->transaction_status );
			    $transactions_item['data'][] = array('name' => __('Gateway','em-pro'), 'value' => $transaction->transaction_gateway );
			    $transactions_item['data'][] = array('name' => __('Date','em-pro'), 'value' => $transaction->transaction_total_amount .' '.$transaction->transaction_currency);
			    $transactions_item['data'][] = array('name' => __('Transaction ID','em-pro'), 'value' => $transaction->transaction_gateway_id );
			    $transactions_item['data'][] = array('name' => __('Notes','em-pro'), 'value' => $transaction->transaction_note );
            }
            $export_items[] = $transactions_item;
        }
		return $export_items;
	}
	
	/**
	 * Deletes bookings pending payment that are more than x minutes old, defined by paypal options.
	 */
	public static function check_timeouts(){
		global $wpdb;
		// Go through each gateway
		$gateway_timeout_default = absint(get_option('dbem_gateway_payment_timeout', 0)); // minutes
		foreach ( static::active_gateways() as $Gateway ) {
			if( $Gateway::$has_timeout === true && get_option('em_'. $Gateway::$gateway . '_booking_timeout_action') !== 'none' ){
				$minutes_to_subtract = 0;
				if( $gateway_timeout_default > 0 ) {
					// adheres to general timeout limit
					$minutes_to_subtract = $gateway_timeout_default;
				} elseif( get_option( 'em_' . $Gateway::$gateway . '_booking_timeout', 0 ) > 0 ) {
					// has a custom timeout limit, add to custom timeout time
					$minutes_to_subtract = absint($Gateway::$has_timeout);
				}
				if( $minutes_to_subtract > 0 ){
					//get booking IDs without pending transactions
					$EM_DateTime = new \EM_DateTime();
					$cut_off_time = $EM_DateTime->sub('PT'.$minutes_to_subtract.'M')->getDateTime(true); //get the time in UTC
					$sql = '
						SELECT b.booking_id FROM '.EM_BOOKINGS_TABLE.' b
						LEFT JOIN ' . EM_TRANSACTIONS_TABLE . ' t ON t.booking_id=b.booking_id
						LEFT JOIN ' . EM_BOOKINGS_META_TABLE ." m ON m.booking_id=b.booking_id
						WHERE booking_date < %s AND booking_status=4 AND transaction_id IS NULL AND m.meta_key='gateway' AND m.meta_value=%s";
					$sql = $wpdb->prepare( $sql, $cut_off_time, $Gateway::$gateway);
					if( EM_MS_GLOBAL ){
						$sql .= $wpdb->prepare(' AND b.event_id IN (SELECT event_id FROM '.EM_EVENTS_TABLE.' WHERE blog_id = %d)', get_current_blog_id());
					}
					if( get_option('dbem_multiple_bookings') ){ //multiple bookings mode
						//If we're in MB mode, check that this isn't the main booking, if it isn't then skip it.
						$sql .= ' AND b.booking_id NOT IN (SELECT booking_id FROM '. EM_BOOKINGS_RELATIONSHIPS_TABLE.')';
					}
					$booking_ids = $wpdb->get_col( $sql );
					if( count($booking_ids) > 0 ){
						$Gateway::handle_booking_timeout( $booking_ids );
					}
				}
			}
		}
	}
}
Gateways::init();