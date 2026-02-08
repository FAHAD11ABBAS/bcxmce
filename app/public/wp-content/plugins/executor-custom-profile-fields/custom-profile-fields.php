<?php
/*
Plugin Name: Custom Profile Fields
Description: Tämä Wordpress-lisäosa lisää WordPress-käyttäjäprofiiliin mukautettuja kenttiä, mahdollistaa niiden muokkauksen ja tallennuksen, ja piilottaa joitain WordPressin oletuskenttiä. Lisäksi kytkee sähköpostivahvistuksen pois käytöstä sähköpostiosoitteen muutoksille WordPressissä. Plugin myös seuraa, milloin käyttäjän profiilia on viimeksi päivitetty, mitä muutettiin ja kuka muutokset teki.
Version: 1.7
Author: Business College Helsinki
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}
require_once('includes/profile-fields.php');
require_once('includes/disable-email-confirmation.php');
require_once('includes/user-profile-tracker.php');
?>