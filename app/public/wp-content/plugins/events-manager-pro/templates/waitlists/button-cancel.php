<?php
/**
 * Outputs a cancellation button, with an optional message on top that will be replaced by another notification if cancellation button is clicked.
 *
 * @var string $message
 */
$EM_Booking = \EM\Waitlist\Bookings::$booking
?>
<div class="<?php em_template_classes('event-booking-form', 'waitlist-booking-cancel'); ?> input">
	<form class="em-ajax-form no-overlay-spinner em-waitlist-booking-cancel" name='booking-form' method='post' action=''>
		<?php if( !empty($message) ): ?>
		<div class="em-booking-message">
			<?php
			echo $EM_Booking->output( $message );
			?>
		</div>
		<?php endif; ?>
		<input type='hidden' name='action' value='waitlist_cancel'/>
		<input type='hidden' name='uuid' value='<?php echo $EM_Booking->booking_uuid; ?>'/>
		<input type='hidden' name='_wpnonce' value='<?php echo wp_create_nonce('waitlist_cancel_'.$EM_Booking->booking_id); ?>'/>
		<button type="submit" class="button-secondary">
			<span class="em-icon em-icon-spinner loading-content"></span>
			<span class="loading-content"><?php esc_html_e('Cancelling', 'em-pro'); ?></span>
			<span class="loaded"><?php esc_html_e('Cancel Reservation', 'em-pro'); ?></span>
		</button>
	</form>
</div>