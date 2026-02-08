<?php
/**
 * @var EM_Booking $EM_Booking
 */
$EM_Event = $EM_Booking->get_event();
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<titleEvents Manager Booking</title>

	<style>
		<?php
		emp_locate_template('printables/pdf-printable.css', true);
		emp_locate_template('printables/pdf-booking/pdf-booking.css', true);
		echo "#content { font-family: ". esc_attr(get_option('dbem_bookings_pdf_font', 'dejavusans')) . ", Helvetica, Arial, sans-serif; }";
		?>
	</style>
</head>

<body>
	<div id="content">
		<?php
		$template = emp_locate_template('printables/pdf-booking/part-header.php');
		include($template);
		?>
		<div class="booking">
			<?php
			$template = emp_locate_template('printables/pdf-booking/part-event.php');
			include($template);
			?>
			<?php
			$template = emp_locate_template('printables/pdf-booking/part-tickets-summary.php');
			include($template);
			?>
		</div>
		<?php
		$template = emp_locate_template('printables/pdf-booking/part-tickets.php');
		include($template);
		?>
	</div>
</body>
</html>