<?php
use EM\Bookings\RSVP\Endpoint;
// get some info about the current booking, firstly, are they checked in ?
$EM_Booking = Endpoint::$data['booking']; /* @var EM_Booking $EM_Booking */
$statuses = EM_Booking::get_rsvp_statuses();
?>
	<?php if( $EM_Booking->get_rsvp_status() !== null ): ?>
	<p class="lead mb-4 text-center text-success"><?php esc_html_e('You have already RSVPed', 'events-manager'); ?></p>
	<?php endif; ?>

	<div class="card my-4">
		<div class="card-body text-align-left">
			<h5 class="card-title pb-2"><?php echo esc_html($EM_Booking->get_event()->event_name); ?></h5>
			<p>
				<i class="bi bi-calendar-event pe-1"></i>
				<?php echo esc_html($EM_Booking->get_event()->output('#_EVENTDATES')); ?>
			</p>
			<p>
				<i class="bi bi-alarm pe-1"></i>
				<?php echo esc_html($EM_Booking->get_event()->output('#_EVENTTIMES')); ?>
			</p>
			<?php if( $EM_Booking->get_event()->has_location() ): ?>
				<p>
					<i class="bi bi-geo-alt"></i>
					<?php echo esc_html($EM_Booking->get_event()->get_location()->location_name); ?>
				</p>
			<?php elseif ( $EM_Booking->get_event()->has_event_location() ) : ?>
				<p>
					<i class="bi bi-geo-alt"></i>
					<?php echo esc_html($EM_Booking->get_event()->get_event_location()->output()); ?>
				</p>
			<?php endif; ?>
			<!-- <h6 class="card-title pt-2 pb-1"><?php esc_html_e('Booking Details', 'em-pro'); ?></h6> -->
			<hr>
			<div class="py-2 d-flex">
				<div class="em-booking-single-info row" style="display: grid; grid-gap: 10px; grid-template-columns: max-content auto">

					<span class="text-muted"><?php esc_html_e('Spaces Booked', 'events-manager'); ?></span>
					<span><?php echo esc_html( $EM_Booking->get_spaces() ); ?></span>

					<?php if( get_option('dbem_bookings_approvals') || $EM_Booking->booking_status > 1 ): ?>
						<span class="text-muted"><?php esc_html_e_emp('Booking Status'); ?></span>
						<span><?php echo esc_html($EM_Booking->get_status()); ?></span>
					<?php endif; ?>
					
					<?php
						$class = 'text-muted';
						switch ( $EM_Booking->get_rsvp_status() ){
							case 0:
								$class = 'text-danger';
								break;
							case 1:
								$class = 'text-success';
								break;
						}
					?>
					<span class="text-muted"><?php esc_html_e_emp('RSVP Status'); ?></span>
					<span class="<?php echo $class ?>"><?php echo esc_html($EM_Booking->get_rsvp_status( true )); ?></span>
				</div>
			</div>
		</div>
	</div>

	<?php if( $EM_Booking->get_rsvp_status() === null || $EM_Booking->can_change_rsvp() ): ?>
		<p class="lead mb-4 text-center"><?php esc_html_e('Will you be attending?', 'events-manager'); ?></p>
		<div class="d-grid gap-2 d-flex justify-content-evenly">
			<?php if( $EM_Booking->can_rsvp(1) !== false ): $i = 1 ?>
			<button type="button" class="btn btn-outline-success px-4 rsvp-action <?php if( $EM_Booking->can_rsvp($i) === null ) echo 'selected'; ?>"
			        data-action="<?php echo esc_attr($statuses[$i]->action); ?>"
			        data-id="<?php echo esc_attr($EM_Booking->booking_uuid); ?>"
			        data-nonce="<?php echo wp_create_nonce('rsvp_'. $statuses[$i]->action ); ?>">
				<i class="loaded bi-check-circle me-1"></i>
				<span class="loaded"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
				<span class="loading-content spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
				<span class="loading-content"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
			</button>
			<?php endif; ?>
			<?php if( $EM_Booking->can_rsvp(0) !== false ): $i = 0 ?>
				<button type="button" class="btn btn-outline-danger px-4 rsvp-action <?php if( $EM_Booking->can_rsvp($i) === null ) echo 'selected'; ?>"
				        data-action="<?php echo esc_attr($statuses[$i]->action); ?>"
				        data-id="<?php echo esc_attr($EM_Booking->booking_uuid); ?>"
				        data-nonce="<?php echo wp_create_nonce('rsvp_'. $statuses[$i]->action ); ?>">
					<i class="loaded bi-x-circle me-1"></i>
					<span class="loaded"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
					<span class="loading-content spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
					<span class="loading-content"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
				</button>
			<?php endif; ?>
			<?php if( $EM_Booking->can_rsvp(2) !== false ): $i = 2 ?>
				<button type="button" class="btn btn-outline-secondary px-4 rsvp-action <?php if( $EM_Booking->can_rsvp($i) === null ) echo 'selected'; ?>"
				        data-action="<?php echo esc_attr($statuses[$i]->action); ?>"
				        data-id="<?php echo esc_attr($EM_Booking->booking_uuid); ?>"
				        data-nonce="<?php echo wp_create_nonce('rsvp_'. $statuses[$i]->action ); ?>">
				<i class="loaded bi-question-circle me-1"></i>
				<span class="loaded"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
				<span class="loading-content spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
				<span class="loading-content"><?php echo esc_html( $statuses[$i]->label_answer ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>