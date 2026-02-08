<?php
use EM\Utils;

class EM_User_Fields {

	use \EM\List_Table\Forms;
	
	public static $form;
	
	public static function init(){
		//Menu/admin page
		add_action('admin_init', array('EM_User_Fields', 'admin_page_actions'),9); //before bookings
		add_action('emp_forms_admin_page', array('EM_User_Fields', 'admin_page'),10);
		add_action('emp_form_user_fields', array('EM_User_Fields', 'emp_booking_user_fields'),1,1); //hook for booking form editor
		//Booking interception
		add_filter('em_form_validate_field_custom', array('EM_User_Fields', 'validate'), 1, 4); //validate object
		add_filter('em_form_save_custom_user_field', array('EM_User_Fields', 'save'), 1, 3); //save fields
		$custom_fields = get_option('em_user_fields', array());
		foreach($custom_fields as $field_id => $field){
			add_action('em_form_output_field_custom_'.$field_id, array('EM_User_Fields', 'output_field'), 1, 2); //validate object
		}
		//WP Dashboard profile page - disable EM user fields and override with our filter
		if( is_admin() ){
			global $pagenow;
			if( $pagenow == 'profile.php' ) add_action('admin_print_scripts', 'EM_User_Fields::admin_print_scripts');
		}
		remove_filter( 'user_contactmethods' , array('EM_People','user_contactmethods'),10,1); //disable EM user fields and override with our filter
		add_action( 'show_user_profile', array('EM_User_Fields','show_profile_fields'), 1 );
		add_action( 'edit_user_profile', array('EM_User_Fields','show_profile_fields'), 1 );
		add_action( 'personal_options_update', array('EM_User_Fields','save_profile_fields') );
		add_action( 'edit_user_profile_update', array('EM_User_Fields','save_profile_fields') );
		add_action( 'delete_user', [ static::class, 'delete_user' ] );
		//admin area additions
		add_filter('em_person_display_summary', array('EM_User_Fields','em_person_display_summary'),10,2);
		//booking no-user mode functions - editing/saving user data
		add_filter('em_booking_get_person_editor', 'EM_User_Fields::em_booking_get_person_editor', 10, 2);
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'booking_modify_person' ){ //only hook in if we're editing a no-user booking
			add_filter('em_booking_get_person_post', 'EM_User_Fields::em_booking_get_person_post', 10, 2);
			add_filter('em_nouser_booking_details_modified', 'EM_User_Fields::em_nouser_booking_details_modified', 10, 2);
		}
		//Booking Table and CSV Export
		static::init_booking_table_hooks();
		add_filter('em_bookings_table_cols_template_groups', array( static::class, 'em_bookings_table_cols_template_groups' ), 10, 2 );
		add_filter('em_bookings_table_rows_col', array( static::class,'em_bookings_table_rows_col' ),10,5 );
		//Data Privacy - exporting user info saved by user fields or in case of guest user within bookings
		add_filter('em_data_privacy_export_user', 'EM_User_Fields::data_privacy_export_user', 10, 2);
		add_filter('em_booking_get_person', 'EM_User_Fields::em_booking_get_person', 10, 2);
	}
	
	public static function admin_print_scripts(){
		$suffix = EM_VERSION > 5.99912 ? true:'post.php'; // backwards compat
		EM_Scripts_and_Styles::admin_enqueue($suffix);
	}
	
	public static function get_form(){
		if( empty(self::$form) ){
			self::$form = new EM_Form('em_user_fields');
			self::$form->form_required_error = get_option('dbem_emp_booking_form_error_required');
			self::$form->is_user_form = true;
		}
		self::$form->uploads_subfolder = 'users';
		return self::$form;
	}
	
	public static function emp_booking_user_fields( $fields ){
		//just get an array of options here
		$custom_fields = get_option('em_user_fields');
		foreach($custom_fields as $field_id => $field){
			if( !in_array($field_id, $fields) ){
				$fields[$field_id] = $field['label'];
			}
		}
		return $fields;
	}
	
	public static function validate($result, $field, $value, $form){
		$EM_Form = self::get_form();
		if( array_key_exists($field['fieldid'], $EM_Form->user_fields) ){
			//override default regex and error message
			//first figure out the type to modify
			$true_field_type = $EM_Form->form_fields[$field['fieldid']]['type'];
			$true_option_type = $true_field_type;
			if( $true_field_type == 'textarea' ) $true_option_type = 'text';
			if( in_array($true_field_type, array('select','multiselect')) ) $true_option_type = 'select';
			if( in_array($true_field_type, array('checkboxes','radio')) ) $true_option_type = 'selection';
			//now do the overriding
			if( !empty($field['options_reg_error']) ){
				$EM_Form->form_fields[$field['fieldid']]['options_'.$true_option_type.'_error'] = $field['options_reg_error'];
			}
			if( !empty($field['options_reg_regex']) ){
				$EM_Form->form_fields[$field['fieldid']]['options_'.$true_option_type.'_regex'] = $field['options_reg_regex'];
			}
			$EM_Form->form_fields[$field['fieldid']]['label'] = $field['label']; //To prevent double required messages for booking user field with different label to original user field
			//validate the original field type
			if( !$EM_Form->validate_field($field['fieldid'], $value) ){
				$form->add_error($EM_Form->get_errors());
				return false;
			}
			return $result && true;
		}
		return $result;
	}

	/**
	 * @param array $field
	 * @param mixed $value
	 * @param EM_Form $form
	 *
	 * @return void
	 */
	public static function save( $result, $field, $form ){
		$EM_Form = self::get_form();
		$field_id =  $field['fieldid'];
		$true_field = $EM_Form->form_fields[$field_id];
		// prep the upload field
		if ( $true_field['type'] === 'upload' ){
			$EM_Form->field_values[$field_id] = $form->field_values[$field_id]; // pass the post value already obtained, we don't want to overwrite anything previously there
			$EM_Form->_request_path = $form->_request_path;
			$EM_Form->uploads_user_id = $form->uploads_user_id;
			// if user is real, we jus store uuid as endpoint
			if ( $EM_Form->uploads_user_id > 0 ) {
				$user_uuid = static::get_user_uuid( $EM_Form->uploads_user_id );
				$EM_Form->uploads_endpoint_uuid = $user_uuid;
				$EM_Form->uploads_filename = $EM_Form->uploads_user_id . '-{field_id}-{uuid}';
			} else {
				// anonymous user, we should try to add more uniqueness to the filename
				if ( $form->uploads_subfolder === 'bookings' ) {
					// prepend 0_bk to endpoint and filename so we know it's a no-user booking, referenced by booking filename structure
					$EM_Form->uploads_endpoint_uuid = '0_bk_'. $form->uploads_endpoint_uuid;
					$EM_Form->uploads_filename = "0_bk_" . $form->uploads_filename;
				}
			}
		}
		// pass back saved value
		if( $EM_Form->save_field( $true_field ) ) {
			return $EM_Form->field_values[$field_id];
		}
		return $result;
	}

	public static function output_field( $field, $post ){
		$EM_Form = self::get_form();
		if( array_key_exists($field['fieldid'], $EM_Form->user_fields) ){
			$real_field = $EM_Form->form_fields[$field['fieldid']];
			$real_field['label'] = $field['label'];
			$real_field = $EM_Form->translate_field($real_field);
			if( empty($_REQUEST[$field['fieldid']]) && is_user_logged_in() && !EM_Bookings::$force_registration ){
				$post = self::get_user_meta(get_current_user_id(), $field['fieldid'], true);
			}
			if( get_option('dbem_emp_booking_form_reg_input') || !is_user_logged_in() || EM_Bookings::$force_registration ){
				echo $EM_Form->output_field_input($real_field, $post);
			}else{
				echo $EM_Form->get_formatted_value($real_field, $post);
			}
		}
	}
	
	/*
	 * ----------------------------------------------------------
	 * Booking Table and CSV Export
	 * ----------------------------------------------------------
	 */
	
	public static $booking_table_prefix = 'registration_';
	
	/**
	 * @return array
	 */
	public static function get_all_forms_data() {
		$form_data = get_option('em_user_fields'); // not like bookings form object with name etc., just a straight-up fields array
		return $form_data ? [$form_data] : [];
	}
	
	/**
	 * Slightly different from the parent function, as we're just wanting to avoid html fields, the rest is permitted
	 * @param $field_id
	 * @param $EM_Form
	 *
	 * @return mixed
	 */
	public static function is_booking_table_field( $field_id, $EM_Form ) {
		return $field_id !== 'dbem_phone' && !in_array($EM_Form->form_fields[$field_id]['type'], ['html', 'captcha']);
	}
	
	/**
	 * Provides values for custom field columns in a bookings table.
	 * @param string $value
	 * @param string $col
	 * @param EM_Booking $EM_Booking
	 * @param EM_Bookings_Table $EM_Bookings_Table
	 * @param boolean $csv
	 * @return string
	 */
	public static function em_bookings_table_rows_col($value, $col, $EM_Object, $EM_Bookings_Table, $format ){
		// only need to deal with prefixed columns belonging to booking form fields
		if( preg_match('/^'. static::$booking_table_prefix .'/', $col) ) {
			$field_id = preg_replace('/^'. static::$booking_table_prefix .'/', '', $col);
			// get the booking object, we'll always have a booking object
			extract( $EM_Bookings_Table->get_item_objects( $EM_Object ) ); /* @var EM_Booking $EM_Booking */ /* @var EM_Ticket_Bookings $EM_Ticket_Bookings */ /* @var EM_Ticket_Booking $EM_Ticket_Booking */
			// get the right form for the current context
			$EM_Form = static::get_bookings_table_form( $EM_Bookings_Table );
			// check for whether this column exists in the booking, and if so, get the value from this form
			if ( $EM_Form->is_user_field( $field_id ) && !empty( $EM_Form->form_fields[ $field_id ] ) ) {
				$field = $EM_Form->form_fields[ $field_id ];
				// first, get this from the user meta if exists, otherwise try registration booking meta
				$value = !$EM_Booking->is_no_user() ? self::get_user_meta( $EM_Booking->person_id, $field_id, true ) : '';
				if ( empty( $value ) && isset( $EM_Booking->booking_meta['registration'][ $field_id ] ) ) {
					$value = $EM_Booking->booking_meta['registration'][ $field_id ];
				}
				if ( !empty( $value ) ) {
					$value = $EM_Form->get_formatted_value( $field, $value, 'table' );
					if ( $field['type'] == 'upload' ) {
						EM_Bookings_Table::$cols_allowed_html[$col] = true;
					}
				}
			}
		}
		return $value;
	}
	
	// make a function for em_bookings_table_cols_template_groups which adds a Booking Custom Fields section with all booking form fields which aren't user fields
	public static function em_bookings_table_cols_template_groups( $template_groups, $EM_Bookings_Table ) {
		$form_fields = static::get_bookings_table_fields( $EM_Bookings_Table );
		// add it to a new template group
		$template_groups['user']['fields'] = array_merge( $template_groups['user']['fields'], array_keys($form_fields) );
		return $template_groups;
	}


	/*
	 * ----------------------------------------------------------
	* No-User Booking Functions - Edit/Save User Info
	* ----------------------------------------------------------
	*/

	public static function em_booking_get_person_editor( $content, $EM_Booking ){
	    //if you want to mess with these values, intercept the em_bookings_single_custom instead
	    ob_start();
	    $EM_Form = self::get_form();
		$name = $EM_Booking->get_person()->get_name();
		$email = $EM_Booking->get_person()->user_email;
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'booking_modify_person' ){
		    $name = !empty($_REQUEST['user_name']) ? $_REQUEST['user_name']:$name;
		    $email = !empty($_REQUEST['user_email']) ? $_REQUEST['user_email']:$email;
		}
		?>
		<table class="em-form-fields">
			<tr><th><?php esc_html_e_emp('Name','events-manager'); ?> : </th><td><input type="text" name="user_name" value="<?php echo esc_attr($name); ?>" /></td></tr>
			<tr><th><?php esc_html_e_emp('Email','events-manager'); ?> : </th><td><input type="text" name="user_email" value="<?php echo esc_attr($email); ?>" /></td></tr>
		    <?php
			foreach($EM_Form->form_fields as $field_id => $field){
				$value = !empty($EM_Booking->booking_meta['registration'][$field_id]) ? $EM_Booking->booking_meta['registration'][$field_id]:false;
				if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'booking_modify_person' ){
					$value = !empty($_REQUEST[$field_id]) ? $_REQUEST[$field_id]:$value;
			    }
    			if( $field['type'] == 'html' ){
					?>
					<td colspan="2"><?php echo $field['options_html_content']; ?></td>
					<?php
				}else{
					?>
					<tr>
						<th><label for="<?php echo $field_id; ?>"><?php echo $field['label']; ?></label></th>
						<td>
							<?php echo $EM_Form->output_field_input($field, $value); ?>
						</td>
					</tr>
					<?php
				}
			}
			do_action( 'em_booking_get_person_editor_bottom', $EM_Booking );
		    ?>
		</table>
	    <?php
	    return ob_get_clean();
	}
	
	public static function em_booking_get_person_post( $result, $EM_Booking ){
		//get, store and validate post data
		$EM_Form = self::get_form();
		if ( $EM_Booking->booking_id ) {
			$EM_Form->field_values = $EM_Booking->booking_meta['registration'];
		}
		if( $EM_Form->get_post() && $EM_Form->validate() && $result ){
			foreach($EM_Form->get_values() as $fieldid => $value){
				//registration fields
				$EM_Booking->booking_meta['registration'][$fieldid] = $value;
			}
		}elseif( count($EM_Form->get_errors()) > 0 ){
			$result = false;
			$EM_Booking->add_error($EM_Form->get_errors());
		}
		return $result;
	}

	/**
	 * When a no-user booking personal details is saved, hook in and save extra form field values.
	 * @param EM_Booking $EM_Booking
	 *
	 * @return void
	 */
	public static function em_nouser_booking_details_modified( $EM_Booking ) {
		$EM_Form = self::get_form();
		$EM_Form->uploads_user_id = 0; // only for no-user bookings anyway
		// save booking uuid to endpoint, and the booking id as part of user id section
		$EM_Form->uploads_endpoint_uuid = '0_bk_'. $EM_Booking->booking_uuid;
		$EM_Form->uploads_filename = "0_bk_" . $EM_Booking->booking_id . '-{field_id}-{uuid}';
		$saved_fields = $EM_Form->save();
		if ( !empty($saved_fields) ) {
			foreach ( $saved_fields as $field_id => $field_value ) {
				$EM_Booking->booking_meta['registration'][$field_id] = $field_value;
			}
			$EM_Booking->update_meta('registration', $EM_Booking->booking_meta['registration']);
		}
	}
	
	/*
	 * ----------------------------------------------------------
	 * Display Functions
	 * ----------------------------------------------------------
	 */
	
	/**
	 * @param string $summary
	 * @param EM_Person $EM_Person
	 * @return string|unknown
	 */
	public static function em_person_display_summary($summary, $EM_Person){
		global $EM_Booking;
		$EM_Form = self::get_form();
		$no_user = $EM_Person->ID == 0;
		ob_start();
		//a bit of repeated stuff from the original EM_Person::display_summary() static function
		?>
		<table class="em-form-fields">
			<tr>
				<td><?php echo get_avatar($EM_Person->ID); ?></td>
				<td style="padding-left:10px; vertical-align: top;">
					<table>
						<?php if( $no_user ): ?>
						<tr><th><?php esc_html_e_emp('Name','events-manager'); ?> : </th><th><?php echo $EM_Person->get_name() ?></th></tr>
						<?php else: ?>
						<tr><th><?php esc_html_e_emp('Name','events-manager'); ?> : </th><th><a href="<?php echo $EM_Person->get_bookings_url(); ?>"><?php echo $EM_Person->get_name() ?></a></th></tr>
						<?php endif; ?>
						<tr><th><?php esc_html_e_emp('Email','events-manager'); ?> : </th><td><?php echo esc_html($EM_Person->user_email); ?></td></tr>
						<?php foreach( $EM_Form->form_fields as $field_id => $field ){
							if( $field['type'] == 'html' ){
								?>
								<td colspan="2"><?php echo $field['options_html_content']; ?></td>
								<?php
							}else{
								$value = self::get_user_meta($EM_Person->ID, $field_id, true);
								//override by registration value in case value is now empty, otherwise show n/a
								if( !empty($EM_Booking->booking_meta['registration'][$field_id]) && (empty($value) || $no_user) ){
									$value = $EM_Booking->booking_meta['registration'][$field_id];
								}elseif( empty($value) || $no_user ){
									$value = "<em>".esc_html__('n/a','em-pro')."</em>";
								}								
								if( $value != "<em>".esc_html__('n/a','em-pro')."</em>" ){
									if ( $field['type'] == 'tel' && EM\Phone::is_enabled() ) {
										$value = $EM_Form->get_formatted_value($field, $value);
									} elseif ( $field['type'] == 'upload' ) {
										$value = $EM_Form->get_formatted_value($field, $value);
									}else {
										$value = esc_html($EM_Form->get_formatted_value($field, $value));
									}
								}
								?>
								<tr><th><?php echo $field['label']; ?> : </th><td><?php echo $value; ?></td></tr>	
								<?php 
							}
						}
						do_action( 'em_person_display_summary_bottom', $EM_Person );
						?>
					</table>
				</td>
			</tr>
		</table>
		<?php
		return ob_get_clean();
	}
	
	/*
	 * ----------------------------------------------------------
	 * ADMIN Functions
	 * ----------------------------------------------------------
	 */
	
	/**
	 * Gets data from the right location according to the field ID provided. For example, user_email (emails) are retreived from the wp_users table whereas other info is usually taken from wp_usermeta
	 * @param int $user_id
	 * @param string $field_id
	 * @param bool $single
	 */
	public static function get_user_meta( $user_id, $field_id, $single=true){
		if( !$user_id ) $user_id = get_current_user_id();
		if( $field_id == 'user_email' ){
			$WP_User = get_user_by('id', $user_id);
			$return = $WP_User->user_email;
		}elseif( $field_id == 'name' ){
			$WP_User = get_user_by('id', $user_id);
			$EM_Person = new EM_Person($WP_User);
			$return = $EM_Person->get_name();
		}elseif( $field_id == 'user_login' ){
			$WP_User = get_user_by('id', $user_id);
			$EM_Person = new EM_Person($WP_User);
			$return = $EM_Person->user_login;
		}else{
			$return = get_user_meta($user_id, $field_id, true);
		}
		return $return;
	}
	
	/**
	 * Updates data to the right location according to the field ID provided. For example, user_email (emails) are saved to the wp_users table whereas other info is usually taken from wp_usermeta
	 * @param string $field_id
	 */
	public static function update_user_meta($user_id, $field_id, $value){
		global $wpdb;
		if( !$user_id ) $user_id = get_current_user_id();
		if( $field_id == 'user_email' && is_email($value) ){
			return $wpdb->update($wpdb->users, array('user_email'=> $value), array('ID'=>$user_id));
		}elseif( $field_id == 'user_name' ){
			$user = new WP_User($user_id);
			if( $user->first_name . ' ' . $user->last_name == $value ) return true;
			$name = explode(' ', $value);
			$return_fn = update_user_meta($user_id, 'first_name', array_shift($name));
			$return_ln = update_user_meta($user_id, 'last_name', implode(' ',$name));
			return $return_fn && $return_ln;
		}else{
			return update_user_meta($user_id, $field_id, $value);
		}
	}

	public static function get_user_uuid( $user_id ) {
		if ( $user_id === 0 ) return 0;
		$user = new WP_User( $user_id );
		if ( !$user->uuid ) {
			// createa uuid if none
			$user->uuid = str_replace('-', '', wp_generate_uuid4());
			update_user_meta( $user_id, 'uuid', $user->uuid );
		}
		return $user->uuid;
	}
	
	/**
	 * Adds phone number to contact info of users, compatible with previous phone field method
	 * @param $array
	 * @return array
	 */
	public static function show_profile_fields($user){
		$EM_Form = self::get_form();
		?>
		<h3><?php _e('Further Information','em-pro'); ?></h3>
		<table class="form-table em">
			<?php 
			foreach($EM_Form->form_fields as $field_id => $field){
    			if( $field['type'] == 'html' ){
					?>
					<tr><td colspan="2"><?php echo $field['options_html_content']; ?></td></tr>
					<?php
				}else{
					?>
					<tr>
						<th><label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field['label']); ?></label></th>
						<td>
							<?php
								$field_val = isset($_REQUEST[$field['fieldid']]) ? $_REQUEST[$field['fieldid']] : self::get_user_meta($user->ID, $field_id, true); 
								echo $EM_Form->output_field_input($field, $field_val); 
							?>
						</td>
					</tr>
					<?php
				}
			}
			do_action('em_user_profile_fields', $user);
			?>	
		</table>
		<?php
	}
	
	public static function save_profile_fields($user_id){
		global $EM_Notices;
		if ( !current_user_can( 'edit_user', $user_id ) )
			return false;
		$EM_Form = self::get_form();
		// populate with previous values to avoid overwriting data such as uploads
		foreach ( $EM_Form->form_fields as $field_id => $field ) {
			$field_val = self::get_user_meta($user_id, $field_id, true);
			$EM_Form->field_values[$field_id] = $field_val;
		}
		// validate and save
		if( $EM_Form->get_post() && $EM_Form->validate() ){ //get post and validate at once
			// save fields via save() in form first
			$user_uuid = static::get_user_uuid($user_id);
			$EM_Form->uploads_endpoint_uuid = $user_uuid;
			$EM_Form->uploads_filename = $user_id . '-{field_id}-{uuid}';
			$EM_Form->save();
			// save all fields to profile
			foreach($EM_Form->form_fields as $field_id => $field){
				self::update_user_meta( $user_id, $field_id, $EM_Form->field_values[$field_id] );
			}
		}
		if( count($EM_Form->errors) > 0 ){
			$EM_Notices->add_error($EM_Form->get_errors());
			add_filter('user_profile_update_errors', 'EM_User_Fields::save_profile_fields_errors', 10, 3);
		}
	}
	
	public static function save_profile_fields_errors( $errors, $update, $user ){
		global $EM_Notices;
		$errors->add('em_user_fields', $EM_Notices->get_errors(false));
		$EM_Notices = new EM_Notices();
		return $errors;
	}

	/**
	 * Perform custom actions just before a user is deleted.
	 *
	 * @param int $user_id The ID of the user being deleted.
	 */
	public static function delete_user( $user_id ) {
		// go through and delete uploaded files
		$EM_Form = self::get_form();
		foreach ( $EM_Form->form_fields as $field_id => $field ) {
			if ( $field['type'] == 'upload' ){
				// get user meta and check if there are files in it
				$file_meta = get_user_meta($user_id, $field_id, true);
				if ( !empty($file_meta['files']) ) {
					foreach ( $file_meta['files'] as $file ) {
						@unlink($file['file']);
					}
				}
			}
		}
	}
	
	public static function admin_page_actions() {
		global $EM_Notices;
		$EM_Form = self::get_form();
		if( !empty($_REQUEST['page']) && $_REQUEST['page'] == 'events-manager-forms-editor' ){
			if( !empty($_REQUEST['form_name']) && $EM_Form->form_name == $_REQUEST['form_name'] ){
				//set up booking form field map and save/retreive previous data
				if( empty($_REQUEST['bookings_form_action']) && $EM_Form->editor_get_post() ){
					//Update Values
					if( count($EM_Form->get_errors()) == 0 ){
						//go through fields and do a little more clenaing up before saving adding them
						$form_fields = array();
						//prefix all with dbem unless shared fields enabled
						foreach($EM_Form->form_fields as $field_id => $field){
							if( substr($field_id, 0, 5) != 'dbem_' && (!defined('EMP_SHARED_CUSTOM_FIELDS') || !EMP_SHARED_CUSTOM_FIELDS) ){
								$field_id = $field['fieldid'] = 'dbem_'.$field_id;
							}
							//add cleaned field for saving
							$form_fields[$field_id] = $field;
						}
						//a bit excessive but also prevent reserved field ids from being used, only relevant if we have shared fields, since we prefix with dbem_ at the moment which won't clash
						if( defined('EMP_SHARED_CUSTOM_FIELDS') && EMP_SHARED_CUSTOM_FIELDS ){
							$field_types = array('html','text','textarea','checkbox','date','checkboxes','radio','select','country','multiselect','time','captcha');
							$reserved_field_ids = array_merge(array_keys($EM_Form->core_user_fields), $field_types);
							foreach($form_fields as $field_id => $field){
								if( in_array($field_id, $reserved_field_ids) ){
									$position = array_search($field_id, array_keys($form_fields));
									unset($form_fields[$field_id]);
									/*
									 * if we have a field ID that matches a normal or user core field type, things will screw up during output,
									 * validation and options because it'll not know whether it's a regular/core field or a custom user field type
									 * we also need to do a check to make sure there isn't any duplicates now that we're prefixing
									 */
									$field_id = $field['fieldid'] = 'dbem_'.$field_id;
									if( array_key_exists($field_id, $form_fields) ){
										$suffix = 2;
										while( array_key_exists($field_id.'_'.$suffix, $form_fields) ) $suffix++;
										$field_id = $field['fieldid'] = $field_id.'_'.$suffix;
									}
									$form_fields = array_slice($form_fields, 0, $position, true) +
										array($field_id => $field) +
										array_slice($form_fields, $position, NULL, true);
								}
							}
						}
						//save fields to options and load user form
						update_option('em_user_fields', $form_fields);
						$EM_Notices->add_confirm(__('Changes Saved','em-pro'));
						self::$form = false; //reset form
						$EM_Form = new EM_Form($form_fields);
					}else{
						$EM_Notices->add_error($EM_Form->get_errors());
					}
				}
			}
		}
		//enable dbem_bookings_tickets_single_form if enabled
	}
	
	public static function admin_page() {
		$EM_Form = self::get_form();
		//enable dbem_bookings_tickets_single_form if enabled
		?>
		<a id="user_fields"></a>
					<div id="em-booking-form-editor" class="postbox">
						<div class="handlediv" title=""><br></div>
						<h3>
							<span><?php _e ( 'User Fields', 'em-pro' ); ?></span>
						</h3>
						<div class="inside">
							<p><?php echo sprintf( __('Registration fields are only shown to guest visitors. If you add new fields here and save, they will then be available as custom registrations in your bookings editor, and this information will be accessible and editable on each user <a href="%s">profile page</a>.', 'em-pro' ), 'profile.php'); ?></p>
							<p><?php _e ( '<strong>Important:</strong> When editing this form, to make sure your current user information is displayed, do not change their field names.', 'em-pro' )?></p>
							<?php echo $EM_Form->editor(false, true, false); ?>
						</div>
					</div>
		<?php
	}
	
	private static function show_reg_fields(){
		return !is_user_logged_in() && get_option('dbem_bookings_anonymous'); 
	}

	public static function data_privacy_exporters( $exporters ){
		$exporters['events-manager-user'] = array(
			'exporter_friendly_name' =>  __( 'Events Manager', 'events-manager' ) . ' - ' .__( 'Further Information', 'events-manager' ),
			'callback' => 'EM_Data_Privacy::export_locations',
		);
	    return $exporters;
    }

    public static function data_privacy_export_user( $export_item, $user ){
	    $EM_Form = self::get_form();
	    $export_item['data'] = array();
        foreach( $EM_Form->form_fields as $field_id => $field ){
            if( $field['type'] != 'html' ){
                $value = self::get_user_meta($user->ID, $field_id, true);
                if( !empty($value) ){
                    $export_item['data'][] = array( 'name' => $field['label'], 'value' => $EM_Form->get_formatted_value($field, $value) );
                }
            }
        }
	    return $export_item;
    }

    //Data Privacy Functions

    public static function data_privacy_export_booking( $export_item, EM_Booking $EM_Booking ){
	    if( $EM_Booking->person_id == 0 ){
            $EM_Form = self::get_form();
            foreach( $EM_Form->form_fields as $field_id => $field ){
                if( $field['type'] != 'html' ){
                    if( !empty($EM_Booking->booking_meta['registration'][$field_id]) ){
                        $value = $EM_Booking->booking_meta['registration'][$field_id];
                        $export_item['data'][$field_id] = array( 'name' => $field['label'], 'value' => $EM_Form->get_formatted_value($field, $value) );
                    }
                }
            }
        }
        return $export_item;
    }

    public static function em_booking_get_person( $EM_Person, $EM_Booking ){
	    $EM_Form = self::get_form();
	    foreach( $EM_Form->form_fields as $field_id => $field ){
		    if( $field['type'] != 'html' ){
			    if( $EM_Person->person_id == 0 && !empty($EM_Booking->booking_meta['registration'][$field_id]) ){
				    $value = $EM_Booking->booking_meta['registration'][$field_id];
				    $EM_Person->custom_user_fields[$field_id] = array('name' => $field['label'], 'value' => $EM_Form->get_formatted_value($field, $value));
			    }else{
				    $value = self::get_user_meta($EM_Person->ID, $field_id, true);
				    $EM_Person->custom_user_fields[$field_id] = array('name' => $field['label'], 'value' => $EM_Form->get_formatted_value($field, $value));
                }
		    }
	    }
	    return $EM_Person;
    }
}
EM_User_Fields::init();