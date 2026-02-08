<?php

function emp_install() {
	global $wp_rewrite, $em_do_not_finalize_upgrade;
	$old_version = get_option('em_pro_version');
	if( version_compare(EMP_VERSION, $old_version, '>') || $old_version == ''|| (is_multisite() && !EM_MS_GLOBAL && get_option('emp_ms_global_install')) ){
	 	// Creates the tables + options if necessary
		if( !EM_MS_GLOBAL || (EM_MS_GLOBAL && is_main_site()) ){
		    //hm....
		 	emp_create_transactions_table();
			emp_create_coupons_table(); 
			emp_create_reminders_table();
			emp_create_bookings_relationships_table();
			emp_create_checkin_table();
			emp_create_automation_table();
			if( defined('EM_AUTOMATION_LOGS') ) {
				emp_create_automation_logs_table();
			}
	 		delete_option('emp_ms_global_install'); //in case for some reason the user changed global settings
	 	}else{
	 		update_option('emp_ms_global_install',1); //in case for some reason the user changes global settings in the future
	 	}
		EM_Pro\License::get_license(true);
		emp_add_options();
		if( empty($em_do_not_finalize_upgrade) ) {
			//trigger update action
			do_action('events_manager_pro_updated');
			//Update Version
			update_option('em_pro_version', EMP_VERSION);
			//flush tables
			$wp_rewrite->flush_rules(true);
			// trigger for other dependents
			do_action('em_pro_updated');
			
		}
	}
}

/**
 * Since WP 4.2 tables are created with utf8mb4 collation. This creates problems when storing content in previous utf8 tables such as when using emojis. 
 * This function checks whether the table in WP was changed 
 * @return boolean
 */
function emp_check_utf8mb4_tables(){
		global $wpdb, $emp_check_utf8mb4_tables;
		
		if( $emp_check_utf8mb4_tables || $emp_check_utf8mb4_tables === false ) return $emp_check_utf8mb4_tables;
		
		$column = $wpdb->get_row( "SHOW FULL COLUMNS FROM {$wpdb->posts} WHERE Field='post_content';" );
		if ( ! $column ) {
			return false;
		}
		
		//if this doesn't become true further down, that means we couldn't find a correctly converted utf8mb4 posts table 
		$emp_check_utf8mb4_tables = false;
		
		if ( $column->Collation ) {
			list( $charset ) = explode( '_', $column->Collation );
			$emp_check_utf8mb4_tables = ( 'utf8mb4' === strtolower( $charset ) );
		}
		return $emp_check_utf8mb4_tables;
		
}


/**
 * Magic function that takes a table name and cleans all non-unique keys not present in the $clean_keys array. if no array is supplied, all but the primary key is removed.
 * @param string $table_name
 * @param array $clean_keys
 */
function emp_sort_out_table_nu_keys($table_name, $clean_keys = array()){
	global $wpdb;
	//sort out the keys
	$new_keys = $clean_keys;
	$table_key_changes = array();
	$table_keys = $wpdb->get_results("SHOW KEYS FROM $table_name WHERE Key_name != 'PRIMARY'", ARRAY_A);
	foreach($table_keys as $table_key_row){
		if( !in_array($table_key_row['Key_name'], $clean_keys) ){
			$table_key_changes[] = "ALTER TABLE $table_name DROP INDEX ".$table_key_row['Key_name'];
		}elseif( in_array($table_key_row['Key_name'], $clean_keys) ){
			foreach($clean_keys as $key => $clean_key){
				if($table_key_row['Key_name'] == $clean_key){
					unset($new_keys[$key]);
				}
			}
		}
	}
	//delete duplicates
	foreach($table_key_changes as $sql){
		$wpdb->query($sql);
	}
	//add new keys
	foreach($new_keys as $key){
		$wpdb->query("ALTER TABLE $table_name ADD INDEX ($key)");
	}
}

function emp_create_transactions_table() {
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$table_name = $wpdb->prefix.'em_transactions'; 
	$sql = "CREATE TABLE ".$table_name." (
		  transaction_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  booking_id bigint(20) unsigned NOT NULL DEFAULT '0',
		  transaction_gateway_id varchar(30) DEFAULT NULL,
		  transaction_payment_type varchar(20) DEFAULT NULL,
		  transaction_payment_type_detail tinytext DEFAULT NULL,
		  transaction_timestamp datetime NOT NULL,
		  transaction_total_amount decimal(14,2) DEFAULT NULL,
		  transaction_currency varchar(35) DEFAULT NULL,
		  transaction_status varchar(35) DEFAULT NULL,
		  transaction_duedate date DEFAULT NULL,
		  transaction_gateway varchar(50) DEFAULT NULL,
		  transaction_note text,
		  transaction_expires datetime DEFAULT NULL,
		  PRIMARY KEY  (transaction_id)
		) DEFAULT CHARSET=utf8 ;";
	
	dbDelta($sql);
	emp_sort_out_table_nu_keys($table_name,array('transaction_gateway','booking_id'));
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_coupons_table() {
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); 
	$table_name = $wpdb->prefix.'em_coupons'; 
	$sql = "CREATE TABLE ".$table_name." (
		  coupon_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  coupon_owner bigint(20) unsigned NOT NULL,
		  blog_id bigint(20) unsigned DEFAULT NULL,
		  coupon_code varchar(20) NOT NULL,
		  coupon_name text NOT NULL,
		  coupon_description text NULL,
		  coupon_max int(10) NULL,
		  coupon_start datetime DEFAULT NULL,
		  coupon_end datetime DEFAULT NULL,
		  coupon_type varchar(20) DEFAULT NULL,
		  coupon_tax varchar(4) DEFAULT NULL,
		  coupon_discount decimal(14,2) NOT NULL,
		  coupon_eventwide bool NOT NULL DEFAULT 0,
		  coupon_sitewide bool NOT NULL DEFAULT 0,
		  coupon_private bool NOT NULL DEFAULT 0,
		  PRIMARY KEY  (coupon_id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	$array = array('coupon_owner','coupon_code');
	if( is_multisite() ) $array[] = 'blog_id'; //only add index if needed
	emp_sort_out_table_nu_keys($table_name,$array);
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_reminders_table(){
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); 
    $table_name = $wpdb->prefix.'em_email_queue';
	$sql = "CREATE TABLE ".$table_name." (
		  queue_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  event_id bigint(20) unsigned DEFAULT NULL,
		  booking_id bigint(20) unsigned DEFAULT NULL,
		  batch_id bigint(20) unsigned DEFAULT NULL,
		  email text NOT NULL,
		  subject text NOT NULL,
		  body text NOT NULL,
		  attachment text NULL,
		  args LONGTEXT NULL,
		  attempts int NOT NULL DEFAULT 0,
		  last_error text NULL,
		  PRIMARY KEY  (queue_id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	emp_sort_out_table_nu_keys($table_name,array('event_id','booking_id'));
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_bookings_relationships_table(){
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); 
    $table_name = $wpdb->prefix.'em_bookings_relationships';
	$sql = "CREATE TABLE ".$table_name." (
		  booking_relationship_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  event_id bigint(20) unsigned DEFAULT NULL,
		  booking_id bigint(20) unsigned DEFAULT NULL,
		  booking_main_id bigint(20) unsigned DEFAULT NULL,
		  PRIMARY KEY  (booking_relationship_id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	emp_sort_out_table_nu_keys($table_name,array('event_id','booking_id','booking_main_id'));
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_checkin_table(){
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$table_name = $wpdb->prefix.'em_tickets_bookings_checkins';
	$sql = "CREATE TABLE ".$table_name." (
		  checkin_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		  ticket_booking_id bigint(20) unsigned NOT NULL,
		  checkin_status int(1) unsigned NOT NULL,
		  checkin_ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (checkin_id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_automation_table(){
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$table_name = $wpdb->prefix.'em_automation';
	$sql = "CREATE TABLE ".$table_name." (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NULL,
			name varchar(255) NOT NULL,
			type varchar(255) NOT NULL,
			status int(1) unsigned NOT NULL DEFAULT 0,
			trigger_data longtext NULL,
			action_data longtext NOT NULL,
			ts TIMESTAMP NULL,
			doing_cron int(1) unsigned NULL,
			PRIMARY KEY  (id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	emp_sort_out_table_nu_keys($table_name,array('object_id','type','ts'));
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_create_automation_logs_table(){
	global  $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$table_name = $wpdb->prefix.'em_automation_logs';
	$sql = "CREATE TABLE ".$table_name." (
			log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) unsigned NOT NULL,
			ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			affected bigint(20) unsigned NULL,
			data longtext NULL,
			PRIMARY KEY  (log_id)
		) DEFAULT CHARSET=utf8 ;";
	dbDelta($sql);
	emp_sort_out_table_nu_keys($table_name,array('automation_id'));
	if( emp_check_utf8mb4_tables() ) maybe_convert_table_to_utf8mb4( $table_name );
}

function emp_add_options() {
	global $wpdb;
	add_option('dbem_disable_css',false); //TODO - remove this or create dependency in admin settings
	// Manual Bookings
	add_option('dbem_bookings_manual', 1);
	// Bookings Manager - Frontend, QR etc.
	add_option('dbem_bookings_manager', true);
	add_option('dbem_bookings_manager_endpoint', 'bookings-manager');
	add_option('dbem_bookings_qr', true);
	// Automation
	add_option('dbem_automation_enabled', false);
	// Waitlist
	add_option('dbem_waitlists', false);
	add_option('dbem_waitlists_guests', true);
	add_option('dbem_waitlists_booking_limit', 1);
	add_option('dbem_waitlists_limit', 25);
	add_option('dbem_waitlists_expiry', 48);
	add_option('dbem_waitlists_events', true);
	add_option('dbem_waitlists_events_default', 1);
	add_option('dbem_waitlists_events_tickets', true);
	add_option('dbem_waitlists_submit_button', esc_html__('Join Waitlist', 'em-pro'));
	add_option('dbem_waitlists_login_text', esc_html__('Please log in to access the waitlist.', 'em-pro'));
	add_option('dbem_waitlists_text_already_waiting', EMP_Formats::dbem_waitlists_text_already_waiting());
	add_option('dbem_waitlists_text_booking_form', EMP_Formats::dbem_waitlists_text_booking_form());
	add_option('dbem_waitlists_text_form', EMP_Formats::dbem_waitlists_text_form() );
	add_option('dbem_waitlists_text_cancelled', EMP_Formats::waitlists_text_cancelled() );
	add_option('dbem_waitlists_text_expired', EMP_Formats::dbem_waitlists_text_expired() );
	add_option('dbem_waitlists_text_full', EMP_Formats::dbem_waitlists_text_full() );
	add_option('dbem_waitlists_feedback_confirmed', esc_html__('You have been added to the waitlist. You are #_WAITLIST_BOOKING_POSITION in line. You will be emailed if a ticket becomes available for booking, you will have #_WAITLIST_EXPIRY hours to make a booking before it is released to the next person in the list.', 'em-pro'));
	add_option('dbem_waitlists_feedback_already_waiting', esc_html__('You are already on the waitlist, you are position #_WAITLIST_BOOKING_POSITION out of #_WAITLIST_WAITING. You will be notified by email if a space becomes available.', 'em-pro'));
	add_option('dbem_waitlists_feedback_already_waiting_guest', esc_html__('You are already on the waitlist, You will be notified by email if a space becomes available.', 'em-pro'));
	add_option('dbem_waitlists_feedback_full', esc_html__('There are no more spaces left available on the waitlist. Please check back later, as spaces may become available.', 'em-pro'));
	add_option('dbem_waitlists_feedback_booking_limit', esc_html__('You cannot reserve more than #_WAITLIST_BOOKING_LIMIT spaces on the waitlist.', 'em-pro'));
	add_option('dbem_waitlists_feedback_spaces_limit', esc_html__('There are not enough available spaces on the waitlist, only #_WAITLIST_AVAILABLE space(s) available.', 'em-pro'));
	add_option('dbem_waitlists_feedback_log_in', get_option('dbem_booking_feedback_log_in')); // take it from
	add_option('dbem_waitlists_feedback_cancelled', esc_html__('You have cancelled your waitlist reservation for this event.', 'em-pro') .' '. esc_html__('Thank you for cancelling your waitlist reservation, so that others can have an opportunity to attend the event!', 'em-pro'));
	// Cancellation Options
	add_option('dbem_bookings_user_cancellation_time', 0);
	add_option('dbem_bookings_user_cancellation_event', 0);
	// Submission Limiting
	add_option('dbem_event_submission_limits_enabled', 0);
	// Waitlist Emails
	add_option('dbem_waitlists_emails_confirmed_subject', esc_html__('Waitlist Confirmation', 'em-pro'). ' - #_EVENTNAME');
	add_option('dbem_waitlists_emails_confirmed_message', EMP_Formats::waitlists_emails_confirmed_message());
	add_option('dbem_waitlists_emails_approved_subject', esc_html__('Waitlist Approved, Book Now!', 'em-pro'). ' - #_EVENTNAME');
	add_option('dbem_waitlists_emails_approved_message', EMP_Formats::waitlists_emails_approved_message());
	add_option('dbem_waitlists_emails_expired_subject', esc_html__('Waitlist Booking Expired', 'em-pro'). ' - #_EVENTNAME');
	add_option('dbem_waitlists_emails_expired_message', EMP_Formats::waitlists_emails_expired_message());
	add_option('dbem_waitlists_emails_cancelled_subject', esc_html__('Waitlist Booking Cancelled', 'em-pro'). ' - #_EVENTNAME');
	add_option('dbem_waitlists_emails_cancelled_message', EMP_Formats::waitlists_emails_cancelled_message());
	//Form Stuff
	$booking_form_data = array( 'name'=> __('Default','em-pro'), 'form'=> array (
	  'name' => array ( 'label' => emp__('Name','events-manager'), 'type' => 'name', 'fieldid'=>'user_name', 'required'=>1 ),
	  'user_email' => array ( 'label' => emp__('Email','events-manager'), 'type' => 'user_email', 'fieldid'=>'user_email', 'required'=>1 ),
    	'dbem_address' => array ( 'label' => emp__('Address','events-manager'), 'type' => 'dbem_address', 'fieldid'=>'dbem_address', 'required'=>1 ),
    	'dbem_city' => array ( 'label' => emp__('City/Town','events-manager'), 'type' => 'dbem_city', 'fieldid'=>'dbem_city', 'required'=>1 ),
    	'dbem_state' => array ( 'label' => emp__('State/County','events-manager'), 'type' => 'dbem_state', 'fieldid'=>'dbem_state', 'required'=>1 ),
    	'dbem_zip' => array ( 'label' => __('Zip/Post Code','em-pro'), 'type' => 'dbem_zip', 'fieldid'=>'dbem_zip', 'required'=>1 ),
    	'dbem_country' => array ( 'label' => emp__('Country','events-manager'), 'type' => 'dbem_country', 'fieldid'=>'dbem_country', 'required'=>1 ),
    	'dbem_phone' => array ( 'label' => emp__('Phone','events-manager'), 'type' => 'dbem_phone', 'fieldid'=>'dbem_phone' ),
    	'dbem_fax' => array ( 'label' => __('Fax','em-pro'), 'type' => 'dbem_fax', 'fieldid'=>'dbem_fax' ),
	  	'booking_comment' => array ( 'label' => emp__('Comment','events-manager'), 'type' => 'textarea', 'fieldid'=>'booking_comment' ),
	));
	add_option('dbem_emp_booking_form_error_required', __('Please fill in the field: %s','em-pro'));
    $new_fields = array(
    	'dbem_address' => array ( 'label' => emp__('Address','events-manager'), 'type' => 'text', 'fieldid'=>'dbem_address', 'required'=>1 ),
    	'dbem_address_2' => array ( 'label' => emp__('Address Line 2','events-manager'), 'type' => 'text', 'fieldid'=>'dbem_address_2' ),
    	'dbem_city' => array ( 'label' => emp__('City/Town','events-manager'), 'type' => 'text', 'fieldid'=>'dbem_city', 'required'=>1 ),
    	'dbem_state' => array ( 'label' => emp__('State/County','events-manager'), 'type' => 'text', 'fieldid'=>'dbem_state', 'required'=>1 ),
    	'dbem_zip' => array ( 'label' => __('Zip/Post Code','em-pro'), 'type' => 'text', 'fieldid'=>'dbem_zip', 'required'=>1 ),
    	'dbem_country' => array ( 'label' => emp__('Country','events-manager'), 'type' => 'country', 'fieldid'=>'dbem_country', 'required'=>1 ),
    	'dbem_phone' => array ( 'label' => emp__('Phone','events-manager'), 'type' => 'text', 'fieldid'=>'dbem_phone' ),
    	'dbem_fax' => array ( 'label' => __('Fax','em-pro'), 'type' => 'text', 'fieldid'=>'dbem_fax' ),
    	'dbem_company' => array ( 'label' => __('Company','em-pro'), 'type' => 'text', 'fieldid'=>'dbem_company' ),
    );
	add_option('em_user_fields', $new_fields);
	$customer_fields = array('address' => 'dbem_address','address_2' => 'dbem_address_2','city' => 'dbem_city','state' => 'dbem_state','zip' => 'dbem_zip','country' => 'dbem_country','phone' => 'dbem_phone','fax' => 'dbem_fax','company' => 'dbem_company');
    add_option('emp_gateway_customer_fields', $customer_fields);
    add_option('em_attendee_fields_enabled', defined('EM_ATTENDEES') && EM_ATTENDEES );
	//Gateway Stuff
    add_option('dbem_emp_booking_form_reg_input', 1);
    add_option('dbem_emp_booking_form_reg_show', 1);
    add_option('dbem_emp_booking_form_reg_show_username', 0);
    add_option('dbem_emp_booking_form_reg_show_email', 0);
    add_option('dbem_emp_booking_form_reg_show_name', !get_option('em_pro_version'));
	add_option('dbem_gateway_use_buttons', 0);
	add_option('dbem_gateway_label', __('Pay With','em-pro'));
	add_option('dbem_gateway_payment_timeout', 0);
	//paypal
	add_option('em_paypal_option_name', 'PayPal');
	add_option('em_paypal_form', '<img src="'.plugins_url('events-manager-pro/includes/images/paypal/paypal_info.png','events-manager').'" width="228" height="61" />');
	add_option('em_paypal_booking_feedback', __('Please wait whilst you are redirected to PayPal to proceed with payment.','em-pro'));
	add_option('em_paypal_booking_feedback_free', emp__('Booking successful.', 'events-manager'));
	add_option('em_paypal_booking_feedback_cancelled', esc_html__emp('Your booking payment has been cancelled, please try again.', 'em-pro'));
	add_option('em_paypal_button', 'http://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif');
	add_option('em_paypal_booking_feedback_completed', __('Thank you for your payment. Your transaction has been completed, and a receipt for your purchase has been emailed to you along with a separate email containing account details to access your booking information on this site. You may log into your account at www.paypal.com to view details of this transaction.', 'em-pro'));
	add_option('em_paypal_inc_tax', get_option('em_pro_version') == false );
	add_option('em_paypal_reserve_pending', absint(get_option('em_paypal_booking_timeout', 1)) > 0 );
	//offline
	add_option('em_offline_option_name', __('Pay Offline', 'em-pro'));
	add_option('em_offline_booking_feedback', emp__('Booking successful.', 'events-manager'));
	add_option('em_offline_button', __('Pay Offline', 'em-pro'));
	//authorize.net
	add_option('em_authorize_aim_option_name', __('Credit Card', 'em-pro'));
	add_option('em_authorize_aim_booking_feedback', emp__('Booking successful.', 'events-manager'));
	add_option('em_authorize_aim_booking_feedback_free', __('Booking successful. You have not been charged for this booking.', 'em-pro'));
	//ical attachments
	$ical_attachments = get_option('em_pro_version') !== false ? 0:1;
	add_option('dbem_bookings_ical_attachments', $ical_attachments);
	add_option('dbem_multiple_bookings_ical_attachments', $ical_attachments);
	// PDF and printables
	add_option('dbem_bookings_pdf', false);
	if( has_custom_logo() ){
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$image = wp_get_attachment_image_src( $custom_logo_id, 'small' );
		add_option('dbem_bookings_pdf_logo', $image[0]);
		add_option('dbem_bookings_pdf_logo_id', $custom_logo_id);
	}else{
		add_option('dbem_bookings_pdf_logo', false);
		add_option('dbem_bookings_pdf_logo_id', false);
	}
	add_option('dbem_bookings_pdf_font', 'dejavusans');
	add_option('dbem_bookings_pdf_font_subset', false);
	add_option('dbem_bookings_pdf_invoice_format', 'EVENT-#_BOOKINGID');
	add_option('dbem_bookings_pdf_invoice_format', 'EVENT-#_BOOKINGID');
	add_option('dbem_bookings_pdf_logo_alt', get_bloginfo('name'));
	add_option('dbem_bookings_pdf_billing_details', "#_BOOKINGFORMCUSTOMREG{user_name}\n#_BOOKINGFORMCUSTOMREG{dbem_address}\n#_BOOKINGFORMCUSTOMREG{dbem_city}\n#_BOOKINGFORMCUSTOMREG{dbem_state}\n#_BOOKINGFORMCUSTOMREG{dbem_zip}\n#_BOOKINGFORMCUSTOMREG{dbem_country}");
	add_option('dbem_bookings_pdf_business_details', '');
	add_option('dbem_bookings_pdf_email_invoice', true);
	add_option('dbem_bookings_pdf_email_tickets', true);
	// Attendance
	add_option('dbem_bookings_attendance', true);
	//email reminders
	add_option('dbem_cron_emails', 0);
	add_option('dbem_cron_emails_limit', get_option('emp_cron_emails_limit', 100));
	add_option('dbem_emp_emails_reminder_subject', __('Reminder','em-pro').' - #_EVENTNAME');
	$email_footer = '<br /><br />-------------------------------<br />Powered by Events Manager - http://wp-events-plugin.com';
	$respondent_email_body_localizable = __("Dear #_BOOKINGNAME, <br />This is a reminder about your #_BOOKINGSPACES space/spaces reserved for #_EVENTNAME.<br />When : #_EVENTDATES @ #_EVENTTIMES<br />Where : #_LOCATIONNAME - #_LOCATIONFULLLINE<br />We look forward to seeing you there!<br />Yours faithfully,<br />#_CONTACTNAME",'em-pro').$email_footer;
	add_option('dbem_emp_emails_reminder_body', str_replace("<br />", "\n\r", $respondent_email_body_localizable));
	add_option('dbem_emp_emails_reminder_time', '12:00 AM');
	add_option('dbem_emp_emails_reminder_days', 1);	
	add_option('dbem_emp_emails_reminder_ical', 1);
	//custom emails
	add_option('dbem_custom_emails', 0);
	add_option('dbem_custom_emails_events', 1);	
	add_option('dbem_custom_emails_events_admins', 1);
	add_option('dbem_custom_emails_gateways', 1);
	add_option('dbem_custom_emails_gateways_admins', 1);
	//email bookings
	
	add_option('dbem_email_bookings', 0);
	update_option('dbem_email_bookings_default_subject', '', 'no');
	update_option('dbem_email_bookings_default_body', '', 'no');
	//multiple bookings
	add_option('dbem_multiple_bookings_feedback_added', __('Your booking was added to your shopping cart.','em-pro'));
	add_option('dbem_multiple_bookings_feedback_already_added', __('You have already booked a spot at this event in your cart, please modify or delete your current booking.','em-pro'));
	add_option('dbem_multiple_bookings_feedback_no_bookings', __('You have not booked any events yet. Your cart is empty.','em-pro'));
	add_option('dbem_multiple_bookings_feedback_loading_cart', __('Loading Cart Contents...','em-pro'));
	add_option('dbem_multiple_bookings_feedback_empty_cart', __('Are you sure you want to empty your cart?','em-pro'));
	add_option('dbem_multiple_bookings_submit_button', __('Place Order','em_pro'));
	//multiple bookings - emails
	$admin_email = get_option('dbem_bookings_notify_admin', false) === false ?  get_site_option('admin_email') : get_option('dbem_bookings_notify_admin');
	add_option('dbem_bookings_notify_admin_mb', $admin_email);
	$contact_person_email_body_template = strtoupper(emp__('Booking Details'))."\n\r".
		emp__('Name','events-manager').' : #_BOOKINGNAME'."\n\r".
		emp__('Email','events-manager').' : #_BOOKINGEMAIL'."\n\r".
		'#_BOOKINGSUMMARY';
		$contact_person_emails['confirmed'] = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Confirmed')))."\n\r".$contact_person_email_body_template;
		$contact_person_emails['pending'] = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Pending')))."\n\r".$contact_person_email_body_template;
		$contact_person_emails['cancelled'] = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Cancelled')))."\n\r".$contact_person_email_body_template;
	
	add_option('dbem_multiple_bookings_contact_email', false);
	add_option('dbem_multiple_bookings_contact_email_user', false);
	add_option('dbem_multiple_bookings_contact_email_confirmed_subject', emp__("Booking Confirmed"));
	$respondent_email_body_localizable = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Confirmed')))."\n\r".$contact_person_email_body_template;
	add_option('dbem_multiple_bookings_contact_email_confirmed_body', $respondent_email_body_localizable);
	
	add_option('dbem_multiple_bookings_contact_email_pending_subject', emp__("Booking Pending"));
	$respondent_email_body_localizable = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Pending')))."\n\r".$contact_person_email_body_template;
	add_option('dbem_multiple_bookings_contact_email_pending_body', $respondent_email_body_localizable);
	
	add_option('dbem_multiple_bookings_contact_email_cancelled_subject', __('Booking Cancelled','em-pro'));
	$respondent_email_body_localizable = sprintf(emp__('The following booking is %s :'),strtolower(emp__('Cancelled')))."\n\r".$contact_person_email_body_template;
	add_option('dbem_multiple_bookings_contact_email_cancelled_body', $respondent_email_body_localizable);
	
	add_option('dbem_multiple_bookings_email_confirmed_subject', __('Booking Confirmed','em-pro'));
	$respondent_email_body_localizable = __("Dear #_BOOKINGNAME, <br />Your booking has been confirmed. <br />Below is a summary of your booking: <br />#_BOOKINGSUMMARY <br />We look forward to seeing you there!",'em-pro').$email_footer;
	add_option('dbem_multiple_bookings_email_confirmed_body', str_replace("<br />", "\n\r", $respondent_email_body_localizable));
	
	add_option('dbem_multiple_bookings_email_pending_subject', __('Booking Pending','em-pro'));
	$respondent_email_body_localizable = __("Dear #_BOOKINGNAME, <br />Your booking is currently pending approval by our administrators. Once approved you will receive another confirmation email. <br />Below is a summary of your booking: <br />#_BOOKINGSUMMARY",'em-pro').$email_footer;
	add_option('dbem_multiple_bookings_email_pending_body', str_replace("<br />", "\n\r", $respondent_email_body_localizable));
	
	add_option('dbem_multiple_bookings_email_rejected_subject', __('Booking Rejected','em-pro'));
	$respondent_email_body_localizable = __("Dear #_BOOKINGNAME, <br />Your requested booking has been rejected. <br />Below is a summary of your booking: <br />#_BOOKINGSUMMARY",'em-pro').$email_footer;
	add_option('dbem_multiple_bookings_email_rejected_body', str_replace("<br />", "\n\r", $respondent_email_body_localizable));
	
	add_option('dbem_multiple_bookings_email_cancelled_subject', __('Booking Cancelled','em-pro'));
	$respondent_email_body_localizable = __("Dear #_BOOKINGNAME, <br />Your requested booking has been cancelled. <br />Below is a summary of your booking: <br />#_BOOKINGSUMMARY",'em-pro').$email_footer;
	add_option('dbem_multiple_bookings_email_cancelled_body', str_replace("<br />", "\n\r", $respondent_email_body_localizable));
	
	// dependent bookings
	add_option('dbem_bookings_dependent_events', 1);
	add_option('dbem_booking_feedback_dependent_guest', esc_html__("You must have previously booked '#_EVENTLINK' in order to attend this event.", 'events-manager'). ' ' . esc_html__('Please log in so we can verify your previous bookings.', 'events-manager-pro'));
	add_option('dbem_booking_feedback_dependent', esc_html__("You must have previously booked '#_EVENTLINK' in order to attend this event.", 'events-manager'));
	
	//Version updates
	$current_version = get_option('em_pro_version');
	if( $current_version ){ //upgrade, so do any specific version updates
		if( $current_version < 2.16 ){ //add new customer information fields
		    $user_fields = get_option('em_user_fields', array () );
		    update_option('em_user_fields', array_merge($new_fields, $user_fields));
		}
		if( $current_version < 2.061 ){ //new booking form data structure
			global $wpdb;
			//backward compatability, check first field to see if indexes start with 'booking_form_...' and change this.
			$form_fields = get_option('em_booking_form_fields', $booking_form_data['form']);
			if( is_array($form_fields) ){
				$booking_form_fields = array();
				foreach( $form_fields as $form_field_id => $form_field_data){
					foreach( $form_field_data as $field_key => $value ){
						$field_key = str_replace('booking_form_', '', $field_key);
						$booking_form_fields[$form_field_id][$field_key] = $value;
					}
				}
				//move booking form to meta table and update wp option with booking form id too
				$booking_form = serialize(array('name'=>__('Default','em-pro'), 'form'=>$booking_form_fields));
				if ($wpdb->insert(EM_META_TABLE, array('meta_key'=>'booking-form','meta_value'=>$booking_form,'object_id'=>0))){
					update_option('em_booking_form_fields',$wpdb->insert_id);
				}
			}
		}
		if( $current_version < 1.6 ){ //make buttons the default option
			update_option('dbem_gateway_use_buttons', 1);
			if( get_option('em_offline_button_text') && !get_option('em_offline_button') ){
				update_option('em_offline_button',get_option('em_offline_button_text')); //merge offline quick pay button option into one
			}
			if( get_option('em_paypal_button_text') && !get_option('em_paypal_button') ){
				update_option('em_paypal_button',get_option('em_paypal_button_text')); //merge offline quick pay button option into one
			}
		}
		if( $current_version < 2.243 ){ //fix badly stored user dates and times
			$EM_User_Form = EM_User_Fields::get_form();
			foreach($EM_User_Form->form_fields as $field_id => $field){
			    if( in_array($field['type'], array('date','time')) ){
			        //search the user meta table and modify all occorunces of this value if the format isn't correct
			        $meta_results = $wpdb->get_results("SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key='".$field_id."'", ARRAY_A);
			        foreach($meta_results as $meta_result){
			            if( is_serialized($meta_result['meta_value']) ){
			                $meta_value = unserialize($meta_result['meta_value']);
				            if( is_array($meta_value) && !empty($meta_value['start']) ){
				                $new_value = $meta_value['start'];
				                if( !empty($meta_value['end']) ){
				                	$new_value .= ','.$meta_value['end'];
				                }
				                //update this user meta with the new value
				                $wpdb->query("UPDATE {$wpdb->usermeta} SET meta_value='$new_value' WHERE umeta_id='{$meta_result['umeta_id']}'");
				            }
			            } 
			        }
			    }
			}
		}
		if( $current_version < 2.36 ){ //disable custom emails for upgrades, prevent unecessary features
			add_option('dbem_custom_emails', 0);	
		}
		if( get_option('dbem_muliple_bookings_form') ){ //fix badly stored user dates and times
			update_option('dbem_multiple_bookings_form', get_option('dbem_muliple_bookings_form'));
			delete_option('dbem_muliple_bookings_form');
		}
		if( $current_version < 2.392 ){ //disable custom emails for upgrades, prevent unecessary features
			update_option('em_paypal_booking_feedback_completed', get_option('em_paypal_booking_feedback_thanks'));
			delete_option('em_paypal_booking_feedback_thanks');
		}
		if( $current_version < 2.4442 ){ //disable custom emails for upgrades, prevent unecessary features
			update_option('dbem_emp_booking_form_error_required', get_option('em_booking_form_error_required'));
			delete_option('em_booking_form_error_required');
		}
		if( $current_version < 2.4443 ){ //transition paypal credentials to serialized array
			$paypal_api_username = get_option('em_paypal_api_username');
			$paypal_api_password = get_option('em_paypal_api_password');
			$paypal_api_signature = get_option('em_paypal_api_signature');
			if( !empty($paypal_api_username) ){
				update_option('em_paypal_api', array(
					'username' => $paypal_api_username,
					'password' => $paypal_api_password,
					'signature' => $paypal_api_signature
				));
				delete_option('em_paypal_api_username');
				delete_option('em_paypal_api_password');
				delete_option('em_paypal_api_signature');
			}
		}
		if( $current_version < 2.5124 ){
			update_option('dbem_multiple_bookings_contact_email_confirmed_subject', get_option('dbem_multiple_bookings_contact_email_subject'));
			update_option('dbem_multiple_bookings_contact_email_confirmed_body', get_option('dbem_multiple_bookings_contact_email_body'));
			delete_option('dbem_multiple_bookings_contact_email_subject');
			delete_option('dbem_multiple_bookings_contact_email_body');
			if( get_option('dbem_multiple_bookings_contact_email_subject_ml') ){
				update_option('dbem_multiple_bookings_contact_email_confirmed_subject_ml', get_option('dbem_multiple_bookings_contact_email_subject_ml'));
				update_option('dbem_multiple_bookings_contact_email_confirmed_body_ml', get_option('dbem_multiple_bookings_contact_email_body_ml'));
				delete_option('dbem_multiple_bookings_contact_email_subject_ml');
				delete_option('dbem_multiple_bookings_contact_email_body_ml');
			}
		}
		if( $current_version < 2.641 ){ //transition authorize.net to serialized array
			$anet_api_username = get_option('em_authorize_aim_api_user');
			$anet_api_key = get_option('em_authorize_aim_api_key');
			if( !empty($anet_api_username) ){
				update_option('em_authorize_aim_api', array(
					'login' => $anet_api_username,
					'key' => $anet_api_key
				));
				delete_option('em_authorize_aim_api_user');
				delete_option('em_authorize_aim_api_key');
			}
		}
		if( $current_version < 2.643 ){ //transition into new license, but don't deactivate their site immediately.
			$license = EM_Pro\License::get_license();
			$EM_DateTime = new EM_DateTime();
			$EM_DateTime->add('P2D');
			if( !is_multisite() ){ //set api key to autoload
				$api_key = get_option('dbem_pro_api_key');
				delete_option('dbem_pro_api_key');
				update_option('dbem_pro_api_key', $api_key, 'yes');
			}
			if( $license->activated ){
				//we're good, just mute the activation message and user will not get confused with change of license interface
				EM_Admin_Notices::remove('em_pro_activated');
			}
		}
		// 3.0 onwards we use version_compare, finally!
		if( version_compare($current_version, '3.0', '<') ){
			global $wpdb;
			// Ticket meta time! For now, attendee data only is really what we're after.
			// the best way to do this will be by doing it by booking meta, only way to do it in bigger batches that won't require as many SQL queries and processing
			$cols = $wpdb->get_row('SELECT * FROM '. EM_BOOKINGS_META_TABLE . ' LIMIT 1', ARRAY_A);
			if( is_array($cols) && !array_key_exists('attendees_migrated', $cols) ) {
				$wpdb->query('ALTER TABLE ' . EM_BOOKINGS_META_TABLE . ' ADD `attendees_migrated` INT(1) NULL');
			}
			// let's go through every booking meta regarding tickets and split it all up
			$query = 'SELECT * FROM '. EM_BOOKINGS_META_TABLE ." WHERE meta_key LIKE '_attendees_%' AND attendees_migrated IS NULL LIMIT 100";
			$results = $wpdb->get_results( $query, ARRAY_A );
			while( !empty($results) ){
				$migrated_ticket_bookings = $migrated_bookings = $ticket_booking_meta_split = array();
				foreach( $results as $ticket_booking_attendees ) {
					$booking_id = $ticket_booking_attendees['booking_id'];
					// get the ticket attendee info raw, split it up
					// now we generate split meta, any meta in an array should be dealt with by corresponding plugin (e.g. Pro for form field meta)
					if( !empty($ticket_booking_attendees['meta_value']) ) {
						$ticket_id = str_replace('_attendees_', '', $ticket_booking_attendees['meta_key']);
						$attendees_data = unserialize($ticket_booking_attendees['meta_value']);
						// double-check we don't have a totally empty array here, it happens
						$continue = false;
						foreach( $attendees_data as $k ){
							if( !empty($k) ){
								$continue = true;
								break;
							}
						}
						if( !$continue ){
							// skip, no data to process for this
							$migrated_bookings[] = absint( $ticket_booking_attendees['meta_id']);
							continue;
						}
						// get the current ticket booking IDs we'll need for this process
						$ticket_booking_ids = $wpdb->get_col('SELECT ticket_booking_id FROM '. EM_TICKETS_BOOKINGS_TABLE .' WHERE booking_id='.$booking_id.' AND ticket_id='.$ticket_id);
						foreach( $attendees_data as $i => $ticket_attendee_data ){
							// we're not checking id validity or anything like that, we're just getting the IDs and putting them into the right place for an SQL query
							if( !empty($ticket_booking_ids[$i]) ) {
								$ticket_booking_id = $ticket_booking_ids[$i];
								// match, now split up every item within the array
								foreach( $ticket_attendee_data as $k => $v ) {
									if( is_array($v) ) {
										// we go down one level for automated array splitting
										$prefix = '_'.$k.'_';
										foreach( $v as $kk => $vv ){
											$kk = $prefix . $kk;
											if( is_array($vv) ) $vv = serialize($vv);
											// handle emojis - copied check from wpdb
											if ( (function_exists( 'mb_check_encoding' ) && !mb_check_encoding( $vv, 'ASCII' )) || preg_match( '/[^\x00-\x7F]/', $vv ) ) {
												$vv = wp_encode_emoji($vv);
											}
											$ticket_booking_meta_split[] = $wpdb->prepare("(%d, %s, %s)", $ticket_booking_id, $kk, $vv);
										}
									}else{
										// handle emojis - copied check from wpdb
										if ( (function_exists( 'mb_check_encoding' ) && !mb_check_encoding( $v, 'ASCII' )) || preg_match( '/[^\x00-\x7F]/', $v ) ) {
											$v = wp_encode_emoji($v);
										}
										$ticket_booking_meta_split[] = $wpdb->prepare("(%d, %s, %s)", $ticket_booking_id, $k, $v);
									}
								}
								$migrated_ticket_bookings[] = absint($ticket_booking_id);
								$migrated_bookings[] = absint( $ticket_booking_attendees['meta_id']);
							}
						}
					}
					// finally update the booking again so we know it was migrated
				}
				if( count($migrated_ticket_bookings) > 0 ){
					// first check that we maybe didn't die halfway through this and there aren't others with the same ticket/bookingid combo by simply deleting all this meta
					$wpdb->query('DELETE FROM '. EM_TICKETS_BOOKINGS_META_TABLE .' WHERE ticket_booking_id IN ('. implode(',', $migrated_ticket_bookings).')');
					// now batch add
					$insert_result = $wpdb->query('INSERT INTO '. EM_TICKETS_BOOKINGS_META_TABLE . ' (ticket_booking_id, meta_key, meta_value) VALUES '. implode(',', $ticket_booking_meta_split) );
					if( $insert_result === false ){
						$message = "<strong>Events Manager is trying to update your database, but the following error occured:</strong>";
						$message .= '</p><p>'.'<code>'. $wpdb->last_error .'</code>';
						$message .= '</p><p>This may likely need some sort of intervention, please get in touch with our support for more advice, we are sorry for the inconveneince.';
						$EM_Admin_Notice = new EM_Admin_Notice(array( 'name' => 'v3.0-ticket-meta-error', 'who' => 'admin', 'where' => 'all', 'message' => $message, 'what'=>'warning' ));
						EM_Admin_Notices::add($EM_Admin_Notice, is_multisite());
						global $em_do_not_finalize_upgrade;
						$em_do_not_finalize_upgrade = true;
						return;
					}
				}else{
					$insert_result = true;
				}
				if( $insert_result ){
					$update_result = $wpdb->query('UPDATE '. EM_BOOKINGS_META_TABLE . ' SET attendees_migrated=1 WHERE meta_id IN ('. implode(',', $migrated_bookings).')');
					if( $update_result === false ){
						$message = "<strong>Events Manager is trying to update your database, but the following error occured whilst migrating to the new ".EM_BOOKINGS_META_TABLE." table:</strong>";
						$message .= '</p><p>'.'<code>'. $wpdb->last_error .'</code>';
						$message .= '</p><p>This may likely need some sort of intervention, please get in touch with our support for more advice, we are sorry for the inconveneince.';
						$EM_Admin_Notice = new EM_Admin_Notice(array( 'name' => 'v3.0-ticket-migrated-flag-error', 'who' => 'admin', 'where' => 'all', 'message' => $message, 'what'=>'warning' ));
						EM_Admin_Notices::add($EM_Admin_Notice, is_multisite());
						global $em_do_not_finalize_upgrade;
						$em_do_not_finalize_upgrade = true;
						return;
					}
				}
				$results = $wpdb->get_results($query, ARRAY_A);
			}
			$wpdb->query('ALTER TABLE '. EM_BOOKINGS_META_TABLE . ' DROP `attendees_migrated`'); // flag done
			EM_Admin_Notices::remove('v3.0-ticket-meta-errorr', is_multisite());
			EM_Admin_Notices::remove('v3.0-ticket-migrated-flag-error', is_multisite());
			// update some options for current users
			update_option('dbem_bookings_manager', false);
			update_option('dbem_bookings_qr', false);
			// notify user of new version!
			$message = esc_html__('Welcome to Events Manager Pro 3! This is a major update which adds lots of new features including PDF Invoices & Tickets, Check-In functionality, QR support and integration with Events Manager 6.1 new storage methods of ticket and booking meta. Most of these features need to be enabled, so head on over to the Bookings tab in our %s!', 'em-pro');
			$settings_page_url = '<a href="'.admin_url('admin.php?page=events-manager-options').'#bookings">'. esc_html__emp('settings page').'</a>';
			$message = sprintf($message, $settings_page_url);
			$EM_Admin_Notice = new EM_Admin_Notice(array( 'name' => 'ProV3-update', 'who' => 'admin', 'where' => 'all', 'message' => "$message" ));
			EM_Admin_Notices::add($EM_Admin_Notice, is_multisite());
		}
		
		if( version_compare($current_version, '3.1.1.1', '<') ){
			wp_clear_scheduled_hook('emp_cron_emails_ical_cleanup');
		}
		
		if( version_compare($current_version, '3.2', '<') ){
			EM_Options::site_set('legacy-gateways', true);
			$message = esc_html__('Welcome to Events Manager Pro 3.2! This is a major update which includes a complete overhaul of our Gateway API foundation! Please visit the %s settings page for more information!', 'em-pro');
			$settings_page_url = '<a href="'.admin_url('admin.php?page=events-manager-gateways').'">'. esc_html__emp('Gateways').'</a>';
			$message = sprintf($message, $settings_page_url);
			$EM_Admin_Notice = new EM_Admin_Notice(array( 'name' => 'ProV3.2-update', 'who' => 'admin', 'where' => 'all', 'message' => "$message" ));
			EM_Admin_Notices::add($EM_Admin_Notice, is_multisite());
		}
		
		if( version_compare($current_version, '3.2.6.2', '<') ){
			// update native legacy gateways for new test mode, in case already in test mode
			// authorize.net
			$current_api = get_option('em_authorize_aim_api');
			if (  ( get_option( 'em_authorize_aim_mode' ) !== 'live' || get_option( 'em_authorize_aim_mode' ) !== 'live' ) && get_option( 'em_authorize_aim_api_test', false ) !== false )  {
				update_option( 'em_authorize_aim_api_test', $current_api );
			}
		}
		
		if( version_compare($current_version, '3.2.10', '<') ){
			// update paypal pending space counting, so it defaults to true and can then be turned off
			update_option( 'em_paypal_reserve_pending', 1 );
		}

		if( version_compare($current_version, '3.6.2', '<') ){
			// fix 'past' event status from 0 to 1
			global $wpdb;
			if ( EM_MS_GLOBAL ) {
				$wpdb->query("UPDATE ". EM_EVENTS_TABLE ." SET event_status=2 WHERE post_id IN ( SELECT ID FROM ". $wpdb->posts ." WHERE post_status='past' AND post_type='". EM_POST_TYPE_EVENT ."' ) AND blog_id=". absint(get_current_blog_id()) );
			} else {
				$wpdb->query("UPDATE ". EM_EVENTS_TABLE ." SET event_status=2 WHERE post_id IN ( SELECT ID FROM ". $wpdb->posts ." WHERE post_status='past' AND post_type='". EM_POST_TYPE_EVENT ."' )");
			}
		}
		
	}else{
		//Booking form stuff only run on install
		$wpdb->insert(EM_META_TABLE, array('meta_value'=>serialize($booking_form_data), 'meta_key'=>'booking-form','object_id'=>0));
		add_option('em_booking_form_fields', $wpdb->insert_id);
	}
}     
?>