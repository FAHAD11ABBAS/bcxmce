<?php
namespace EM;

class Dependent_Bookings {
	
	public static function init(){
		add_filter( 'em_event_load_postdata_other_attributes', '\EM\Dependent_Bookings::em_event_load_postdata_other_attributes', 10, 2 );
		add_filter( 'em_booking_validate', '\EM\Dependent_Bookings::em_booking_validate', 10, 2 );
		add_filter( 'em_multiple_booking_validate', '\EM\Dependent_Bookings::em_multiple_booking_validate', 10, 2 );
		add_action('em_events_admin_bookings_footer',array('\EM\Dependent_Bookings', 'event_bookings_meta_box'),20,1);
		add_action('em_event_save_meta_pre',array('\EM\Dependent_Bookings', 'em_event_save_meta_pre'),10,1);
		add_action('em_bookings_is_open', array('\EM\Dependent_Bookings', 'em_bookings_is_open'),10,2);
	}
	
	public static function em_event_load_postdata_other_attributes( $atts, $EM_Event ){
		$atts[] = 'dependent_event';
		return $atts;
	}
	
	/**
	 * @param boolean $result
	 * @param \EM_Booking $EM_Booking
	 */
	public static function em_booking_validate( $result, $EM_Booking ) {
		//check if the event has a designated parent event
		$EM_Event = $EM_Booking->get_event();
		if ( ! empty( $EM_Event->event_attributes['dependent_event'] ) ) {
			//get the parent event and make sure it exists
			$EM_Event = em_get_event( $EM_Event->event_attributes['dependent_event'] );
			if ( empty( $EM_Event->event_id ) ) {
				return $result;
			}
			$has_booked_parent_event = static::has_booked($EM_Event);
			//if parent booking not found, provide error message and fail validation
			if ( ! $has_booked_parent_event ) {
				$error = static::get_error_message( $EM_Event );
				$EM_Booking->add_error($error);
				$result = false;
			}
		}
		return $result;
	}
	
	public static function em_multiple_booking_validate( $result, $EM_Multiple_Booking ) {
		foreach ( $EM_Multiple_Booking->get_bookings() as $EM_Booking ) {
			/* @var \EM_Booking $EM_Booking */
			if ( !static::em_booking_validate( true, $EM_Booking ) ) {
				$result = false;
			}
		}
		return $result;
	}
	
	
	public static function em_bookings_is_open( $result, $EM_Bookings ){
		$EM_Event = $EM_Bookings->get_event();
		if ( ! empty( $EM_Event->event_attributes['dependent_event'] ) && !isset($EM_Event->event_attributes['dependent_event_result']) ) {
			//get the parent event and make sure it exists
			$EM_Event = em_get_event( $EM_Event->event_attributes['dependent_event'] );
			if ( empty( $EM_Event->event_id ) ) {
				return $result;
			}
			$result = static::has_booked($EM_Event);
			$EM_Event->event_attributes['dependent_event_result'] = $result;
			remove_action('em_booking_form_status_closed', 'em_booking_form_status_closed');
			add_action('em_booking_form_status_closed', array('\EM\Dependent_Bookings', 'em_booking_form_status_closed'),1,1);
		} elseif ( isset($EM_Event->event_attributes['dependent_event_result']) ){
			return !empty($EM_Event->event_attributes['dependent_event_result']);
		}
		return $result;
	}
	
	public static function em_booking_form_status_closed( $EM_Event ){
		if ( !empty($EM_Event->event_attributes['dependent_event']) && empty($EM_Event->event_attributes['dependent_event_result']) ) {
			//get the parent event and make sure it exists
			$EM_Event = em_get_event( $EM_Event->event_attributes['dependent_event'] );
			if ( !empty( $EM_Event->event_id ) ) {
				echo '<p>'. static::get_error_message($EM_Event) . '</p>';
				return true;
			}
		}
		em_booking_form_status_closed();
	}
	
	public static function has_booked( $EM_Event ){
		$has_booked_parent_event = false;
		//if user isn't logged in, we fail validation as they must log in so we can verify their booking of the parent event. If MB is enabled, we skip since they may have booked both events at once
		if ( ! is_user_logged_in() && ! get_option( 'dbem_multiple_bookings' ) ) {
			return false;
		}
		//check if user has previously boooked this parent event
		if ( is_user_logged_in() ) {
			//make sure we search all events 'owner'=>false and only booked or pending offline payment events 'status'=>'1,5'
			$EM_Bookings = \EM_Bookings::get( array(
				'person' => get_current_user_id(),
				'event'  => $EM_Event->event_id,
				'owner'  => false,
				'status' => '1',
				'scope'  => 'all',
			) );
			if ( ! empty( $EM_Bookings->bookings ) ) {
				$has_booked_parent_event = true;
			}
		}
		//check if this is booking mod and the event is already in the cart
		if ( ! $has_booked_parent_event && get_option( 'dbem_multiple_bookings' ) ) {
			$EM_Multiple_Booking = \EM_Multiple_Bookings::get_multiple_booking();
			foreach ( $EM_Multiple_Booking->get_bookings() as $em_booking ) {
				/* @var \EM_Booking $em_booking */
				if ( $em_booking->event_id == $EM_Event->event_id ) {
					$has_booked_parent_event = true;
					break;
				}
			}
		}
		return $has_booked_parent_event;
	}
	
	public static function get_error_message( $EM_Event ){
		if( !is_user_logged_in() ){
			$error = $EM_Event->output(get_option('dbem_booking_feedback_dependent_guest'));
		}else{
			$error = $EM_Event->output(get_option('dbem_booking_feedback_dependent'));
		}
		return $error;
	}
	
	/**
	 * Saves the custom booking form as post meta. This is done on em_event_save_meta_pre since at that point we know the post id and this will get passed onto recurrences as well.
	 * @param \EM_Event $EM_Event
	 */
	public static function em_event_save_meta_pre($EM_Event){
		if( !empty($EM_Event->duplicated) ) return; //if just duplicated, we ignore this and let EM carry over duplicate event data
		if( $EM_Event->event_rsvp && !empty($_REQUEST['dependent_event']) && is_numeric($_REQUEST['dependent_event']) ){
			$id = absint($_REQUEST['dependent_event']);
			$event = em_get_event($id);
			if( $event->event_id ) {
				update_post_meta( $EM_Event->post_id, '_dependent_event', $id );
			}
		}else{
			delete_post_meta($EM_Event->post_id, '_dependent_event');
		}
	}
	
	public static function event_bookings_meta_box(){
		//Get available coupons for user
		global $EM_Event;
		?>
		<div class="em-booking-options">
			<h4><?php esc_html_e('Dependent Events', 'events-manager-pro'); ?> </h4>
			<p><em><?php esc_html_e('You can choose to require a booking to have been made for a specific event before this event becomes available for booking. Note that users will need to have booked the dependent event as a registered user to gain access to this event.','em-pro'); ?></em></p>
			<div>
				<?php esc_html_e('Event ID','em-pro'); ?> :
				<input type="text" name="dependent_event" value="<?php if( !empty($EM_Event->event_attributes['dependent_event']) ) echo esc_attr($EM_Event->event_attributes['dependent_event']); ?>">
				<br>
				<em><?php esc_html_e('Enter the ID number of the event you would like to require a previous booking for before this event can be booked by users.'); ?></em>
			</div>
		</div>
		<?php
	}
}
Dependent_Bookings::init();