<?php
namespace EM\Bookings;

class RSVP {
	
	/**
	 * Handles a confirmation or cancellation and returns an array with result and message.
	 * @param \EM_Booking $EM_Booking
	 * @param string $action 'checkin' or 'checkout', defaults to 'checkin' if not supplied
	 * @return array
	 */
	public static function handle_action( $EM_Booking, $action = null){
		$result = array();
		if( $action ) {
			if( $action == 'confirm' ){
				$action = static::confirm( $EM_Booking );
				$result['result'] = true; // we'll set this back lower down
				$result['status'] = 1;
				if( $action === true ){
					$result['message'] = esc_html__('You have confirmed your booking!', 'em-pro');
				}elseif( $action === null ){
					$result['result'] = false;
					$result['message'] = esc_html__('You have already confirmed your booking.', 'em-pro');
				}
			}
		}elseif( $EM_Booking->can_cancel() ){
			// we'll assume we're checking in
			$EM_Booking->can_cancel();
			$result['result'] = true; // we'll set this back lower down
			$result['status'] = 0;
			if( $action === true ){
				$result['message'] = esc_html__('Your booking has been cancelled.', 'em-pro');
			}elseif( $action === null ){
				$result['result'] = false;
				$result['message'] = esc_html__('You have already cancelled your booking.', 'em-pro');
			}
		}
		if( $action === false ){
			$result['status'] = static::get_status($EM_Booking);
			$result['result'] = false;
			if( in_array( $EM_Booking->booking_status, array(2,3) ) ){
				$result['message'] = esc_html__('Could not complete action because booking is cancelled or rejected.', 'em-pro');
			}else {
				$result['message'] = esc_html__('Could not complete action due to an error.', 'em-pro');
			}
		}
		$result['id'] = $EM_Booking->ticket_booking_id;
		$result['uuid'] = $EM_Booking->ticket_uuid;
		return $result;
	}
	
}