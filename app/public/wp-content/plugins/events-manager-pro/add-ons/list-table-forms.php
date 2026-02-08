<?php
namespace EM\List_Table;

use EM_Form;

/*
 *
 * The following properties must be defined in overriding class;
 *
 * @static  string  $default_form_option
 * @static  string  $booking_table_prefix
 * @static  array   $bookings_table_fields
 * @static  array   $bookings_table_forms
 *
 */
trait Forms {

	public static $bookings_table_forms = array();
	public static $bookings_table_fields = array();
	
	public static function init_booking_table_hooks( $args = array() ) {
		if( empty($args['skip_bookings_sql_orderby']) ) {
			add_filter('em_bookings_sql_fields_orderby_booking_meta', array( static::class, 'em_bookings_sql_fields_orderby_x' ), 10 );
		}
		add_filter('em_bookings_table_get_sortable_columns', array( static::class, 'em_bookings_table_get_sortable_columns' ), 10, 2 );
		if( !empty(static::$booking_table_cols_template) ) {
			add_filter('em_bookings_table_' . static::$booking_table_cols_template, array( static::class,'em_bookings_table_cols_template' ),10,2 );
		} else {
			add_filter('em_bookings_table_cols_template', array( static::class,'em_bookings_table_cols_template' ),10,2 );
		}
	}
	
	/**
	 * Add these fields to the cols template so they can be displayed in the bookings table, this can be overriden and added to base templates instead
	 * @param array $template
	 * @param \EM_Bookings_Table $EM_Bookings_Table
	 *
	 * @return array
	 */
	public static function em_bookings_table_cols_template($template, $EM_Bookings_Table){
		$form_fields = static::get_bookings_table_fields( $EM_Bookings_Table );
		foreach( $form_fields as $field_id => $field ){
			$template[$field_id] = wp_kses($field['label'], array()); // remove all HTML so we don't have CSS grid display issues with sortables
		}
		return $template;
	}
	
	/**
	 * Adds booking form custom fields to orderby options used in EM_Bookings::get() and obtained via EM_Bookings:get_sql_accepted_fields();
	 *
	 * Classes using this trait should add a filter for a specific orderby_... filter and call this method.
	 *
	 * For example, em_bookings_sql_fields_orderby_booking_meta would add fields to booking meta searchable fields.
	 *
	 * @param array $accepted_fields
	 *
	 * @return array
	 */
	public static function em_bookings_sql_fields_orderby_x( $accepted_fields ) {
		$EM_Form = self::get_all_fields_form();
		foreach( $EM_Form->form_fields as $field_id => $field ) {
			if( $EM_Form->is_normal_field($field) ) {
				$accepted_fields[ static::$booking_table_prefix . $field_id ] = $field_id;
			}
		}
		return $accepted_fields;
	}
	
	public static function em_bookings_table_get_sortable_columns( $sortable_columns, $EM_Bookings_Table ) {
		if ( !empty(static::$booking_table_sortable_views) && !in_array( $EM_Bookings_Table->view, static::$booking_table_sortable_views ) ) {
			return $sortable_columns;
		}
		$raw_form_fields = self::get_bookings_table_fields( $EM_Bookings_Table );
		$form_fields = [];
		// check that the field type is one that makes sense sorting and add to array if so
		$sortable_types = ['date', 'text', 'textarea', 'tel', 'checkbox', 'radio', 'select', 'country'];
		foreach( $raw_form_fields as $form_field => $field_data ) {
			if ( in_array( $field_data['type'], $sortable_types ) ) {
				$form_fields[ $form_field ] = [ $form_field, false ];
			}
		}
		return $sortable_columns + $form_fields;
	}
	
	public static function get_default_form_id() {
		if ( !empty(static::$default_form_option) ) {
			return get_option( static::$default_form_option );
		}
		return false;
	}
	
	/**
	 * Create a form containing all fields of this form type. If there's a default form, then that takes precedence over repeated field IDs.
	 * @return EM_Form
	 */
	public static function get_all_fields_form() {
		$forms = static::get_all_forms_data();
		$form_fields = [];
		$default_form_id = static::get_default_form_id();
		foreach( $forms as $form_id => $form_data ) {
			// we just need the field_ids and their labels, no need to create attendee form objects to be faster
			foreach( $form_data as $field_id => $field ){
				if( empty($form_fields[$field_id]) || $form_id == $default_form_id ) {
					$form_fields[ $field_id ] = $field;
				}
			}
		}
		// bung it all into one form
		return new EM_Form( $form_fields );
	}
	
	public static function get_all_forms_data() {
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare("SELECT meta_id, meta_value FROM ".EM_META_TABLE." WHERE meta_key = %s", static::$form_meta_key) );
		$forms = [];
		foreach ( $results as $row ) {
			$form_data = unserialize($row->meta_value);
			$forms[$row->meta_id] = $form_data['form'];
		}
		return $forms;
	}
	
	/**
	 * Get the form relatieve to bookings table context, this form is not a 'real' form, it contains fields, and we use the EM_Form to determine displayability.
	 *
	 * It could, in theory, contain all fields for multiple forms for the general context view, for example.
	 *
	 * @param $EM_Event
	 *
	 * @return EM_Form
	 */
	public static function get_bookings_table_form( $EM_Bookings_Table ) {
		if( $EM_Bookings_Table->context == 'event' || $EM_Bookings_Table->context == 'ticket' ) {
			$EM_Event = $EM_Bookings_Table->get_event();
			if( !empty(static::$bookings_table_forms[$EM_Event->event_id]) ) return static::$bookings_table_forms[$EM_Event->event_id];
			$EM_Form = static::get_form( $EM_Event );
			static::$bookings_table_forms[$EM_Event->event_id] = $EM_Form;
		} else {
			if( !empty(static::$bookings_table_forms['all']) ) return static::$bookings_table_forms['all'];
			$EM_Form = static::get_all_fields_form();
			static::$bookings_table_forms['all'] = $EM_Form;
		}
		return $EM_Form;
	}
	
	public static function is_booking_table_field( $field_id, $EM_Form ) {
		return $EM_Form->is_normal_field( $field_id );
	}
	
	/**
	 * Gets the custom fields for the current view of a booking table
	 *
	 * @param \EM_Bookings_Table $EM_Bookings_Table
	 *
	 * @return array
	 */
	public static function get_bookings_table_fields ( $EM_Bookings_Table ) {
		// got here, get attendee fields for the current view
		$fields = array();
		$form_context_id = $EM_Bookings_Table->get_event() ? $EM_Bookings_Table->get_event()->event_id : 'all';
		if( !empty(static::$bookings_table_fields[$form_context_id]) ) return static::$bookings_table_fields[$form_context_id];
		$EM_Form = self::get_bookings_table_form( $EM_Bookings_Table );
		// filter out non-normal fields for this form type
		foreach( $EM_Form->form_fields as $field_id => $field ){
			if( static::is_booking_table_field( $field_id, $EM_Form ) ){
				$booking_field_id = static::$booking_table_prefix . $field_id;
				$fields[ $booking_field_id ] = array(
					'label' => $field['label'],
					'type' => $field['type'],
				);
			}
		}
		static::$bookings_table_fields[$form_context_id] = $fields;
		return $fields;
	}
}