<?php
namespace EM_Pro;
use QRCode, EM\Feature;

class QR {
	
	public static function init() {
		Feature::register('qr');
		add_filter('em_ticket_booking_output_placeholder', '\EM_Pro\QR::em_ticket_booking_output_placeholder', 10, 3);
		// rule flushes are handled by EM itself, we can just add our endpoints
		if (!empty($_REQUEST[static::get_endpoint()])) {
			static::deliver_qr();
		}
	}
	
	public static function get_endpoint(){
		$endpoint = 'em-qr';
		if( defined('EM_QR_ENDPOINT') ){
			$endpoint = EM_QR_ENDPOINT;
		}
		return $endpoint;
	}
	
	public static function get_qr_url( $type, $uuid, $size = '' ){
		if( get_option('dbem_bookings_qr_url') !== '' ){
			$endpoint = $type . '/' . $uuid;
			if (!empty($size)) $endpoint .= '/' . $size;
			$frontend_url = Bookings_Manager_Frontend::get_endpoint_url($endpoint);
			$url = self::get_fallback_url( $frontend_url );
		}else {
			$endpoint = $type . '/' . $uuid;
			if (!empty($size)) $endpoint .= '/' . $size;
			$url = add_query_arg(static::get_endpoint(), $endpoint, get_home_url());
		}
		return $url;
	}
	
	public static function deliver_qr() {
		// we can output the QR and just die
		$endpoint = $_REQUEST[static::get_endpoint()];
		if( preg_match('/^((ticket|booking)\/[a-zA-Z0-9]{32})(\/([0-9]{1,3})\/?)?\/?$/', $endpoint, $matches) ){
			// outputs image directly into browser, as PNG stream
			require_once('phpqrcode/qrlib.php');
			$url = Bookings_Manager_Frontend::get_endpoint_url();
			$url .= $matches[1];
			$size = !empty($matches[4]) ? $matches[4]:3;
			QRcode::png($url, false, QR_ECLEVEL_L, $size, 0);
		}else{
			echo 'Invalid QR code request.';
		}
		die();
	}
	
	public static function get_fallback_url( $data ){
		return sprintf( get_option('dbem_bookings_qr_url'), urlencode($data));
	}
	
	/**
	 * Outputs a base64 encoded QR png ready to include in an img src attribute.
	 * If fallback URL is supplied, that'll be used instead.
	 * IMPORTANT! Header is reaset to text/html since QR generator sends a 'content-type: image/png' header. If a header was previously defined, we attempt to redeclare it here, otherwise we send a text/html header.
	 * @param $endpoint
	 * @return string
	 */
	public static function base64( $endpoint ){
		if( preg_match('/^((ticket|booking)\/[a-zA-Z0-9]{32})(\/([0-9]{1,3})\/?)?\/?$/', $endpoint, $matches) ){
			if( get_option('dbem_bookings_qr_url') !== '' ){
				$url = Bookings_Manager_Frontend::get_endpoint_url($endpoint);
				$content = wp_remote_fopen( static::get_fallback_url($url) );
				$qr_encoded = base64_encode( $content );
				return array('result'=> true, 'src' => 'data:image/png;base64,'.$qr_encoded);
			}else{
				// outputs image directly into browser, as PNG stream
				require_once('phpqrcode/qrlib.php');
				$url = Bookings_Manager_Frontend::get_endpoint_url($endpoint);
				$size = !empty($matches[4]) ? $matches[4]:3;
				// get previous sent header (if any)
				if( !headers_sent() ){
					$headers = headers_list();
					$header = trim('Content-type',': ');
					$prev_header = false;
					foreach ($headers as $hdr) {
						if (stripos($hdr, $header) !== false) {
							$prev_header = $hdr;
						}
					}
				}
				// output PNG and catch output encoded
				ob_start();
				@QRcode::png($url, false, QR_ECLEVEL_L, $size, 0);
				$qr_encoded = base64_encode( ob_get_clean() );
				// output
				if( !headers_sent() ){
					if( $prev_header !== false ){
						// reset to previous header if already set
						header( $hdr );
					}else{
						// reset to html header
						header('Content-type: text/html');
					}
				}
				return array('result'=> true, 'src' => 'data:image/png;base64,'.$qr_encoded);
			}
		}else{
			$not_found_icon = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 59.001 59.001"><path d="M58.693 55.636 46.848 35.33c-.405-.695-1.128-1.11-1.933-1.11-.805 0-1.527.415-1.933 1.11L31.137 55.636c-.409.7-.412 1.539-.008 2.242.404.703 1.129 1.123 1.94 1.123H56.76c.811 0 1.536-.42 1.939-1.123.405-.703.403-1.541-.006-2.242zm-1.727 1.246c-.03.055-.092.119-.205.119H33.07c-.114 0-.175-.064-.206-.119s-.056-.14.001-.238L44.71 36.338c.057-.098.143-.118.205-.118.063 0 .148.021.206.118l11.845 20.306c.056.098.032.183 0 .238z"/><path d="M45 41.001c-.552 0-1 .447-1 1v8c0 .553.448 1 1 1s1-.447 1-1v-8c0-.553-.448-1-1-1zM44.29 53.291c-.18.189-.29.449-.29.71 0 .27.11.52.29.71.19.18.45.29.71.29.26 0 .52-.11.7-.29.19-.181.3-.44.3-.71 0-.271-.11-.521-.29-.7-.37-.38-1.06-.38-1.42-.01zM36 32.001h9v-11H7v29h20v-9h9v-9zm0-9h7v7h-7v-7zm-9 0h7v7h-7v-7zm-9 0h7v7h-7v-7zm7 16h-7v-7h7v7zm-16-16h7v7H9v-7zm0 9h7v7H9v-7zm7 16H9v-7h7v7zm9 0h-7v-7h7v7zm9-9h-7v-7h7v7z"/><path d="M26 55.001H2v-39h48v17c0 .553.447 1 1 1s1-.447 1-1v-28c0-.553-.447-1-1-1h-5v-3c0-.553-.448-1-1-1h-7c-.552 0-1 .447-1 1v3H15v-3c0-.553-.448-1-1-1H7c-.552 0-1 .447-1 1v3H1c-.552 0-1 .447-1 1v51c0 .553.448 1 1 1h25c.552 0 1-.447 1-1s-.448-1-1-1zm13-53h5v6h-5v-6zm-31 0h5v6H8v-6zm-6 4h4v3c0 .553.448 1 1 1h7c.552 0 1-.447 1-1v-3h22v3c0 .553.448 1 1 1h7c.552 0 1-.447 1-1v-3h4v8H2v-8z"/></svg>';
			$qr_encoded = 'data:image/svg+xml;base64,' .base64_encode($not_found_icon);
			return array('result'=> false, 'src' => 'data:image/png;base64,'.$qr_encoded);
		}
	}
	
	public static function em_ticket_booking_output_placeholder( $replace, $EM_Ticket_Booking, $full_result) {
		switch ($full_result){
			case '#_TICKETBOOKING_QR':
			case '#_TICKETBOOKINGQR':
				$replace = '<img src="'. static::base64('ticket/'. $EM_Ticket_Booking->ticket_uuid)['src'] .'" width="99" height="99">';
				break;
			case '#_TICKETBOOKINGQRURL':
			case '#_TICKETBOOKING_QR_URL':
				$replace = static::get_qr_url('ticket', $EM_Ticket_Booking->ticket_uuid);
				break;
			case '#_TICKETBOOKING_BASE64':
			case '#_TICKETBOOKINGQRBASE64':
				$replace = static::base64('ticket/'. $EM_Ticket_Booking->ticket_uuid)['src'];
				break;
		}
		return $replace;
	}
}
QR::init();