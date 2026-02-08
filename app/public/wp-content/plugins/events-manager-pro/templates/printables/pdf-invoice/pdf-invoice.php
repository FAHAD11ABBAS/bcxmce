<?php
/**
 * @var EM_Booking $EM_Booking
 */
$EM_Event = $EM_Booking->get_event();
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php esc_html_e('Event Booking', 'events-manager-pro'); ?></title>

	<style>
		<?php
		emp_locate_template('printables/pdf-printable.css', true);
		emp_locate_template('printables/pdf-invoice/pdf-invoice.css', true);
		echo "#content { font-family: ". esc_attr(get_option('dbem_bookings_pdf_font', 'dejavusans')) . ", Helvetica, Arial, sans-serif; }";
		?>
	</style>
</head>

<body>
	<div id="content">
		<?php
		$template = emp_locate_template('printables/pdf-invoice/part-header.php');
		include($template);
		$template = emp_locate_template('printables/pdf-invoice/part-event.php');
		include($template);
		$template = emp_locate_template('printables/pdf-invoice/part-invoice.php');
		include($template);
		?>
	</div>
</body>
</html>