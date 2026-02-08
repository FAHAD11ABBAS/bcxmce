<?php
namespace EM_Pro\Attendance;

use EM_Booking, EM_Ticket_Booking, EM_Ticket_Bookings;

/**
 * Handles functionality within the admin areas of bookings, so admins can check in users directly in admin areas and view attendance history
 */
class Booking_Admin {
	public static function init(){
		add_action('em_bookings_admin_ticket_booking_row', [static::class, 'checkin_button'], 1, 1);
		add_action('em_bookings_manager_template_scripts', [static::class, 'em_bookings_manager_template_scripts' ]);
		
		// add checkin/checkout actions
		add_filter('em_bookings_table_get_action_data_items', [static::class, 'em_bookings_table_get_action_data_items'], 10, 3);
		add_filter('em_bookings_table_get_booking_allowed_actions', [static::class, 'em_bookings_table_get_booking_allowed_actions'], 10, 3);
		add_action('em_bookings_table_ajax_table_row_action', [static::class, 'em_bookings_table_ajax_table_row_action'], 10, 1);
		
		// add attendee data to tables
		add_action('em_bookings_table_cols_template', [static::class, 'em_bookings_table_cols_template'], 10,1);
		add_action('em_bookings_table_cols_template_groups', [static::class, 'em_bookings_table_cols_template_groups'], 10,1);
		add_filter('em_bookings_table_rows_col_checkedin',[static::class, 'em_bookings_table_rows_col_checkedin'], 10, 3);
		add_filter('em_bookings_table_rows_col_checkedout',[static::class, 'em_bookings_table_rows_col_checkedout'], 10, 3);
		add_filter('em_bookings_table_rows_col_not_attended',[static::class, 'em_bookings_table_rows_col_not_attended'], 10, 3);
		add_filter('em_bookings_table_rows_col_checkin_status',[static::class, 'em_bookings_table_rows_col_checkin_status'], 10, 3);
	}
	
	public static function em_bookings_table_get_action_data_items( $data, $EM_Bookings_Table, $args ){
		$data_template = $args['data_template'];
		$data_template['context'] = 'attendees';
		$data['attendance'] = [
			'label' => esc_html__('Attendance', 'em-pro'),
			'actions' => [
				'checkin' => [ 'label' => __('Check In','em-pro'), 'data' => array_merge($data_template, ['row_action' => 'checkin']) ],
				'checkout' => [ 'label' => __('Check Out','events-manager'), 'data' => array_merge($data_template, ['row_action' => 'checkout']) ],
			],
		];
		return $data;
	}
	
	public static function em_bookings_table_ajax_table_row_action ( $requested_action ) {
		if ( $requested_action === 'checkin' ||  $requested_action === 'checkout' ) {
			$EM_Bookings_Table = new \EM_Bookings_Table();
			$EM_Booking = ( !empty($_REQUEST['booking_id']) ) ? em_get_booking($_REQUEST['booking_id']) : em_get_booking();
			// are we dealing with a booking, ticket or attendee?
			if ( $_REQUEST['view'] === 'attendees' ) {
				$EM_Ticket_Booking = new EM_Ticket_Booking( $_REQUEST['row_id'] );
				Attendance::handle_action( $EM_Ticket_Booking, $requested_action );
				$EM_Bookings_Table->single_row( $EM_Ticket_Booking );
			} elseif ( $_REQUEST['view'] === 'tickets' ) {
				$row_id = explode( '-', $_REQUEST['row_id'] );
				$data = array( 'booking_id' => $row_id[0], 'ticket_id' => $row_id[1] );
				$EM_Ticket_Bookings = new EM_Ticket_Bookings( $data );
				foreach ( $EM_Ticket_Bookings as $EM_Ticket_Booking ) {
					Attendance::handle_action( $EM_Ticket_Booking, $requested_action );
					if( !empty($EM_Ticket_Booking->errors) ) {
						$EM_Ticket_Bookings->add_error( $EM_Ticket_Booking->get_errors() );
					} else {
						$EM_Ticket_Bookings->feedback_message = $EM_Ticket_Booking->feedback_message;
					}
				}
				$EM_Bookings_Table->single_row( $EM_Ticket_Bookings );
			} else {
				foreach( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ) {
					foreach ( $EM_Ticket_Bookings as $EM_Ticket_Booking ) {
						Attendance::handle_action( $EM_Ticket_Booking, $requested_action );
						if ( !empty( $EM_Ticket_Booking->errors ) ) {
							$EM_Booking->add_error( $EM_Ticket_Booking->get_errors() );
						} else {
							$EM_Booking->feedback_message = $EM_Ticket_Booking->feedback_message;
						}
					}
				}
				$EM_Bookings_Table->single_row( $EM_Booking );
			}
		}
	}
	
	public static function em_bookings_table_get_booking_allowed_actions( $booking_actions, $EM_Bookings_Table, $args ){
		extract( $EM_Bookings_Table->get_item_objects($args['item']) ); /* @var \EM_Ticket $EM_Ticket *//* @var \EM_Ticket_Booking $EM_Ticket_Booking *//* @var \EM_Ticket_Bookings $EM_Ticket_Bookings *//* @var \EM_Booking $EM_Booking */
		// determine which keys to show
		if ( $EM_Booking->booking_status === 1 ) {
			$booking_actions['attendance'] = $args['actions']['attendance'];
		}
		return $booking_actions;
	}
	
	/**
	 * Adds columns in the bookings tables
	 * @param array $template
	 * @return array
	 */
	public static function em_bookings_table_cols_template( $template = array() ){
		$template['checkedin'] = esc_html__('Checked In', 'em-pro');
		$template['checkedout'] = esc_html__('Checked Out', 'em-pro');
		$template['not_attended'] = esc_html__('Not Attended', 'em-pro');
		$template['checkin_status'] = esc_html__('Checked In/Out', 'em-pro');
		return $template;
	}
	
	public static function em_bookings_table_cols_template_groups( $template_groups ) {
		$template_groups['attendance'] = array(
			'label' => __('Attendance','events-manager'),
			'fields' => array_keys( static::em_bookings_table_cols_template() ),
		);
		return $template_groups;
	}
	
	/**
	 * Gets
	 * @param EM_Booking|EM_Ticket_Bookings|EM_Ticket_Booking $EM_Object
	 * @param $status
	 *
	 * @return string
	 */
	public static function em_bookings_table_rows_col_status( $EM_Object, $status = false ){
		// get a single query to sum up latest status for each ticket booking
		if ( $EM_Object instanceof EM_Ticket_Booking ) {
			return Attendance::get_status( $EM_Object ) === $status ? esc_html__emp('Yes', 'default') : esc_html__emp('No', 'default');
		} elseif ( $EM_Object instanceof EM_Ticket_Bookings ){
			$EM_Booking = $EM_Object->get_booking();
			$ticket_id = $EM_Object->get_ticket()->ticket_id;
			$total = $EM_Object->get_spaces();
		} else {
			$EM_Booking = $EM_Object;
			$ticket_id = false;
			$total = $EM_Booking->get_spaces();
		}
		if( $status !== false ) {
			$bookings = Attendance::get_booking_ticket_ids_with_status( $EM_Booking, $status, $ticket_id );
			$bookings = count($bookings);
			return $bookings .'/'. $total;
		} else {
			$bookings = [];
			foreach ( [1,0,null] as $status ) {
				$bookings[] = count( Attendance::get_booking_ticket_ids_with_status( $EM_Booking, $status, $ticket_id ) );
			}
			return implode('/', $bookings);
		}
	}
	
	/**
	 * @param string $val
	 * @param EM_Booking|EM_Ticket_Bookings|EM_Ticket_Booking $EM_Object
	 */
	public static function em_bookings_table_rows_col_checkedin($val, $EM_Object){
		return static::em_bookings_table_rows_col_status( $EM_Object, 1 );
	}
	
	/**
	 * @param string $val
	 * @param EM_Booking|EM_Ticket_Bookings|EM_Ticket_Booking $EM_Object
	 */
	public static function em_bookings_table_rows_col_checkedout($val, $EM_Object){
		return static::em_bookings_table_rows_col_status( $EM_Object, 0 );
	}
	
	/**
	 * @param string $val
	 * @param EM_Booking|EM_Ticket_Bookings|EM_Ticket_Booking $EM_Object
	 */
	public static function em_bookings_table_rows_col_not_attended($val, $EM_Object){
		return static::em_bookings_table_rows_col_status( $EM_Object, null );
	}
	
	/**
	 * @param string $val
	 * @param EM_Booking|EM_Ticket_Bookings|EM_Ticket_Booking $EM_Object
	 */
	public static function em_bookings_table_rows_col_checkin_status($val, $EM_Object){
		\EM_Bookings_Table::$cols_allowed_html['checkin_status'] = true; // guilty until proven innocent each time
		if ( $EM_Object instanceof EM_Ticket_Booking ) {
			$status = Attendance::get_status( $EM_Object );
			$s = Attendance::get_status_text( $EM_Object );
			$color = 'orange';
			if ( $status !== null ) {
				$color = $status ? 'green' : 'red';
			}
			return '<strong style="color: ' . $color . '">' . $s . '</strong>';
		} else {
			$s = static::em_bookings_table_rows_col_status( $EM_Object );
			$status = explode('/', $s);
			// first check if they're all checked in/out/not attended, otherwise show summary
			if ( $status[0] > 0 && $status[1] == 0 && $status[2] == 0 ) {
				return '<strong style="color:green">' . esc_html__('Checked In', 'em-pro') . '</strong>';
			} elseif ( $status[0] == 0 && $status[1] > 0 && $status[2] == 0 ) {
				return '<strong style="color:red">' . esc_html__('Checked Out', 'em-pro') . '</strong>';
			} elseif ( $status[0] == 0 && $status[1] == 0 && $status[2] > 0 ) {
				return '<strong style="color:orange">' . esc_html__('Not Attended', 'em-pro') . '</strong>';
			}
			// show summary
			return '<strong style="color:green">' . $status[0] . '</strong> / <strong style="color:red">' . $status[1] . '</strong> / <strong style="color:orange">' . $status[2] . '</strong>';
		}
	}
	
	public static function checkin_button( $EM_Ticket_Booking ){
		$checkin_status = Attendance::get_status($EM_Ticket_Booking);
		?>
		<div class="em-booking-single-info">
			<button type="button" class="em-clickable button-secondary with-icon attendance-action attendance-status-1 <?php if ($checkin_status !== 1) echo 'hidden'; ?>" data-action="checkout" data-uuid="<?php echo esc_attr($EM_Ticket_Booking->ticket_uuid); ?>">
				<span class="loaded em-icon em-icon-cross-circle"></span>
				<span class="loaded"><?php esc_html_e('Check Out', 'em-pro'); ?></span>
				<span class="loading-content em-icon em-icon-spinner" role="status" aria-hidden="true"></span>
				<span class="loading-content"><?php esc_html_e('Loading...', 'em-pro'); ?></span>
			</button>
			<button type="button" class="em-clickable button-secondary with-icon attendance-action attendance-status-0  attendance-status-null <?php if ($checkin_status === 1) echo 'hidden'; ?>" data-action="checkin" data-uuid="<?php echo esc_attr($EM_Ticket_Booking->ticket_uuid); ?>">
				<span class="loaded em-icon em-icon-checkmark-circle"></span>
				<span class="loaded"><?php esc_html_e('Check In', 'em-pro'); ?></span>
				<span class="loading-content em-icon em-icon-spinner" role="status" aria-hidden="true"></span>
				<span class="loading-content"><?php esc_html_e('Loading...', 'em-pro'); ?></span>
			</button>
			<span class="em-tooltip attendance-history-toggle" aria-label="<?php esc_html_e('Click to view history', 'em-pro'); ?>">
				<span class="attendance-current-status attendance-display-status-<?php echo $checkin_status === null ? 'null':$checkin_status; ?> attendance-status-x">
					<?php
						echo Attendance::get_status_text($EM_Ticket_Booking);
						if( $checkin_status !== null ){
							$checkin_ts = Attendance::get_status_timestamp( $EM_Ticket_Booking );
							$EM_DateTime = new \EM_DateTime($checkin_ts);
							echo ' @ '. $EM_DateTime->formatDefault();
						}
					?>
				</span>
				<span class="attendance-status-0 hidden">
					<?php esc_html_e('Checked Out', 'em-pro'); ?>
				</span>
				<span class="attendance-status-1 hidden">
					<?php esc_html_e('Checked In', 'em-pro'); ?>
				</span>
				<span class="attendance-history-hidden em-icon em-icon-chevron-down"></span>
				<span class="attendance-history-visible em-icon em-icon-chevron-up"></span>
			</span>
			<div class="attendance-history">
				<table cellpadding="0" cellspacing="0">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e_emp('When'); ?></th>
							<th scope="col"><?php esc_html_e_emp('Action'); ?></th>
						</tr>
						</thead>
					<tbody>
					<?php
					$attendance_history = Attendance::get_history($EM_Ticket_Booking);
					if( !empty($attendance_history ) ) {
						foreach ($attendance_history as $item) {
							$status_color = 'attendance-display-status-null';
							if( $item['status'] == 1 ){
								$status_color = 'attendance-display-status-1';
							}elseif( $item['status'] == 0 ){
								$status_color = 'attendance-display-status-0';
							}
							?>
							<tr>
								<td><span class="em-tooltip" aria-label="<?php echo $item['date']; ?>"><?php echo $item['time']; ?></span></td>
								<td class="<?php echo $status_color; ?>"><?php echo $item['action']; ?></td>
							</tr>
							<?php
						}
					}else{
						echo '<tr><td colspan="2"><em class="text-muted">'. esc_html__('No attendance activity.', 'em-pro') . '</em></td></tr>';
					}
					?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		wp_enqueue_script('events-manager-pro-attendance', plugins_url('attendance.js',__FILE__), array(), EMP_VERSION, true);
	}
	
	public static function em_bookings_manager_template_scripts(){
		\EM_Scripts_and_Styles::localize_script(); // get the localized script here, saved in the global below, WP's localization won't ever get hit
		global $em_localized_js;
		echo 'const EM = '.json_encode($em_localized_js) . ';'."\r\n";
		include('attendance.js');
	}
}
Booking_Admin::init();