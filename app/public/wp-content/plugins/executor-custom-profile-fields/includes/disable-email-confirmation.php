<?php
/*
Plugin Name: Disable Email Confirmation
Description: Disables the email confirmation for email changes in WordPress.
Version: 1.0
*/

add_filter('send_email_change_email', '__return_false'); // Disable admin email notification for user email changes
add_filter('send_user_request', '__return_false'); // Disable confirmation email sent to the userx

add_action('personal_options_update', 'bypass_email_confirmation_for_admin');
function bypass_email_confirmation_for_admin($user_id) {
    if (current_user_can('manage_options') && isset($_POST['email']) && is_email($_POST['email'])) {
        global $wpdb;
        $new_email = sanitize_email($_POST['email']);
        $wpdb->update(
            $wpdb->users,
            array('user_email' => $new_email),
            array('ID' => $user_id)
        );
        clean_user_cache($user_id);
    }
}

