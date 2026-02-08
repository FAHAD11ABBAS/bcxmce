<?php
namespace EM\List_Table;
use EM\Archetypes;
use EM\List_Table;
use EM\Payments\Gateways, EM_DateTime, EM_Events;
use EM_Bookings;
use EM_Event, EM_Booking, EM_Multiple_Bookings, EM_Ticket;
use stdClass;

// TODO ordery by event name and person name
// TOOD search by event name and person name
class Transactions extends List_Table {
	
	public static $basename = 'em_transactions_table';
	public $cols = array('event_name', 'user_name', 'transaction_total_amount', 'transaction_timestamp', 'transaction_gateway_id', 'transaction_gateway', 'transaction_status', 'transaction_note');
	public $checkbox_id = 'transaction_id';
	public static $cols_allowed_html = array('event_name', 'user_name');
	public static $filter_vars = array( 'search' => 'em_search', 'gateway', 'currency' );
	public static $show_filters = false;
	
	public $booking;
	public $person;
	public $ticket;
	public $event;
	
	function load_columns() {
		$this->cols_template = apply_filters('em_events_bookings_table_cols_template', array(
			'user_name' => emp__('Name'),
			'event_name' => emp__('Event Name'),
			'transaction_id' => __('Transaction ID', 'em-pro'),
			'booking_id' => emp__('Booking ID'),
			'transaction_gateway_id' => __('Gateway ID', 'em-pro'),
			'transaction_payment_type' => __('Payment Method', 'em-pro'),
			'transaction_timestamp' => __('Timestamp', 'em-pro'),
			'transaction_total_amount' => emp__('Total Amount'),
			'transaction_currency' => emp__('Currency'),
			'transaction_status' => emp__('Status'),
			'transaction_duedate' => __('Due Date', 'em-pro'),
			'transaction_gateway' => __('Gateway', 'em-pro'),
			'transaction_note' => __('Note', 'em-pro'),
			'transaction_expires' => __('Expires', 'em-pro'),
			'transaction_payment_type_detail' => __('Payment Method Info', 'em-pro'),
		), $this);
	}
	
	public function load_current_context() {
		if( empty($this->context) ) {
			// load collumn context settings
			if( !empty($_REQUEST['booking_id']) ) {
				$this->context = 'EM_Booking';
			} elseif ( $this->get_person() !== false ) {
				$this->context = 'EM_Person';
			} elseif ( $this->get_ticket() !== false ) {
				$this->context = 'EM_Ticket';
			} elseif ( $this->get_event() !== false ) {
				$this->context = 'EM_Event';
			}
		}
		return parent::load_current_context();
	}
	
	public static function em_ajax_table_row_action( $requested_action ) {
		if( $requested_action === 'delete' ){
			//get booking from transaction, ensure user can manage it before deleting
			global $wpdb;
			$booking_id = $wpdb->get_var('SELECT booking_id FROM '.EM_TRANSACTIONS_TABLE." WHERE transaction_id='".$_REQUEST['row_id']."'");
			if( !empty($booking_id) ){
				$EM_Booking = em_get_booking($booking_id);
				if( (!empty($EM_Booking->booking_id) && $EM_Booking->can_manage()) || is_super_admin() ){
					//all good, delete it
					$result = $wpdb->query('DELETE FROM '.EM_TRANSACTIONS_TABLE." WHERE transaction_id='".$_REQUEST['row_id']."'");
					if ( $result > 0 ) {
						echo '<span class="em-icon em-icon-trash em-tooltip" aria-label="' . sprintf( emp__( '%s deleted' ), __( 'Transaction', 'em-pro' ) ) . '"></span>';
						exit();
					}
				}
			}
			echo '<span class="em-icon em-icon-error em-tooltip" aria-label="'. esc_html__('Transaction could not be deleted', 'em-pro') .'"></span>';
			exit();
		}
	}
	
	/**
	 * @return \EM_Person|false
	 */
	function get_person(){
		global $EM_Person;
		if( !empty($this->person) && is_object($this->person) ){
			return $this->person;
		}elseif( !empty($_REQUEST['person_id']) && !empty($EM_Person) && is_object($EM_Person) ){
			return $EM_Person;
		}elseif( !empty($_REQUEST['person_id']) ){
			return new \EM_Person( $_REQUEST['person_id'] );
		}
		return false;
	}
	/**
	 * @return \EM_Ticket|false
	 */
	function get_ticket(){
		global $EM_Ticket;
		if( !empty($this->ticket) && is_object($this->ticket) ){
			return $this->ticket;
		}elseif( !empty($EM_Ticket) && is_object($EM_Ticket) ){
			return $EM_Ticket;
		} elseif( !empty($_REQUEST['ticket_id']) ) {
			return new EM_Ticket( $_REQUEST['ticket_id'] );
		}
		return false;
	}
	/**
	 * @return EM_Event|false
	 */
	function get_event(){
		global $EM_Event;
		if( !empty($this->event) && is_object($this->event) ){
			return $this->event;
		}elseif( !empty($EM_Event) && is_object($EM_Event) ){
			return $EM_Event;
		} elseif( !empty($_REQUEST['event_id']) ) {
			return em_get_event( $_REQUEST['event_id'] );
		}
		return false;
	}
	/**
	 * @return \EM_Booking|false
	 */
	function get_booking(){
		global $EM_Booking;
		if( !empty($this->booking) && is_object($this->booking) ){
			return $this->booking;
		}elseif( !empty($EM_Booking) && is_object($EM_Booking) ){
			return $EM_Booking;
		} elseif( !empty($_REQUEST['booking_id']) ) {
			return em_get_booking( $_REQUEST['booking_id'] );
		}
		return false;
	}
	
	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(){
		$fields = EM_Events::get_sql_accepted_fields();
		$sortable_cols = array(
			//'event_name' => array('event_name', false),
			'transaction_id' => array('transaction_id', false),
			'booking_id' => array('booking_id', false),
			'transaction_gateway_id' => array('transaction_gateway_id', false),
			'transaction_payment_type' => array('transaction_payment_type', false),
			'transaction_timestamp' => array('transaction_timestamp', false),
			'transaction_total_amount' => array('transaction_total_amount', false),
			'transaction_currency' => array('transaction_currency', false),
			'transaction_status' => array('transaction_status', false),
			'transaction_duedate' => array('transaction_duedate', false),
			'transaction_gateway' => array('transaction_gateway', false),
			'transaction_note' => array('transaction_note', false),
			'transaction_expires' => array('transaction_expires', false),
			'transaction_payment_type_detail' => array('transaction_payment_type_detail', false),
		);
		foreach( $fields as $field => $col ){
			if( empty($sortable_cols[$field]) ) {
				$sortable_cols[ $field ] = array( $field, false );
			}
		}
		// some specific fields that still map
		$sortable_cols['event_date'] = array('event_start', false);
		return apply_filters('em_events_bookings_table_get_sortable_columns', $sortable_cols, $this);
	}

	
	function get_items(){
		global $wpdb;
		$joins = [];
		$conditions = array();
		$table = EM_BOOKINGS_TABLE;
		//we can determine what to search for, based on if certain variables are set.
		if( $this->get_booking() !== false && $this->get_booking()->can_manage('manage_bookings','manage_others_bookings') ){
			$EM_Booking = $this->get_booking();
			$booking_condition = "tx.booking_id = ".$EM_Booking->booking_id;
			if( get_option('dbem_multiple_bookings') && $EM_Booking->can_manage('manage_others_bookings') ){
				//in MB mode, if the user can manage others bookings, they can view information about the transaction for a group of bookings
				$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking($EM_Booking);
				if( $EM_Multiple_Booking !== false ){
					if( $EM_Booking->booking_id != $EM_Multiple_Booking->booking_id){
						//we're looking at a booking within a multiple booking, so we can show payments specific to this event too
						$booking_condition = 'tx.booking_id IN ('.absint($EM_Multiple_Booking->booking_id).','.absint($EM_Booking->booking_id).')';
					}else{
						//this is a MB booking, so we should show transactions related to the MB or any bookings within it
						$booking_condition = "( $booking_condition OR tx.booking_id IN (SELECT booking_id FROM ".EM_BOOKINGS_RELATIONSHIPS_TABLE." WHERE booking_main_id={$EM_Multiple_Booking->booking_id}))";
					}
				}
			}
			$conditions[] = $booking_condition;
		}elseif( $this->get_event() && $this->get_event()->can_manage('manage_bookings','manage_others_bookings') ){
			$joins[$table] = " JOIN $table ON $table.booking_id=tx.booking_id";
			$EM_Event = $this->get_event();
			if ( $EM_Event->is_recurring() ) {
				$booking_condition = $wpdb->prepare( "event_id IN ( SELECT event_id FROM ". EM_EVENTS_TABLE ." WHERE `recurrence_set_id` IN ( SELECT recurrence_set_id FROM ". EM_EVENT_RECURRENCES_TABLE ." WHERE event_id=%d ) )", $EM_Event->get_event_id() );
			} else {
				$booking_condition = "event_id = ".$EM_Event->get_event_id();
			}
			if ( $EM_Event->is_timeslot() ) {
				$conditions[] = 'timeslot_id=' . absint($EM_Event->timeslot_id);
			}
			if( get_option('dbem_multiple_bookings') && $EM_Event->can_manage('manage_others_bookings') ){
				//in MB mode, if the user can manage others bookings, they can view information about the transaction for a group of bookings
				$booking_condition = "( $booking_condition OR tx.booking_id IN (SELECT booking_main_id FROM ".EM_BOOKINGS_RELATIONSHIPS_TABLE." WHERE $booking_condition )";
			}
			$conditions[] = $booking_condition;
		}elseif( $this->get_person() ){
			$joins[$table] = " JOIN $table ON $table.booking_id=tx.booking_id";
			$conditions[] = "person_id = ".$this->get_person()->ID;
		}elseif( $this->get_ticket() && $this->get_ticket()->can_manage('manage_bookings','manage_others_bookings') ){
			$booking_ids = array();
			foreach( EM_Bookings::get( array('ticket_id' => $this->get_ticket()->ticket_id, 'array' => 'booking_id') ) as $booking ){
				$booking_ids[] = $booking['booking_id'];
			}
			if( count($booking_ids) > 0 ){
				$conditions[] = "tx.booking_id IN (".implode(',', $booking_ids).")";
			}else{
				return new stdClass();
			}
		}
		$archetype_sql = '';
		if ( Archetypes::$types ) {
			$archetype = Archetypes::get_current();
			$archetype_sql = $wpdb->prepare( " AND event_archetype=%s", $archetype );
		}
		if( EM_MS_GLOBAL && (!is_main_site() || is_admin()) ){ //if not main blog, we show only blog specific booking info
			global $blog_id;
			$joins[$table] = " JOIN $table ON $table.booking_id=tx.booking_id";
			$conditions['ms_global'] = "$table.booking_id IN (SELECT $table.booking_id FROM $table, ".EM_EVENTS_TABLE." e WHERE $table.event_id=e.event_id AND e.blog_id=".$blog_id." $archetype_sql)";
		} elseif ( $archetype_sql ) {
			$conditions['archetypes'] = "tx.booking_id IN (SELECT $table.booking_id FROM $table, ".EM_EVENTS_TABLE." e WHERE $table.event_id=e.event_id $archetype_sql)";
		}
		//filter by gateway
		if( !empty($this->filters['gateway']) ){
			$conditions[] = $wpdb->prepare('transaction_gateway = %s',$this->filters['gateway']);
		}
		//build conditions string
		if( !empty($this->filters['search']) ) {
			$conditions[] = $wpdb->prepare( "(tx.transaction_id = %d OR tx.transaction_gateway_id = %s) ", $this->filters['search'], $this->filters['search'] );
		}
		$offset = ( $this->page > 1 ) ? ($this->page-1)*$this->limit : 0;
		
		// order by
		$sortable_columns = $this->get_sortable_columns();
		$orderby = !empty($_REQUEST['orderby']) && !empty($sortable_columns[$_REQUEST['orderby']]) ? $_REQUEST['orderby'] : 'transaction_timestamp';
		$order = !empty($_REQUEST['order']) && strtoupper($_REQUEST['order']) === 'DESC' ? 'DESC':'ASC';
		
		$sql_parts = apply_filters('em_gateways_transactions_table_sql_parts', ['joins' => $joins, 'conditions' => $conditions, 'orderby' => $orderby, 'order' => $order], $this);
		$condition = (!empty($sql_parts['conditions'])) ? "WHERE ".implode(' AND ', $sql_parts['conditions']):'';
		$join =  (!empty($sql_parts['joins'])) ? implode(' ', $sql_parts['joins']) : '';
		
		// prepare and execute statement
		$sql = $wpdb->prepare( "SELECT *, transaction_id AS id FROM ".EM_TRANSACTIONS_TABLE." tx {$join} {$condition} ORDER BY {$sql_parts['orderby']} {$sql_parts['order']}  LIMIT %d, %d", $offset, $this->limit );
		$return = $wpdb->get_results( $sql );
		$this->total_items = $wpdb->get_var( "SELECT COUNT(transaction_id) FROM ".EM_TRANSACTIONS_TABLE." tx {$join} $condition" );
		return $return;
	}
	
	public function extra_tablenav( $which ) {
		if ( $which != 'top' ) {
			parent::extra_tablenav( $which );
			return null;
		}
		$id = esc_attr($this->id);
		?>
		<div class="alignleft actions filters em-list-table-filters <?php echo $id; ?>-filters <?php if ( !static::$show_filters ) echo 'hidden'; ?>">
			<input name="em_search" type="text" class="inline <?php echo $id; ?>-filter" placeholder="<?php esc_attr_e('Search Transaction ID', 'em-pro'); ?> ..." value="<?php echo esc_attr($this->filters['search']);?>">
			<select name="gateway">
				<option value="">All</option>
				<?php
					global $EM_Gateways;
					foreach ( Gateways::list() as $Gateway ) {
						?><option value='<?php echo $Gateway::$gateway ?>' <?php if($Gateway::$gateway == $this->filters['gateway']) echo "selected='selected'"; ?>><?php echo esc_html($Gateway::$title); ?></option><?php
					}
				?>
			</select>
			<?php do_action('em_events_bookings_table_output_table_filters', $this); ?>
			<input name="pno" type="hidden" value="1">
			<input id="post-query-submit" class="button-secondary" type="submit" value="<?php esc_attr_e( 'Filter' ); ?>">
		</div>
		<?php parent::extra_tablenav( $which ); ?>
		<?php
	}
	
	// convert the print_transactions function to a column_default overriding method
	public function default_column_data($item, $column_name) {
		
		$EM_Booking = em_get_booking($item->booking_id);
		
		switch ($column_name) {
			case 'transaction_id':
				return $item->transaction_id;
			case 'booking_id':
				if( !empty($item->booking_id) ){
					$EM_Booking = em_get_booking($item->booking_id);
					if($EM_Booking){
						return '<a href="' . esc_url(add_query_arg(array('page'=>'events-manager-bookings', 'booking_id'=>$item->booking_id))) . '">' . $item->booking_id . '</a>';
					}
				}
				return $item->booking_id;
			case 'transaction_gateway_id':
				$item_gateway_id = apply_filters('em_gateways_transactions_table_gateway_id', $item->transaction_gateway_id, $item, $EM_Booking);
				//use the below filter to override specific gateways, the above for modifying the field for all gateways
				return apply_filters('em_gateways_transactions_table_gateway_id_'.$item->transaction_gateway, $item_gateway_id, $item, $EM_Booking);
			case 'transaction_payment_type':
				return $item->transaction_payment_type;
			case 'transaction_timestamp':
				$EM_DateTime = new EM_DateTime($item->transaction_timestamp, 'UTC');
				$EM_DateTime->setTimezone();
				return $EM_DateTime->i18n( 'Y-m-d H:i T' );
			case 'transaction_total_amount':
				$amount = $item->transaction_total_amount;
				return em_get_currency_formatted($amount, $item->transaction_currency);
			case 'transaction_currency':
				return $item->transaction_currency;
			case 'transaction_status':
				return $item->transaction_status;
			case 'transaction_duedate':
				return $item->transaction_duedate;
			case 'transaction_gateway':
				return $item->transaction_gateway;
			case 'transaction_note':
				return $item->transaction_note;
			case 'transaction_expires':
				return $item->transaction_expires;
			case 'transaction_payment_type_detail':
				return $item->transaction_payment_type_detail;
			case 'event_name':
				if (!empty($item->booking_id)) {
					$EM_Booking = em_get_booking($item->booking_id);
					if ($EM_Booking) {
						ob_start();
						if (!empty($EM_Booking->booking_meta['test'])) {
							echo '<span class="em-icon em-icon-warning em-tooltip" aria-label="' . esc_html__('Test Mode', 'em-pro') . '"></span>';
						}
						if (get_class($EM_Booking) == 'EM_Multiple_Booking') {
							$link = em_add_get_params($EM_Booking->get_admin_url(), array('booking_id' => $EM_Booking->booking_id, 'em_ajax' => null, 'em_obj' => null));
							echo '<a href="' . $link . '">' . $EM_Booking->get_event()->event_name . '</a>';
						} else {
							echo '<a href="' . $EM_Booking->get_event()->get_bookings_url() . '">' . $EM_Booking->get_event()->event_name . '</a>';
						}
						return ob_get_clean();
					}
				}
				return '';
			case 'user_name':
				if (!empty($item->booking_id)) {
					$EM_Booking = em_get_booking($item->booking_id);
					if ($EM_Booking) {
						return '<a href="' . $EM_Booking->get_person()->get_bookings_url() . '">' . $EM_Booking->person->get_name() . '</a>';
					}
				}
				return '';
		}
	}
	
	public function display_attributes() {
		$attributes = array(
			'data-action-delete' => __('Are you sure you want to delete? This may make your transaction history out of sync with your payment gateway provider.', 'em-pro'),
		);
		return apply_filters(static::$basename . '_display_attributes', $attributes, $this);
	}
	
	public function get_bulk_actions () {
		$bulk_actions = parent::get_bulk_actions();
		$bulk_actions['delete']['data']['confirm'] = __('Are you sure you want to delete? This may make your transaction history out of sync with your payment gateway provider.', 'em-pro');
		return $bulk_actions;
	}
	
	/**
	 * Generate an array of HTML links consisting of booking actions, this can be a multi-level array, which will split into sections if supplied to output_action_links())
	 * @param $EM_Booking
	 *
	 * @return mixed|null
	 */
	public function get_row_actions ( $EM_Booking ) {
		$booking_actions = $this->get_action_links( $EM_Booking );
		$booking_actions['edit'] = array(
			'actions' => [
				'edit' => '<a class="em-list-table-row-edit" href="'.em_add_get_params($EM_Booking->get_event()->get_bookings_url(), array('booking_id'=>$EM_Booking->booking_id)).'">' . sprintf(emp__('Edit/View %s'), emp__('Booking')) . '</a>',
			],
		);
		$booking_actions = apply_filters( static::$basename . '_booking_actions_'.$EM_Booking->booking_status , $booking_actions, $EM_Booking);
		return apply_filters( static::$basename . '_cols_col_action', $booking_actions, $EM_Booking);
	}
	
	/**
	 * @param \stdClass $item
	 *
	 * @return string
	 */
	public function column_cb( $item ){
		$html = sprintf('<input type="checkbox" name="column_id[]" value="%d" />', $item->transaction_id);
		if( defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['row_action']) && $_REQUEST['row_action'] == 'bookings_delete' ){
			// booking deleted, no editing/actions possible
			return $html;
		}
		$EM_Booking = em_get_booking($item->booking_id);
		$edit_url = em_add_get_params($EM_Booking->get_event()->get_bookings_url(), array('booking_id'=>$EM_Booking->booking_id, 'em_ajax'=>null, 'em_obj'=>null));
		ob_start();
		?>
		<button type="button" class="em-list-table-actions em-tooltip-ddm em-clickable" data-tooltip-class="em-list-table-actions-tooltip" title="<?php esc_attr_e('Booking Actions', 'events-manager'); ?>">...</button>
		<div class="em-tooltip-ddm-content em-bookings-admin-get-invoice-content">
			<?php echo $this->output_action_links( $this->get_row_actions($EM_Booking) ); ?>
		</div>
		<div class="em-loader"></div>
		<?php
		$html .= ob_get_clean();
		return $html;
	}
	
	public function primary_column_responsive_meta( $item, $column_name ) {
		return em_get_currency_formatted($item->transaction_total_amount, $item->transaction_currency) . ' - ' . $item->transaction_status;
	}
}
Transactions::init();
?>
