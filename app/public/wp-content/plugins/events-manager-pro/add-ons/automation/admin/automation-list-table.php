<?php
namespace EM\Automation\Admin;
use EM\Automation;

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Automation_List_Table extends List_Table {
	
	public $item_type = 'item';
	public $per_page_var = 'emio_items_per_page';
	
	public function __construct($args = array()) {
		$this->item_type = str_replace('em-pro-', '', $_GET['page']);
		parent::__construct($args);
	}
	
	/**
	 * Get the table data
	 *
	 * @return array
	 */
	protected function table_data(){
		$args = array();
		//set pagination and ordering
		$args['limit'] = $this->per_page;
		$args['page'] = $this->get_pagenum();
		$args['orderby'] = !empty($_REQUEST['orderby']) ? $_REQUEST['orderby']:'name';
		$args['order'] = !empty($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc':'asc';
		//check search filters
		if( !empty($_REQUEST['frequency']) && $_REQUEST['frequency'] != 'all' ){
		}
		//we set frequency_active not status since we're searchng by frequency
		if( !empty($_REQUEST['status']) ){
			$args['status'] = $_REQUEST['status'] == 'active' ? true:false;
		}else{
			$args['status'] = null;
		}
		//check view selection
		if( !empty($_REQUEST['type']) ){
			$args['type'] = $_REQUEST['type'];
		}
		//search by action
		if( !empty($_REQUEST['actions']) ){
			$args['actions'] = $_REQUEST['actions'];
		}
		//Do the search
		$Automations = new Automations($args);
		$this->total_items = $Automations->total_count;
		//Prepare data
		return $Automations->items;
	}
	
	protected function get_views() {
		global $wpdb;
		$query = 'SELECT COUNT(*) FROM '.EM_AUTOMATION_TABLE;
		$query_args = array();
		$status_links = $this->get_views_template();
		foreach( $status_links as $filter_name => $filter_link ){
			$this_query = $query;
			if( $filter_name != 'all' ){
				$this_query .= " WHERE type=%s";
				$query_args['format'] = $filter_name;
			}
			$the_query = !empty($query_args) ? $wpdb->prepare($this_query, $query_args) : $this_query;
			$status_links[$filter_name] = sprintf( $filter_link, $wpdb->get_var($the_query) );
		}
		$filter = !empty($_GET['filter']) && isset($status_links[$_GET['filter']]) ? $_GET['filter'] : 'all';
		$status_links[$filter] = '<strong>'.$status_links[$filter].'</strong>';
		return $status_links;
	}
	
	private function get_views_template(){
		$formats = Automation::$triggers;
		$views = array(
			"all" => '<a href="'. esc_url(add_query_arg('filter',null)) .'">'. __('All','em-pro') . ' (%d)</a>',
		);
		foreach( $formats as $trigger => $trigger_class ){
			$views[$trigger] = '<a href="'. esc_url(add_query_arg('filter',$trigger)) .'">'. $trigger_class::get_name() . ' (%d)</a>';
		}
		return $views;
	}
	
	public function extra_tablenav( $which ) {
		if( $which != 'top' ) return null;
		echo wp_nonce_field('emio-items-bulk', 'emio_'. $this->item_type .'_nonce');
		$status_value = !empty($_REQUEST['status']) ? $_REQUEST['status'] : '';
		$trigger_value = !empty($_REQUEST['type']) ? $_REQUEST['type'] : '';
		$action_value = !empty($_REQUEST['actions']) ? $_REQUEST['actions'] : '';
		?>
		<div class="alignleft actions">
			<label for="filter-by-status" class="screen-reader-text"><?php esc_html_e('Filter by Status', 'em-pro'); ?></label>
			<select id="filter-by-status" name="status">
				<option value="0"><?php esc_html_e('Active and Inactive', 'em-pro'); ?></option>
				<option value="active" <?php if($status_value == 'active') echo 'selected'; ?>><?php esc_html_e('Active', 'em-pro'); ?></option>
				<option value="inactive" <?php if($status_value == 'inactive') echo 'selected'; ?>><?php esc_html_e('Inactive', 'em-pro'); ?></option>
			</select>
			<label for="filter-by-trigger" class="screen-reader-text"><?php esc_html_e('Filter by Trigger', 'em-pro'); ?></label>
			<select id="filter-by-trigger" name="type">
				<option value="0"><?php echo sprintf(esc_html__('All %s', 'em-pro'), esc_html__('Triggers', 'em-pro')); ?></option>
				<?php foreach( Automation::$triggers as $trigger_type => $trigger ): ?>
					<option value="<?php echo esc_attr($trigger_type) ?>" <?php if($trigger_type == $trigger_value) echo 'selected'; ?>><?php echo $trigger::get_name(); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="filter-by-action" class="screen-reader-text"><?php esc_html_e('Filter by Action', 'em-pro'); ?></label>
			<select id="filter-by-action" name="actions">
				<option value="0"><?php echo sprintf(esc_html__('All %s', 'em-pro'), esc_html__('Actions', 'em-pro')); ?></option>
				<?php foreach( Automation::$actions as $action_type => $action ): ?>
					<option value="<?php echo esc_attr($action_type) ?>" <?php if($action_value == $action_type) echo 'selected'; ?>><?php echo $action::get_name(); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			submit_button(__('Filter', 'events-manager'), 'secondary', 'post-query-submit', false);
			?>
		</div>
		<?php
	}
	
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(){
		$columns = array();
		$columns['cb'] = '<input type="checkbox" />';
		$columns['name'] = __('Name','em-pro');
		$columns['trigger'] = __('Trigger','em-pro');
		$columns['actions'] = __('Actions','em-pro');
		$columns['status'] = __('Status','em-pro');
		//$columns['ts'] = __('Last Run','em-pro');
		return $columns;
	}
	
	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(){
		return array(
			'name' => array('name', false),
			'type' => array('type', false),
		);
	}
	
	public function column_name( $item ){
		$title = '<strong>' . esc_html($item['name']) . '</strong>';
		$actions = array(
			'edit' => '<a href="'.esc_url( add_query_arg(array('view'=>'edit', 'automation_id'=> $item['id'], 'orderby'=>false, 'order'=>false)) ).'" class="edit">'. __('Edit','em-pro') .'</a>',
			'delete' => '<a href="'.esc_url( add_query_arg(array('action'=>'delete', 'automation_id'=> $item['id'], '_wpnonce'=> wp_create_nonce('em-automation-delete-'.$item['id']))) ).'" class="delete">'. __('Delete','em-pro') .'</a>',
		);
		if( empty($item['status']) ){
			$actions['activate'] = '<a href="'. esc_url( add_query_arg(array('action'=>'activate', 'automation_id'=> $item['id'], '_wpnonce'=> wp_create_nonce('em-automation-activate-'.$item['id']))) ) .'" class="activate">'. esc_html__('Activate','em-pro') .'</a>';
		}else{
			$actions['deactivate'] = '<a href="'. esc_url( add_query_arg(array('action'=>'deactivate', 'automation_id'=> $item['id'], '_wpnonce'=> wp_create_nonce('em-automation-deactivate-'.$item['id']))) ) .'" class="deactivate">'. esc_html__('Deactivate','em-pro') .'</a>';
		}
		return $title . $this->row_actions( $actions );
	}
	
	/**
	 * Bulk Edit Checkbox
	 * @param array $item
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf('<input type="checkbox" name="automation_id[]" value="%s" />', $item['id']);
	}
	
	/**
	 * Bulk Edit Checkbox
	 * @param array $item
	 * @return string
	 */
	function column_trigger( $item ) {
		$trigger = Automation::get_trigger($item['type']);
		if( $trigger ) {
			return $trigger::get_name();
		}else{
			return $item['type'];
		}
	}
	
	/**
	 * Bulk Edit Checkbox
	 * @param array $item
	 * @return string
	 */
	function column_actions( $item ) {
		$item['action_data'] = maybe_unserialize($item['action_data']);
		$actions = array();
		foreach( $item['action_data'] as $action_data ){
			$action = Automation::get_action($action_data['type']);
			if( $action ) {
				$actions[] = $action::get_name();
			}else{
				$actions[] = '<a class="em-tooltip" aria-label="'. esc_html__('This action does not exist anymore.', 'em-pro') . '"><span class="em-icon em-icon-warning"></span> ' . $action_data['type'] . '</span>';
			}
		}
		return implode(' + ', $actions);
	}
	
	/**
	 * Bulk Edit Checkbox
	 * @param array $item
	 * @return string
	 */
	function column_status( $item ) {
		return $item['status'] ? esc_html__('Active', 'em-pro') : esc_html__('Inactive', 'em-pro');
	}
	
	/**
	 * Returns an associative array of bulk actions
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __('Delete','em-pro'),
			'activate' => __('Activate','em-pro'),
			'deactivate' => __('Deactivate','em-pro'),
		);
	}
}
?>