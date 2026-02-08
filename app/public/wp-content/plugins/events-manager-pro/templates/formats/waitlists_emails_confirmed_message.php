<?php esc_html_e('Hello #_BOOKINGNAME', 'em-pro'); ?>


<?php esc_html_e('You have successfully reserved #_BOOKINGSPACES spaces for #_EVENTNAME on #_EVENTDATES at #_EVENTTIMES.', 'em-pro'); ?>


<?php esc_html_e('You are number #_WAITLIST_BOOKING_POSITION in line.', 'em-pro'); ?>

{has_waitlist_expiry}
<?php esc_html_e('Please remember that you have #_WAITLIST_EXPIRY hours to book reserved spaces once they become available, you will be notified immediately when an elegible ticket becomes available.', 'em-pro'); ?>

{/has_waitlist_expiry}
{no_waitlist_expiry}
<?php esc_html_e('You will be notified immediately when an elegible ticket becomes available', 'em-pro'); ?>

{/no_waitlist_expiry}

<?php esc_html_e('If you do not want to attend this event anymore, please cancel your reservation so others can have an opportunity to attend this event.', 'em-pro'); ?> <?php esc_html_e('You can view all the information about your reservation and cancel by following this link:', 'em-pro'); ?>


#_WAITLIST_BOOKING_URL

<?php esc_html_e('Best Regards,', 'em-pro'); ?>


#_CONTACTNAME