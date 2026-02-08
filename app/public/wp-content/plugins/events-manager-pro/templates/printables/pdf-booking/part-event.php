<?php
/* @var EM_Booking $EM_Booking */
?>
<table class="event-info" cellpadding="0" cellspacing="0">
	<tr class="heading">
		<td colspan="3"><?php esc_html_e('Event Details', 'events-manager') ?></td>
	</tr>
	<tr>
		<td colspan="2">
			<?php
			$EM_Event = $EM_Booking->get_event();
			include( emp_locate_template('printables/pdf-part-event-details.php') );
			?>
		</td>
		<?php if( EM\Feature::has('qr') ): ?>
			<td class="qr">
				<img src="<?php echo \EM_Pro\QR::base64('booking/' . $EM_Booking->booking_uuid)['src']; ?>" style="width:99px;">
			</td>
		<?php endif; ?>
	</tr>
</table>