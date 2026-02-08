<?php
namespace EM\Bookings\Minimum_Capacity;

class Listener {

	public static function init(){
		//set up cron for clearing email queue
		if( !wp_next_scheduled('emp_cron_minimum_capacity') ){
			$result = wp_schedule_event( time(),'em_minute','emp_cron_minimum_capacity');
		}
		// cron actions
		add_action('emp_cron_minimum_capacity', array( static::class, 'scan') );
		// admin ui
		add_action('em_events_admin_bookings_footer', array( static::class, 'em_events_admin_bookings_footer' ));
		// saving actions
		add_filter('em_event_get_post_meta', array( static::class, 'em_event_get_post_meta' ), 10, 2);
		add_filter('em_event_save_meta', array( static::class, 'em_event_save_meta' ), 10, 2);
		add_filter('em_event_load_postdata_other_attributes', array( static::class, 'event_other_attributes'), 10, 1);
		add_filter('em_event_save_post_meta_other_attributes', array( static::class, 'event_other_attributes'), 10, 1);
	}
	
	public static function scan(){
		global $wpdb;
		// get event ids that need checking within the min capacity date
		$sql = "
			SELECT meta_id, object_id, meta_value, meta_date
			FROM ". EM_META_TABLE . "
			WHERE meta_key='minimum_capacity_check'
				AND meta_date <= NOW()
				AND meta_value = 1
		";
		$upcoming = $wpdb->get_results( $sql );
		foreach ( $upcoming as $event_meta ) {
			// load the event
			$EM_Event = em_get_event( $event_meta->object_id );
			// check minimum capcity against bookings
			if ( !empty($EM_Event->event_attributes['minimum_capacity_spaces']) ) {
				$minimum_capacity = $EM_Event->event_attributes['minimum_capacity_spaces'];
				$booked_spaces = $EM_Event->get_bookings()->get_booked_spaces();
				if( get_option('dbem_bookings_approval') ){ // include pending spaces for purposes of non-cancellation
					$booked_spaces += $EM_Event->get_bookings()->get_pending_spaces();
				}
				if( $minimum_capacity > $booked_spaces ) {
					// not enough bookings, cancel event and bookings
					if( $EM_Event->cancel() ){
						$wpdb->update( EM_META_TABLE, array('meta_value' => 0), array('meta_id' => $event_meta->meta_id));
					}
				}else{
					$wpdb->update( EM_META_TABLE, array('meta_value' => 0), array('meta_id' => $event_meta->meta_id));
				}
			} else {
				$wpdb->update( EM_META_TABLE, array('meta_value' => 0), array('meta_id' => $event_meta->meta_id));
			}
		}
	}
	
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return void
	 */
	public static function em_events_admin_bookings_footer( $EM_Event ){
		$cancel_hours = !empty($EM_Event->event_attributes['minimum_capacity_time']) ? $EM_Event->event_attributes['minimum_capacity_time'] : '';
		if( $cancel_hours === '' && !$EM_Event->event_id ) {
			$cancel_hours = get_option('dbem_minium_capacity_time', '');
		}
		$cancel_minimum = !empty($EM_Event->event_attributes['minimum_capacity_spaces']) ? $EM_Event->event_attributes['minimum_capacity_spaces'] : '';
		if( $cancel_minimum === '' && !$EM_Event->event_id ) {
			$cancel_minimum = get_option('dbem_minium_capacity_spaces', '');
		}
		?>
		<div class="em-waitlist-options em-booking-options">
			<h4><?php echo esc_html(sprintf(emp__('%s Options'), __('Minimum Capacity', 'em-pro'))); ?></h4>
			<p>
				<?php esc_html_e('Choose a certain amount of time before the event start date and minimum capacity required for the event to proceed. If the number of confirmed and pending bookings are less than the minimum requirement, the event will be cancelled.', 'em-pro'); ?>
			</p>
			<div class="em-waitlist-option">
				<p>
					<label for="em_minimum_capacity_spaces"><?php esc_html_e_emp('Minimum Capacity'); ?></label>
					<input type="text" name="minimum_capacity_spaces" id="em_minimum_capacity_spaces" size="3" value="<?php echo $cancel_minimum; ?>">
				</p>
				<p>
					<label for="em_minimum_capacity_time"><?php esc_html_e('Minimum Capacity Cut-off','em-pro'); ?></label>
					<?php ob_start(); ?>
					<input type="text" name="minimum_capacity_time" value="<?php echo $cancel_hours; ?>" id="em_minimum_capacity_time" size="8">
					<?php echo sprintf( esc_html__('%s hours before the event starts.', 'em-pro'), ob_get_clean() ); ?><br>
					<em>
						<?php
							$cancellation_hours_desc = esc_html__emp('%s are also accepted, for example %s equals 1 month and 12 hours before the event starts.');
							$cancellation_hours_desc = sprintf($cancellation_hours_desc, '<a href="https://www.php.net/manual/en/dateinterval.construct.php" target="_blank">'.esc_html__emp('PHP date intevals').'</a>', '<code>P1MT12H</code>');
							$cancellation_hours_desc .= ' '. esc_html__emp('Add a negative number or minus sign to the start of the date interval to allow cancellations after events have started.');
							echo $cancellation_hours_desc;
						?>
					</em>
				</p>
			</div>
		</div>
		<?php
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_get_post_meta( $result, $EM_Event ){
		// set specifics first, delete later if needed
		if ( $EM_Event->event_rsvp && !$EM_Event->is_recurring( true ) ) {
			if ( isset( $_REQUEST['minimum_capacity_time'] ) && $_REQUEST['minimum_capacity_time'] !== '' && ( is_numeric( $_REQUEST['minimum_capacity_time'] ) || \EM_Booking::is_dateinterval_string( $_REQUEST['minimum_capacity_time'] ) ) ) {
				$event_reactivated = $EM_Event->previous_active_status != $EM_Event->event_active_status && $EM_Event->event_active_status == 1;
				$event_min_cap_time_changed = !empty( $EM_Event->event_attributes['minimum_capacity_time'] ) && $EM_Event->event_attributes['minimum_capacity_time'] != $_REQUEST['minimum_capacity_time'];
				if ( $event_min_cap_time_changed || $event_reactivated ) {
					$EM_Event->event_attributes['minimum_capacity_redo'] = 1;
				}
				$EM_Event->event_attributes['minimum_capacity_time'] = $_REQUEST['minimum_capacity_time'];
			} else {
				unset( $EM_Event->event_attributes['minimum_capacity_time'] );
			}
			// check whether to enable/disable or use default
			if ( !empty( $_REQUEST['minimum_capacity_spaces'] ) ) {
				$EM_Event->event_attributes['minimum_capacity_spaces'] = absint( $_REQUEST['minimum_capacity_spaces'] );
			} else {
				unset( $EM_Event->event_attributes['minimum_capacity_spaces'] );
				unset( $EM_Event->event_attributes['minimum_capacity_time'] );
			}
		} else {
			unset( $EM_Event->event_attributes['minimum_capacity_spaces'] );
			unset( $EM_Event->event_attributes['minimum_capacity_time'] );
		}
		return $result;
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_save_meta( $result, $EM_Event ){
		global $wpdb;
		if( $result ){
			if( isset($EM_Event->event_attributes['minimum_capacity_time']) ){
				// set a flag to check based on event date
				$time = $EM_Event->event_attributes['minimum_capacity_time'];
				if( is_numeric($time) ){
					$EM_DateTime = $EM_Event->start()->copy()->sub('PT'.absint($time).'H');
				}elseif( \EM_Booking::is_dateinterval_string($time) ){
					$EM_DateTime = $EM_Event->start()->copy()->sub($time);
				}
			}
			if( !empty($EM_DateTime->valid) ) {
				// check if this already exists
				$trigger_flag = $wpdb->get_row( $wpdb->prepare('SELECT * FROM ' . EM_META_TABLE . ' WHERE meta_key=%s AND object_id=%d', 'minimum_capacity_check', $EM_Event->id ), ARRAY_A );
				// set the flag
				if( empty($trigger_flag) ){
					$wpdb->insert( EM_META_TABLE, array('meta_key' => 'minimum_capacity_check', 'meta_value' => 1, 'meta_date' => $EM_DateTime->format(), 'object_id' => $EM_Event->event_id));
				}elseif( !empty($EM_Event->event_attributes['minimum_capacity_redo']) ){
					$wpdb->update( EM_META_TABLE, array('meta_value' => 1, 'meta_date' => $EM_DateTime->format()), array('meta_id' => $trigger_flag['meta_id']));
				}else{
					$wpdb->update( EM_META_TABLE, array('meta_date' => $EM_DateTime->format()), array('meta_id' => $trigger_flag['meta_id']));
				}
			}else{
				// remove flag with changes
				$wpdb->delete( EM_META_TABLE, array('meta_key' => 'minimum_capacity_check', 'object_id' => $EM_Event->event_id));
			}
		}
		return $result;
	}
	
	/**
	 * Get waitlist meta loaded in the EM_Event->load_postdata() function
	 * @param array $array
	 * @return array
	 */
	public static function event_other_attributes( $array ){
		return array_merge($array, array('minimum_capacity_time', 'minimum_capacity_spaces'));
	}
}
Listener::init();