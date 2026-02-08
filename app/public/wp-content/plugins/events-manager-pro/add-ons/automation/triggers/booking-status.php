<?php
namespace EM\Automation\Triggers;

class Booking_Status extends Trigger {
	
	public static $type = 'booking-status';
	public static $context = 'bookings';
	public static $listener = array(
		'em_booking_status_changed' => 2, // status changes, 2 accepted args
		'em_bookings_add' => 2, // new bookings, 2 accepted args
	);
	
	public function run( $runtime_data = array() ) {
		// extract runtime data
		if( $runtime_data[0] instanceof \EM_Booking ) {
			$EM_Booking = $runtime_data[0]; /* @var \EM_Booking $EM_Booking */
		} else {
			if( empty($runtime_data[0]) || !(!empty($runtime_data[1]) && $runtime_data[1] instanceof \EM_Booking) ) return; // not successful
			$EM_Booking = $runtime_data[1]; /* @var \EM_Booking $EM_Booking */
		}
		// fired when a booking status has changed or saved first time, params passed ore that of the $listener hooks which are a $result and $EM_Booking object.
		$from = !empty($this->trigger_data['status_from']) ?  $this->trigger_data['status_from'] : array();
		// if booking has status we are looking for, then it was either changed or saved to that status
		if( empty($from) || in_array( (string) $EM_Booking->previous_status, $this->trigger_data['status_from']) || ($EM_Booking->previous_status === false && in_array('new', $this->trigger_data['status_from'])) ){
			// em_booking_save
			$this->fire( $this->filter($EM_Booking) );
		}
	}
	
	public static function get_name(){
		return esc_html__("Booking Status Change", 'em-pro');
	}
	
	public static function get_description(){
		return esc_html__('Triggered when a booking status has changed from one status to another, or newly created booking into any status type.', 'em-pro');
	}
}
Booking_Status::init();