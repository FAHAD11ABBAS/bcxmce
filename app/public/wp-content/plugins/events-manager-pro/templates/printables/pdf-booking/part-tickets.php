<div class="tickets">
	<?php foreach( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ): ?>
		<?php foreach( $EM_Ticket_Bookings as $EM_Ticket_Booking ): ?>
			<div class="page-break"></div>
			<table class="ticket" cellpadding="0" cellspacing="0">
				<tr>
					<?php if( $EM_Event->get_image_url() ): ?>
						<td class="event-image">
							<div>
								<?php echo $EM_Event->output('#_EVENTIMAGE{250,0}'); ?>
							</div>
						</td>
					<?php endif; ?>
					
					<td class="ticket-info">
						<div class="dates-header">
							<table cellpadding="0" cellspacing="0">
								<tr>
									<td class="weekday"><?php echo strtoupper($EM_Event->start()->i18n('l')); ?></td>
									<td class="date"><?php echo strtoupper($EM_Event->start()->i18n('F jS')); ?></td>
									<td class="year"><?php echo $EM_Event->start()->i18n('Y'); ?></td>
								</tr>
							</table>
						</div>
						<p class="event-name"><?php echo esc_html($EM_Event->event_name); ?></p>
						<?php if( $EM_Event->event_end_date != $EM_Event->event_start_date ): ?>
						<p class="dates"><?php echo $EM_Event->output_dates(); ?></p>
						<?php endif; ?>
						<p class="times"><?php echo $EM_Event->output_times(); ?></p>
						
						<?php if( $EM_Event->has_location() || $EM_Event->has_event_location() ) : ?>
							<table class="location-separator" cellpadding="0" cellspacing="0">
								<tr><td><hr></td><td class="at">@</td><td><hr></td></tr>
							</table>
							<?php if( $EM_Event->has_location() ): ?>
							<table class="location" cellpadding="0" cellspacing="0">
								<tr>
									<td class="location-name"><?php echo esc_html($EM_Event->get_location()->location_name); ?></td>
									<td class="location-address">
										<?php
											$EM_Location = $EM_Event->get_location();
											$address = array( $EM_Location->location_address );
											if( !empty($EM_Location->location_address2) ) $address[] = $EM_Location->location_address2;
											if( !empty($EM_Location->city) ) $address[] = $EM_Location->city;
											echo esc_html(implode(', ', $address));
										?>
									</td>
								</tr>
							</table>
							<?php elseif( $EM_Event->has_event_location() ): ?>
							<div class="location" cellpadding="0" cellspacing="0">
								<p class="location-name"><?php echo $EM_Event->get_event_location()->output() ?></p>
							</div>
							<?php endif; ?>
						<?php endif; ?>
						
						<p>
							<?php echo $EM_Ticket_Booking->get_ticket()->description; ?>
						</p>
					</td>
					
					<td class="qr">
						<p class="ticket-name"><?php echo strtoupper($EM_Ticket_Booking->get_ticket()->name); ?></p>
						<div class="mini-info">
						<?php if( !empty($EM_Ticket_Booking->meta['attendee_name']) ) : ?>
							<p class="event-name"><?php echo esc_html($EM_Ticket_Booking->meta['attendee_name']); ?></p>
						<?php else: ?>
							<p class="event-name"><?php echo esc_html($EM_Event->event_name); ?></p>
							<p class="when"><?php echo $EM_Event->start()->i18n( 'd/m @ h:i A' ); ?></p>
						<?php endif; ?>
						</div>
						<?php if( EM\Feature::has('qr') ): ?>
						<img src="<?php echo \EM_Pro\QR::base64('ticket/'. $EM_Ticket_Booking->ticket_uuid)['src']; ?>" style="width:75px;">
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<div class="ticket-cutter"></div>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>
