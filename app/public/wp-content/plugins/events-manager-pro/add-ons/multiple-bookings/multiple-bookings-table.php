<?php
namespace EM\List_Table;

use EM_Booking_Form, EM_Bookings, EM_Multiple_Booking, EM_Multiple_Bookings;
use EM_Bookings_Table;

class Multiple_Bookings_Table {
	
	use Forms;
	
	static $form_meta_key = 'booking-form';
	public static $default_form_option = 'dbem_multiple_bookings_form';
	public static $booking_table_prefix = 'booking_meta_';
	
	public static function init() {
		static::init_booking_table_hooks();
		//booking table and csv filters
		add_filter('em_bookings_table_rows_col', [ static::class, 'em_bookings_table_rows_col' ],100,6);
		add_filter('em_bookings_table_cols_template_groups', [ static::class, 'em_bookings_table_cols_template_groups' ],100,6);
		add_filter('em_bookings_table_cols_template', [ static::class, 'em_bookings_table_cols_template' ],100,2);
		add_filter('em_bookings_table_views', [ static::class, 'em_bookings_table_views' ], 10, 2 );
		add_filter('em_bookings_table_get_items', [ static::class, 'em_bookings_table_get_items' ], 10, 3 );
		add_filter('em_bookings_table_get_action_message', [ static::class, 'em_bookings_table_get_action_message' ], 10, 5 );
		add_filter('em_bookings_table_get_bulk_action_message', [ static::class, 'em_bookings_table_get_bulk_action_message' ], 10, 5 );
		// search hooks for EM_Bookings, so we can search with multiple_bookings
		add_filter('em_bookings_get_default_search', [ static::class, 'em_bookings_get_default_search' ], 10, 3 );
		add_filter('em_bookings_build_sql_conditions', [ static::class, 'em_bookings_build_sql_conditions' ], 10, 2 );
		add_filter('em_bookings_get_sql', [ static::class, 'em_bookings_get_sql' ], 10, 3 );
	}
	
	/**
	 * Get MB booking form
	 * @return array
	 */
	public static function get_all_forms_data() {
		global $wpdb;
		$form_data_id = get_option('dbem_multiple_bookings_form'); // not like bookings form object with name etc., just a straight-up fields array
		$form_data = $wpdb->get_var('SELECT meta_value FROM ' . EM_META_TABLE . ' WHERE meta_id = ' . absint($form_data_id) );
		if( $form_data ) $form_data = maybe_unserialize( $form_data );
		return [ $form_data_id => $form_data['form'] ?? [] ];
	}
	
	/**
	 * Gets MB booking form, irrespective of context etc.
	 * @return \EM_Form
	 */
	public static function get_bookings_table_form( $EM_Bookings_Table ) {
		return static::get_all_fields_form();
	}
	
	/**
	 * @param $value
	 * @param $col
	 * @param \EM_Booking|\EM_Ticket_Booking|\EM_Ticket_Bookings $EM_Object
	 * @param \EM_Bookings_Table $EM_Bookings_Table
	 * @param $format
	 *
	 * @return false|float|int|mixed|string|null
	 */
	public static function em_bookings_table_rows_col( $value, $col, $EM_Object, $EM_Bookings_Table, $format ){
		if( $EM_Object instanceof EM_Multiple_Booking ){
			$EM_Multiple_Booking = $EM_Object;
			// we want to override some values with either multiple values or a single value if available for sub-events
			// we will skip certian values, including certain core fields and booking form fields belonging to the MB booking
			$booking_col_templates = ['cols_tickets_template', 'cols_attendees_template', 'cols_events_template'];
			foreach ( $booking_col_templates as $booking_col_template ) {
				if( array_key_exists( $col, $EM_Bookings_Table->{$booking_col_template}) ) {
					$booking_col = true;
				}
			}
			$booking_meta_cols = EM_Booking_Form::get_bookings_table_fields($EM_Bookings_Table);
			$mb_meta_cols = static::get_all_fields_form()->form_fields;
			$booking_meta_cols = array_diff( array_keys($booking_meta_cols), array_keys($mb_meta_cols) );
			if( !empty($booking_col) || in_array($col, $booking_meta_cols) ) {
				// this is an event-specific col, so what we do is figure out if we have multiple bookings, or a single booking
				$bookings = $EM_Multiple_Booking->get_bookings();
				if( count($bookings) == 1 ) {
					$EM_Booking = current($bookings);
					$value = $EM_Bookings_Table->default_column_data($EM_Booking, $col);
				} else {
					$values = [];
					foreach( $bookings as $EM_Booking ) {
						$heading = '<a href="'. $EM_Booking->get_admin_url() .'">'. sprintf(esc_html__('Booking ID #%s', 'em-pro'), $EM_Booking->booking_id) . '</a>';
						$booking_value = $EM_Bookings_Table->default_column_data($EM_Booking, $col);
						if( $booking_value ) {
							$values[ $heading ] = $EM_Bookings_Table->default_column_data( $EM_Booking, $col );
						}
					}
					if( !empty($values) ) {
						EM_Bookings_Table::$cols_allowed_html[ $col ] = true;
						// determine the type for a placeholder
						$placeholder = null;
						if ( preg_match( '/^event_/', $col ) ) {
							$placeholder = __( '%d Events', 'em-pro' );
						} elseif ( preg_match( '/^ticket_/', $col ) ) {
							$placeholder = sprintf( __( '%d Tickets', 'em-pro' ), $EM_Multiple_Booking->get_tickets_bookings()->count() );
						} elseif ( preg_match( '/^attendee_/', $col ) ) {
							$placeholder = sprintf( __( '%d Attendees', 'em-pro' ), $EM_Multiple_Booking->get_spaces() );
						}
						$value = static::get_multiple_col( $values, $col, $EM_Multiple_Booking, $EM_Bookings_Table, $placeholder );
					}
				}
			}
		} else {
			//is this part of a multiple booking?
			extract( $EM_Bookings_Table->get_item_objects($EM_Object) ); /* @var \EM_Ticket $EM_Ticket *//* @var \EM_Ticket_Booking $EM_Ticket_Booking *//* @var \EM_Ticket_Bookings $EM_Ticket_Bookings *//* @var \EM_Booking $EM_Booking */
			$EM_Multiple_Booking = EM_Multiple_Bookings::get_main_booking( $EM_Object );
			if( $EM_Multiple_Booking !== false ){
				if( $col == 'payment_total' ){
					$value = $EM_Multiple_Booking->get_total_paid(true);
					if( $format == 'html' ) $value = '<a href="'.$EM_Multiple_Booking->get_admin_url().'">'.$value.'</a>';
				}elseif( $col == 'booking_price' ){
					$value = $EM_Multiple_Booking->get_booking_price($EM_Booking);
					if( $format == 'html' && $EM_Booking->get_price(true) != $value ) $value .= '*'; //add asterisk if MB price had an adjustment
				}elseif( $col == 'mb_booking_price' ){
					$value = $EM_Multiple_Booking->get_price(true);
					if( $format == 'html' ) $value = '<a href="'.$EM_Multiple_Booking->get_admin_url().'">'.$value.'</a>';
				}else{
					if( preg_match('/^mb_/', $col) ){
						$col = preg_replace('/^mb_/', '', $col);
						$EM_Form = EM_Booking_Form::get_form(false, get_option('dbem_multiple_bookings_form'));
						if( array_key_exists($col, $EM_Form->form_fields) ){
							$field = $EM_Form->form_fields[$col];
							if( isset($EM_Multiple_Booking->booking_meta['booking'][$col]) ){
								$value = $EM_Form->get_formatted_value($field, $EM_Multiple_Booking->booking_meta['booking'][$col]);
							}
						}
					}
				}
			}else{
				if( $col == 'mb_booking_price' ){
					$value = '-';
				}
			}
			if( $col == 'booking_price_gross' ){
				// this is the price without discounts/surcharges factored in
				$value = $EM_Booking->get_price(true);
			}
		}
		return $value;
	}
	
	/**
	 * Handles how to show multiple items of a booking when viewing in MB mode.
	 * @param $values
	 * @param $col
	 * @param EM_Multiple_Booking $EM_Multiple_Booking
	 *
	 * @return false|string
	 */
	public static function get_multiple_col( $values, $col, $EM_Multiple_Booking, $EM_Bookings_Table, $placeholder = null ){
		$placeholder = $placeholder ?? emp__( '%d Bookings');
		ob_start();
		if( !in_array( $EM_Bookings_Table->format, ['csv', 'xls', 'xlsx'] ) ){
			$id = $EM_Bookings_Table->uid . '-col-tickets-tooltip-content-' . $EM_Multiple_Booking->booking_id . '-' . $col;
			?>
			<section class="em-list-table-col-tooltip em-bookings-table-tickets-tooltip">
				<a class="em-tooltip" data-content="#<?php echo esc_attr($id); ?>" data-tippy-interactive="true">
					<?php echo sprintf(esc_html($placeholder), count($values)); ?>
				</a>
				<aside class="em-tooltip-content hidden" id="<?php echo esc_attr($id); ?>" data-type="ticket">
					<section>
						<?php foreach( $values as $heading => $data ): ?>
							<header class="title">
								<?php echo $heading; ?>
							</header>
							<div class="general-data">
								<?php echo $data; ?>
							</div>
						<?php endforeach; ?>
					</section>
				</aside>
			</section>
			<?php
			return ob_get_clean();
		}
		return $value;
	}
	
	/**
	 * Add MB columns if we're not in MB view, since they're the same columns as a regular booking column
	 * @param $template
	 * @param $EM_Bookings_Table
	 *
	 * @return mixed
	 */
	public static function em_bookings_table_cols_template($template, $EM_Bookings_Table){
		$prefix = 'booking_meta_';
		$label_prefix = '';
		if ( $EM_Bookings_Table->view !== 'multiple-bookings' ) {
			$label_prefix = '[MB] ';
			if( !empty($EM_Bookings_Table->cols_bookings_template['payment_total']) ) $template ['payment_total'] = $label_prefix . $EM_Bookings_Table->cols_bookings_template['payment_total'];
			if( !empty($EM_Bookings_Table->cols_bookings_template['booking_price']) ) $template ['mb_booking_price'] = $label_prefix . $EM_Bookings_Table->cols_bookings_template['booking_price'];
			$template ['booking_price_gross'] = $label_prefix . __( 'Total (Gross)', 'em-pro' );
			$prefix = 'mb_';
		}
		$EM_Form = EM_Booking_Form::get_form(false, get_option('dbem_multiple_bookings_form'));
		foreach($EM_Form->form_fields as $field_id => $field ) {
			if ( $EM_Form->is_normal_field( $field ) ) { //user fields already handled, htmls shouldn't show
				//prefix MB fields with mb_ to avoid clashes with normal booking forms
				$field = $EM_Form->translate_field( $field );
				$template[ $prefix . $field_id ] = $label_prefix . $field['label'];
			}
		}
		return $template;
	}
	
	public static function em_bookings_table_cols_template_groups ( $template_groups, $EM_Bookings_Table) {
		$template_groups['multiple_booking'] = [
			'label' => __('Multiple Bookings Data','events-manager'),
			'fields' => array_keys( static::em_bookings_table_cols_template([], $EM_Bookings_Table) ),
		];
		return $template_groups;
	}
	
	public static function em_bookings_table_views( $views, $EM_Bookings_Table ) {
		$views['multiple-bookings'] = [
			'label' => __('Multiple Booking','em-pro'),
			'label_singular' => __('Multiple Booking', 'em-pro'),
			'limit' => 20,
			'cols' => array('user_name','event_name', 'event_date', 'event_time', 'booking_spaces','booking_status','booking_price'),
			'contexts' => array(
				'event' => array(
					'cols' => array('user_name','booking_spaces','booking_status','booking_price'),
				),
			)
		];
		return $views;
	}
	
	public static function em_bookings_table_get_items( $items, $EM_Bookings_Table, $extra_args ) {
		if ( $EM_Bookings_Table->view === 'multiple-bookings') {
			$search_args = $extra_args['search_args'];
			$count_args = $extra_args['count_args'];
			$search_args['mb'] = $count_args['mb'] = true; // we hook into this here
			$EM_Bookings_Table->total_items = EM_Bookings::count( $count_args );
			$items = EM_Bookings::get( $search_args )->load(); // get the bookings only as an array of EM_Bookings via load()
		}
		return $items;
	}
	
	public static function em_bookings_table_get_bulk_action_message( $message, $action, $context, $EM_Bookings_Table, $args ) {
		if( $EM_Bookings_Table->view === 'multiple-bookings' && $context == 'bookings' ) {
			$message = esc_html__('This action will be applied to the bookings the selected multiple bookings belong to. Do you want to continue?', 'em-pro');
		}
		// prepened deletion message, we know there's no more to it than that
		$parent_message = $args['parent_message'];
		if( $parent_message && $action === 'delete' ) $message = $parent_message . '&#10;&#10;' . $message;
		// return message via custom filtersd
		return $message;
	}
	
	public static function em_bookings_table_get_action_message( $message, $action, $context, $EM_Bookings_Table, $args ) {
		if( $EM_Bookings_Table->view === 'multiple-bookings' && $context == 'bookings' ) {
			$message = $args['parent_message']; // start from base
			$context_msg = esc_html__( 'This action will be applied all the bookings belonging to this multiple booking. Do you want to continue?', 'em-pro' );
			if( $message ) $message .= "\n\n";
			$message .= sprintf( $context_msg, $EM_Bookings_Table->views['multiple-bookings']['label_singular'] );
		}
		return $message;
	}
	
	public static function em_bookings_build_sql_conditions( $conditions, $args ) {
		// check if we're in MB mode, if so we decide whether to wrap the SQL as a subquery, or just add the condition
		if( !empty($args['mb']) ) {
			// otherwise, this depends if any other conditions other than status, person or blog is set as these can be searched on on MB level
			$extra_conditions = array_diff( array_keys( $conditions ), ['status','blog','person'] );
			if ( ( count($extra_conditions) == 1 && in_array('event', $extra_conditions) ) ) {
				// we have to deal with the possibility that we are just searching for event_id != 0, if so then remove that from query and allow mb to pass as a condition rather than superquery
				if ( $args['event'] === false ) {
					unset( $conditions['event'] );
					$extra_conditions = [];
				}
			}
			if( empty($extra_conditions) ) {
				// no special queries so we wrap it in a subquery
				$conditions['mb'] = EM_BOOKINGS_TABLE . '.booking_id IN (SELECT booking_main_id FROM ' . EM_BOOKINGS_RELATIONSHIPS_TABLE . ')';
			}
		}
		return $conditions;
	}
	
	public static function em_bookings_get_default_search( $search_defaults, $array, $defaults ) {
		$search_defaults['mb'] = false;
		if( !empty($array['mb']) ) {
			$search_defaults['mb'] = $array['mb'] == true;
		}
		return $search_defaults;
	}
	
	public static function em_bookings_get_sql ( $sql, $args, $sql_parts ) {
		// check if we added a condition in em_bookings_build_sql_conditions, if not we need to interfere here and wrap a subquery
		if ( !empty($args['mb']) && empty($sql_parts['data']['conditions']['mb']) ) {
			// remove the MB condition and copy $sql_parts to make main query
			unset( $sql_parts['data']['conditions']['mb'] );
			$sql_mb_parts = $sql_parts;
			// tweak the $sql_parts to make a subquery
			$sql_parts['data']['selectors'] = EM_BOOKINGS_TABLE.'.booking_id';
			$sql_parts['statement']['select'] = "SELECT {$sql_parts['data']['selectors']} FROM ".EM_BOOKINGS_TABLE;
			$sql_parts['statement']['limit'] = $sql_parts['statement']['offset'] = '';
			$sql_subquery = implode(' ', $sql_parts['statement']);
			// now tweak SQL parts to make main query, we remove the  join, orderby and groupby - where is changed later
			$sql_mb_parts['statement']['orderby'] = $sql_mb_parts['statement']['join'] = $sql_mb_parts['statement']['groupby'] = '';
			$sql_mb_parts['data']['orderbys'] = $sql_mb_parts['data']['joins'] = $sql_mb_parts['data']['groupbys'] = [];
			// add MB condition and subquery back into main query
			$mb_condition = EM_BOOKINGS_TABLE . '.booking_id IN (SELECT booking_main_id FROM ' . EM_BOOKINGS_RELATIONSHIPS_TABLE . ' WHERE booking_id IN (' . $sql_subquery . '))';
			$sql_mb_parts['data']['conditions'] = [ 'mb' => $mb_condition ];
			$sql_mb_parts['statement']['where'] = " WHERE " . implode ( " AND ", $sql_mb_parts['data']['conditions'] );
			$sql = implode(' ', $sql_mb_parts['statement']);
		} else {
			$sql_mb_parts = [];
		}
		return apply_filters('em_multiple_bookings_get_sql', $sql, $args, $sql_mb_parts, $sql_parts);
	}
}
Multiple_Bookings_Table::init();