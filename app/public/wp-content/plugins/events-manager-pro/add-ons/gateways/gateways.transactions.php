<?php

use EM\Archetypes;
use EM\Payments\Gateways, EM\Payments\Gateway;

if(!class_exists('EM_Gateways_Transactions')) {
class EM_Gateways_Transactions{
	var $limit = 20;
	var $total_transactions = 0;
	public $order;
	public $orderby;
	public $page;
	public $gateway;
	
	function __construct(){
		$this->order = ( !empty($_REQUEST ['order']) ) ? $_REQUEST ['order']:'ASC';
		$this->orderby = ( !empty($_REQUEST ['order']) ) ? $_REQUEST ['order']:'booking_name';
		$this->limit = ( !empty($_REQUEST['limit']) ) ? $_REQUEST['limit'] : 20;//Default limit
		$this->page = ( !empty($_REQUEST['pno']) ) ? $_REQUEST['pno']:1;
		$this->gateway = !empty($_REQUEST['gateway']) ? $_REQUEST['gateway']:false;
		//Add options and tables to EM admin pages
		if( current_user_can('manage_others_bookings') ){
			add_action('em_bookings_dashboard', array(&$this, 'output'),10,1);
			add_action('em_bookings_ticket_footer', array(&$this, 'output'),10,1);
			add_action('em_bookings_single_footer', array(&$this, 'output'),10,1);
			add_action('em_bookings_person_footer', array(&$this, 'output'),10,1);
			add_action('em_bookings_event_footer', array(&$this, 'output'),10,1);
		}
		//Booking Total Payments Hook
		add_filter('em_booking_get_total_paid', 'EM_Gateways_Transactions::get_total_paid', 10, 2);
		//Clean up of transactions when booking is deleted
		add_action('em_bookings_deleted', array(&$this, 'em_bookings_deleted'), 10, 2);
		//Booking Tables UI
		add_filter('em_bookings_table_rows_col', array(&$this,'em_bookings_table_rows_col'),10,5);
		add_filter('em_bookings_table_cols_template', array( static::class, 'em_bookings_table_cols_template' ),10,2 );
		add_filter('em_bookings_table_cols_template_groups', array( static::class, 'em_bookings_table_cols_template_groups' ),10,2 );
		add_action('wp_ajax_em_transactions_table', array(&$this, 'ajax'),10,1);
	}
	
	/**
	 * @param bool $result
	 * @param int[] $booking_ids
	 * @return bool
	 */
	public static function em_bookings_deleted($result, $booking_ids){
		if( $result && count($booking_ids) > 0 ){
			global $wpdb;
			foreach($booking_ids as $k => $v){ $booking_ids[$k] = absint($v); if( empty($booking_ids[$k]) ) unset($booking_ids[$k]); }
			$wpdb->query('DELETE FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id IN (".implode(',', $booking_ids).")");
		}
		return $result;
	}
	
	/**
	 * Returns the total paid for a specific booking. Hooks into em_booking_get_total_paid.
	 * @param EM_Booking $EM_Booking
	 * @return string|float
	 */
	public static function get_total_paid( $total, $EM_Booking ){
		global $wpdb;
		$total = $wpdb->get_var('SELECT SUM(transaction_total_amount) FROM '.EM_TRANSACTIONS_TABLE." WHERE booking_id={$EM_Booking->booking_id}");
		return $total;
	}
	
	function ajax(){
		if( wp_verify_nonce($_REQUEST['_wpnonce'],'em_transactions_table') ){
			//Get the context
			global $EM_Event, $EM_Booking, $EM_Ticket, $EM_Person;
			em_load_event();
			$context = false;
			if( !empty($_REQUEST['booking_id']) && is_object($EM_Booking) && $EM_Booking->can_manage('manage_bookings','manage_others_bookings') ){
				$context = $EM_Booking;
			}elseif( !empty($_REQUEST['event_id']) && is_object($EM_Event) && $EM_Event->can_manage('manage_bookings','manage_others_bookings') ){
				$context = $EM_Event;
			}elseif( !empty($_REQUEST['person_id']) && is_object($EM_Person) && current_user_can('manage_bookings') ){
				$context = $EM_Person;
			}elseif( !empty($_REQUEST['ticket_id']) && is_object($EM_Ticket) && $EM_Ticket->can_manage('manage_bookings','manage_others_bookings') ){
				$context = $EM_Ticket;
			}
			echo $this->mytransactions($context);
			exit;
		}
	}
	
	function output( $context = false ) {
		?>
		<div class="wrap">
		<h2><?php _e('Transactions','em-pro'); ?></h2>
			<?php
				$transactions = new \EM\List_Table\Transactions();
				$transactions->display();
			?>
		</div>
		<?php
	}
	
	/**
	 * @param mixed $context
	 * @return stdClass|false
	 */
	function get_transactions($context=false) {
		global $wpdb;
		$join = '';
		$conditions = array();
		$table = EM_BOOKINGS_TABLE;
		//we can determine what to search for, based on if certain variables are set.
		if( is_object($context) && $context instanceof EM_Booking && $context->can_manage('manage_bookings','manage_others_bookings') ){
			$booking_condition = "tx.booking_id = ".$context->booking_id;
			if( get_option('dbem_multiple_bookings') && $context->can_manage('manage_others_bookings') ){
				//in MB mode, if the user can manage others bookings, they can view information about the transaction for a group of bookings
				$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking($context);
				if( $EM_Multiple_Booking !== false ){
				    if( $context->booking_id != $EM_Multiple_Booking->booking_id){
				        //we're looking at a booking within a multiple booking, so we can show payments specific to this event too
                        $booking_condition = 'tx.booking_id IN ('.absint($EM_Multiple_Booking->booking_id).','.absint($context->booking_id).')';
				    }else{
				        //this is a MB booking, so we should show transactions related to the MB or any bookings within it
                        $booking_condition = "( $booking_condition OR tx.booking_id IN (SELECT booking_id FROM ".EM_BOOKINGS_RELATIONSHIPS_TABLE." WHERE booking_main_id={$EM_Multiple_Booking->booking_id}))";
				    }
				}
			}
			$conditions[] = $booking_condition;
		}elseif( is_object($context) && get_class($context)=="EM_Event" && $context->can_manage('manage_bookings','manage_others_bookings') ){
			$join = " JOIN $table ON $table.booking_id=tx.booking_id";
			$EM_Event = $context;

			if ( $EM_Event->is_recurring() ) {
				$booking_condition = $wpdb->prepare( "event_id IN ( SELECT event_id FROM ". EM_EVENTS_TABLE ." WHERE `recurrence_set_id` IN ( SELECT recurrence_set_id FROM ". EM_EVENT_RECURRENCES_TABLE ." WHERE event_id=%d ) AS RecurringEvents )", $EM_Event->get_event_id() );
			} else {
				$booking_condition = "event_id = ".$EM_Event->get_event_id();
			}
			if ( $EM_Event->is_timeslot() ) {
				$conditions[] = 'timeslot_id=' . absint($EM_Event->timeslot_id);
			}
			if( get_option('dbem_multiple_bookings') && $context->can_manage('manage_others_bookings') ){
				//in MB mode, if the user can manage others bookings, they can view information about the transaction for a group of bookings
				if ( $EM_Event->is_recurring() ) {
					$booking_condition = "( $booking_condition OR tx.booking_id IN (SELECT booking_main_id FROM ".EM_BOOKINGS_RELATIONSHIPS_TABLE." WHERE $booking_condition )";
				} else {
					$booking_condition = "( $booking_condition OR tx.booking_id IN (SELECT booking_main_id FROM ".EM_BOOKINGS_RELATIONSHIPS_TABLE." WHERE event_id={$EM_Event->get_event_id()}))";
				}
			}
			$conditions[] = $booking_condition;
		}elseif( is_object($context) && get_class($context)=="EM_Person" ){
			$join = " JOIN $table ON $table.booking_id=tx.booking_id";
			$conditions[] = "person_id = ".$context->ID;
		}elseif( is_object($context) && get_class($context)=="EM_Ticket" && $context->can_manage('manage_bookings','manage_others_bookings') ){
			$booking_ids = array();
			$EM_Ticket = $context;
			foreach( EM_Bookings::get( array('ticket_id' => $EM_Ticket->ticket_id, 'array' => 'booking_id') ) as $booking ){
				$booking_ids[] = $booking['booking_id'];
			}
			if( count($booking_ids) > 0 ){
				$conditions[] = "tx.booking_id IN (".implode(',', $booking_ids).")";
			}else{
				return new stdClass();
			}
		}
		if( EM_MS_GLOBAL && (!is_main_site() || is_admin()) ){ //if not main blog, we show only blog specific booking info
			global $blog_id;
			$join = " JOIN $table ON $table.booking_id=tx.booking_id";
			$archetype = Archetypes::get_current();
			$conditions['blog'] = "$table.booking_id IN (SELECT $table.booking_id FROM $table, ".EM_EVENTS_TABLE." e WHERE $table.event_id=e.event_id AND e.blog_id=".$blog_id." AND e.event_archetype='$archetype')";
		}
		//filter by gateway
		if( !empty($this->gateway) ){
			$conditions[] = $wpdb->prepare('transaction_gateway = %s',$this->gateway);
		}
		// add archetype support if not added to blog
		if ( !empty($conditions['blog']) ) {
			$join = " JOIN $table ON $table.booking_id=tx.booking_id";
			$archetype = Archetypes::get_current();
			$conditions['archetype'] = "tx.booking_id IN (SELECT $table.booking_id FROM $table, ".EM_EVENTS_TABLE." e WHERE $table.event_id=e.event_id AND e.event_archetype='$archetype')";
		}
		//build conditions string
		$condition = (!empty($conditions)) ? "WHERE ".implode(' AND ', $conditions):'';
		$offset = ( $this->page > 1 ) ? ($this->page-1)*$this->limit : 0;
		$sql = $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM ".EM_TRANSACTIONS_TABLE." tx $join $condition ORDER BY transaction_id DESC  LIMIT %d, %d", $offset, $this->limit );
		$return = $wpdb->get_results( $sql );
		$this->total_transactions = $wpdb->get_var( "SELECT FOUND_ROWS();" );
		return $return;
	}

	
	/*
	 * ----------------------------------------------------------
	 * Booking Table and CSV Export
	 * ----------------------------------------------------------
	 */
	
	function em_bookings_table_rows_col($value, $col, $EM_Booking, $EM_Bookings_Table, $format){
		if( $col == 'gateway_txn_id' || $col === 'transaction_payment_type' || $col === 'transaction_payment_type_detail' ) {
			//check if this isn't a multiple booking, otherwise look for info from main booking
			if( get_option('dbem_multiple_bookings') ){
				$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking($EM_Booking);
				if( $EM_Multiple_Booking !== false ){
					$EM_Booking = $EM_Multiple_Booking;
				}
			}
			//get latest transaction with an ID
			$old_limit = $this->limit;
			$old_orderby = $this->orderby;
			$old_page = $this->page;
			$this->limit = $this->page = 1;
			$this->orderby = 'booking_date';
			$transactions = $this->get_transactions($EM_Booking);
			if(count($transactions) > 0){
				if( $col === 'gateway_txn_id' ) {
					$value = $transactions[0]->transaction_gateway_id;
				}else {
					$value = $transactions[0]->$col;
				}
			}
			$this->limit = $old_limit;
			$this->orderby = $old_orderby;
			$this->page = $old_page;
			$value = apply_filters('em_gateways_transactions_table_'.$col, $value, $transactions, $EM_Booking, $EM_Bookings_Table, $format);
		} elseif ( $col === 'payment_total' ) {
			$value = $EM_Booking->get_total_paid(true);
			$value = apply_filters('em_gateways_transactions_table_'.$col, $value, array(), $EM_Booking, $EM_Bookings_Table, $format);
		}
		return $value;
	}
	
	public static function em_bookings_table_cols_template( $template, $EM_Bookings_Table ){
		if( get_option('dbem_multiple_bookings') && $EM_Bookings_Table->view !== 'multiple-bookings' ){
			$template['gateway_txn_id'] = '[MB] '. __('Transaction ID','em-pro');
			$template['transaction_payment_type'] = '[MB] '. __('Payment Method','em-pro');
			$template['transaction_payment_type_detail'] = '[MB] '. __('Payment Method Info','em-pro');
		}else{
			$template['gateway_txn_id'] = __('Transaction ID','em-pro');
			$template['transaction_payment_type'] = __('Payment Method','em-pro');
			$template['transaction_payment_type_detail'] = __('Payment Method Info','em-pro');
		}
		$template['payment_total'] = __('Total Paid','em-pro');
		return $template;
	}
	
	public static function em_bookings_table_cols_template_groups( $template_groups, $EM_Bookings_Table ) {
		$template_groups['payment']['fields'] = array_merge( $template_groups['payment']['fields'], array_keys( static::em_bookings_table_cols_template([], $EM_Bookings_Table) ) );
		return $template_groups;
	}
}
}

/**
 * Checks for any deletions requested
 */
function emp_transactions_init(){
	global $EM_Gateways_Transactions;
	$EM_Gateways_Transactions = new EM_Gateways_Transactions();
	
	if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'transaction_delete' && wp_verify_nonce($_REQUEST['_wpnonce'], 'transaction_delete_'.$_REQUEST['txn_id'].'_'.get_current_user_id()) ){
		//get booking from transaction, ensure user can manage it before deleting
		global $wpdb;
		$booking_id = $wpdb->get_var('SELECT booking_id FROM '.EM_TRANSACTIONS_TABLE." WHERE transaction_id='".$_REQUEST['txn_id']."'");
		if( !empty($booking_id) ){
			$EM_Booking = em_get_booking($booking_id);
			if( (!empty($EM_Booking->booking_id) && $EM_Booking->can_manage()) || is_super_admin() ){
				//all good, delete it
				$wpdb->query('DELETE FROM '.EM_TRANSACTIONS_TABLE." WHERE transaction_id='".$_REQUEST['txn_id']."'");
				_e('Transaction deleted','em-pro');
				exit();
			}
		}
		_e('Transaction could not be deleted', 'em-pro');
		exit();
	}
}
add_action('init','emp_transactions_init');