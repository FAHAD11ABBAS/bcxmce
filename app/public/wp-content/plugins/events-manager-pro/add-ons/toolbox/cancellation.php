<?php
namespace EM\Toolbox;
use EM_DateTime, EM_Booking;

class Bookings_Cancellation {
	
	public static function init(){
		// cancellation decision filter
		add_filter('em_booking_can_cancel', '\EM\Toolbox\Bookings_Cancellation::em_booking_can_cancel', 10, 2);
		
		// event override
		add_action('em_events_admin_bookings_footer', '\EM\Toolbox\Bookings_Cancellation::em_events_admin_bookings_footer', 1, 1);
		add_filter('em_event_load_postdata_other_attributes', '\EM\Toolbox\Bookings_Cancellation::event_other_attributes', 10, 1);
		add_filter('em_event_get_post_meta', '\EM\Toolbox\Bookings_Cancellation::em_event_get_post_meta', 10, 2);
		add_filter('em_event_save_meta', '\EM\Toolbox\Bookings_Cancellation::em_event_save_meta', 10, 2);
		
		// admin area option
		if( is_admin() ){
			add_action('em_options_page_bookings_cancellations_after', '\EM\Toolbox\Bookings_Cancellation::em_options_page_bookings_cancellations_after');
		}
	}
	
	/**
	 * @param bool $can_cancel
	 * @param \EM_Booking $EM_Booking
	 * @return bool
	 */
	public static function em_booking_can_cancel( $can_cancel, $EM_Booking ){
		// check event-specific settings
		$EM_Event = $EM_Booking->get_event();
		if( get_option('dbem_bookings_user_cancellation_event') && !empty($EM_Event->event_attributes['bookings_can_cancel']) ){ // 0 = default settings
			if( $EM_Event->event_attributes['bookings_can_cancel'] == 1 ){
				$can_cancel = time() < $EM_Booking->get_event()->start()->getTimestamp(); // previously default was rsvp end
				if( !empty($EM_Event->event_attributes['bookings_can_cancel_time']) ){
					$time = $EM_Event->event_attributes['bookings_can_cancel_time'];
					if( is_numeric($time) ){
						if( $time < 0 ){
							$EM_DateTime = $EM_Event->start()->copy()->add('PT'.absint($time).'H');
						}else{
							$EM_DateTime = $EM_Event->start()->copy()->sub('PT'.$time.'H');
						}
					}elseif( EM_Booking::is_dateinterval_string($time) ){
						if( $time[0] === '-' ){
							$time = substr($time, 1);
							$EM_DateTime = $EM_Event->start()->copy()->add($time);
						}else{
							$EM_DateTime = $EM_Event->start()->copy()->sub($time);
						}
					}
					if( !empty($EM_DateTime->valid) ) {
						$can_cancel = time() < $EM_DateTime->getTimestamp();
					}else{
						$can_cancel = false; // just in case
					}
				}
			}else{ // 2 = cannot cancel at all
				$can_cancel = false;
			}
		}elseif( get_option('dbem_bookings_user_cancellation') ){
			// add support for negatives
			$time = get_option('dbem_bookings_user_cancellation_time');
			if( is_numeric($time) && $time < 0 ){
				$EM_DateTime = $EM_Event->start()->copy()->add('PT'.absint($time).'H');
				$can_cancel = time() < $EM_DateTime->getTimestamp();
			}elseif( EM_Booking::is_dateinterval_string($time) && $time[0] === '-' ){
				$time = substr($time, 1);
				$EM_DateTime = $EM_Event->start()->copy()->add($time);
				$can_cancel = time() < $EM_DateTime->getTimestamp();
			}
		}
		return $can_cancel;
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return void
	 */
	public static function em_events_admin_bookings_footer( $EM_Event ){
		if( get_option('dbem_bookings_user_cancellation_event') ){
			$can_cancel = !empty($EM_Event->event_attributes['bookings_can_cancel']) ? $EM_Event->event_attributes['bookings_can_cancel'] : 0;
			$cancel_hours = !empty($EM_Event->event_attributes['bookings_can_cancel_time']) ? $EM_Event->event_attributes['bookings_can_cancel_time']:'';
			$site_default = get_option('dbem_bookings_user_cancellation') ? esc_html__emp('Enabled', 'default') : esc_html__emp('Disabled', 'default');
			if( $site_default && get_option('dbem_bookings_user_cancellation_time') ){
				$time = get_option('dbem_bookings_user_cancellation_time');
				if( is_numeric($time) ){
					if( $time >= 0 ){
						$before = true;
						$interval = $time . ' ' . esc_html__emp('hours', 'default');
					}else{
						$before = false;
						$interval = $time . ' ' . esc_html__emp('hours', 'default');
					}
				}else{
					$before = $time[0] !== '-';
					if( !$before ) $time = substr($time, 1);
					$interval = '<code>' . $time . '</code>';
				}
				if( $before ){
					$site_default .= ' - ' . sprintf( esc_html__('%s before the event starts.', 'em-pro'), $interval);
				}else{
					$site_default .= ' - ' . sprintf( esc_html__('%s after the event starts.', 'em-pro'), $interval);
				}
			}
			?>
			<div class="em-cancellation-options em-booking-options">
				<h4><?php echo esc_html(sprintf(emp__('%s Options'), __('Cancellation', 'em-pro'))); ?></h4>
				<p>
					<?php esc_html_e('You can override the default cancellation policy for this event, or leave the default rules in place. Additionally you can disable cancellation entirely for this specific event even if cancellation is generally permitted.', 'em-pro'); ?>
				</p>
				<p>
					<?php echo sprintf( esc_html__('The current default setting is : %s', 'em-pro'), $site_default ); ?>
				</p>
				<p>
					<label for="bookings_can_cancel"><?php esc_html_e_emp( 'Can users cancel their booking?'); ?></label>
					<select name="bookings_can_cancel" id="bookings_can_cancel">
						<option value="0" <?php if( $can_cancel == 0 ) echo 'selected'; ?>><?php echo sprintf(esc_html__('Default', 'em-pro'), $site_default); ?></option>
						<option value="1" <?php if( $can_cancel == 1 ) echo 'selected'; ?>><?php esc_html_e_emp('Yes', 'default'); ?></option>
						<option value="2" <?php if( $can_cancel == 2 ) echo 'selected'; ?>><?php esc_html_e_emp('No', 'default'); ?></option>
					</select>
				</p>
				<p id="bookings_can_cancel_time_row">
					<label for="bookings_can_cancel_time"><?php esc_html_e('Cancellation Cut-off','em-pro'); ?></label>
					<?php ob_start(); ?>
					<input type="text" name="bookings_can_cancel_time" value="<?php echo $cancel_hours; ?>" id="bookings_can_cancel_time" size="8">
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
				<script>
					let select = document.getElementById('bookings_can_cancel');
					select.addEventListener('change', function(e){
						if( select.options[select.selectedIndex].value === '1' ){
							document.getElementById('bookings_can_cancel_time_row').style.display = 'block';
						}else{
							document.getElementById('bookings_can_cancel_time_row').style.display = 'none';
						}
					});
					select.dispatchEvent( new Event('change') );
				</script>
			</div>
			<?php
		}
	}
	
	/**
	 * Get waitlist meta loaded in the EM_Event->load_postdata() function
	 * @param array $array
	 * @return array
	 */
	public static function event_other_attributes( $array ){
		return array_merge($array, array('bookings_can_cancel', 'bookings_can_cancel_time'));
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_get_post_meta( $result, $EM_Event ){
		// set specifics first, delete later if needed
		if( isset($_REQUEST['bookings_can_cancel_time']) && $_REQUEST['bookings_can_cancel_time'] !== '' && (is_numeric($_REQUEST['bookings_can_cancel_time']) || \EM_Booking::is_dateinterval_string($_REQUEST['bookings_can_cancel_time'])) ) {
			$EM_Event->event_attributes['bookings_can_cancel_time'] = $_REQUEST['bookings_can_cancel_time'];
		}else{
			unset($EM_Event->event_attributes['bookings_can_cancel_time']);
		}
		// check whether to enable/disable or use default
		if( !empty($_REQUEST['bookings_can_cancel']) ){
			$EM_Event->event_attributes['bookings_can_cancel'] = absint($_REQUEST['bookings_can_cancel']);
		}else{
			unset($EM_Event->event_attributes['bookings_can_cancel']);
			unset($EM_Event->event_attributes['bookings_can_cancel_time']);
		}
		return $result;
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_save_meta( $result, $EM_Event ){
		if( $result ){
			if( isset($EM_Event->event_attributes['bookings_can_cancel']) ){
				update_post_meta( $EM_Event->post_id, '_bookings_can_cancel', $EM_Event->event_attributes['bookings_can_cancel']);
				foreach( array('bookings_can_cancel_time') as $key ){
					if( isset($EM_Event->event_attributes[$key]) ) {
						update_post_meta( $EM_Event->post_id, '_'.$key, $EM_Event->event_attributes[$key]);
					}else{
						delete_post_meta( $EM_Event->post_id, '_'.$key);
					}
				}
			}else{
				delete_post_meta( $EM_Event->post_id, '_bookings_can_cancel');
				foreach( array('bookings_can_cancel_time') as $key ){
					delete_post_meta( $EM_Event->post_id, '_'.$key);
				}
			}
		}
		return $result;
	}
	
	public static function em_options_page_bookings_cancellations_after(){
		em_options_radio_binary ( __( 'Allow event-specific settings?', 'events-manager'), 'dbem_bookings_user_cancellation_event', __( 'If enabled, cancellation options will be available in the booking settings of each event and can override these default settings.', 'events-manager') );
	}
}
Bookings_Cancellation::init();