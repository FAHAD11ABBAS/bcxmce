<?php
namespace EM\Automation\Admin;
use EM_Object, Iterator, Countable;

/**
 * Class used to search and retrieve imports and exports
 * @author marcus
 *
 */
class Automations extends EM_Object implements Iterator, Countable {
	
	/**
	 * Number of found records
	 * @var int
	 */
	public $total_count = 0;
	/**
	 * Items loaded in search
	 * @var array
	 */
	public $items = array();
	
	/**
	 * Array of registered format for this collection (imports or exports).
	 * @var array
	 */
	public static $formats = array();
	
	/**
	 * EMIO_Items constructor.
	 * @param array $args
	 */
	public function __construct($args = array()) {
		global $wpdb;
		
		$args = self::get_default_search($args);
		$limit = ($args['limit'] && is_numeric($args['limit'])) ? "LIMIT {$args['limit']}" : '';
		$offset = ($limit != "" && is_numeric($args['offset'])) ? "OFFSET {$args['offset']}" : '';
		
		//Get the default conditions
		$conditions = self::build_sql_conditions($args);
		//Put it all together
		$where = (count($conditions) > 0) ? " WHERE " . implode(" AND ", $conditions) : '';
		
		$orderby = self::build_sql_orderby($args);
		$orderby_sql = (count($orderby) > 0) ? 'ORDER BY ' . implode(', ', $orderby) : '';
		
		$sql = apply_filters('em_autmoation_objects_sql', "
				SELECT SQL_CALC_FOUND_ROWS * FROM " . EM_AUTOMATION_TABLE . "
				$where
				$orderby_sql
				$limit $offset
				", $args);
		$results = $wpdb->get_results($sql, ARRAY_A);
		$this->items = $results;
		$this->total_count = $wpdb->get_var('SELECT FOUND_ROWS()');
		return $this->items;
	}
	
	/**
	 * @param array $args
	 * @param array $accepted_fields
	 * @param string $default_order
	 * @return array
	 */
	public static function build_sql_orderby($args, $accepted_fields = array(), $default_order = 'ASC') {
		if (empty($accepted_fields)) $accepted_fields = array('id', 'object_id', 'name', 'type', 'when', 'listener', 'doing_cron');
		return parent::build_sql_orderby($args, $accepted_fields, $default_order);
	}
	
	/**
	 * Builds array of SQL search conditions. We don't need EM_Object conditions so we override that function entirely
	 * @param array $args
	 * @return array
	 */
	public static function build_sql_conditions($args = array()) {
		global $wpdb;
		$conditions = array();
		//other simple search flags
		if( $args['status'] !== null ) { //overrides frequency_active
			$conditions['status'] = $args['status'] ? 'status = 1' : 'status = 0';
		}
		if( !empty($args['type']) ) {
			$conditions['type'] = $wpdb->prepare('type = %s', $args['type']);
		}
		if( !empty($args['actions']) && preg_match('/^[a-zA-Z\-_0-9]+$/', $args['actions']) ){
			$conditions['actions'] = 'action_data LIKE \'%:4:"type";s:'. strlen($args['actions']).':"'.$args['actions'].'"%\'';
		}
		return $conditions;
	}
	
	/**
	 * @param array $array_or_defaults
	 * @param array $array
	 * @return array|mixed|void
	 */
	public static function get_default_search($array_or_defaults = array(), $array = array()) {
		$defaults = array('orderby' => 'name', 'order' => 'DESC', 'status' => null, 'offset' => 0, 'page' => 1, 'type' => false, 'limit' => 20, 'doing_cron' => false, 'actions' => false);
		//sort out whether defaults were supplied or just the array of search values
		if( empty($array) ) {
			$array = $array_or_defaults;
		}else{
			$defaults = array_merge($defaults, $array_or_defaults);
		}
		//let EM_Object clean out these
		$args = apply_filters('em_automation_get_default_search', parent::get_default_search($defaults, $array), $array, $defaults);
		//we only need args present in our $default, so clean out the rest
		return array_intersect_key($args, $defaults);
	}
	
	//Countable Implementation
	#[\ReturnTypeWillChange]
	public function count() {
		return count($this->items);
	}
	
	//Iterator Implementation
	#[\ReturnTypeWillChange]
	public function rewind() {
		reset($this->items);
	}
	#[\ReturnTypeWillChange]
	public function current() {
		return current($this->items);
	}
	#[\ReturnTypeWillChange]
	public function key() {
		return key($this->items);
	}
	#[\ReturnTypeWillChange]
	public function next() {
		return next($this->items);
	}
	#[\ReturnTypeWillChange]
	public function valid() {
		$key = key($this->items);
		$var = ($key !== NULL && $key !== FALSE);
		return $var;
	}
}