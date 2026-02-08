
<table class="information" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<?php
			$output = $EM_Booking->output(get_option('dbem_bookings_pdf_billing_details'));
			$output = preg_replace('/\n[ \t]+\n/', "\n", $output);
			echo nl2br($output);
			?>
		</td>
		
		<td>
			<?php echo nl2br( get_option('dbem_bookings_pdf_business_details') ); ?>
		</td>
	</tr>
</table>