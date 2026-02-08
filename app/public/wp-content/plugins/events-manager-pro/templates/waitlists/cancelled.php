<?php
/* @var $EM_Event EM_Event */
?>
<div class="<?php em_template_classes('event-booking-form'); ?> input">
	<div class="em-notice">
		<?php
		$format = get_option('dbem_waitlists_text_cancelled');
		echo \EM\Waitlist\Bookings::$booking->output( $format );
		?>
	</div>
</div>