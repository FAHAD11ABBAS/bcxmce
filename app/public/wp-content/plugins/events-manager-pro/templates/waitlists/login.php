<?php
/* @var $EM_Event EM_Event */
echo '<div class="em-notice">';
echo get_option('dbem_waitlists_login_text');
echo '</div>';
include( em_locate_template('forms/bookingform/login.php') );
?>