<?php
namespace EM\Automation\Admin;
use WP_List_Table;

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class List_Table extends WP_List_Table {
	
	public $per_page = 20;
	public $total_items = 0;
	public $per_page_var = 'automations_per_page';
	public $has_checkboxes = true;
	
	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items(){
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		
		$this->per_page = $this->get_items_per_page( $this->per_page_var, 20 );
		$this->items = $this->table_data();
		
		$this->set_pagination_args( array(
			'total_items' => $this->total_items,
			'per_page'    => $this->per_page,
		) );
		
		$this->_column_headers = array($columns, $hidden, $sortable);
	}
	
	/**
	 * Define which columns are hidden
	 *
	 * @return array
	 */
	public function get_hidden_columns(){
		return array();
	}
	
	/**
	 * Should be overriden, obtains data for populating the table.
	 * @return array
	 */
	protected function table_data(){
		return array();
	}
	
	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ){
		if( array_key_exists($column_name, $item) ){
			return $item[$column_name];
		}
		return print_r( $item, true ) ;
	}
}
