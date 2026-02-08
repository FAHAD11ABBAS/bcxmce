<?php
// Load WordPress functions (adjust the path if needed)
require_once('wp-load.php');

// Query for all users with an activation key (newly created users)
global $wpdb;
$users = $wpdb->get_results("SELECT ID, user_login, user_email, user_activation_key FROM hdYqL_users WHERE user_activation_key != ''");

foreach ($users as $user) {
    $user_id = $user->ID;
    $email = $user->user_email;
    $username = $user->user_login;
    $activation_key = $user->user_activation_key;

    // Generate the password reset URL
    $reset_url = site_url("wp-login.php?action=lostpassword");

    // Email subject & message
    $subject = "Tervetuloa uudelle MC-Executors alustalle!";

    $message = "$username,\n\n";
    $message .= "Tilisi on luotu onnistuneesti. Käyttäjätunnuksesi on: $username / $email.\n\n";
    $message .= "Ennen kuin voit kirjautua sisään, sinun täytyy asettaa salasanasi.\n";
    $message .= "Seuraa alla olevia ohjeita:\n\n";
    $message .= "1. Klikkaa alla olevaa linkkiä siirtyäksesi salasanan palautussivulle:\n";
    $message .= "$reset_url\n\n";
    $message .= "2. Syötä sähköpostiosoitteesi (se on: $email) ja seuraa ohjeita salasanasi asettamiseksi.\n";
    $message .= "3. Kun olet asettanut salasanan, voit käyttää sitä kirjautuaksesi sisään ja päästäksesi käsiksi tiliisi.\n\n";

    // Send the email
    wp_mail($email, $subject, $message);

    // Optional: Clear the activation key after sending email
    $wpdb->update('hdYqL_users', ['user_activation_key' => ''], ['ID' => $user_id]);
}

echo "Password reset emails sent!";
?>
