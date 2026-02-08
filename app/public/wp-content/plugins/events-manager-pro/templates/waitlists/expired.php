<?php
/* @var $EM_Event EM_Event */
?>
<div class="<?php em_template_classes('event-booking-form'); ?> input">
	<div class="em-notice em-notice-error">
		<?php
		$format = get_option('dbem_waitlists_text_expired');
		echo \EM\Waitlist\Bookings::$booking->output( $format );
		?>
	</div>
</div>
<?php echo \EM\Waitlist\Bookings::$booking->get_event()->output('#_BOOKINGFORM'); ?>