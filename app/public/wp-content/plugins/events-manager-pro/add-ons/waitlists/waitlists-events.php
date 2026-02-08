<?php
namespace EM\Waitlist;

class Events {
	
	public static function init(){
		if( get_option('dbem_waitlists_events') ) {
			// placeholders
			add_filter('em_event_output_placeholder','\EM\Waitlist\Events::em_event_output_placeholder',1,3);
			add_filter('em_event_output_show_condition', '\EM\Waitlist\Events::em_event_output_show_condition', 1, 4);
			// display/load/save meta
			add_filter('em_events_admin_bookings_footer', '\EM\Waitlist\Events::em_events_admin_bookings_footer', 10, 1);
			add_filter('em_event_load_postdata_other_attributes', '\EM\Waitlist\Events::em_event_load_postdata_other_attributes', 10, 1);
			add_filter('em_event_get_post_meta', '\EM\Waitlist\Events::em_event_get_post_meta', 10, 2);
			add_filter('em_event_save_meta', '\EM\Waitlist\Events::em_event_save_meta', 10, 2);
			// ticket stuff handed in Tickets object
		}
	}
	
	/**
	 * @param string $replace
	 * @param \EM_Event $EM_Event
	 * @param string $result
	 * @return string
	 */
	public static function em_event_output_placeholder($replace, $EM_Event, $result){
		switch( $result ){
			case '#_WAITLIST_EXPIRY': // hours an approved waitlisted booking keeps available spaces reserved
				$replace = static::get_var('expiry', $EM_Event);
				break;
			case '#_WAITLIST_SPACES': // max number of spaces available to waitlist
			case '#_WAITLIST_LIMIT':
				$waitlist_limit = static::get_var('limit', $EM_Event);
				$replace = $waitlist_limit == 0 ? esc_html__('Unlimited', 'em-pro') : $waitlist_limit;
				break;
			case '#_WAITLIST_BOOKING_LIMIT':
				$replace = static::get_var('booking_limit', $EM_Event);
				break;
			case '#_WAITLIST_WAITING': // number of people waiting (not including approved wait-listed bookings)
				$replace = static::get_waiting_bookings($EM_Event);
				break;
			case '#_WAITLIST_AVAILABLE': // number of spaces left to reserve on waitlist
				$replace = static::get_available_spaces($EM_Event);
				if( $replace === true ){
					$replace = esc_html__('Unlimited', 'em-pro');
				}
				break;
			case '#_WAITLIST_SPACES_RESERVED': // number of spaces already reserved in booking
				$replace = static::get_waiting_spaces($EM_Event);
				break;
			case '#_WAITLIST_SPACES_APPROVED': // number of approved (but not booked yet) spaces
				$replace = static::get_waiting_approved_spaces($EM_Event);
				break;
		}
		return $replace;
	}
	
	/**
	 * @param bool $show
	 * @param string $condition
	 * @param string $full_match
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_output_show_condition($show, $condition, $full_match, $EM_Event){
		switch ($condition) {
			case 'has_waitlist' : // event has waitlist enabled
				$show = static::get_var('waitlist', $EM_Event);
				break;
			case 'no_waitlist' : // event doesn't have waitlists enabled
				$show = !static::get_var('waitlist', $EM_Event);
				break;
			case 'waitlist_open' : // waitlist is open for reservations - does not account for user-restricted tickets or whether users must be logged in to apply
			case 'waitlist_closed' : // waitlist is closed to reservations, this can mean it's full, not open yet or not enabled - does not account for user-restricted tickets or whether users must be logged in to apply
			case 'waitlist_full' : // waitlist is enabled and full
				$is_enabled = static::get_var('waitlist', $EM_Event);
				$is_fully_booked = $EM_Event->get_bookings()->get_available_spaces() <= 0;
				$is_waitlist_available = !Events::get_available_spaces( $EM_Event ); // could also be true i.e. unlimited, 0 or false means no spaces
				$show = $is_enabled && $is_fully_booked && $is_waitlist_available;
				if( $condition === 'waitlist_closed' ) $show = !$show; // opposite of closed
				if( $condition === 'waitlist_full' ) $show = $is_enabled && $is_fully_booked && !$is_waitlist_available; // only waitlist should be unavailable here
				break;
			case 'has_waitlist_limit' : // event has limited spaces for waitlist
				$show = static::get_var('limit', $EM_Event) > 0;
				break;
			case 'no_waitlist_limit' : // event has limited spaces for waitlist
				$show = !static::get_var('waitlist', $EM_Event);
				break;
			case 'has_waitlist_booking_limit' : // user can book limited spaces on waitlist
				$show = static::get_var('limit', $EM_Event) > 0;
				break;
			case 'no_waitlist_booking_limit' : // user has no limit of spaces to book per booking, although possibly limited by the general waitlist limit of available spaces
				$show = !static::get_var('limit', $EM_Event);
				break;
			case 'has_waitlist_expiry' : // approved waitlist bookings have an expiry time
				$show = static::get_var('expiry', $EM_Event) > 0;
				break;
			case 'no_waitlist_expiry' : // approved waitlist bookings don't have an expiry time
				$show = !static::get_var('expiry', $EM_Event);
				break;
		}
		return $show;
	}
	
	/**
	 * Get the number of waitlisted spaces currently in line. This doesn't include approved waitlist reservations because they're considered booked or auto-cancelling after x hours.
	 * @param \EM_Event $EM_Event
	 * @return int
	 */
	public static function get_waiting_spaces($EM_Event ){
		global $wpdb;
		$reserved = $wpdb->get_var('SELECT SUM(booking_spaces) FROM '.EM_BOOKINGS_TABLE.' WHERE event_id='. absint($EM_Event->event_id) .' AND booking_status=6 GROUP BY event_id');
		if( !$reserved ) $reserved = 0;
		return absint($reserved);
	}
	
	/**
	 * Get the number of approved waitlist reservations, waiting to complete a booking. These are considered as 'booked' with an auto-cancel if they pass expiry time.
	 * @param \EM_Event $EM_Event
	 * @return int
	 */
	public static function get_waiting_approved_spaces($EM_Event ){
		global $wpdb;
		$reserved = $wpdb->get_var('SELECT SUM(booking_spaces) FROM '.EM_BOOKINGS_TABLE.' WHERE event_id='. absint($EM_Event->event_id) .' AND booking_status=7 GROUP BY event_id');
		if( !$reserved ) $reserved = 0;
		return absint($reserved);
	}
	
	/**
	 * Get the number of waitlist reservations for the current event. This is not the number of spaces reserved, but the number of reservations which may be requesting more than one space.
	 * @param \EM_Event $EM_Event
	 * @return int
	 */
	public static function get_waiting_bookings( $EM_Event ){
		global $wpdb;
		$reserved = $wpdb->get_var('SELECT COUNT(*) FROM '.EM_BOOKINGS_TABLE.' WHERE event_id='. absint($EM_Event->event_id) .' AND booking_status=6');
		if( !$reserved ) $reserved = 0;
		return absint($reserved);
	}
	
	/**
	 * Get the number of waitlist reservations, waiting to complete a booking. These are considered as 'booked' with an auto-cancel if they pass expiry time.
	 * IF spaces are unlimited, a true value is returned
	 * @param \EM_Event $EM_Event
	 * @return int|true
	 */
	public static function get_available_spaces( $EM_Event ){
		$waitlist_limit = static::get_var('limit', $EM_Event);
		$waiting = static::get_waiting_spaces($EM_Event);
		if( $waitlist_limit == 0 ) return true;
		if( $waitlist_limit <= $waiting ) return 0;
		return $waitlist_limit - $waiting;
	}
	
	/**
	 * @param 'expiry'|'limit'|'booking_limit'|'enabled'|'waitlist' $var
	 * @param \EM_Event $EM_Event
	 * @return false|mixed|null
	 */
	public static function get_var( $var, $EM_Event ){
		$value = null;
		if( $var === 'expiry' || $var === 'limit' || $var === 'booking_limit' ){
			$value = get_option('dbem_waitlists_'.$var);
			if( !empty($EM_Event->event_attributes['waitlist_'.$var]) ){
				$value = $EM_Event->event_attributes['waitlist_'.$var];
			}
		}
		if( $var === 'enabled' || $var === 'waitlist' ){
			$value = get_option('dbem_waitlists');
			if( !empty($EM_Event->event_attributes['waitlist']) ){
				$value = $EM_Event->event_attributes['waitlist'];
			}
		}
		return $value;
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return void
	 */
	public static function em_events_admin_bookings_footer( $EM_Event ){
		?>
		<div class="em-waitlist-options em-booking-options">
			<h4><?php echo esc_html(sprintf(emp__('%s Options'), __('Waitlist', 'em-pro'))); ?></h4>
			<p>
				<?php esc_html_e('When your event is fully booked, you can enable a waitlist so people can sign up for when spaces become available due to cancellations.', 'em-pro'); ?>
				<?php esc_html_e('An event is considered fully booked if all available spaces are booked for an event. This is different from events where bookings are closed, meaning that spaces are still available but there are no more available tickets due to date or other restrictions.', 'em-pro'); ?>
			</p>
			<?php if( get_option('dbem_waitlists_events_default') !== 2 ): // if enabled for all, we by default will not include the value and assume it's enabled elsewhere ?>
			<p>
				<?php
				if( isset($EM_Event->event_attributes['waitlist']) ){
					$checked = !empty($EM_Event->event_attributes['waitlist']);
				}else{
					$checked = get_option('dbem_waitlists_events_default') == 1;
				}
				?>
				<input type="checkbox" name="waitlist" id="em_waitlist_enabled" value="1" <?php if( $checked ) echo 'checked'; ?>>
				<label for="em_waitlist_enabled"><?php esc_html_e_emp('Enable Waitlist?'); ?> </label>
			</p>
			<?php endif; ?>
			<div class="em-waitlist-option">
				<p>
					<label for="em_waitlist_limit"><?php esc_html_e_emp( 'Total Spaces' ); ?></label>
					<input type="text" name="waitlist_limit" id="em_waitlist_limit" size="3" value="<?php if( isset($EM_Event->event_attributes['waitlist_limit']) ){ echo $EM_Event->event_attributes['waitlist_limit']; } ?>"><br>
					<em><?php esc_html_e('Restrict how many spaces events will have availble for waitlisting.','em-pro'); ?> <?php echo sprintf(esc_html__('Leave blank for default %d, 0 for no limit.','em-pro'), get_option('dbem_waitlists_limit')); ?></em>
				</p>
				<p>
					<label for="em_waitlist_booking_limit"><?php esc_html_e_emp('Maximum Spaces Per Booking'); ?></label>
					<input type="text" name="waitlist_booking_limit" id="em_waitlist_booking_limit" size="3" value="<?php if( isset($EM_Event->event_attributes['waitlist_booking_limit']) ){ echo $EM_Event->event_attributes['waitlist_booking_limit']; } ?>"><br>
					<em><?php esc_html_e_emp('If set, the total number of spaces for a single booking to this event cannot exceed this amount.'); ?> <?php echo sprintf(esc_html__('Leave blank for default %s, 0 for no limit.','em-pro'), get_option('dbem_waitlists_booking_limit')); ?></em>
				</p>
				<p>
					<label for="em_waitlist_expiry"><?php esc_html_e('Waitlist Approval Expiry', 'em-pro'); ?></label>
					<input type="text" name="waitlist_expiry" id="em_waitlist_expiry" size="3" value="<?php if( isset($EM_Event->event_attributes['waitlist_expiry']) ){ echo $EM_Event->event_attributes['waitlist_expiry']; } ?>"><br>
					<em>
						<?php esc_html_e('Hours until an wait-listed booking can keep their reserved spot once it becomes available to them. If a booking is not completed within that time-frame the reservation is cancelled and the next wait-listed booking is allowed to book.', 'em-pro'); ?>
						<?php echo sprintf(esc_html__('Leave blank for default (%d hours), 0 for no limit.','em-pro'), get_option('dbem_waitlists_expiry')); ?>
					</em>
				</p>
			</div>
		</div>
		<script>
			let waitlist_checkbox = document.getElementById('em_waitlist_enabled');
			if( waitlist_checkbox !== null ){
				waitlist_checkbox.addEventListener('change', function(e){
					if( waitlist_checkbox.checked ){
						document.querySelectorAll('.em-waitlist-option').forEach( function( option ){
							option.classList.remove('hidden');
						});
					}else{
						document.querySelectorAll('.em-waitlist-option').forEach( function( option ){
							option.classList.add('hidden');
						});
					}
				});
				waitlist_checkbox.dispatchEvent(new Event('change'));
			}
		</script>
		<?php
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_get_post_meta( $result, $EM_Event ){
		// set specifics first, delete later if needed
		if( isset($_REQUEST['waitlist_limit']) && $_REQUEST['waitlist_limit'] !== '' ) {
			$EM_Event->event_attributes['waitlist_limit'] = absint($_REQUEST['waitlist_limit']) ;
		}else{
			unset($EM_Event->event_attributes['waitlist_limit']);
		}
		if( isset($_REQUEST['waitlist_booking_limit']) && $_REQUEST['waitlist_booking_limit'] !== '' ) {
			$EM_Event->event_attributes['waitlist_booking_limit'] = absint($_REQUEST['waitlist_booking_limit']) ;
		}else{
			unset($EM_Event->event_attributes['waitlist_booking_limit']);
		}
		if( isset($_REQUEST['waitlist_expiry']) && $_REQUEST['waitlist_expiry'] !== '' ) {
			$EM_Event->event_attributes['waitlist_expiry'] = absint($_REQUEST['waitlist_expiry']) ;
		}else{
			unset($EM_Event->event_attributes['waitlist_expiry']);
		}
		// check whether to enable/disable or use default
		if( get_option('dbem_waitlists_events_default') !== 2 ){
			if( !empty($_REQUEST['waitlist']) ){
				$EM_Event->event_attributes['waitlist'] = 1;
			}else{
				$EM_Event->event_attributes['waitlist'] = 0;
				unset($EM_Event->event_attributes['waitlist_expiry']);
				unset($EM_Event->event_attributes['waitlist_limit']);
				unset($EM_Event->event_attributes['waitlist_booking_limit']);
			}
		}else{
			unset($EM_Event->event_attributes['waitlist']);
		}
		return $result;
	}
	
	/**
	 * Get waitlist meta loaded in the EM_Event->load_postdata() function
	 * @param array $array
	 * @return array
	 */
	public static function em_event_load_postdata_other_attributes( $array ){
		return array_merge($array, array('waitlist', 'waitlist_limit', 'waitlist_booking_limit', 'waitlist_expiry'));
	}
	
	/**
	 * @param bool $result
	 * @param EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_save_meta( $result, $EM_Event ){
		if( $result ){
			if( isset($EM_Event->event_attributes['waitlist']) ){
				update_post_meta( $EM_Event->post_id, '_waitlist', $EM_Event->event_attributes['waitlist']);
				foreach( array('waitlist_limit', 'waitlist_booking_limit', 'waitlist_expiry') as $key ){
					if( isset($EM_Event->event_attributes[$key]) ) {
						update_post_meta( $EM_Event->post_id, '_'.$key, $EM_Event->event_attributes[$key]);
					}else{
						delete_post_meta( $EM_Event->post_id, '_'.$key);
					}
				}
			}else{
				delete_post_meta( $EM_Event->post_id, '_waitlist');
				foreach( array('waitlist_limit', 'waitlist_booking_limit', 'waitlist_expiry') as $key ){
					delete_post_meta( $EM_Event->post_id, '_'.$key);
				}
			}
		}
		return $result;
	}
}
Events::init();