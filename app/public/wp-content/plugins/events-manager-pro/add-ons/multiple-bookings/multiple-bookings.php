<?php
class EM_Multiple_Bookings{
    
    /**
     * Multiple Booking object instance for current user session
     * @var EM_Multiple_Booking
     */
    public static $booking_data;
    public static $session_started = false;
    protected static $main_booking_ids = array();
    
    public static function init(){
		include('multiple-booking.php');
		include('multiple-bookings-widget.php');
	    include('multiple-bookings-table.php');
		add_action('init', [ static::class, 'wp_init' ]);
		add_filter('em_booking_save',[ static::class, 'em_booking_save' ],100,2); // when saving bookings, we need to make sure MB objects update the total price
		add_filter('em_get_booking',[ static::class, 'em_get_booking' ]); // switch EM_Booking with EM_Multiple_Booking object if applicable
	    add_filter('em_booking_get_user_meta', [ static::class, 'em_booking_get_user_meta' ], 10, 3); // get MB level user data if a no-user booking
	    add_filter('em_booking_output_placeholder',[ static::class, 'placeholders' ],1,5); // for emails of individual bookings needing info from the master booking (this class)
	    add_filter('em_booking_button',[ static::class, 'em_booking_button' ],100,2);
		add_filter('em_wp_localize_script', [ static::class, 'em_wp_localize_script' ]);
		add_filter('em_scripts_and_styles_public_enqueue_pages', [ static::class, 'pages_enqueue' ]);
		//cart/checkout pages
	    add_filter('template_redirect', [ static::class, 'template_redirect' ]);
		add_filter('the_content', [ static::class, 'pages' ]);
		//ajax calls for cart actions
		add_action('wp_ajax_emp_checkout_remove_item',[ static::class, 'remove_booking' ]);
		add_action('wp_ajax_nopriv_emp_checkout_remove_item',[ static::class, 'remove_booking' ]);
		add_action('wp_ajax_emp_empty_cart',[ static::class, 'empty_cart_ajax' ]);
		add_action('wp_ajax_nopriv_emp_empty_cart',[ static::class, 'empty_cart_ajax' ]);
		//ajax calls for cart checkout
		add_action('wp_ajax_emp_checkout',[ static::class, 'checkout' ]);
		add_action('wp_ajax_nopriv_emp_checkout',[ static::class, 'checkout' ]);
		//ajax calls for cart contents
		add_action('wp_ajax_em_cart_page_contents',[ static::class, 'cart_page_contents_ajax' ]);
		add_action('wp_ajax_nopriv_em_cart_page_contents',[ static::class, 'cart_page_contents_ajax' ]);
		add_action('wp_ajax_em_checkout_page_contents',[ static::class, 'checkout_page_contents_ajax' ]);
		add_action('wp_ajax_nopriv_em_checkout_page_contents',[ static::class, 'checkout_page_contents_ajax' ]);
		add_action('wp_ajax_em_cart_contents',[ static::class, 'cart_contents_ajax' ]);
		add_action('wp_ajax_nopriv_em_cart_contents',[ static::class, 'cart_contents_ajax' ]);
		//cart content widget and shortcode
		add_action('wp_ajax_em_cart_widget_contents',[ static::class, 'cart_widget_contents_ajax' ]);
		add_action('wp_ajax_nopriv_em_cart_widget_contents',[ static::class, 'cart_widget_contents_ajax' ]);
		add_shortcode('em_cart_contents', [ static::class, 'cart_contents' ]);
		add_action('em_enqueue_assets', [ static::class, 'em_enqueue_assets' ]);
		//booking admin pages
		add_action('em_bookings_admin_page', [ static::class, 'bookings_admin_notices' ]); //add MB warnings if booking is part of a bigger booking
		add_action('em_bookings_multiple_booking', [ static::class, 'booking_admin' ],1,1); //handle page for showing a single multiple booking
			//no user booking mode
			add_filter('em_booking_get_person_editor', [ static::class, 'em_booking_get_person_editor' ], 100, 2); 
			if( !empty($_REQUEST['emp_no_user_mb_global_change']) && !empty($_REQUEST['action']) && $_REQUEST['action'] == 'booking_modify_person'){ //only hook in if we're editing a no-user booking
				add_filter('em_nouser_booking_details_modified', [ static::class, 'em_nouser_booking_details_modified' ], 100, 2);
			}
		//price adjustment on booking admin page
		add_action('em_bookings_admin_ticket_totals_footer', [ static::class, 'em_bookings_admin_ticket_totals_footer' ], 100,1);
	    // session saving
		add_action('shutdown', [ static::class, 'session_save' ]);
		//multilingual hook
		add_action('em_ml_init', [ static::class, 'em_ml_init' ]);
		// conditional placeholders
	   add_filter('em_booking_output_show_condition', array( static::class, 'em_booking_output_show_condition' ), 10, 3 );
    }
    
    public static function wp_init(){
    	if( (empty($_REQUEST['action']) || $_REQUEST['action'] != 'manual_booking') && !(!empty($_REQUEST['manual_booking']) && wp_verify_nonce($_REQUEST['manual_booking'], 'em_manual_booking_'.$_REQUEST['event_id'])) ){ //not admin area or a manual booking
    		//modify traditional booking forms behaviour
		    add_filter('em_get_template_classes', [ static::class, 'booking_form_classes' ], 10, 2);
    		add_action('em_booking_form_custom',[ static::class, 'prevent_user_fields' ], 1); //prevent user fields from showing
    		add_filter('em_booking_validate', [ static::class, 'prevent_user_validation' ], 1); //prevent user fields validation
    		//hooking into the booking process
    		add_action('em_booking_add',[ static::class, 'em_booking_add' ], 5, 3); //prevent booking being made and add to cart
    	}
    	//if we're manually booking, don't load the cart JS stuff
    	if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'manual_booking' ){
    		define('EM_CART_JS_LOADED',true);
    	}
	    //data privacy
	    add_filter('em_data_privacy_export_bookings_item', [ static::class, 'data_privacy_export' ], 10, 2); //add MB bookings to export
	    if( get_option('dbem_data_privacy_consent_bookings') == 1 || ( get_option('dbem_data_privacy_consent_bookings') == 2 && !is_user_logged_in() ) ){
	    	//remove booking form privacy filters and add them to mb checkout form
		    remove_action('em_booking_form_footer', [ 'EM\Consent\Privacy', 'checkbox' ], 9); // backwards compatible
		    remove_action('em_booking_form_after_user_details', [ 'EM\Consent\Privacy', 'checkbox' ], 9); // EM 6.4 onwards
		    remove_filter('em_booking_get_post', [ 'EM\Consent\Privacy', 'em_booking_get_post' ], 10);
		    remove_filter('em_booking_validate', [ 'EM\Consent\Privacy', 'em_booking_validate' ], 10);
		    remove_filter('em_booking_save', [ 'EM\Consent\Privacy', 'em_booking_save' ], 10);
		    add_action('em_checkout_form_after_user_details', [ 'EM\Consent\Privacy', 'checkbox' ], 100);
		    add_filter('em_multiple_booking_get_post', [ 'EM\Consent\Privacy', 'em_booking_get_post' ], 10, 2);
		    add_filter('em_multiple_booking_validate', [ 'EM\Consent\Privacy', 'em_booking_validate' ], 10, 2);
		    add_filter('em_multiple_booking_save_bookings', [ 'EM\Consent\Privacy', 'em_booking_save' ], 10, 2);
	    }
		// comms consent
	    if( get_option('dbem_data_comms_consent_bookings') == 1 || ( get_option('dbem_data_comms_consent_bookings') == 2 && !is_user_logged_in() ) ){
		    //remove booking form privacy filters and add them to mb checkout form
		    remove_action('em_booking_form_footer', [ 'EM\Consent\Comms', 'checkbox' ], 9); // backwards compatible
		    remove_action('em_booking_form_after_user_details', [ 'EM\Consent\Comms', 'checkbox' ], 9); // EM 6.4 onwards
		    remove_filter('em_booking_get_post', [ 'EM\Consent\Comms', 'em_booking_get_post' ], 10);
		    remove_filter('em_booking_validate', [ 'EM\Consent\Comms', 'em_booking_validate' ], 10);
		    remove_filter('em_booking_save', [ 'EM\Consent\Comms', 'em_booking_save' ], 10);
		    add_action('em_checkout_form_after_user_details', [ 'EM\Consent\Comms', 'checkbox' ], 100);
		    add_filter('em_multiple_booking_get_post', [ 'EM\Consent\Comms', 'em_booking_get_post' ], 10, 2);
		    add_filter('em_multiple_booking_validate', [ 'EM\Consent\Comms', 'em_booking_validate' ], 10, 2);
		    add_filter('em_multiple_booking_save_bookings', [ 'EM\Consent\Comms', 'em_booking_save' ], 10, 2);
	    }
	    //session id - previously we didn't need to start a session on init, but we need to as of PHP 7.1 since sessions must be started before headers are sent
	    //to get around this, we check if a session was previously started so we can recover saved session data, otherwise we can start a session later on when actually needed
	    $ajax_actions = array('emp_checkout_remove_item', 'emp_empty_cart', 'emp_checkout', 'em_cart_page_contents', 'em_checkout_page_contents', 'em_cart_contents', 'em_cart_widget_contents');
	    $is_ajax_request = !empty($_REQUEST['action']) && in_array( $_REQUEST['action'], $ajax_actions );
	    $is_booking_action = !empty($_REQUEST['action']) && in_array( $_REQUEST['action'], array('booking_add', 'booking_add_one'));
	    if( !empty($_COOKIE['PHPSESSID']) ) self::session_start( !$is_ajax_request && !$is_booking_action && session_status() != PHP_SESSION_ACTIVE );
    }
    
    public static function em_ml_init(){ include('multiple-bookings-ml.php'); }
    
    public static function em_get_booking($EM_Booking){
        if( !($EM_Booking instanceof EM_Multiple_Booking) && !empty($EM_Booking->booking_id) && $EM_Booking->event_id == 0 ){
            return new EM_Multiple_Booking($EM_Booking);
        }
        return $EM_Booking;
    }
    
    public static function em_wp_localize_script( $vars ){
	    $vars['mb_empty_cart'] = get_option('dbem_multiple_bookings_feedback_empty_cart');
	    return $vars;
    }
    
    /**
     * Starts a session, and returns whether session successfully started or not.
     * We can start a session after the headers are sent in this case, it's ok if a session was started earlier, since we're only grabbing server-side data
     * @param $close_after_read
     * @return boolean
     */
    public static function session_start( $close_after_read = false ){
	    if( !empty($_SESSION) && $close_after_read ){
			// we can circumvent since $_SESSION has been loaded for reading already
			self::$session_started = true;
	    }
        if( empty(self::$session_started) ){
            self::$session_started = session_status() != PHP_SESSION_ACTIVE ? @session_start() : true;
        }
	    if( $close_after_read ) self::session_close();
        return self::$session_started;
    }
    
    public static function session_close(){
	    if( self::$session_started ){
	    	self::session_save();
		    self::$session_started = false;
	    	session_write_close();
	    }
	    return !self::$session_started;
    }
    
    /**
     * Grabs multiple booking from session, or creates a new multiple booking object
     * @return EM_Multiple_Booking
     */
    public static function get_multiple_booking(){
        if( empty(self::$booking_data) ){
	        self::session_start();
	        //load and unserialize EM_Multiple_Booking from session
	        if( !empty($_SESSION['em_multiple_bookings']) && is_serialized($_SESSION['em_multiple_bookings']) ){
	            $obj = unserialize( $_SESSION['em_multiple_bookings'] );
				if ( $obj instanceof EM_Multiple_Booking ) {
					self::$booking_data = $obj;
				}
	        }
	        //create new EM_Multiple_Booking object if one wasn't created
            if ( !is_object(self::$booking_data) || get_class( self::$booking_data ) != 'EM_Multiple_Booking' ){
    			self::$booking_data = new EM_Multiple_Booking();
    		}
        }
        return self::$booking_data;
    }
    
    public static function session_save(){
        if( !empty(self::$booking_data) ){
			//clean booking data to remove unecessary bloat
			foreach( self::$booking_data->bookings as $EM_Booking ){
				//don't try removing the booking objects, because there's no booking ID yet, but anything with an ID already can go
				$EM_Booking->tickets = null;
				$EM_Booking->event = null;
				foreach($EM_Booking->tickets_bookings->tickets_bookings as $key => $EM_Ticket_Booking){
					$EM_Booking->tickets_bookings->tickets_bookings[$key]->event = null;
					$EM_Booking->tickets_bookings->tickets_bookings[$key]->ticket = null;
				}
			}
			$_SESSION['em_multiple_bookings'] = serialize(self::$booking_data);
        }
    }
	
	public static function booking_form_classes($classes, $component){
		if( $component === 'event-booking-form' ) {
			$classes[] = 'em-multiple-booking-form';
		}
		return $classes;
	}
    
    public static function prevent_user_fields(){
		add_filter('emp_form_show_reg_fields', '__return_false');
    }
    
    public static function prevent_user_validation($result){
        self::prevent_user_fields();
        return $result;
    }
    
    /**
     * Hooks into em_booking_add ajax action early and prevents booking from being saved to the database, instead it adds the booking to the bookings cart.
     * If this is not an AJAX request (due to JS issues) then a redirect is made after processing the booking.
     * @param EM_Event $EM_Event
     * @param EM_Booking $EM_Booking
     * @param boolean $post_validation
     */
    public static function em_booking_add( $EM_Event, $EM_Booking, $post_validation ){
        global $EM_Notices;
        $feedback = '';
        $result = false;
        if( self::session_start() ){
	        if ( $post_validation ) {
	            //booking can be added to cart
	            if( self::get_multiple_booking()->add_booking($EM_Booking) ){
	            	if( !empty($_SESSION['em_multiple_bookings_restore']) ) unset( $_SESSION['em_multiple_bookings_restore'] ); // we remove any previously saved restorable data at this point
	                $result = true;
		            $feedback = get_option('dbem_multiple_bookings_feedback_added');
		            $EM_Notices->add_confirm( $feedback, !defined('DOING_AJAX') ); //if not ajax, make this notice static for redirect
	            }else{
	                $result = false;
	                $feedback = '';
	                $EM_Notices->add_error( $EM_Booking->get_errors(), !defined('DOING_AJAX') ); //if not ajax, make this notice static for redirect
	            }
	        }else{
				$result = false;
				$EM_Notices->add_error( $EM_Booking->get_errors() );
			}
        }else{
			$EM_Notices->add_error(__('Sorry for the inconvenience, but we are having technical issues adding your bookings, please contact an administrator about this issue.','em-pro'), !defined('DOING_AJAX'));
        }
		ob_clean(); //em_booking_add uses ob_start(), so flush it here
		if( defined('DOING_AJAX') ){
			$return = array('result'=>$result, 'message'=>$feedback, 'errors'=> $EM_Notices->get_errors());
			if( $result && get_option('dbem_multiple_bookings_redirect') ){
			    $return['redirect'] = get_permalink(get_option('dbem_multiple_bookings_checkout_page'));
	        }
			if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'booking_add_one' ){
				if( $result ) {
					$return['text'] = esc_html__('Checkout', 'em-pro');
					$return['redirect'] = get_permalink(get_option('dbem_multiple_bookings_cart_page'));
				}elseif( empty($feedback) ){
					$return['message'] = implode("\r\n", $EM_Booking->get_errors());
				}
			}
            echo EM_Object::json_encode(apply_filters('em_action_'.$_REQUEST['action'], $return, $EM_Booking));
		}else{
			wp_redirect(em_wp_get_referer());
		}
	    die();
    }
	
	/**
	 * Replaces booking button with a link to cart if already added to cart.
	 * @param string $button
	 * @param EM_Event $EM_Event
	 * @return string
	 */
    public static function em_booking_button( $button, $EM_Event ){
    	$EM_Multiple_Booking = self::get_multiple_booking();
    	if( !empty($EM_Multiple_Booking->bookings[$EM_Event->event_id]) ){
    		$link = get_permalink(get_option('dbem_multiple_bookings_cart_page'));
    		$button = '<a class="button em-booked-button" href="'.$link.'">'. esc_html__('Checkout', 'em-pro') .'</a>';
	    }
        return $button;
    }
    
    /**
     * @param boolean $result
     * @param EM_Booking $EM_Booking
     */
    public static function em_booking_save($result, $EM_Booking){
        //only do this to a previously saved EM_Booking object, not newly added
    	if( $result && get_class($EM_Booking) == 'EM_Booking' && $EM_Booking->previous_status !== false ){
            $EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
            //if part of multiple booking, recalculate and save mb object too
            if( $EM_Multiple_Booking !== false ){
                $EM_Multiple_Booking->calculate_price();
                $EM_Multiple_Booking->save(false);
            }
        }
        return $result;
    }
	
	/**
	 * @param string $replace
	 * @param EM_Booking $EM_Booking
	 * @param string $full_result
	 * @param string $target
	 * @param array $placeholder_atts
	 * @return string
	 */
	public static function placeholders($replace, $EM_Booking, $full_result, $target, $placeholder_atts = array()){
		if( $replace == $full_result && !empty($placeholder_atts) ){
			if( $placeholder_atts[0] == '#_BOOKINGFORMCUSTOM_MB' || $placeholder_atts[0] == '#_BOOKINGFORMCUSTOMREG_MB' || $placeholder_atts[0] == '#_BOOKINGFORMCUSTOM' || $placeholder_atts[0] == '#_BOOKINGFORMCUSTOMREG' ){
				if( static::is_child_booking($EM_Booking) ){
					// we output this placeholder as if it was a regular #_BOOKINGFORMCUSTOM in context of the multiple booking wrapper
					$full_result = str_replace(array('#_BOOKINGFORMCUSTOM_MB', '#_BOOKINGFORMCUSTOMREG_MB'), array('#_BOOKINGFORMCUSTOM', '#_BOOKINGFORMCUSTOMREG'), $full_result);
					$EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
					$replace = $EM_Multiple_Booking->output($full_result);
				}
			}
		}
		return $replace;
	}
    
    public static function remove_booking(){
        $EM_Multiple_Booking = self::get_multiple_booking();
        if( !empty($_REQUEST['event_id']) && $EM_Multiple_Booking->remove_booking($_REQUEST['event_id']) ){
		    if( count($EM_Multiple_Booking->bookings) == 0 ) self::empty_cart(); 
		    $feedback = '';
		    $result = true;
		}else{
		    $feedback = __('Could not remove booking due to an unexpected error.', 'em-pro');
		    $result = false;
		}
        if( defined('DOING_AJAX') ){
        	$return = array('result'=>$result, 'message'=>$feedback);
        	header('Content-Type: text/javascript; charset=utf-8'); //to prevent MIME type errors in MultiSite environments
        	echo EM_Object::json_encode(apply_filters('em_action_'.$_REQUEST['action'], $return, $EM_Multiple_Booking));
        }else{
        	wp_redirect(em_wp_get_referer());
        }
        die();
    }
    
    public static function empty_cart(){
	    self::session_start();
        unset($_SESSION['em_multiple_bookings']);
        self::$booking_data = null;
        self::session_close();
    }
    
    public static function restore_cart(){
	    static::session_start();
	    if( !empty($_SESSION['em_multiple_bookings_restore']) && empty(static::$booking_data) ){
	    	static::$booking_data = $_SESSION['em_multiple_bookings_restore'];
	    	unset($_SESSION['em_multiple_bookings_restore']);
	    }
	    static::session_close();
    }
    
    public static function empty_cart_ajax(){
	    self::empty_cart();
        header( 'Content-Type: application/javascript; charset=UTF-8', true ); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
	    echo EM_Object::json_encode(array('success'=>true));
	    die();
    }
    
    public static function checkout(){
        global $EM_Notices, $EM_Booking;
		check_ajax_referer('emp_checkout');
		$EM_Booking = $EM_Multiple_Booking = self::get_multiple_booking();
        //remove filters so that our master booking validates user fields
		remove_action('em_booking_form_custom',[ static::class, 'prevent_user_fields' ], 1); //prevent user fields from showing
		remove_filter('em_booking_validate', [ static::class, 'prevent_user_validation' ], 1); //prevent user fields validation
		//now validate the master booking
        $EM_Multiple_Booking->get_post();
        $post_validation = $EM_Multiple_Booking->validate();
		//re-add filters to prevent individual booking problems
		add_action('em_booking_form_custom',[ static::class, 'prevent_user_fields' ], 1); //prevent user fields from showing
		add_filter('em_booking_validate', [ static::class, 'prevent_user_validation' ], 1); //prevent user fields validation
		$bookings_validation = $EM_Multiple_Booking->validate_bookings();
		//fire the equivalent of the em_booking_add action, but multiple variation 
		do_action('em_multiple_booking_add', $EM_Multiple_Booking->get_event(), $EM_Multiple_Booking, $post_validation && $bookings_validation); //get_event returns blank, just for backwards-compatibility
		//proceed with saving bookings if all is well
		$result = false; $feedback = '';
        if( $bookings_validation && $post_validation ){
        	// copy MB object temporarily in case users clicks 'back' button on gateways, will delete next page reload
	        static::session_start();
	        $_SESSION['em_multiple_bookings_restore'] = clone( $EM_Multiple_Booking );
	        static::session_close();
			//save user registration
       	    $registration = em_booking_add_registration($EM_Multiple_Booking);
        	//save master booking, which in turn saves the other bookings too
        	if( $registration && $EM_Multiple_Booking->save_bookings() ){
        	    $result = true;
        		$EM_Notices->add_confirm( $EM_Multiple_Booking->feedback_message );
        		$feedback = $EM_Multiple_Booking->feedback_message;
        		self::empty_cart(); //we're done with this checkout!
        	}else{
        		$EM_Notices->add_error( $EM_Multiple_Booking->get_errors() );
        		$feedback = $EM_Multiple_Booking->feedback_message;
        	}
        	global $em_temp_user_data; $em_temp_user_data = false; //delete registered user temp info (if exists)
        }else{
            $EM_Notices->add_error( $EM_Multiple_Booking->get_errors() );
        }
		if( defined('DOING_AJAX') ){
        	header( 'Content-Type: application/javascript; charset=UTF-8', true ); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
		    if( $result ){
				$return = array('result'=>true, 'message'=>$feedback, 'checkout'=>true);
				echo EM_Object::json_encode(apply_filters('em_action_'.$_REQUEST['action'], $return, $EM_Multiple_Booking));
			}elseif( !$result ){
				$return = array('result'=>false, 'message'=>$feedback, 'errors'=>$EM_Notices->get_errors(), 'checkout'=>true);
				echo EM_Object::json_encode(apply_filters('em_action_'.$_REQUEST['action'], $return, $EM_Multiple_Booking));
			}
			die();
		}
    }
	
	/**
	 * Checks to see the current post and if it's a cart page, validate here whilst we can read-to-modify sessions
	 * @param $template
	 *
	 * @return void
	 */
	public static function template_redirect( $template ) {
		global $post;
		$cart_page_id = get_option ( 'dbem_multiple_bookings_cart_page' );
		$checkout_page_id = get_option( 'dbem_multiple_bookings_checkout_page' );
		if( !empty($post->ID) && in_array($post->ID, array($cart_page_id, $checkout_page_id)) ){
			// output notices
			if( !self::get_multiple_booking()->validate_bookings_spaces() ){
				static::session_start();
				global $EM_Notices;
				$EM_Notices->add_error(self::get_multiple_booking()->get_errors());
				static::session_close();
			}
		}
		return $template;
	}
    
    /**
     * Hooks into the_content and checks if this is a checkout or cart page, and if so overwrites the page content with the relevant content. Uses same concept as em_content.
     * @param string $page_content
     * @return string
     */
    public static function pages($page_content) {
		global $post;
    	if( empty($post) ) return $page_content; //fix for any other plugins calling the_content outside the loop
    	$cart_page_id = get_option ( 'dbem_multiple_bookings_cart_page' );
    	$checkout_page_id = get_option( 'dbem_multiple_bookings_checkout_page' );
    	if( in_array($post->ID, array($cart_page_id, $checkout_page_id)) ){
    		ob_start();
    		if( $post->ID == $checkout_page_id && $checkout_page_id != 0 ){
    			self::checkout_page();
    		}elseif( $post->ID == $cart_page_id && $cart_page_id != 0 ){
    			self::cart_page();
    		}
    		$content = ob_get_clean();
    		//Now, we either replace CONTENTS or just replace the whole page
    		if( preg_match('/CONTENTS/', $page_content) ){
    			$content = str_replace('CONTENTS',$content,$page_content);
    		}
    		return $content;
    	}
    	return $page_content;
    }
    
    public static function pages_enqueue( $pages ){
    	$pages['checkout'] = get_option( 'dbem_multiple_bookings_checkout_page' );
    	$pages['cart'] = get_option ( 'dbem_multiple_bookings_cart_page' );
    	return $pages;
    }
    
    public static function cart_contents_ajax(){
    	emp_locate_template('multiple-bookings/cart-table.php', true);
    	die();
    }
    
    /* Checkout Page Code */
    
    public static function em_enqueue_assets( $assets ) {
	    $url = trailingslashit( plugins_url( '', __FILE__ ) );
	    $js = EM_Scripts_and_Styles::get_minified_extension_js().'.js'.'?v='.EMP_VERSION;
	    $assets['.em-checkout-page-contents, .em-cart-page-contents'] = [
		    'js' => [
			    'em-multiple-bookings' => [ 'url' => $url . 'multiple-bookings'. $js ],
		    ],
	    ];
		$assets['.em-cart-widget'] = [
			'js' => [
				'em-cart-widget' => [ 'url' => $url . 'cart-widget'. $js ],
			],
		];
	    return $assets;
    }
	
	public static function checkout_page_contents_ajax(){
		emp_locate_template('multiple-bookings/page-checkout.php',true);
		die();
	}

	public static function checkout_page(){
		// backwards compatibility for EM Pro 3.2 and earlier
		add_action('em_checkout_form_confirm_footer', array( static::class, 'checkout_page_backcompat')); // for oudated code using deprecated action
		add_action('em_checkout_form_footer', array( static::class, 'checkout_page_polyfill')); // for outdated overriden templates that don't use new actions
		//load contents if not using caching, do not alter this conditional structure as it allows the cart to work with caching plugins
		echo '<div class="em em-checkout-page-contents" style="position:relative;">';
		if( !defined('WP_CACHE') || !WP_CACHE ){
			emp_locate_template('multiple-bookings/page-checkout.php',true);
		}else{
			echo '<p>'.get_option('dbem_multiple_bookings_feedback_loading_cart').'</p>';
		}
		echo '</div>';
		EM_Bookings::enqueue_js();
    }
	
	/**
	 * Provides a polyfill for outdated templates that don't trigger new actions such as em_checkout_form_confirm_footer, which is used for
	 * crucial gateway-related things.
	 *
	 * @param EM_Multiple_Booking $EM_Multiple_Booking
	 *
	 * @return void
	 */
	public static function checkout_page_polyfill( $EM_Multiple_Booking ) {
		// remove the backcompat to prevent loop
		remove_action('em_checkout_form_confirm_footer', array( static::class, 'checkout_page_backcompat'));
		// output booking intent
		echo $EM_Multiple_Booking->output_intent_html();
		// trigger the new actions required for gateways etc to work
		do_action('em_checkout_form_after_user_details', $EM_Multiple_Booking);
		do_action('em_checkout_form_confirm_footer', $EM_Multiple_Booking);
	}
	
	/**
	 * Triggers any actions that are bound to the deprecated em_checkout_form_footer action, ensuring compatibility with outdated code that
	 * hasn't changed its trigger action to em_checkout_form_confirm_footer. Any code using this oudated filter should do so, as there's a polyfill
	 * that will handle any themes that override the new template with older versions containing em_checkout_form_Footer.
	 *
	 * @return void
	 */
	public static function checkout_page_backcompat( $EM_Multiple_Booking ) {
		// remove polyfill to prevent loop
		remove_action('em_checkout_form_footer', array( static::class, 'checkout_page_polyfill'));
		// trigger deprecated action
		if( has_action('em_checkout_form_footer') ){
			do_action('em_checkout_form_footer', $EM_Multiple_Booking);
		}
	}
    
    /* Shopping Cart Page */
	
	public static function cart_page_contents_ajax(){
		emp_locate_template('multiple-bookings/page-cart.php',true);
		die();
	}
        
    public static function cart_page(){
		//load contents if not using caching, do not alter this conditional structure as it allows the cart to work with caching plugins
		echo '<div class="em em-cart-page-contents" style="position:relative;">';
		if( !defined('WP_CACHE') || !WP_CACHE ){
			emp_locate_template('multiple-bookings/page-cart.php',true);
		}else{
			echo '<p>'.get_option('dbem_multiple_bookings_feedback_loading_cart').'</p>';
		}
		echo '</div>';
	}
    
    /* Shopping Cart Widget */
    
    public static function cart_widget_contents_ajax(){
        emp_locate_template('multiple-bookings/widget.php', true, array('instance'=>$_REQUEST));
        die();
    }
    
    public static function cart_contents( $instance ){
		$defaults = array(
			'title' => __('Event Bookings Cart','em-pro'),
			'format' => '#_EVENTLINK - #_EVENTDATES<ul><li>#_BOOKINGSPACES Spaces - #_BOOKINGPRICE</li></ul>',
			'loading_text' =>  __('Loading...','em-pro'),
			'checkout_text' => __('Checkout','em-pro'),
			'cart_text' => __('View Cart','em-pro'),
			'no_bookings_text' => __('No events booked yet','em-pro')
		);
		$instance = array_merge($defaults, (array) $instance);
		ob_start();
		?>
		<div class="em em-cart-widget">
			<form>
				<input type="hidden" name="action" value="em_cart_widget_contents" />
				<input type="hidden" name="format" value="<?php echo $instance['format'] ?>" />
				<input type="hidden" name="cart_text" value="<?php echo $instance['cart_text'] ?>" />
				<input type="hidden" name="checkout_text" value="<?php echo $instance['checkout_text'] ?>" />
				<input type="hidden" name="no_bookings_text" value="<?php echo $instance['no_bookings_text'] ?>" />
				<input type="hidden" name="loading_text" value="<?php echo $instance['loading_text'] ?>" />
			</form>
			<div class="em-cart-widget-contents">
				<?php if( !defined('WP_CACHE') || !WP_CACHE ) emp_locate_template('multiple-bookings/widget.php', true, array('instance'=>$instance)); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
	
	public static function em_bookings_admin_ticket_totals_footer( $EM_Booking ){
		if( get_class($EM_Booking) != 'EM_Multiple_Booking' ){
			//is this part of a multiple booking?
			$EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
			if( $EM_Multiple_Booking !== false ){
				//get adjusted price and if different, print it out
				$booking_mb_price = $EM_Multiple_Booking->get_booking_price($EM_Booking, false);
				$booking_total = $EM_Booking->get_price(false);
				if( $booking_mb_price != $EM_Booking->get_price(false) ){
					?>
					<tr class="em-hr">
						<th>* <?php esc_html_e('Further Adjustments','em-pro'); ?></th>
						<th>&nbsp;</th>
						<th><?php echo $EM_Multiple_Booking->format_price($booking_mb_price - $booking_total); ?></th>
					</tr>
					<tr>
						<th>
							* <?php esc_html_e('Total Adjusted Price','em-pro'); ?><br>
							<em>* <?php esc_html_e('price estimate is calculated by proportionally applying discounts from the main booking','em-pro'); ?></em>
						</th>
						<th>&nbsp;</th>
						<th>
							<?php echo $EM_Multiple_Booking->format_price($booking_mb_price); ?><br>
							<em>[<a href="<?php echo $EM_Multiple_Booking->get_admin_url(); ?>"><?php esc_html_e('View Main Booking','em-pro'); ?></a>]</em>
						</th>
					</tr>
					<?php
				}
			}
		}
	}

    /*
     * ----------------------------------------------------------
    * No-User Bookings Admin Stuff
    * ----------------------------------------------------------
    */
    public static  function em_booking_get_person_editor($summary, $EM_Person){
		global $EM_Booking;
		if( !empty($EM_Booking) && current_user_can('manage_others_bookings') ){
			$EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
			if( !empty($EM_Multiple_Booking) ){
				ob_start();
				?>
				<p>
					<em>
						<?php 
						if($EM_Multiple_Booking->booking_id == $EM_Booking->booking_id ){
							esc_html_e('This booking makes part of multiple bookings made at once.','em-pro');
							esc_html_e('Since this is part of multiple booking, you can also change these values for all individual bookings.', 'em-pro');
						}else{
							esc_html_e('This booking contains multiple individual bookings made at once.', 'em-pro');
						} 
						esc_html_e('You can sync this modification with all other related bookings.','em-pro');
						?>
					</em><br />
					<?php _e('Make these changes to all bookings?','em-pro'); ?> <input type="checkbox" name="emp_no_user_mb_global_change" value="1" checked="checked" />
				</p>
				<?php
				$notice = ob_get_clean();
				$summary = $summary . $notice;
			}
		}
		//if this is an MB booking or part of one, add a note mentioning that all bookings made will get modified
		return $summary;
	}
	
	/**
	 * Saves personal booking information to all bookings if user has permission, once booking wa
	 * @return boolean
	 */
	public static function em_nouser_booking_details_modified( $EM_Booking ) {
		if( $EM_Booking->can_manage('manage_bookings', 'manage_others_bookings') ){
			//if this is an MB booking or part of one, edit all the other records too
			$EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
			if( !empty($EM_Multiple_Booking) ){
				//save personal info to main booking if this isn't the main booking
				if( get_class($EM_Booking) != 'EM_Multiple_Booking' ){
					$EM_Multiple_Booking->booking_meta['registration'] = $EM_Booking->booking_meta['registration'];
					$EM_Multiple_Booking->save(false);
				}
				//save other sub-bookings
				$EM_Bookings = $EM_Multiple_Booking->get_bookings();
				foreach($EM_Bookings as $booking){ /* @var $booking EM_Booking */
					if($EM_Booking->booking_id != $booking->booking_id ){
						//save data
						$booking->booking_meta['registration'] = $EM_Booking->booking_meta['registration'];
						$booking->save();
					}
				}
			}
		}
	}
	
	public static function em_booking_get_user_meta( $meta_value, $meta_key, $EM_Booking ) {
		if( $EM_Booking->person_id === 0 ) {
			// we may need to look at main booking meta in this case
			if( static::is_child_booking( $EM_Booking ) ) {
				$EM_MB_Booking = self::get_main_booking( $EM_Booking );
				$meta_value = $EM_MB_Booking->booking_meta['registration'][$meta_key] ?? null;
			}
		}
		return $meta_value;
	}
	
	/*
     * ----------------------------------------------------------
     * Retrieval and checking of booking objects
     * ----------------------------------------------------------
	 */
	
	/**
	 * Returns the main booking object of a supplied EM_Booking object, itself if it is in fact the parent booking, or returns false if booking doesn't have a parent booking.
	 * @param EM_Booking|EM_Multiple_Booking $EM_Booking
	 * @return false|EM_Multiple_Booking
	 */
	public static function get_main_booking( $EM_Booking ){
		$main_booking_id = static::get_main_booking_id( $EM_Booking );
		if( !empty($main_booking_id) && $main_booking_id !== $EM_Booking->booking_id ){
			return new EM_Multiple_Booking($main_booking_id);
		}elseif( $main_booking_id === $EM_Booking->booking_id ){
			return $EM_Booking;
		}
		return false;
	}
	
	/**
	 * Returns the booking ID of multiple booking object, if one is associated to this booking. If supplied booking object is a EM_Multiple_Booking object, the same id is given back.
	 * @param EM_Booking|EM_Multiple_Booking $EM_Booking
	 * @return false|int
	 */
	public static function get_main_booking_id( $EM_Booking ){
		global $wpdb;
		if( get_class($EM_Booking) == 'EM_Multiple_Booking' ) return $EM_Booking->booking_id; //If already main booking, return the booking_id
		if( get_class($EM_Booking) != 'EM_Booking' ) return false; //if this is not an EM_Booking object, just return false
		if( !empty(static::$main_booking_ids[$EM_Booking->booking_id]) ){ //cache ids to allow quick reference
			return static::$main_booking_ids[$EM_Booking->booking_id];
		}
		$main_booking_id = $wpdb->get_var($wpdb->prepare('SELECT booking_main_id FROM '.EM_BOOKINGS_RELATIONSHIPS_TABLE.' WHERE booking_id=%d', $EM_Booking->booking_id));
		if( $main_booking_id > 0 ){
			static::$main_booking_ids[$EM_Booking->booking_id] = $main_booking_id;
		}else{
			$main_booking_id = false;
		}
		return $main_booking_id;
	}
	
	/**
	 * Returns true if booking is a multiple booking object, or if the booking object isn't part of a multiple booking object.
	 * @param EM_Booking|EM_Multiple_Booking $EM_Booking
	 * @return bool
	 */
	public static function is_main_booking( $EM_Booking ){
		if( get_class($EM_Booking) == 'EM_Multiple_Booking' ) return true;
		// we don't need to know the main booking id, just if it has one
		return static::get_main_booking_id( $EM_Booking ) === false;
	}
	
	/**
	 * Returns trus if booking has a multiple booking object booking associated with it.
	 * @param EM_Booking|EM_Multiple_Booking $EM_Booking
	 * @return bool
	 */
	public static function is_child_booking( $EM_Booking ){
		if( get_class($EM_Booking) == 'EM_Multiple_Booking' ) return false; // mb booking can't be a child
		// if this booking has a main booking id, then it is a child
		return static::get_main_booking_id( $EM_Booking ) > 0;
	}

    /*
     * ----------------------------------------------------------
     * Admin Stuff
     * ----------------------------------------------------------
    */
	
    public static function bookings_admin_notices(){
		global $EM_Booking;
		$EM_Notices = new EM_Notices(false); //not global because we'll get repeated printing of errors here, this is just a notice
		if( current_user_can('manage_others_bookings') ){
	    	if( !empty($EM_Booking) && get_class($EM_Booking) != 'EM_Multiple_Booking' ){
				//is this part of a multiple booking?
				$EM_Multiple_Booking = self::get_main_booking( $EM_Booking );
				if( $EM_Multiple_Booking !== false ){
					$EM_Notices->add_info(sprintf(__('This single booking is part of a larger booking made by this person at once. <a href="%s">View Main Booking</a>.','em-pro'), $EM_Multiple_Booking->get_admin_url()));
					echo $EM_Notices;
				}
			}elseif( !empty($EM_Booking) && get_class($EM_Booking) == 'EM_Multiple_Booking' ){
				$EM_Notices->add_info(__('This booking contains a set of bookings made by this person. To edit particular bookings click on the relevant links below.','em-pro'));
				echo $EM_Notices;
			}
		}
    }
    
    public static function booking_admin(){
		emp_locate_template('multiple-bookings/admin.php',true);
		if( !defined('EM_CART_JS_LOADED') ){
			//load 
			function em_cart_js_footer(){
				?>
				<script type="text/javascript">
					<?php include('multiple-bookings.js'); ?>
				</script>
				<?php
			}
			add_action('wp_footer','em_cart_js_footer', 20);
			add_action('admin_footer','em_cart_js_footer', 20);
			define('EM_CART_JS_LOADED',true);
		}
	}

	/**
     * Modifies exported multiple booking items
	 * @param array $export_item
	 * @param EM_Multiple_Booking $EM_MB_Booking
	 * @return array
	 */
	public static function data_privacy_export($export_item, $EM_MB_Booking ){
        if( get_class($EM_MB_Booking) == 'EM_Multiple_Booking' ){
            $export_item['group_id'] = 'events-manager-multiple-bookings';
	        $export_item['group_label'] = __('Multiple Bookings', 'em-pro');
            //remove some inaccurate data and rebuild those sections
            $export_item['data']['event'] = array( 'name' => emp__('Events') );
            $events = array();
            foreach( $EM_MB_Booking->get_bookings() as $EM_Booking ){ /* @var EM_Booking $EM_Booking */
	            //handle potentially deleted events in a MB booking
	            $events[] = !empty($EM_Booking->get_event()->post_id) ? $EM_Booking->get_event()->output('#_EVENTLINK - #_EVENTDATES @ #_EVENTTIMES') : __('Deleted Event', 'em-pro');
            }
	        $export_item['data']['event']['value'] = implode('<br>', $events);
            unset( $export_item['data']['tickets'] );
        }else{
	        $EM_Booking = EM_Multiple_Bookings::get_main_booking($EM_MB_Booking);
            if( $EM_Booking->booking_id != $EM_MB_Booking->booking_id ){
                //we have a booking with a master booking, so we remove pricing since it's the overall booking cost that matters:
            }
        }
		return $export_item;
	}
	
	public static function em_booking_output_show_condition( $show_condition, $args, $EM_Booking){
		$condition = $args['condition'];
		if( $condition === 'is_multi_booking' ){
			$show_condition = get_class($EM_Booking) === 'EM_Multiple_Booking';
		}elseif( $condition == 'not_multi_booking'){
			$show_condition = get_class($EM_Booking) !== 'EM_Multiple_Booking';
		}elseif( $condition == 'is_in_multi_booking'){
			$show_condition = self::is_child_booking($EM_Booking);
		}elseif( $condition == 'not_in_multi_booking'){
			$show_condition = !self::is_child_booking($EM_Booking);
		}
		return $show_condition;
	}
}
EM_Multiple_Bookings::init();