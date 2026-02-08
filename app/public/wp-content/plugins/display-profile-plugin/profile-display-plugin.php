<?php
/*
Plugin Name: Profile plugin
Description: Tämä WordPress-laajennus tarjoaa kolme lyhytkoodia, jotka parantavat käyttäjäprofiilien hallintaa ja esittämistä. [display_user_info] näyttää käyttäjän profiilitiedot ja mahdollistaa niiden muokkaamisen, mukaan lukien salasanan vaihto ja profiilikuvan päivitys. [display_selected_user_profile] näyttää yksittäisen käyttäjän profiilin mukautetulla näkymällä, ja [display_all_users] listaa kaikki käyttäjäprofiilit hakutoiminnolla, suodattimilla ja tyylikkäillä taulukko- tai ruudukkonäkymillä. Laajennus tukee piilotettavia tietoja, VIP-merkintöjä ja responsiivista muotoilua, parantaen käyttökokemusta ja yksityisyyttä. 
Version: 1.8
Author: Business College Helsinki
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}
require_once('includes/display-all-users.php');
require_once('includes/display-one-profile.php');
require_once('includes/display-user-info.php');

?>