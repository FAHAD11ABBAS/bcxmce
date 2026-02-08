<?php
if ( defined('EM_AUTOMATION_LOGS') ) {
	define( 'EM_AUTOMATION_LOGS_TABLE', EM_AUTOMATION_TABLE . '_logs' );
}

if( get_option('dbem_automation_enabled') ){
	include('automation.php');
}
if( is_admin() ){
	include('admin/admin.php');
}