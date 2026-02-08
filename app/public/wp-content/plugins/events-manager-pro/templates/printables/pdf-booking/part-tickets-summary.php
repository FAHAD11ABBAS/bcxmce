<table class="items" cellpadding="0" cellspacing="0">
	<tr class="heading">
		<td><?php esc_html_e_emp('Item'); ?></td>
		<td class="mid"><?php esc_html_e_emp('Spaces'); ?></td>
	</tr>
	
	<?php
	$i = count($EM_Booking->get_tickets_bookings());
	?>
	<tbody class="tickets">
	<?php foreach($EM_Booking->get_tickets_bookings() as $EM_Ticket_Booking): $i-- /* @var $EM_Ticket_Booking EM_Ticket_Booking */ ?>
		<tr class="item <?php if( $i === 0 ) echo 'last'; ?>">
			<td><?php echo $EM_Ticket_Booking->get_ticket()->ticket_name; ?></td>
			<td><?php echo $EM_Ticket_Booking->get_spaces(); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>

</table>