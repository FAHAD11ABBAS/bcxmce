<?php
/* @var EM_Booking $EM_Booking */
$bookings = $EM_Booking instanceof EM_Multiple_Booking ? $EM_Booking->bookings : array( $EM_Booking );
?>
<div class="invoice">
	
	<table class="items" cellpadding="0" cellspacing="0">
		<tr class="heading">
			<?php if( $EM_Booking instanceof EM_Multiple_Booking ): ?>
				<td><?php esc_html_e_emp('Event'); ?></td>
			<?php endif; ?>
			<td><?php esc_html_e_emp('Item'); ?></td>
			<td class="mid"><?php esc_html_e_emp('Quantity'); ?></td>
			<td><?php esc_html_e_emp('Price'); ?></td>
		</tr>
		
		<?php
		$i = count($EM_Booking->get_tickets_bookings());
		?>
		<tbody class="tickets">
		<?php foreach( $bookings as $booking ): ?>
			<?php foreach($booking->get_tickets_bookings() as $EM_Ticket_Booking): $i-- /* @var $EM_Ticket_Booking EM_Ticket_Booking */ ?>
				<tr class="item <?php if( $i === 0 ) echo 'last'; ?>">
					<?php if( $EM_Booking instanceof EM_Multiple_Booking ): ?>
					<td><?php echo esc_html($booking->get_event()->event_name); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html($EM_Ticket_Booking->get_ticket()->ticket_name); ?></td>
					<td class="mid"><?php echo $EM_Ticket_Booking->get_spaces(); ?></td>
					<td><?php echo $EM_Ticket_Booking->get_price(true); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
		</tbody>
		
		<tbody class="subtotals">
		<?php
		$colspan = $EM_Booking instanceof EM_Multiple_Booking ? 2:1;
		$price_summary = $EM_Booking->get_price_summary_array();
		//we should now have an array of information including base price, taxes and post/pre tax discounts
		?>
		<tr class="subheader">
			<td colspan="<?php echo $colspan; ?>"></td>
			<td><?php _e('Sub Total','events-manager'); ?></td>
			<td><?php echo $EM_Booking->get_price_base(true); ?></td>
		</tr>
		
		<?php if( count($price_summary['discounts_pre_tax']) > 0 ): ?>
			<?php foreach( $price_summary['discounts_pre_tax'] as $discount_summary ): ?>
				<tr>
					<td colspan="<?php echo $colspan; ?>"></td>
					<td><?php echo $discount_summary['name']; ?></td>
					<td>- <?php echo $discount_summary['amount']; ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php if( count($price_summary['surcharges_pre_tax']) > 0 ): ?>
			<?php foreach( $price_summary['surcharges_pre_tax'] as $surcharge_summary ): ?>
				<tr>
					<td colspan="<?php echo $colspan; ?>"></td>
					<td><?php echo $surcharge_summary['name']; ?></td>
					<td><?php echo $surcharge_summary['amount']; ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		
		<?php if( !empty($price_summary['taxes']['amount'])  ): ?>
			<tr>
				<td colspan="<?php echo $colspan; ?>"></td>
				<td><?php _e('Taxes','events-manager'); ?> ( <?php echo $price_summary['taxes']['rate']; ?> )</td>
				<td><?php echo $price_summary['taxes']['amount']; ?></td>
			</tr>
		<?php endif; ?>
		
		<?php if( count($price_summary['discounts_post_tax']) > 0 ): ?>
			<?php foreach( $price_summary['discounts_post_tax'] as $discount_summary ): ?>
				<tr>
					<td colspan="<?php echo $colspan; ?>"></td>
					<td><?php echo $discount_summary['name']; ?></td>
					<td>- <?php echo $discount_summary['amount']; ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		
		<?php if( count($price_summary['surcharges_post_tax']) > 0 ): ?>
			<?php foreach( $price_summary['surcharges_post_tax'] as $surcharge_summary ): ?>
				<tr>
					<td colspan="<?php echo $colspan; ?>"></td>
					<td><?php echo $surcharge_summary['name']; ?></td>
					<td><?php echo $surcharge_summary['amount']; ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
		
		<tr class="total line">
			<td colspan="<?php echo $colspan; ?>"></td>
			<td><?php _e('Total Price','events-manager'); ?></td>
			<td><?php echo $price_summary['total']; ?></td>
		</tr>
	</table>
</div>