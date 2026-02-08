<?php esc_html_e('Hello #_BOOKINGNAME', 'em-pro'); ?>


<?php esc_html_e('Great news! You are now able to book up to #_BOOKINGSPACES spaces for #_EVENTNAME on #_EVENTDATES at #_EVENTTIMES.', 'em-pro'); ?>


<?php esc_html_e('If you do not want to attend this event anymore, please cancel your reservation so others can have an opportunity to attend this event.', 'em-pro'); ?>


<?php esc_html_e('Please follow the following link to complete or cancel your booking:', 'em-pro'); ?>


#_WAITLIST_BOOKING_URL

{has_waitlist_expiry}
<?php esc_html_e('Please remember that you have #_WAITLIST_EXPIRY hours to complete this booking, otherwise your reservation will be cancelled and these spaces will be made available to the next person in line.', 'em-pro'); ?>
{/has_waitlist_expiry}

<?php esc_html_e('Best Regards,', 'em-pro'); ?>


#_CONTACTNAME