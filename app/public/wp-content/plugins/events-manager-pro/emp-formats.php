<?php
/**
 * Extends EM_Formats for location templates in the events-manager-pro plugin folder. along with pro-specific formats.
 */
class EMP_Formats extends EM_Formats {
	protected static $formats_filter = 'emp_formats_filter';
	
	public static function locate_template($template){
		return emp_locate_template( $template );
	}
	
	/**
	 * Override parent by including pro-specific formatting modes map with a specific filter too.
	 * @return mixed|void
	 */
	public static function get_formatting_modes_map(){
		$formatting_modes_map = array (
			/*
			'events-list' => array(
				'dbem_event_list_item_format_header',
				...
			),
			*/
		);
		return apply_filters('emp_formats_formatting_modes_map', $formatting_modes_map);
	}
}
EMP_Formats::init();