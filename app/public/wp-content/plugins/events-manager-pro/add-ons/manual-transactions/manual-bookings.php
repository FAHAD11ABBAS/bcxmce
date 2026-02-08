<?php
namespace EM\Manual_Transactions;
use EM\Scripts_and_Styles;
use EM_Bookings, EM_Booking, EM_Person, EM\Payments\Offline\Gateway;
use \EM\Payments\Gateways;
use EM_Gateways;

class Bookings {
	
	/**
	 * @var \EM_Booking
	 */
	public static $current_booking = null;
	
	public static function init(){
		// buttons and links to add manual booking
		add_action('em_admin_event_booking_options_buttons', array(get_called_class(), 'event_booking_options_buttons'),10);
		add_action('em_admin_event_booking_options', array(get_called_class(), 'event_booking_options'),10);
		
		// add a manual booking admin page support
		add_action('em_bookings_manual_booking', array(get_called_class(), 'add_booking_form'),1,1);
		add_action('em_booking_admin', array(get_called_class(), 'em_booking_admin'),1,1);
		
		// interecept status sets in manual bookings
		add_filter('em_booking_status_changed', array( static::class, 'em_booking_status_changed'), 10, 2 );
		
		// check request and add actions to circumvent a regular booking
		add_action('em_before_booking_action_booking_add', array( static::class, 'em_before_booking_action_booking_add'), 10, 1);

		// handle loading of manual booking pages and AJAX loading of manual booking forms for recurring events
		if( !empty($_REQUEST['action']) && !empty($_REQUEST['event_id']) ){
			if ( $_REQUEST['action'] == 'manual_booking' ) {
				add_action( 'admin_enqueue_scripts', array ( 'EM_Scripts_and_Styles', 'enqueue_public_styles' ) );
				add_action( 'wp_enqueue_scripts', array ( 'EM_Scripts_and_Styles', 'enqueue_public_styles' ) );
				add_action( 'admin_head', function () {
					do_action( 'em_manual_bookings_head' );
				} );
				add_filter( 'em_booking_form_assets', [ static::class, 'em_booking_form_assets' ] );
				Scripts_and_Styles::add_js_var('manual_booking', [
					'consents' => [
						'privacy' => [
							'enabled' => esc_js( get_option('dbem_data_privacy_consent_bookings') ),
							'remember' => esc_js( get_option('dbem_data_privacy_consent_remember') ),
							'field' => '.em-booking-form p.input-field-data_privacy_consent',
						],
						'comms' => [
							'enabled' => esc_js( get_option('dbem_data_comms_consent_bookings') ),
							'remember' => esc_js( get_option('dbem_data_comms_consent_remember') ),
							'field' => '.em-booking-form p.input-field-data_comms_consent',
						]
					]
				]);
			}
			// check if we're doing AJAX and getting data for a manual booking
			if ( defined('DOING_AJAX') && DOING_AJAX && $_REQUEST['action'] === 'booking_form' && strstr( wp_get_referer(), 'action=manual_booking') ) {
				add_action( 'em_ajax_output_booking_form', [ static::class, 'em_ajax_output_booking_form' ] );
			}
		}
	}

	public static function em_booking_form_assets( $assets ){
		$js_url = EMP_DIR_URI . 'add-ons/manual-transactions/';
		$js = Scripts_and_Styles::get_minified_extension_js().'.js'.'?v='.EM_VERSION;
		$scripts = [
			'em-manual-bookings' => ['url' => $js_url.'manual-bookings'.$js, 'event' => 'em_booking_form_js_loaded' ]
		];
		$assets['js'] = array_merge( $scripts, $assets['js'] );
		return $assets;
	}
	
	public static function activate_manual_booking_gateways( $gateways ) {
		// legacy is only offline, new allows offline and any supported gateway for manual bookings, i.e. anything with direct card input or redirection to pay directly (e.g. not paypal standard)
		if( !( \EM_Options::site_get('legacy-gateways', false) || em_constant('EMP_GATEWAY_LEGACY') ) ) {
			foreach( $gateways as $gateway => $active ){
				if ( $active && Gateways::is_registered($gateway) && !Gateways::get($gateway)::$supports_manual_bookings ) {
					$gateways[$gateway] = false;
				}
			}
		}
		$gateways = static::activate_offline_gateway( $gateways );
		return $gateways;
	}
	
	public static function activate_offline_gateway( $gateways ){
		// legacy is only offline, new allows offline and any supported gateway for manual bookings, i.e. anything with direct card input or redirection to pay directly (e.g. not paypal standard)
		if( \EM_Options::site_get('legacy-gateways', false) || em_constant('EMP_GATEWAY_LEGACY') ) {
			$gateways = array('offline' => true);
		} else {
			$gateways['offline'] = true;
		}
		return $gateways;
	}
	
	public static function booking_form_confirmation_header( $value ) {
		return esc_html__emp('Payment and Confirmation');
	}
	
	public static function get_manual_booking_url( $EM_Event ) {
		$queryargs = array(
			'action'=>'manual_booking',
	        'event_id'=>$EM_Event->event_id
		);
		return em_add_get_params($EM_Event->get_bookings_url(), $queryargs );
	}
	
	public static function get_booking_confirmation_url() {
		if ( static::$current_booking ) {
			$EM_Booking = static::$current_booking;
			$gateway = $EM_Booking->booking_meta['gateway'];
			$params = array(
				'gateway' => $gateway,
				'manual_payment' => wp_create_nonce( $gateway . '_' . $EM_Booking->booking_id )
			);
			$url = add_query_arg( $params, static::$current_booking->get_admin_url() );
			if( $gateway ) {
				$url = apply_filters( 'em_manual_booking_confirmation_url_' . $gateway, $url, $params, $EM_Booking );
			}
			return apply_filters( 'em_manual_booking_confirmation_url', $url, $params, $EM_Booking );
		}
		return false;
	}
	
	/**
	 * @param EM_Booking $EM_Booking
	 *
	 * @return void
	 */
	public static function em_booking_admin( $EM_Booking ) {
		// check if this is returning from a payment or page reload to summarize booking
		if( !empty($_REQUEST['manual_payment']) && !empty($_REQUEST['gateway']) ) {
			// fire hook to possibly process a payment already
			do_action('em_manual_booking_success', $EM_Booking);
			if( $EM_Booking->booking_meta['gateway'] == $_REQUEST['gateway'] && wp_verify_nonce( $_REQUEST['manual_payment'], $_REQUEST['gateway'] . '_' . $EM_Booking->booking_id ) ) {
				// add a notice to confirm booking was processed successfully
				$EM_Notices = new \EM_Notices( false );
				$add_button = ' <a href="'. static::get_manual_booking_url( $EM_Booking->get_event() ) .'"" class="button button-secondary">'.__('Add Another Booking','em-pro').'</a>';
				// gateway-specific hook
				$msg = apply_filters('em_manual_booking_success_' . $EM_Booking->booking_meta['gateway'], esc_html__emp('Booking Successful') ,$EM_Booking);
				$EM_Notices->add_confirm( '<p>' .  $msg . '</p><p>' . $add_button . '</p>' );
				echo $EM_Notices;
			}
		}
	}
	
	/**
	 * Adds an add manual booking button to admin pages
	 */
	public static function event_booking_options_buttons(){
		global $EM_Event;
		?><a href="<?php echo static::get_manual_booking_url($EM_Event); ?>" class="button button-secondary"><?php _e('Add Booking','em-pro') ?></a><?php
	}
	
	/**
	 * Adds a link to add a new manual booking in admin pages
	 */
	public static function event_booking_options(){
		global $EM_Event;
		?><a href="<?php echo em_add_get_params($EM_Event->get_bookings_url(), array('action'=>'manual_booking','event_id'=>$EM_Event->event_id)); ?>"><?php _e('add booking','em-pro') ?></a><?php
	}
	
	
	/**
	 * Generates a booking form where an event admin can add a booking for another user. $EM_Event is assumed to be global at this point.
	 */
	public static function add_booking_form() {
		/* @var $EM_Event \EM_Event */
		global $EM_Event;
		if( !is_object($EM_Event) ) { return; }
		do_action('em_before_manual_booking_form');
		$header_button_classes = is_admin() ? 'page-title-action':'button add-new-h2';
		?>
		<div class='wrap em-manual-booking'>
			<?php if( is_admin() ): ?>
				<h1 class="wp-heading-inline"><?php echo sprintf(__('Add Booking For &quot;%s&quot;','em-pro'), $EM_Event->name); ?></h1>
				<a href="<?php echo esc_url($EM_Event->get_bookings_url()); ?>" class="<?php echo $header_button_classes; ?>"><?php echo esc_html(sprintf(__('Go back to &quot;%s&quot; bookings','em-pro'), $EM_Event->name)) ?></a>
				<hr class="wp-header-end" />
			<?php else: ?>
				<h2>
					<?php echo sprintf(__('Add Booking For &quot;%s&quot;','em-pro'), $EM_Event->name); ?>
					<a href="<?php echo esc_url($EM_Event->get_bookings_url()); ?>" class="<?php echo $header_button_classes; ?>"><?php echo esc_html(sprintf(__('Go back to &quot;%s&quot; bookings','em-pro'), $EM_Event->name)) ?></a>
				</h2>
			<?php endif; ?>
			<?php
			static::output_booking_form( $EM_Event );
			?>
		</div>
		<?php
		do_action('em_after_manual_booking_form');
		//add js that calculates final price, and also user auto-completer
		//if user is chosen, we use normal registration and change person_id after the fact
		//make sure payment amounts are resporcted
	}

	public static function output_booking_form( $EM_Event ) {
		// enable the offline gateway
		add_filter('option_em_payment_gateways', array( static::class, 'activate_manual_booking_gateways' ) );
		// short-circuit header valuese so free/paid is the same
		add_filter('pre_option_dbem_bookings_header_confirm', array( static::class, 'booking_form_confirmation_header' ) );
		add_filter('pre_option_dbem_bookings_header_confirm_free', array( static::class, 'booking_form_confirmation_header' ) );
		//force all user fields to be loaded
		EM_Bookings::$force_registration = EM_Bookings::$disable_restrictions = true;
		//make all tickets available
		foreach( $EM_Event->get_bookings()->get_tickets() as $EM_Ticket ) $EM_Ticket->is_available = true; //make all tickets available
		//remove unecessary footer payment stuff and add our own
		remove_action('em_booking_form_confirm_footer', array( '\EM\Payments\Gateways', 'event_booking_payment_form'),10,2);
		// legacy removals
		remove_action('em_booking_form_footer', array('EM_Gateways','event_booking_form_footer'),10);
		remove_action('em_booking_form_footer', array('EM_Gateways', 'em_booking_form_footer'),10,2);
		remove_action('em_booking_form_footer_before_buttons', array('EM_Gateways','event_booking_form_footer'),10);
		// add manual booking sections
		add_action('em_booking_form_confirm_footer', array( static::class, 'em_booking_form_confirm_footer'),9,2);
		// backwards compatibility - add manual booking sections
		add_action('em_booking_form_footer', array( static::class, 'em_booking_form_footer'),10,2);
		add_action('em_booking_form_custom', array( static::class, 'em_booking_form_custom'), 1);
		// continue with other settings
		add_action('pre_option_dbem_bookings_double','__return_true'); //so we don't get a you're already booked here message
		//Data privacy consent - not added in admin by default, so we add it here
		if( get_option('dbem_data_privacy_consent_bookings') > 0 ){
			add_filter('pre_option_dbem_data_privacy_consent_remember', '__return_zero');
			add_action('em_booking_form_footer', [ '\EM\Consent\Privacy', 'checkbox' ], 9, 0); // backwards compatible - supply 0 args since arg is $EM_Event and callback will think it's an event submission form
			add_action('em_booking_form_after_user_details', [ '\EM\Consent\Privacy', 'checkbox' ], 9, 0); //supply 0 args since arg is $EM_Event and callback will think it's an event submission form
		}
		if( get_option('dbem_data_comms_consent_bookings') > 0 ){
			add_filter('pre_option_dbem_data_comms_consent_remember', '__return_zero');
			add_action('em_booking_form_footer', [ '\EM\Consent\Comms', 'checkbox' ], 9, 0); // backwards compatible - supply 0 args since arg is $EM_Event and callback will think it's an event submission form
			add_action('em_booking_form_after_user_details', [ '\EM\Consent\Comms', 'checkbox' ], 9, 0); //supply 0 args since arg is $EM_Event and callback will think it's an event submission form
		}
		echo $EM_Event->output_booking_form();
		remove_filter('pre_option_dbem_data_privacy_consent_remember', '__return_zero');
		remove_filter('pre_option_dbem_data_comms_consent_remember', '__return_zero');
	}

	public static function em_ajax_output_booking_form( $EM_Event ) {
		static::output_booking_form( $EM_Event );
		exit();
	}
	
	/**
	 * Modifies the booking status if the event isn't free and also adds a filter to modify user feedback returned.
	 * Triggered by the em_booking_add_yourgateway action.
	 * @param \EM_Event $EM_Event
	 */
	public static function em_before_booking_action_booking_add( $EM_Event ){
		//manual bookings
		if( !empty($_REQUEST['manual_booking']) && wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$EM_Event->event_id) ){
			if( !empty($_REQUEST['manual_booking_override']) ) {
				add_action( 'pre_option_dbem_bookings_double', '__return_true' ); //so we don't get a you're already booked here message
				EM_Bookings::$disable_restrictions = true; // disable other restrictions
			}
			// enable the offline gateway for manual booking
			add_filter('option_em_payment_gateways', array( static::class, 'activate_manual_booking_gateways' ) );
			//add filters to add extra manual booking stuff
			add_filter('em_booking_get_post', array( static::class, 'em_booking_get_post' ), 1, 2 );
			add_filter('em_booking_validate', array( static::class, 'em_booking_validate' ), 9, 2 ); //before EM_Bookings_Form hooks in
			add_filter('em_booking_save', array( static::class, 'em_booking_save' ), 10, 2 );
			//set flag that we're manually booking here, and set gateway to offline
			if( empty($_REQUEST['person_id']) || $_REQUEST['person_id'] < 0 ){
				EM_Bookings::$force_registration = true;
			}
			// if any manual payment made, we're in offline mode
			if( !empty($_REQUEST['payment_full']) || !empty($_REQUEST['payment_amount']) ) {
				$_REQUEST['gateway'] = 'offline';
			}
		}
	}
	
	/**
	 * Hooks into the em_booking_save filter and checks whether a partial or full payment has been submitted
	 * @param boolean $result
	 * @param \EM_Booking $EM_Booking
	 */
	public static function em_booking_save( $result, $EM_Booking ){
		if( $result && wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$EM_Booking->event_id) ){
			$previous_status = $EM_Booking->previous_status; // check whether to reset status to null, otherwise we'll run into email issues should the booking be pre-approved
			$booking_status = 1;
			$meta = array(
				'by' => get_current_user_id(),
				'confirmed' => !empty($_REQUEST['manual_booking_confirm']),
				'override' => !empty($_REQUEST['manual_booking_override']),
			);
			if ( !empty($_REQUEST['payment_full']) ) {
				$price = $EM_Booking->get_price();
				$meta['paid'] = $price;
				// legacy mode workaround
				if( \EM_Options::site_get('legacy-gateways', false) || em_constant('EMP_GATEWAY_LEGACY') ) {
					$Gateway = EM_Gateways::get_gateway('offline');
					$Gateway->record_transaction($EM_Booking, $price, get_option('dbem_bookings_currency'), current_time('mysql'), '', 'Completed', __('Manual booking.', 'em-pro'));
				} else {
					Gateway::record_transaction($EM_Booking, $price, get_option('dbem_bookings_currency'), current_time('mysql'), '', 'Completed', __('Manual booking.', 'em-pro'));
				}
				$EM_Booking->set_status($booking_status, false);
				$EM_Booking->update_meta('gateway', 'offline');
			} elseif (!empty($_REQUEST['payment_amount']) && is_numeric($_REQUEST['payment_amount'])) {
				// legacy mode workaround
				if( \EM_Options::site_get('legacy-gateways', false) || em_constant('EMP_GATEWAY_LEGACY') ) {
					$Gateway = EM_Gateways::get_gateway('offline');
					$Gateway->record_transaction($EM_Booking, $_REQUEST['payment_amount'], get_option('dbem_bookings_currency'), current_time('mysql'), '', 'Completed', __('Manual booking.', 'em-pro'));
				} else {
					Gateway::record_transaction($EM_Booking, $_REQUEST['payment_amount'], get_option('dbem_bookings_currency'), current_time('mysql'), '', 'Completed', __('Manual booking.', 'em-pro'));
				}
				if ($_REQUEST['payment_amount'] >= $EM_Booking->get_price()) {
					$EM_Booking->set_status($booking_status, false);
				}
				$EM_Booking->update_meta('gateway', 'offline');
				$meta['paid'] = number_format(2, floatval($_REQUEST['payment_amount']));
			} else {
				// we can process the booking status if paid offline, otherwise we let the gateway used to handle the status
				if( empty($EM_Booking->booking_meta['gateway']) ) {
					$booking_status = !get_option('dbem_bookings_approval') || !empty($_REQUEST['manual_booking_confirm']) ? 1:0;
					$EM_Booking->set_status($booking_status, false);
				} elseif ( $EM_Booking->booking_meta['gateway'] === 'offline' && !empty($_REQUEST['manual_booking_confirm']) ) {
					$EM_Booking->set_status(1, false);
				}
			}
			$EM_Booking->update_meta('manual_booking', $meta);
			if( $previous_status === false && $booking_status === $EM_Booking->previous_status ) $EM_Booking->previous_status = null; // set status back to null if it was previously, due to this status set during initial phase of booking
			add_filter('em_action_booking_add', array( static::class, 'em_action_booking_add') );
			// circumvent return and cancel urls for gateways if they exist
			if( !empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_meta['gateway'] !== 'offline' ) {
				$gateway = $EM_Booking->booking_meta['gateway'];
				add_filter('pre_option_em_' . $gateway . "_return", array( static::class, 'get_booking_confirmation_url') );
				add_filter('pre_option_em_' . $gateway . "_cancel", array( static::class, 'get_booking_confirmation_url') );
				add_filter('pre_option_em_' . $gateway . "_success", array( static::class, 'get_booking_confirmation_url') );
				static::$current_booking = $EM_Booking;
			}
			do_action('em_manual_booking_added', $EM_Booking);
		}
		return $result;
	}
	
	public static function em_booking_status_changed( $EM_Booking ) {
		if( !empty($EM_Booking->booking_meta['manual_booking']['confirmed']) && !empty($EM_Booking->booking_meta['gateway']) && $EM_Booking->booking_status == 0 ) {
			if( $EM_Booking->booking_meta['gateway'] !== 'offline' ) {
				// check if we're switching from previous pending gateway status to 0 and if so set it to 1
				$Gateway = Gateways::get($EM_Booking->booking_meta['gateway']);
				if( $Gateway && $Gateway::$status === $EM_Booking->previous_status && $Gateway::$status > 0 ) {
					$EM_Booking->set_status(1, false, $EM_Booking->booking_meta['manual_booking']['override']);
				}
			}
		}
	}
	
	public static function em_action_booking_add( $feedback ){
		$add_txt = '<a href="'.em_wp_get_referer().'"">'.__('Add another booking','em-pro').'</a>';
		$feedback["message"] = esc_html__emp('Booking Successful') .'<br><br>'. $add_txt;
		return $feedback;
	}
	
	public static function em_booking_validate($result, $EM_Booking){
		if( wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$EM_Booking->event_id) ){
			//validate post
			if( !empty($_REQUEST['payment_amount']) && !is_numeric($_REQUEST['payment_amount'])){
				$result = false;
				$EM_Booking->add_error( 'Invalid payment amount, please provide a number only.', 'em-pro' );
			}
			if( !empty($_REQUEST['person_id']) ){
				//@todo allow users to update user info during manual booking
				add_filter('option_dbem_emp_booking_form_reg_input', '__return_false');
				//impose double bookings here, because earlier we had to disable it due to the fact that the logged in admin is checked for double booking rather than represented user
				remove_all_actions('pre_option_dbem_bookings_double'); //so we don't get a you're already booked here message
				if( !get_option('dbem_bookings_double') && $EM_Booking->get_event()->get_bookings()->has_booking($_REQUEST['person_id']) ){
					$result = false;
					$EM_Booking->add_error( get_option('dbem_booking_feedback_already_booked') );
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param boolean $result
	 * @param \EM_Booking $EM_Booking
	 */
	public static function em_booking_get_post( $result, $EM_Booking ){
		if( $result && wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$EM_Booking->event_id) ){ // additional check for concurrent booking manipulation - remove in future
			if( !empty($_REQUEST['person_id']) ){
				$person = new EM_Person($_REQUEST['person_id']);
				if( !empty($person->ID) ){
					$EM_Booking->person = $person;
					$EM_Booking->person_id = $person->ID;
				}
			}elseif( get_option('dbem_bookings_registration_disable') ){
				//for no-user bookings mode we circumvent
				$EM_Booking->person = new EM_Person(0);
				$EM_Booking->person_id = 0;
				// back-compat error fix
				if( $EM_Booking->get_person()->ID !== 0 ) {
					$EM_Person = $EM_Booking->get_person();
					$EM_Person->ID = 0;
				}
			}
		}
		return $result;
	}
	
	/**
	 * Called before EM_Forms fields are added, when a manual booking is being made
	 */
	public static function em_booking_form_custom(){
		global $wpdb;
		?>
		<p>
			<?php
			$person_id = (!empty($_REQUEST['person_id'])) ? $_REQUEST['person_id'] : false;
			//get consent info for each user, for use later on
			$user_consents_raw = $wpdb->get_results("SELECT user_id, meta_key, meta_value FROM " . $wpdb->usermeta . " WHERE meta_key='em_data_privacy_consent' OR meta_key='em_data_comms_consent'");
			$user_consents = [ 'privacy' => [], 'comms' => [] ];
			foreach( $user_consents_raw as $user_consent ){
				$index = $user_consent->meta_key === 'em_data_privacy_consent' ? 'privacy' : 'comms';
				$user_consents[$index][$user_consent->user_id] = $user_consent->meta_value;
			}
			//output list of users
			$users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC', 'fields' => array('ID','display_name','user_login') ) );
			if( !empty( $users ) ){
				$placeholder = esc_html__( "Select a user (type to search), or enter a new one below.", 'em-pro' );
				$selectized = apply_filters('em_gateway_offline_select_user_manual_booking', 'em-selectize');
				echo '<select name="person_id" id="person_id" class="'. $selectized .'" placeholder="'.$placeholder.'">';
				echo "\t<option value=''>" . $placeholder . "</option>\n";
				foreach ( (array) $users as $user ) {
					$display = sprintf( _x( '%1$s (%2$s)', 'user dropdown' ), $user->display_name, $user->user_login );
					$_selected = selected( $user->ID, $person_id, false );
					$privacy_consented = !empty($user_consents['privacy'][$user->ID]) ? 1:0;
					$comms_consented = !empty($user_consents['comms'][$user->ID]) ? 1:0;
					echo "\t<option value='$user->ID' data-data='{\"comms-consented\": $comms_consented, \"privacy-consented\": $privacy_consented}' data-privacy-consented='$privacy_consented' data-comms-consented='$comms_consented' $_selected>" . esc_html( $display ) . "</option>\n";
				}
				echo '</select>';
			}
			//wp_dropdown_users ( array ('name' => 'person_id', 'show_option_none' => __ ( "Select a user, or enter a new one below.", 'em-pro' ), 'selected' => $person_id  ) );
			?>
		</p>
		<?php
	}
	
	/**
	 * Outputs the relevant footer fields before payment confirmation button
	 * @param \EM_Event $EM_Event
	 *
	 * @return void
	 */
	public static function em_booking_form_confirm_footer( $EM_Event ) {
		if( $EM_Event->can_manage('manage_bookings','manage_others_bookings') ){
			//Admin is adding a booking here, so let's show a different form here.
			?>
			<div class="em-booking-section em-booking-section-manual">
				<input type="hidden" name="manual_booking" value="<?php echo wp_create_nonce('em_manual_booking_'.$EM_Event->event_id); ?>" />
				<p class="input-group input-text input-manual-amount">
					<label><?php _e('Amount Paid','em-pro'); ?></label>
					<input type="text" name="payment_amount" id="em-payment-amount" value="<?php if(!empty($_REQUEST['payment_amount'])) echo esc_attr($_REQUEST['payment_amount']); ?>">
				</p>
				<p class="input-group input-checkbox input-manual-fully-paid">
					<label>
						<input type="checkbox" name="payment_full" id="em-payment-full" value="1">
						<?php _e('Fully Paid','em-pro'); ?>
					</label>
					<em><?php _e('If you check this as fully paid, and leave the amount paid blank, it will be assumed the full payment has been made.' ,'em-pro'); ?></em>
				</p>
				<?php if( get_option('dbem_bookings_approval') ): ?>
					<p class="input-group input-checkbox input-manual-payment-status">
						<label style="width:100%;">
							<input type="checkbox" name="manual_booking_confirm" value="1" checked>
							<?php _e('Confirm Booking?','em-pro'); ?>
						</label>
						<em><?php _e('If you check this, the booking will be marked as confirmed automatically.' ,'em-pro'); ?></em>
					</p>
				<?php endif; ?>
				<p class="input-group input-checkbox">
					<label style="width:100%;">
						<input type="checkbox" name="manual_booking_override" value="1">
						<?php _e('Override any restrictions to ticket availability and limits (this may lead to overbooking).','em-pro'); ?>
					</label>
				</p>
			</div>
			<?php
			do_action('em_manual_booking_form_confirm_footer', $EM_Event);
		}
	}
	
	/**
	 * Supports legacy em_booking_form_footer hook when templates are overriden but outdated without the em_booking_form_confirm_footer hook.
	 * @param \EM_Event $EM_Event
	 */
	public static function em_booking_form_footer($EM_Event){
		// if firing hook via the back-compat mode then don't proceed, since we'll also likely have the new hook above
		if( !did_action('em_booking_form_confirm_footer') ){
			static::em_booking_form_confirm_footer( $EM_Event );
		}
	}
	
	/**
	 * Verification of whether current page load is for a manual booking or not. If $new_registration is true, it will also check whether a new user registration
	 * is being requested and return true or false depending on both conditions being met.
	 * @param boolean $new_registration
	 * @return boolean
	 */
	public static function is_manual_booking( $new_registration = false ){
		if( !empty($_REQUEST['manual_booking']) && wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$_REQUEST['event_id']) ){
			if( $new_registration ){
				return empty($_REQUEST['person_id']) || $_REQUEST['person_id'] < 0;
			}
			return true;
		}
		return false;
	}
}
Bookings::init();