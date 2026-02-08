<?php
namespace EM\Bookings\RSVP\Policies;
use EM\Bookings\RSVP\Policies;

class Events {
	
	public static function init() {
		// placeholders
		add_filter('em_event_output_show_condition', array( static::class, 'em_event_output_show_condition'), 1, 4);
		add_filter('em_event_output_placeholder', array( static::class, 'em_event_output_placeholder'),1,3);
		// event saving
		if ( get_option('dbem_bookings_rsvp_policy_events') ) {
			add_filter('em_events_admin_bookings_footer', array( static::class, 'em_events_admin_bookings_footer'), 10, 1);
			add_filter('em_event_load_postdata_other_attributes', array( static::class, 'em_event_load_postdata_other_attributes'), 10, 1);
			add_filter('em_event_get_post_meta', array( static::class, 'em_event_get_post_meta'), 10, 2);
			add_filter('em_event_save_meta', array( static::class, 'em_event_save_meta'), 10, 2);
		}
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
			case 'has_rsvp_policy' : // event has rsvp plicy enabled
				$show = Policies::has_policy( $EM_Event );
				break;
			case 'no_rsvp_policy' : // event has waitlist enabled
				$show = !Policies::has_policy( $EM_Event );
				break;
			case 'is_rsvp_policy_open' : // event has waitlist enabled
				$show = Policies::has_policy( $EM_Event ) && Policies::get_policy( $EM_Event )->type == 'open';
				break;
			case 'is_rsvp_policy_flexible' : // event has waitlist enabled
				$show = Policies::has_policy( $EM_Event ) && Policies::get_policy( $EM_Event )->type == 'flexible';
				break;
			case 'is_rsvp_policy_strict' : // event has waitlist enabled
				$show = Policies::has_policy( $EM_Event ) && Policies::get_policy( $EM_Event )->type == 'strict';
				break;
			case 'is_rsvp_policy_active' : // event has waitlist enabled
				$show = Policies::is_policy_active( $EM_Event );
				break;
		}
		return $show;
	}
	
	/**
	 * @param string $replace
	 * @param \EM_Event $EM_Event
	 * @param string $result
	 * @return string
	 */
	public static function em_event_output_placeholder($replace, $EM_Event, $result){
		switch( $result ){
			case '#_EVENT_RSVP_DEADLINE_DATE':
				$replace = Policies::get_policy_deadline( $EM_Event )->formatDefault( false );
				break;
			case '#_EVENT_RSVP_DEADLINE_TIME':
				$replace = Policies::get_policy_deadline( $EM_Event )->i18n( em_get_hour_format() );
				break;
			case '#_EVENT_RSVP_DEADLINE_TIMEZONE':
				$replace = Policies::get_policy_deadline( $EM_Event )->getTimezone()->getName();
				break;
			case '#_EVENT_RSVP_POLICY_TYPE':
				$replace = Policies::get_policy( $EM_Event )->type;
				break;
			case '#_EVENT_RSVP_POLICY_NAME':
				$replace = Policies::get_policy( $EM_Event )->label;
				break;
			case '#_EVENT_RSVP_POLICY_DESCRIPTION':
				$replace = Policies::get_policy( $EM_Event )->description;
				break;
		}
		return $replace;
	}
	
	/**
	 * @param \EM_Event $EM_Event
	 * @return void
	 */
	public static function em_events_admin_bookings_footer( $EM_Event ){
		?>
		<div class="em-waitlist-options em-booking-options">
			<h4><?php echo esc_html(sprintf(emp__('%s Options'), __('RSVP Policy', 'em-pro'))); ?></h4>
			<p>
				<?php esc_html_e('When your event is fully booked, you can enable an RSVP policy so that users that have not confirmed their booking within a certain timeframe of the event start risk having their booking cancelled or left open to cancellation if someone else wants to book.', 'em-pro'); ?>
				<br><em><?php esc_html_e('If left to default setting, if RSVP policy or policy type is enabled or disabled site-wide the event will dynamically inherit those settings.', 'em-pro'); ?></em>
			</p>
			<p>
				<?php
					if( isset($EM_Event->event_attributes['rsvp_policy']) ){
						$checked = !empty($EM_Event->event_attributes['waitlist']);
					}else{
						$checked = get_option('dbem_waitlists_events_default') == 1;
					}
				?>
				<label for="rsvp_policy"><?php esc_html_e_emp('Enforce RSVP Policy?'); ?></label>
				<select name="rsvp_policy" id="rsvp_policy">
					<?php
						$default = get_option('dbem_bookings_rsvp_policy_default') ? emp__('Yes', '') : emp__('No', '');
						$policy = 'default';
						if( isset($EM_Event->event_attributes['rsvp_policy']) ){
							$policy = absint($EM_Event->event_attributes['rsvp_policy']);
						}
					?>
					<option value="default" <?php if( $policy === 'default' ) echo 'selected'; ?>><?php echo sprintf(esc_html__('%s (Site Default)', 'em-pro'), $default); ?></option>
					<option value="1" <?php if( $policy === 1 ) echo 'selected'; ?>><?php echo emp__('Yes', ''); ?></option>
					<option value="0" <?php if( $policy === 0 ) echo 'selected'; ?>><?php echo emp__('No', ''); ?></option>
				</select>
			</p>
			<p>
				<?php
					$default = get_option('dbem_bookings_rsvp_policy_type', 'open');
					$defaults = array(
						'strict' => esc_html__('Strict', 'em-pro'),
						'flexible' => esc_html__('Flexible', 'em-pro'),
						'open' => esc_html__('Open', 'em-pro'),
					);
					$policy_type = 'default';
					if( isset($EM_Event->event_attributes['rsvp_policy_type']) ){
						$policy_type = $EM_Event->event_attributes['rsvp_policy_type'];
					}
				?>
				<label for="rsvp_policy_type"><?php esc_html_e_emp('RSVP Policy Type'); ?></label>
				<select name="rsvp_policy_type" id="rsvp_policy_type">
					<option value="default" <?php if( $policy_type === 'default' ) echo 'selected'; ?>><?php echo sprintf(esc_html__('%s (Site Default)', 'em-pro'), $defaults[$default]); ?></option>
					<option value="open" <?php if( $policy_type === 'open' ) echo 'selected'; ?>>
						<?php esc_html_e('Open', 'em-pro'); ?> -
						<?php esc_html_e('RSVPing attendance is optional, but no action is taken in any case.', 'em-pro' ); ?>
					</option>
					<option value="flexible" <?php if( $policy_type === 'flexible' ) echo 'selected'; ?>>
						<?php esc_html_e('Flexible', 'em-pro'); ?> -
						<?php esc_html_e('Bookings are not cancelled, but spaces available for booking. If booked, unconfirmed bookings get cancelled.', 'em-pro'); ?>
					</option>
					<option value="strict" <?php if( $policy_type === 'strict' ) echo 'selected'; ?>>
						<?php esc_html_e('Strict', 'em-pro'); ?> -
						<?php esc_html_e('Bookings are cancelled after cutoff time is reached.', 'em-pro'); ?>
					</option>
				</select>
			</p>
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
		if( isset($_REQUEST['rsvp_policy']) && $_REQUEST['rsvp_policy'] !== 'default' ) {
			$EM_Event->event_attributes['rsvp_policy'] = absint($_REQUEST['rsvp_policy']) ;
		}else{
			unset($EM_Event->event_attributes['rsvp_policy']);
		}
		if( isset($_REQUEST['rsvp_policy_type']) && in_array($_REQUEST['rsvp_policy_type'], array('open', 'flexible', 'strict')) ) {
			$EM_Event->event_attributes['rsvp_policy_type'] = sanitize_text_field($_REQUEST['rsvp_policy_type']);
		}else{
			unset($EM_Event->event_attributes['rsvp_policy_type']);
		}
		return $result;
	}
	
	/**
	 * Get policy type meta loaded in the EM_Event->load_postdata() function
	 * @param array $array
	 * @return array
	 */
	public static function em_event_load_postdata_other_attributes( $array ){
		return array_merge($array, array('rsvp_policy', 'rsvp_policy_type'));
	}
	
	/**
	 * @param bool $result
	 * @param EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_save_meta( $result, $EM_Event ){
		if( $result ){
			if ( isset($EM_Event->event_attributes['rsvp_policy']) ) {
				update_post_meta( $EM_Event->post_id, '_rsvp_policy', $EM_Event->event_attributes['rsvp_policy']);
			} else {
				delete_post_meta( $EM_Event->post_id, '_rsvp_policy');
			}
			if( isset($EM_Event->event_attributes['rsvp_policy']) || get_option('dbem_bookings_rsvp_policy_default') ){
				foreach( array('rsvp_policy_type') as $key ){
					if( isset($EM_Event->event_attributes[$key]) ) {
						update_post_meta( $EM_Event->post_id, '_'.$key, $EM_Event->event_attributes[$key]);
					}else{
						delete_post_meta( $EM_Event->post_id, '_'.$key);
					}
				}
			}
		}
		return $result;
	}
}
Events::init();