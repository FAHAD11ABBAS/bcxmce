<?php
/**
* This displays the content of the #_BOOKINGATTENDEES placeholder.
* You can override the default display settings pages by copying this file to yourthemefolder/plugins/events-manager-pro/placeholders/ and modifying it however you need.
* For more information, see http://wp-events-plugin.com/documentation/using-template-files/
*
* @var EM_Booking $EM_Booking
*/
$EM_Tickets_Bookings = $EM_Booking->get_tickets_bookings();
$attendee_datas = EM_Attendees_Form::get_booking_attendees($EM_Booking);
foreach($EM_Tickets_Bookings->tickets_bookings as $EM_Ticket_Bookings ){
	//Display ticket info
	echo "\r\n". emp__('Ticket').' - '. $EM_Ticket_Bookings->get_ticket()->ticket_name ."\r\n". '-----------------------------';
	//display a row for each space booked on this ticket
	$i = 1;
	foreach( $EM_Ticket_Bookings->get_ticket_bookings() as $EM_Ticket_Booking ){ /* @var EM_Ticket_Booking $EM_Ticket_Booking */
		echo "\r\n\r\n".  sprintf(__('Attendee %s', 'em-pro'), $i) ."\r\n". '------------';
		ob_start();
		$template = emp_locate_template('formats/ticket_booking_output.php');
		include($template);
		$format = ob_get_clean();
		echo $EM_Ticket_Booking->output( $format );
		$i++;
	}
	echo  "\r\n";
}