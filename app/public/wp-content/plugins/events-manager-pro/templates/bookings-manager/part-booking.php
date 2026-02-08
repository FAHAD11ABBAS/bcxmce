<?php
use EM_Pro\Attendance\Attendance;
// get some info about the current booking, firstly, are they checked in ?
$checked_in = true;
$EM_Booking = \EM_Pro\Bookings_Manager_Frontend::$data['booking']; /* @var EM_Booking $EM_Booking */
?>
<main class="container-fluid m-auto text-start">

	<div class="accordion my-3" id="booking-data">
		<div class="accordion-item">
			<h2 class="accordion-header" id="booking-details-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#booking-details" aria-expanded="true" aria-controls="booking-details">
					<?php esc_html_e_emp('Booking Details'); ?>
				</button>
			</h2>
			<div id="booking-details" class="accordion-collapse collapse" aria-labelledby="booking-details-header" data-bs-parent="booking-details">
				<div class="accordion-body">
					<dl class="em-booking-single-info row">
						<dt class="col-sm-4"><?php esc_html_e_emp('Booking Status'); ?></dt>
						<dd class="col-sm-8"><?php echo esc_html($EM_Booking->get_status()); ?></dd>
						<?php
						$user = $EM_Booking->get_person();
						$EM_Form = \EM_Booking_Form::get_form($EM_Booking->event_id, $EM_Booking);
						foreach($EM_Form->form_fields as $field){
							if( $field['type'] != 'html' ){
								?>
								<dt class="col-sm-4"><?php echo esc_html($field['label']); ?></dt>
								<dd class="col-sm-8">
									<?php
									$field_id = $field['fieldid'];
									if( !empty($user->$field_id) ){
										//user profile is freshest, using this
										echo is_array($user->$field_id) ? implode(', ', $user->$field_id) : $user->$field_id;
									}elseif( !empty($EM_Booking->booking_meta['registration'][$field['fieldid']]) ){
										//reg fields only exist as reg fields
										echo $EM_Form->get_formatted_value($field, $EM_Booking->booking_meta['registration'][$field['fieldid']]);
									}elseif( !empty($EM_Booking->booking_meta['booking'][$field['fieldid']]) ){
										//match for custom field value
										echo $EM_Form->get_formatted_value($field, $EM_Booking->booking_meta['booking'][$field['fieldid']]);
									}else{
										echo '<span class="text-muted"><i>'. esc_html__('n/a', 'em-pro') . '</i></span>';
									}
									?>
								</dd>
								<?php
							}
						}
						?>
					</dl>
				</div>
			</div>
		</div>
	</div>
	<div class="actions d-grid gap-2 my-3">
		<a href="<?php echo $EM_Booking->get_admin_url() ?>" class="btn btn-outline-primary">
			<?php esc_html_e('Go To Dashboard', 'em-pro'); ?>
		</a>
	</div>
	
	<?php foreach( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ): ?>
		<p class="fs-6 px-1 fw-light">
			<?php
				esc_html_e_emp('Ticket'); ?> : <?php echo esc_html($EM_Ticket_Bookings->get_ticket()->ticket_name);
				$i = 0;
			?>
		</p>
		<div class="accordion my-3 text-start" id="ticket-booking-data">
			<?php foreach( $EM_Ticket_Bookings as $EM_Ticket_Booking ) : ?>
				<?php
				$i++;
				$id = $EM_Ticket_Bookings->ticket_id . '-' . $i;
				$ticket_url = \EM_Pro\Bookings_Manager_Frontend::get_endpoint_url('/ticket/'.$EM_Ticket_Booking->ticket_uuid);
				if( get_option('dbem_bookings_attendance') ) {
					$checkin_status = Attendance::get_status($EM_Ticket_Booking);
				}
				?>
				<div class="accordion-item em-ticket-booking-<?php echo absint($EM_Ticket_Booking->ticket_booking_id); ?> ticket-<?php echo $EM_Ticket_Booking->ticket_uuid; ?>">
					<h2 class="accordion-header" id="ticket-details-header-<?php echo $id; ?>">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ticket-details-<?php echo $id; ?>" aria-expanded="true" aria-controls="ticket-details-<?php echo $id; ?>">
							<?php if( get_option('dbem_bookings_attendance') ) : ?>
							<i class="attendance-status-1 bi-check-circle text-success me-2 <?php if( $checkin_status !== 1 ) echo 'hidden' ?>"></i>
							<i class="attendance-status-0 bi-x-circle text-danger me-2 <?php if( $checkin_status !== 0 ) echo 'hidden' ?>"></i>
							<i class="attendance-status-null bi-x-circle text-muted me-2 <?php if( $checkin_status !== null ) echo 'hidden' ?>"></i>
							<?php endif; ?>
							<?php
								echo sprintf(esc_html__emp('Attendee %s'), $i);
							?>
						</button>
					</h2>
					<div id="ticket-details-<?php echo $id; ?>" class="accordion-collapse collapse" aria-labelledby="ticket-details-header-<?php echo $id; ?>" data-bs-parent="ticket-booking-details">
						<div class="accordion-body">
							<dl class="em-ticket-booking-info row">
								<?php
								// output info about the user
								foreach( EM_Attendees_Form::get_ticket_booking_attendee($EM_Ticket_Booking, true) as $attendee_label => $attendee_value ){
									?>
									<dt class="col-sm-3"><?php echo $attendee_label ?></dt>
									<dd class="col-sm-9"><?php echo $attendee_value; ?></dd>
									<?php
								}
								?>
							</dl>
							<?php if( get_option('dbem_bookings_attendance') ) : ?>
							<button type="button" class="btn btn-outline-danger attendance-action attendance-status-1 <?php if ($checkin_status !== 1) echo 'hidden'; ?>" data-action="checkout" data-id="<?php echo esc_attr($EM_Ticket_Booking->ticket_booking_id); ?>">
								<i class="loaded bi-check-circle me-1"></i>
								<span class="loaded"><?php esc_html_e('Check Out', 'em-pro'); ?></span>
								<span class="loading-content spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
								<span class="loading-content"><?php esc_html_e('Loading...', 'em-pro'); ?></span>
							</button>
							<button type="button" class="btn btn-outline-success attendance-action attendance-status-0  attendance-status-null <?php if ($checkin_status === 1) echo 'hidden'; ?>" data-action="checkin" data-id="<?php echo esc_attr($EM_Ticket_Booking->ticket_booking_id); ?>">
								<i class="loaded bi-x-circle me-1"></i>
								<span class="loaded"><?php esc_html_e('Check In', 'em-pro'); ?></span>
								<span class="loading-content spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
								<span class="loading-content"><?php esc_html_e('Loading...', 'em-pro'); ?></span>
							</button>
							<?php endif; ?>
							<a href="<?php echo $ticket_url ?>" class="btn btn-outline-secondary">
								<i class="bi-info-circle me-2"></i>
								<?php esc_html_e('View Ticket Details', 'em-pro'); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</main>