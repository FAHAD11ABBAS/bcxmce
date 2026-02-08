<?php
use Dompdf\Dompdf;

if( get_option('dbem_bookings_pdf') ){
	include('printables-pdfs.php');
}
if( is_admin() ){
	include('printables-admin.php');
}