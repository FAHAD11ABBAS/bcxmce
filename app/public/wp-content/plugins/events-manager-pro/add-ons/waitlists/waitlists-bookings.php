<?php
namespace EM\Waitlist;
use EM_Booking, EM_Object, EM_Ticket_Booking, EM_Ticket_Bookings, EM_DateTime;

class Bookings {
	
	// caches
	public static $event_pending_spaces = array();
	public static $event_waiting_spaces = array();
	public static $event_waiting_approved_spaces = array();
	
	// flags
	public static $ignore_all = false;
	public static $ignore_waitlisted = false;
	public static $ignore_waitlisted_approved = false;
	
	/**
	 * If currently displaying a booking with waiting list status, it's loaded here for reference whilst being manipulated
	 * This allows for reference between functions and templates during the same pageload.
	 * @var EM_Booking
	 */
	public static $booking;
	/**
	 * Flag showing if booking form via waiting booking is being displayed, used to prevent endless loops.
	 * @var bool
	 */
	public static $displaying_booking_form;
	
	public static function init(){
		// reserve the waitlisted spaces
		add_filter('em_bookings_get_pending_spaces', '\EM\Waitlist\Bookings::em_bookings_get_pending_spaces', 1, 3);
		
		// actions
		add_action('wp_ajax_nopriv_waitlist_booking', '\EM\Waitlist\Bookings::waitlist_booking');
		add_action('wp_ajax_waitlist_booking', '\EM\Waitlist\Bookings::waitlist_booking');
		add_action('wp_ajax_waitlist_booking_add_one', '\EM\Waitlist\Bookings::waitlist_booking'); // only logged in users, but same destination
		add_action('wp_ajax_nopriv_waitlist_cancel', '\EM\Waitlist\Bookings::waitlist_cancel');
		add_action('wp_ajax_waitlist_cancel', '\EM\Waitlist\Bookings::waitlist_cancel');
		
		// intercept booking to prevent double-booking issues
		add_filter('em_before_booking_action_booking_add', '\EM\Waitlist\Bookings::em_before_booking_action_booking_add', 10, 2);
		
		// output and intercept waitlist form
		remove_action('em_booking_form_status_full', 'em_booking_form_status_full');
		add_action('em_booking_form_status_full', '\EM\Waitlist\Bookings::waitlist_form', 10, 1);
		add_filter('em_booking_button', '\EM\Waitlist\Bookings::em_booking_button', 10, 4);
		// add hidden input to booking forms resulting from approved waitlist
		add_filter('em_booking_form_header', '\EM\Waitlist\Bookings::em_booking_form_header', 10, 1);
		
		// my bookings page
		add_filter('em_my_bookings_booking_action_links', '\EM\Waitlist\Bookings::em_my_bookings_booking_action_links', 10, 2);
		// circumvent my bookings page to allow booking a waitlist approved event
		if( !empty($_REQUEST['waitlist_booking']) ) {
			add_filter('em_locate_template', '\EM\Waitlist\Bookings::em_locate_template', 1, 4);
		}
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return bool|string
	 */
	public static function can_user_wait( $EM_Event ){
		$is_logged_in = is_user_logged_in();
		// firstly we determine if this user has any potential tickets that would be avialable now if cancelleations occur
		if( is_user_logged_in() || get_option('dbem_waitlists_guests') ){
			// user can book waitlists
			static::$booking = null;
			// check if user is requesting a specific booking (logged in or not)
			if( !empty($_REQUEST['waitlist_booking']) && !empty($_REQUEST['uuid']) ){
				$EM_Booking = em_get_booking($_REQUEST['uuid']);
				if( $EM_Booking instanceof Booking && $EM_Booking->booking_id && in_array($EM_Booking->booking_status, array(3,6,7,8)) ){
					// first we make sure that the email is also matching, for double-security
					if( empty($_REQUEST['email']) || $_REQUEST['email'] !== $EM_Booking->booking_meta['registration']['user_email']) {
						return false;
					}
					static::$booking = $EM_Booking;
				}
			}elseif( is_user_logged_in() ){
				// check if user has a booking we can load for them automatically
				global $wpdb;
				$sql = 'SELECT * FROM '. EM_BOOKINGS_TABLE . ' WHERE person_id=%d AND event_id=%d AND booking_status IN (6,7)';
				$booking = $wpdb->get_row( $wpdb->prepare($sql, get_current_user_id(), $EM_Event->event_id), ARRAY_A );
				if( is_array($booking) ){
					static::$booking = new Booking($booking);
				}
			}
			if( static::$booking ){
				if( static::$booking->booking_status == 7 ) return 'approved';
				if( static::$booking->booking_status == 6 ) return 'waiting';
				// we don't return 'expired' because the user could potentially submit another waitlist reservation, but we also leave the currently expired one in the Bookings::$bookings object
			}
			// check if waitlist is full, if so then no point checking further
			if( Events::get_available_spaces($EM_Event) === 0 ){
				return 'full';
			}
			// check tickets
			foreach( $EM_Event->get_bookings()->get_tickets() as $EM_Ticket ){
				// we assume a fully booked event means event is at capacity, meaning the only thing we check is spaces
				// in scenarios where a ticket will be available in the future, then the event isn't at capacity therefore waiting lists doesn't make sense, or rather it's a 'notify me when...' situation
				if( empty($EM_Ticket->ticket_meta['waitlist_excluded']) ){ // check if ticket is excluded from waitlist, if so ignore
					// we also check that there are available tickets based on the user login status, if they're a member with access to registered user tickets, they should log in
					if( $EM_Ticket->is_available(false, false, true) ){
						return true;
					}elseif( !$is_logged_in && $EM_Ticket->is_available(true, false, true) ){
						$show_login_form = true;
					}
				}
			}
		}else{
			$show_login_form = true;
		}
		// if we're here, check user isn't logged in because if so they could log in and join the waitlist that way
		if( !empty($show_login_form) ){
			return 'login';
		}
		return false;
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function can_event_waitlist( $EM_Event ){
		// determine of waitlist is to be shown for this event
		if( $EM_Event->event_active_status === 0 ) return false;
		if( get_option('dbem_waitlists_events') ) {
			$show_waitlist = false;
			$waitlist_events = get_option('dbem_waitlists_events_default');
			if ($waitlist_events == 2) {
				// on for all
				$show_waitlist = true;
			} elseif (in_array($waitlist_events, array(0, 1))) {
				// check if event has waitlists enabled
				if (!empty($EM_Event->event_attributes['waitlist'])) {
					// disabled by default
					$show_waitlist = true;
				}
				if ($waitlist_events == 1 && !isset($EM_Event->event_attributes['waitlist'])) {
					// enabled by default
					$show_waitlist = true;
				}
			}
		}else{
			// event overrides disabled, we're here because waitlists are enabled so show waitlist if possible
			$show_waitlist = true;
		}
		return $show_waitlist;
	}
	
	/**
	 * Gets waitlisted booking provided the booking to be retrieved belongs to the user (or can belong to guest)
	 * @param string $uuid
	 * @return EM_Booking|false
	 */
	public static function get_booking( $uuid ){
		if( static::$booking && static::$booking->booking_uuid == $uuid ){
			return static::$booking;
		}
		$EM_Booking = em_get_booking($uuid);
		if( $EM_Booking->booking_id && $EM_Booking instanceof Booking) {
			// ensure this is either a guest booking access or if a registered user access it belongs to that specific user, registered users can claim a guest booking
			if( $EM_Booking->person_id == 0 || ( is_user_logged_in() && $EM_Booking->person_id == get_current_user_id() )){
				// continue with allowing overrides so user can view the booking info
				$EM_Booking->manage_override = true;
				$needed_spaces = !empty($EM_Booking->booking_meta['waitlist']) ? absint($EM_Booking->booking_meta['waitlist']) : 1;
				// limit spaces bookable by limiting the event
				$EM_Event = $EM_Booking->get_event();
				$EM_Event->event_rsvp_spaces = $needed_spaces;
				static::$booking = $EM_Booking;
				return $EM_Booking;
			}
		}
		return false;
	}
	
	/* ==================
	 *  Actions
	/* ================== */
	
	/**
	 * AJAX handler for adding someone to the waitlist
	 */
	public static function waitlist_booking(){
		global $EM_Notices /* @var \EM_Notices $EM_Notices */;
		$result = false;
		$feedback = 'Unrecognized action...';
		$errors = array();
		$EM_Booking = new Booking();
		if( !empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'waitlist_booking') && !empty($_REQUEST['event_id']) ){
			$EM_Event = em_get_event($_REQUEST['event_id']);
			// double-check again usre can be on a waitlist
			$can_user_wait = static::can_user_wait($EM_Event);
			if( $can_user_wait ){
				if( $can_user_wait === true ){
					// create a booking with a blank ticket representing a waitlist
					$EM_Booking->event_id = $EM_Event->event_id;
					$EM_Booking->event = $EM_Event;
					// we purposefully skip validation to prevent other stuff interfering, the only validation required is when getting the person post above, the rest is hard-coded data already
					if( $EM_Booking->get_post() && $EM_Booking->validate() && $EM_Booking->save() ){
						// user is waitlisted, confirm
						$result = true;
						if( $_REQUEST['action'] == 'waitlist_booking_add_one' ){
							$feedback = '';
						}else{
							$feedback = $EM_Booking->output( get_option('dbem_waitlists_feedback_confirmed') );
						}
					}else{
						$feedback = $EM_Booking->feedback_message;
						$errors = $EM_Booking->get_errors();
					}
					//remove_filter('em_bookings_ticket_exists', '__return_true', 10); - left over? commented out for now,
					//remove_filter('em_ticket_is_available', '__return_true', 10);
				}elseif( $can_user_wait === 'login' ){
					// user needs to log in
					$feedback = $EM_Event->output(get_option('dbem_waitlists_feedback_log_in'));
				}elseif( $can_user_wait === 'waiting' ){
					// user needs to log in
					$feedback = static::$booking->output(get_option('dbem_waitlists_feedback_already_waiting'));
				}
			}
		}
		static::handle_ajax_return($result, $feedback, $errors, $EM_Booking);
	}
	
	public static function waitlist_cancel(){
		global $EM_Notices /* @var \EM_Notices $EM_Notices */;
		$result = false;
		$feedback = 'Unrecognized action...';
		$errors = array();
		if( !empty($_REQUEST['uuid']) ){
			$EM_Booking = static::get_booking($_REQUEST['uuid']);
			if( !empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'waitlist_cancel_'.$EM_Booking->booking_id) ) {
				if( $EM_Booking->cancel() ){
					$result = true;
					$feedback = $EM_Booking->output( get_option('dbem_waitlists_feedback_cancelled') );
				}else{
					$feedback = $EM_Booking->feedback_message;
					$errors = $EM_Booking->get_errors();
				}
			}
		}else{
			$EM_Booking = new Booking();
		}
		static::handle_ajax_return($result, $feedback, $errors, $EM_Booking);
	}
	
	public static function handle_ajax_return( $result, $feedback, $errors, $EM_Booking ){
		$return = array('result' => $result, 'success' => $result, 'message' => $feedback);
		if( !$result ){
			global $EM_Notices;
			if( empty($errors) ){ $errors[] = $feedback; }
			$EM_Notices->add_error($errors);
			$return['errors'] = $EM_Notices->get_errors();
		}
		if( defined('DOING_AJAX') || !empty($_REQUEST['em_ajax']) ) {
			header('Content-Type: application/javascript; charset=UTF-8', true); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
			echo EM_Object::json_encode(apply_filters('em_action_' . $_REQUEST['action'], $return, $EM_Booking));
			die();
		}
	}
	
	/* =============================
	 *  Restriction circumvention
	/* ============================= */
	
	/**
	 * Disables any flags and filters that impose restrictions preventing a user making a waitlist-approved booking currently loaded in Waitlist\Bookings
	 * Adds any waitlist-relevant filters or flags that are needed to properly validate a wailt-listed booking.
	 * @return void
	 */
	public static function disable_booking_restrictions(){
		static::$displaying_booking_form = true;
		static::$ignore_all = true;
		\EM_Bookings::$disable_restrictions = true;
		add_filter('em_ticket_is_available', '\EM\Waitlist\Tickets::em_ticket_is_available', 10, 2); // make sure tickets aren't checking quantities
		add_filter('pre_option_dbem_bookings_double', '__return_true', 789); // unique number in case other filters at play
		// sort out flags if we're dealing with a currently loaded booking
		if( static::$booking ) {
			$EM_Event = static::$booking->get_event();
			$EM_Event->get_bookings()->get_spaces(true);
			$EM_Event->event_attributes['temp_event_rsvp_spaces'] = $EM_Event->event_rsvp_spaces;
			$EM_Event->event_rsvp_spaces = static::$booking->get_spaces();
		}
		do_action('em_waitlist_disable_booking_restrictions');
	}
	
	/**
	 * Re-enable any flags and filters that impose restrictions preventing a user making a waitlist-approved booking
	 * @return void
	 */
	public static function reenable_booking_restrictions(){
		static::$displaying_booking_form = false;
		static::$ignore_all = false;
		\EM_Bookings::$disable_restrictions = false;
		remove_filter('em_ticket_is_available', '\EM\Waitlist\Tickets::em_ticket_is_available', 10, 2); // make sure tickets aren't checking quantities
		remove_filter('pre_option_dbem_bookings_double', '__return_true', 789); // unique number in case other filters at play
		// if booking is loaded
		if( static::$booking ) {
			$EM_Event = static::$booking->get_event();
			$EM_Event->event_rsvp_spaces = $EM_Event->event_attributes['temp_event_rsvp_spaces'];
			unset($EM_Event->event_attributes['temp_event_rsvp_spaces']);
		}
		do_action('em_waitlist_disable_booking_restrictions');
	}
	
	public static function em_before_booking_action_booking_add( $EM_Event, $EM_Booking ){
		if( !empty( $_REQUEST['waitlist_booking_uuid'] ) && static::get_booking($_REQUEST['waitlist_booking_uuid']) !== false ) {
			static::disable_booking_restrictions();
			add_action('em_bookings_added', '\EM\Waitlist\Bookings::reenable_booking_restrictions');
		}
	}
	
	
	/* ====================================
	 *  Booking form overrides and display
	/* ==================================== */
	
	
	/**
	 * Outputs a waitlist form, in place the of an 'event full' message if applicable for this event.
	 * May display other templates if the event waitlist is full, login required or the user is already on the waitlist.
	 * @param \EM_Event $EM_Event
	 * @return void
	 */
	public static function waitlist_form( $EM_Event ){
		if( !static::$displaying_booking_form && !(!empty($_REQUEST['action']) && $_REQUEST['action'] == 'manual_booking' && is_user_logged_in() && current_user_can('manage_bookings')) ){
			if( !empty($_REQUEST['waitlist_booking']) && !empty($_REQUEST['uuid']) && static::get_booking($_REQUEST['uuid']) !== false ){
				static::display_booking_form();
				return null;
			}else{
				// does event have waitlists enabled?
				if( static::can_event_waitlist( $EM_Event ) ){
					// can user waitlist this event due to tickets, login status, role etc?
					$can_user_wait = static::can_user_wait($EM_Event);
					if( $can_user_wait ){
						if( ($can_user_wait === true || $can_user_wait == 'login') && !empty(static::$booking) ){
							// this happens if user is visiting a link with a uuid, so we show info about the booking
							if( static::$booking->booking_status == 3 ){
								include( emp_locate_template('waitlists/cancelled.php') );
							}elseif( static::$booking->booking_status == 8 ){
								include( emp_locate_template('waitlists/expired.php') );
							}
						}
						if( $can_user_wait === true ){
							include( emp_locate_template('waitlists/form.php') );
						}elseif( $can_user_wait == 'login' ){
							include( emp_locate_template('waitlists/login.php') );
						}elseif( $can_user_wait == 'waiting' ){
							include( emp_locate_template('waitlists/already-waiting.php') );
						}elseif( $can_user_wait == 'full' ){
							include( emp_locate_template('waitlists/full.php') );
						}elseif( $can_user_wait == 'approved' ){
							static::display_booking_form();
						}
						echo "<script>";
						include( emp_locate_template('waitlists/waitlists.js') );
						echo "</script>";
						return null;
					}
				}
			}
		}
		// continue with default action if we get here
		if( doing_action('em_booking_form_status_already_booked') ){
			em_booking_form_status_already_booked();
		} else {
			em_booking_form_status_full();
		}
	}
	
	public static function display_booking_form(){
		static::disable_booking_restrictions();
		// show booking form
		include( emp_locate_template('waitlists/booking.php') );
		// re-enable flags
		static::$booking = null;
		static::reenable_booking_restrictions();
	}
	
	/**
	 * @param string $button
	 * @param \EM_Event $EM_Event
	 * @param string $status
	 * @return string
	 */
	public static function em_booking_button( $button, $EM_Event, $status = null, $EM_Booking = null ){
		if( $status == 'full' || ( $status === null && $EM_Event->get_bookings()->get_available_spaces() <= 0 ) ){
			// would only get here if we actually have a fully booked booking without the user having a booking already, so we are just showing the join button if applicable
			if( static::can_event_waitlist( $EM_Event ) ) {
				$can_user_wait = static::can_user_wait($EM_Event);
				if( $can_user_wait === true ){
					ob_start();
					?>
					<a class="button em-booking-button-action" href="#"
					    data-nonce="<?php echo wp_create_nonce('waitlist_booking'); ?>"
					    data-event-id="<?php echo $EM_Event->event_id; ?>"
					    data-action="waitlist_booking_add_one"
						data-success="<?php esc_html_e('Waitlist Joined!', 'em-pro'); ?>"
					    data-loading="<?php esc_html_e('Joining Waitlist...', 'em-pro'); ?>" >
						<?php echo esc_html(get_option('dbem_waitlists_submit_button')); ?>
					</a>
					<?php
					return ob_get_clean();
				}
			}
		}elseif( $EM_Booking instanceof Booking ){
			if( in_array($EM_Booking->booking_status, array(6,7)) ){
				ob_start();
				static::$booking = $EM_Booking;
				emp_locate_template('waitlists/button-cancel.php');
				return ob_get_clean();
			}
		}
		return $button;
	}
	
	/**
	 * Add a hidden input into booking forms that are a result of a waitlisted booking, so we can let it through even in a fully booked event.
	 * @param $EM_Event
	 * @return void
	 */
	public static function em_booking_form_header( $EM_Event ){
		if( static::$booking && $EM_Event->event_id == static::$booking->event_id ){
			echo '<input type="hidden" name="waitlist_booking_uuid" value="'. static::$booking->booking_uuid .'">';
		}
	}
	
	public static function em_my_bookings_booking_action_links( $links, $EM_Booking){
		if( $EM_Booking->booking_status == 7 ){
			$EM_Booking = new Booking($EM_Booking);
			$url = $EM_Booking->get_booking_url();
			$links[] = '<a href="'. esc_url($url) .'">'. esc_html_x('Book Now', 'Reserve a space', 'em-pro') . '</a> ';
		}
		if( in_array($EM_Booking->booking_status, array(6,7)) ){
			$links[] = '<a id="em-cancel-button_' . $EM_Booking->booking_id . '"  data-uuid="' . $EM_Booking->booking_uuid .'" data-_wpnonce="' . wp_create_nonce('waitlist_cancel_'.$EM_Booking->booking_id) .'" data-action="waitlist_cancel" class="button em-cancel-button" href="#">' . esc_html__('Cancel', 'em-pro') . '</a>';
		}
		return $links;
	}
	
	public static function em_locate_template( $located, $template_name ){
		if( $template_name == 'templates/my-bookings.php' ||$template_name == 'buddypress/my-bookings.php' ){
			// locate the booking itself here, and the subsequent template can reference it
			if( !empty($_REQUEST['waitlist_booking']) && !empty($_REQUEST['uuid']) && strlen($_REQUEST['uuid']) == 32 ){
				$EM_Booking = static::get_booking( $_REQUEST['uuid'] );
			}
			if( !empty($EM_Booking) ){
				// first we make sure that the email is also matching, for double-security
				if( $EM_Booking->person_id == 0 && (!is_user_logged_in() || !$EM_Booking->can_manage()) ) {
					if (empty($_REQUEST['email']) || $_REQUEST['email'] !== $EM_Booking->booking_meta['registration']['user_email']) {
						return false;
					}
				}
				if( in_array( $EM_Booking->booking_status, array(3,6,7,8) ) ){
					$located = dirname(__FILE__) . '/my-bookings.php';
				}
			}
		}
		return $located;
	}
	
	/**
	 * Modifies pending spaces calculations to include waitlisted bookings
	 * @param integer $count
	 * @param \EM_Bookings $EM_Bookings
	 * @return integer
	 */
	public static function em_bookings_get_pending_spaces($count, $EM_Bookings, $force_refresh = false){
		global $wpdb;
		if( static::$ignore_all || (static::$ignore_waitlisted && static::$ignore_waitlisted_approved) ) return $count;
		$statuses = array();
		if( !static::$ignore_waitlisted ) $statuses['waitlisted'] = 6;
		if( !static::$ignore_waitlisted_approved ) $statuses['waitlisted_approved'] = 7;
		if( !array_key_exists($EM_Bookings->event_id, static::$event_pending_spaces) || $force_refresh || static::$ignore_waitlisted || static::$ignore_waitlisted_approved ){
			$sql = 'SELECT SUM(booking_spaces) FROM '.EM_BOOKINGS_TABLE. ' WHERE booking_status IN ('. implode(',', $statuses) .') AND event_id=%d';
			$pending_spaces = $wpdb->get_var( $wpdb->prepare($sql, $EM_Bookings->event_id) );
			if( !static::$ignore_waitlisted && !static::$ignore_waitlisted_approved ) {
				static::$event_pending_spaces[$EM_Bookings->event_id] = $pending_spaces > 0 ? $pending_spaces : 0;
			}
		}else{
			$pending_spaces = static::$event_pending_spaces[$EM_Bookings->event_id];
		}
		return $count + $pending_spaces;
	}
}
Bookings::init();
