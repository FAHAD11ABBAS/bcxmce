<?php
namespace EM\Bookings;
use EM\Archetypes;
use EM_Booking;

class Move_Booking {
	public static function init() {
		// add form to move booking
		add_action( 'em_bookings_admin_booking_event_row', [ static::class, 'em_bookings_admin_booking_event_row' ], 10, 1 );
		add_action( 'wp_ajax_em_move_booking', [ static::class, 'em_move_booking' ] );
		add_action( 'wp_ajax_em_get_event_timeslots', [ static::class, 'em_get_event_timeslots' ] );
	}

	/**
	 * @param EM_Booking $EM_Booking
	 *
	 * @return void
	 */
	public static function em_bookings_admin_booking_event_row( $EM_Booking ) {
		// add dropdown that gets upcoming dates of an event
		$EM_Event = $EM_Booking->get_event();
		// consider timeslots
		$has_timeslots = $EM_Event->is_timeslot(); // if it's a timeslot, then the event itself has timeslots
		if ( $has_timeslots ) {
			$EM_Event = $EM_Event->get_parent();
		}
		if ( $EM_Event->is_recurrence() || $has_timeslots ) {
			// calculate the number of ticket spaces required so we can check availability per recurrence quickly
			?>
			<tbody class="em-booking-admin-event-date-change">
				<tr class="em-booking-admin-event-date-change-form hidden">
					<th><?php esc_html_e_emp('Date/Time'); ?></th>
					<td>
						<form action="" method="post" id="em-move-booking-<?php echo $EM_Booking->booking_id; ?>">
							<?php if ( $EM_Event->is_recurrence() ) : ?>
							<select name="event_id" data-nonce="<?php echo wp_create_nonce('em_get_timeslots_' . $EM_Booking->booking_id); ?>">
								<option value="0" selected="selected"><?php echo $EM_Booking->get_event()->start()->formatDefault(); ?></option>
								<?php
									$spaces = [];
									foreach ( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ) {
										$ticket_id = $EM_Ticket_Bookings->get_ticket()->ticket_parent;
										$spaces[ $ticket_id ] = $EM_Ticket_Bookings->get_spaces();
									}
									$events = [];
									// we get recurrence dates first, then we get timeslots
									$recurrences = $EM_Event->get_recurring_event()->get_recurrence_sets()->get_recurrences();
									foreach ( $recurrences as $recurrence ) {
										// check availability if timeslots aren't in play (so, only the date is checked)
										if ( !$has_timeslots ) {
											$event = em_get_event( $recurrence['event_id'] );
											$availability = static::check_availability( $spaces, $event );
											$booked = $availability ? '' : ( $availability === false ? ' data-booked' : '' );
											$disabled = $availability === null || $event->event_id === $EM_Booking->event_id ? ' disabled' : '';
											$events[ $event->event_id ] = [
												'booked' => $booked,
												'disabled' => $disabled,
												'date' => $event->start()->formatDefault(),
											];
											if ( $availability === false ) {
												$events[ $event->event_id ]['date'] = '* ' . $events[ $event->event_id ]['date'];
											}
										} else {
											// we just need the id and date, so we can just use the supplied array data
											$events[ $recurrence['event_id'] ] = [
												'booked' => '',
												'disabled' => '',
												'date' => \EM_DateTime::create( $recurrence['start'] )->formatDefault( false ),
											];
										}
									}
								?>
								<?php foreach( $events as $event_id => $e ) : ?>
									<option value="<?php echo absint($event_id) ?>" <?php echo $e['booked'] . $e['disabled']; ?>><?php echo esc_html($e['date']); ?></option>
								<?php endforeach; ?>
							</select>
							<?php else: ?>
								<?php echo $EM_Booking->get_event()->output('#_EVENTDATES @ '); ?>
							<?php endif; ?>
							<?php if ( $has_timeslots ) : ?>
							<select name="event_uid" data-loading="<?php esc_attr_e('Loading Times ...', 'em-pro'); ?>">
								<?php self::timeslot_options( $EM_Event, $EM_Booking ); ?>
							</select>
							<?php endif; ?>
							<input type="hidden" name="booking_id" value="<?php echo $EM_Booking->booking_id; ?>">
							<input type="hidden" name="action" value="em_move_booking">
							<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('em_move_booking_' . $EM_Booking->booking_id); ?>">
							<button type="button" class="button button-secondary cancel"><?php esc_html_e_emp('Cancel'); ?></button>
							<button type="submit" name="em_bookings_move_booking" class="button button-primary" data-loading="<?php esc_attr_e('Moving...', 'em-pro'); ?>"><?php esc_html_e('Move Booking', 'em-pro'); ?></button>
						</form>
					</td>
				</tr>
				<tr class="em-booking-admin-event-date-change-trigger">
					<td colspan="2">
						<a href="#" class="button button-secondary form-trigger" id="id"><?php esc_html_e('Modify Booking Date', 'em-pro'); ?></a>
						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								let bookingID = <?php echo $EM_Booking->booking_id; ?>;

								let form = document.getElementById('em-move-booking-' + bookingID);
								let container = form.closest('tbody');
								let formWrapper = form.closest('tr');
								let dateValue = container.closest('table').querySelector('.em-booking-admin-event-date');
								let triggerWrapper = container.querySelector('.em-booking-admin-event-date-change-trigger');
								let eventIdSelect = form.querySelector('select[name="event_id"]');
								let eventUidSelect = form.querySelector('select[name="event_uid"]');

								form.querySelector('.cancel').addEventListener('click', function(e) {
									e.preventDefault();
									formWrapper.classList.add('hidden');
									triggerWrapper.classList.remove('hidden');
									dateValue.classList.remove('hidden');
								})

								triggerWrapper.querySelector('.form-trigger').addEventListener('click', function(e) {
									e.preventDefault();
									formWrapper.classList.remove('hidden');
									triggerWrapper.classList.add('hidden');
									dateValue.classList.add('hidden');
								})

								// Add event listener for event_id select change
								eventIdSelect?.addEventListener('change', function(e) {
									if ( eventUidSelect ) {
										let selectedEventId = e.target.value;
										if ( selectedEventId ) {
											// Clear existing options except the first one
											eventUidSelect.innerHTML = `<option value="0">${ eventUidSelect.dataset.loading }</option>`;

											// Make AJAX call to get timeslots
											let formData = new FormData( form );
											formData.set( 'action', 'em_get_event_timeslots' );
											formData.set( 'nonce', eventIdSelect.dataset.nonce );

											fetch( EM.ajaxurl, {
												method: 'post',
												body: formData
											} ).then( function ( response ) {
												return response.text();
											} ).then( function ( response ) {
												eventUidSelect.innerHTML = response;
											} ).catch( function ( error ) {
												console.error( 'Error fetching timeslots:', error );
											} );
										}
									}
								});

								form.addEventListener('submit', function(e) {
									e.preventDefault();
									if ( eventIdSelect?.querySelector(':checked')?.dataset.booked !== null || eventUidSelect?.querySelector(':selected')?.dataset.booked !== null ) {
										let msg = '<?php echo esc_html( sprintf( __('One or more tickets in this %s is unavailable or fully booked. Would you still like to move the booking?', 'em-pro'), Archetypes::get( $EM_Event->event_archetype )['label_single'] ) ); ?>';
										if ( !confirm( msg ) ) {
											return;
										}
									}
									let formData = new FormData( form );
									let button = form.querySelector('button[type="submit"]');
									let buttonText = button.innerHTML;
									button.setAttribute('disabled', 'disabled');
									button.innerHTML = button.dataset.loading;
									fetch( EM.ajaxurl, { method: 'post', body : formData } ).then( function( response ) {
										return response.json();
									}).then( function( response ) {
										alert( response.message );
										if ( response.result ) {
											window.location.reload();
										}
									}).finally( function() {
										button.removeAttribute('disabled');
										button.innerHTML = buttonText;
									});
								});

							});
						</script>
					</td>
				</tr>
			</tbody>
			<?php
		}
	}

	public static function check_availability( $spaces, $EM_Event ) {
		// go through current tickets and check if they have enough spaces in this event
		foreach ( $EM_Event->get_tickets() as $EM_Ticket ) {
			$ticket_id = $EM_Event->is_recurrence() && $EM_Ticket->ticket_parent ? $EM_Ticket->ticket_parent : $EM_Ticket->ticket_id;
			if ( !empty( $spaces[ $ticket_id ] ) ) {
				if ( !$EM_Ticket->is_available( true ) || $EM_Ticket->get_available_spaces() > $spaces[ $ticket_id ] ) {
					// available
					return true;
				} else {
					// one ticket not available, meaning it's not available
					return false;
				}
			}
		}
		return null;
	}

	public static function timeslot_options( $EM_Event, $EM_Booking )  {
		if ( $EM_Event && $EM_Event->event_id ) {
			// go thorugh timeslots and check availability
			$spaces = [];
			$is_recurring = $EM_Booking->get_event()->is_timeslot() ? $EM_Booking->get_event()->get_parent()->is_recurrence() : $EM_Booking->get_event()->is_recurrence();
			foreach ( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ) {
				$ticket_id = $is_recurring ? $EM_Ticket_Bookings->get_ticket()->ticket_parent : $EM_Ticket_Bookings->get_ticket()->ticket_id;
				$spaces[ $ticket_id ] = $EM_Ticket_Bookings->get_spaces();
			}
			$options = '';
			foreach ( $EM_Event->get_timeranges()->get_timeslots() as $Timeslot ) { /* @var \EM\Event\Timeslot $Timeslot */
				if ( $Timeslot->timeslot_status ) {
					$availability = static::check_availability( $spaces, $Timeslot->get_event() ) && false;
					$booked = $availability ? '' : ( $availability === false ? ' data-booked' : '' );
					$disabled = $availability === null ? ' disabled' : '';
					if ( $Timeslot->get_uid() === $EM_Booking->event_id ) {
						$options = '<option value="0" selected="selected" ' . $booked . $disabled . '>' . $Timeslot->start->i18n( $EM_Event->get_option( 'dbem_time_format' ) ) . ( $timezone ?? '' ) . '</option>' . $options;
					} else {
						if ( $Timeslot->start->getTimezone()->getValue() !== $EM_Booking->get_event()->start()->getTimezone()->getValue() ) {
							$timezone = ' (' . $Timeslot->start->getTimezone()->getName() . ')';
						}
						$options .= '<option value="' . $Timeslot->get_uid() . '">' . ( $booked ? '* ':'' ) . $Timeslot->start->i18n( $EM_Event->get_option( 'dbem_time_format' ) ) . ( $timezone ?? '' ) . '</option>';
					}
				}
			}
			echo $options;
		} else {
			echo '<option value="0">No Timeslots Found</option>';
		}
	}

	public static function em_get_event_timeslots() {
		$result = false;
		$message = 'Could not verify submitted data and/or nonce.';
		$timeslots = [];

		if ( !empty( $_POST['event_id'] ) && !empty( $_POST['nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'em_get_timeslots_' . $_POST['booking_id'] ?? '' ) ) {
				$EM_Event = em_get_event( absint( $_POST['event_id'] ) );
				$EM_Booking = new EM_Booking( absint( $_POST['booking_id'] ) );
				static::timeslot_options( $EM_Event, $EM_Booking );
			}
		}

		echo json_encode([
			'result' => $result,
			'message' => $message,
			'timeslots' => $timeslots,
		]);
		die();
	}

	public static function em_move_booking() {
		$result = false;
		$message = 'Could not verify submitted data and/or nonce for authorizing move.';
		if ( !empty( $_POST['booking_id'] ) && !empty( $_POST['nonce']) ) {
			if ( wp_verify_nonce( $_POST['nonce'], 'em_move_booking_' . absint( $_POST['booking_id'] ) ) ) {
				// just do a quick SQL to move the timeslot ID to the new event
				$EM_Booking = new EM_Booking( absint( $_POST['booking_id'] ) );
				if ( $_POST['event_uid'] && preg_match( '/^(\d+):(\d+)$/', $_POST['event_uid'], $matches ) ) {
					// this is a timeslot move, so we need to update the timeslot ID
					$id = absint( $matches[1] );
					$timeslot_id = $matches[2];
					$EM_Event = em_get_event( $_POST['event_uid'] );
					// check if timeslot belongs to same event ID (i.e. same day), if so ticket IDs etc. would be the same
					if ( $EM_Event->get_event_id() === $EM_Booking->get_event_id() ) {
						// move it
						$EM_Booking->timeslot_id = $timeslot_id;
						$EM_Booking->save();
						$result = true;
						$message = esc_js( __( 'Booking moved successfully', 'em-pro' ) );
					} else {
						$message = 'Cannot move timeslot to a different event ID';
					}
				} else {
					$EM_Event = em_get_event( $_POST['event_id'] );
				}
				if ( !$result && $EM_Booking->get_event()->is_recurrence() ) {
					// this is not just a timeslot move, we need to check the recurrence and move to a different ticket ID
					// get the ticket ID of the destination parent
					if ( $EM_Event->event_id ) {
						if ( $EM_Event->get_recurring_event()->get_event_id() === $EM_Booking->get_event()->get_recurring_event()->get_event_id() ) {
							$result = true;
							$EM_Booking->event_id =  $EM_Event->event_id;
							$EM_Booking->timeslot_id = $timeslot_id ?? null;
							foreach ( $EM_Booking->get_tickets_bookings() as $EM_Ticket_Bookings ) {
								// get the ticket ID of the destination parent
								$ticket_parent = $EM_Ticket_Bookings->get_ticket()->ticket_parent;
								foreach ( $EM_Event->get_tickets() as $EM_Ticket ) {
									if ( $EM_Ticket->ticket_parent === $ticket_parent ) {
										$EM_Ticket_Bookings->ticket_id = $EM_Ticket->ticket_id;
										foreach ( $EM_Ticket_Bookings as $EM_Ticket_Booking ) {
											$EM_Ticket_Booking->ticket_id = $EM_Ticket->ticket_id;
										}
									} else {
										$result = false;
										$message = esc_html( sprintf( __('Could not match all tickets from current booking in the destination %s'), Archetypes::get( $EM_Event->event_archetype )['label_single'] ) );
									}
								}
							}
							if ( $result ) {
								$EM_Booking->save();
								$message = esc_js( __( 'Booking moved successfully', 'em-pro' ) );
							}
						} else {
							$message = 'Cannot move to a different event set';
						}
					} else {
						$message = 'Could not find event ID';
					}
				}
			}
		}
		echo json_encode([
			'result' => $result,
			'message' => $message,
		]);
		die();
	}
}
Move_Booking::init();