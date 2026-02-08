<?php
use EM_Pro\Attendance\Attendance, EM_Pro\Bookings_Manager_Frontend;
$EM_Ticket_Booking = Bookings_Manager_Frontend::$data['ticket_booking']; /* @var EM_Ticket_Booking $EM_Ticket_Booking */
?>
<div class="container py-2 nav-secondary">
	<div class="row">
		<div class="col text-start py-1">
			<a class="link-secondary" href="<?php echo Bookings_Manager_Frontend::get_endpoint_url('booking/'.$EM_Ticket_Booking->get_booking()->booking_uuid) ?>">
				< <?php esc_html_e('View Booking', 'events-manager'); ?>
			</a>
		</div>
		<?php if( get_option('dbem_bookings_attendance') ): ?>
		<div class="col text-end py-1">
			<form>
				<div class="form-check form-switch form-check-reverse">
					<input class="form-check-input" type="checkbox" role="switch" id="auto-checkin" value="1" checked>
					<label class="form-check-label" for="auto-checkin" data-bs-toggle="tooltip" data-bs-title="<?php esc_html_e('Enable this to automatically check someone in when scanning a QR and loading their ticket on this page.', 'events-manager'); ?>"><?php esc_html_e('Auto Check-In', 'em-pro'); ?></label>
				</div>
			</form>
		</div>
		<?php endif; ?>
	</div>
</div>

<main class="container-md m-auto ticket-single em-ticket-booking-<?php echo absint($EM_Ticket_Booking->ticket_booking_id); ?> ticket-<?php echo $EM_Ticket_Booking->ticket_uuid; ?>"><?php
	// get some info about the current booking, firstly, are they checked in ?
	if( get_option('dbem_bookings_attendance') ){
		$checkin_status = $checkin_result = Attendance::get_status($EM_Ticket_Booking);
		if( empty($_COOKIE['em_manual_checkin']) ){
			$result = Attendance::handle_action($EM_Ticket_Booking, 'checkin');
			$checkin_result = $result['status'];
			if( $result['result'] === false ) {
				$checkin_result = 'x';
			}else{
				$checkin_status = $result['status'];
			}
		}elseif( $checkin_status === 1 ){
			// if user is alredy checked in, warn user to avoid confusion that
			?>
			<div class="alert alert-primary" role="alert">
				<i class="bi-info-circle-fill"></i>
				<?php
				$last_timestamp = Attendance::get_status_timestamp($EM_Ticket_Booking);
				$timezone = $EM_Ticket_Booking->get_booking()->get_event()->get_timezone();
				$EM_DateTime = new EM_DateTime($last_timestamp, $timezone);
				$message = esc_html__('Already checked in on %s', 'em-pro');
				echo sprintf( $message, '<em>'.$EM_DateTime->i18n('M j @ h:i A').'</em>' );
				?>
			</div>
			<?php
		}
		?>
		<div class="checkin-status my-3 mb-4">
			<div class="checked-in attendance-status attendance-status-1 <?php if ($checkin_result !== 1) echo 'hidden'; ?> text-success">
				<i class="bi-check-circle"></i>
				<p class="display-6"><?php esc_html_e('Checked In', 'em-pro'); ?></p>
			</div>
			<div class="checked-out attendance-status attendance-status-0 <?php if ($checkin_result !== 0) echo 'hidden'; ?> text-danger" >
				<i class="bi-x-circle"></i>
				<p class="display-6"><?php esc_html_e('Checked Out', 'em-pro'); ?></p>
			</div>
			<div class="not-checked-in attendance-status attendance-status-null <?php if ($checkin_result !== null) echo 'hidden'; ?> text-black-50" >
				<i class="bi-x-circle"></i>
				<p class="display-6"><?php esc_html_e('Not Checked In', 'em-pro'); ?></p>
			</div>
			<div class="not-checked-in attendance-status attendance-status-null <?php if ($checkin_result !== 'x') echo 'hidden'; ?> text-danger" >
				<i class="bi-exclamation-circle"></i>
				<div class="alert alert-danger"><?php echo esc_html($result['message'] ?? ''); ?></div>
			</div>
		</div>
		<div class="actions d-grid gap-2 my-3">
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
		</div>
		<?php
	}
	?>
	<div class="accordion my-3 text-start" id="ticket-booking-details">
		<div class="accordion-item">
			<h2 class="accordion-header" id="ticket-details-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ticket-details" aria-expanded="true" aria-controls="ticket-details">
					<?php esc_html_e_emp('Ticket'); ?> : <?php echo $EM_Ticket_Booking->get_ticket()->ticket_name; ?>
				</button>
			</h2>
			<div id="ticket-details" class="accordion-collapse collapse" aria-labelledby="ticket-details-header" data-bs-parent="ticket-booking-details">
				<div class="accordion-body">
					<dl class="em-ticket-booking-info row">
						<?php
						// output info about the user
						$EM_Tickets_Bookings = $EM_Ticket_Booking->get_booking()->get_tickets_bookings();
						$attendee_data = EM_Attendees_Form::get_ticket_booking_attendee($EM_Ticket_Booking, true);
						$attendee_index = 0; // we're going to first get the index of this ticket, this will change for 3.0
						foreach( $EM_Tickets_Bookings[$EM_Ticket_Booking->ticket_id] as $uuid => $ticket_booking ){
							if( $uuid == $EM_Ticket_Booking->ticket_uuid ) continue;
							$attendee_index++;
						}
						foreach( $attendee_data as $attendee_label => $attendee_value ){
							?>
							<dt class="col-sm-3"><?php echo $attendee_label ?></dt>
							<dd class="col-sm-9"><?php echo $attendee_value; ?></dd>
							<?php
						}
						?>
					</dl>
				</div>
			</div>
		</div>
		<div class="accordion-item">
			<h2 class="accordion-header" id="booking-details-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#booking-details" aria-expanded="true" aria-controls="booking-details">
					<?php esc_html_e_emp('Booking Details'); ?>
				</button>
			</h2>
			<div id="booking-details" class="accordion-collapse collapse" aria-labelledby="booking-details-header" data-bs-parent="booking-details">
				<div class="accordion-body">
					<dl class="em-booking-single-info row">
						<dt class="col-sm-3"><?php esc_html_e_emp('Booking Status'); ?></dt>
						<dd class="col-sm-9"><?php echo esc_html($EM_Ticket_Booking->get_booking()->get_status()); ?></dd>
						<?php
						$user = $EM_Ticket_Booking->get_booking()->get_person();
						$EM_Form = \EM_Booking_Form::get_form($EM_Ticket_Booking->get_booking()->event_id, $EM_Ticket_Booking->get_booking());
						foreach($EM_Form->form_fields as $field){
							if( $field['type'] != 'html' ){
								?>
								<dt class="col-sm-3"><?php echo esc_html($field['label']); ?></dt>
								<dd class="col-sm-9">
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
		<?php if( get_option('dbem_bookings_attendance') ): ?>
		<div class="accordion-item">
			<h2 class="accordion-header" id="attendance-history-header">
				<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#attendance-history" aria-expanded="true" aria-controls="attendance-history">
					<?php esc_html_e('Attendance History', 'em-pro'); ?>
				</button>
			</h2>
			<div id="attendance-history" class="accordion-collapse collapse" aria-labelledby="attendance-history-header" data-bs-parent="attendance-history">
				<div class="accordion-body">
					<table class="em-attendance-history table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e_emp('When'); ?></th>
								<th scope="col"><?php esc_html_e_emp('Action'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$attendance_history = Attendance::get_history($EM_Ticket_Booking);
						if( !empty($attendance_history ) ) {
							foreach ($attendance_history as $item) {
								$status_color = 'text-muted';
								if( $item['status'] == 1 ){
									$status_color = 'text-success';
								}elseif( $item['status'] == 0 ){
									$status_color = 'text-danger';
								}
								?>
								<tr>
									<td><span data-bs-toggle="tooltip" data-bs-title="<?php echo $item['date']; ?>"><?php echo $item['time']; ?></span></td>
									<td class="<?php echo $status_color; ?>"><?php echo $item['action']; ?></td>
								</tr>
								<?php
							}
						}else{
							echo '<tr><td colspan="2"><em class="text-muted">'. esc_html__('No attendance activity.', 'em-pro') . '</em></td></tr>';
						}
						?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>
</main>