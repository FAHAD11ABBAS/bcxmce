<?php
namespace EM_Pro\Printables;
use Dompdf\Dompdf, EM_Mailer, EM_Multiple_Bookings;

class PDFs {
	
	public static function init(){
		add_action('init', '\EM_Pro\Printables\PDFs::wp_init');
		//add booking email icals
		add_filter('em_booking_email_messages', '\EM_Pro\Printables\PDFs::booking_email_attachments', 1000, 2);
		add_filter('em_multiple_booking_email_messages', '\EM_Pro\Printables\PDFs::booking_email_attachments', 1000, 2);
		add_action('em_bookings_admin_booking_details_actions', '\EM_Pro\Printables\PDFs::booking_admin_download_buttons', 10, 2);
		add_action('em_multiple_booking_table_booking_actions', '\EM_Pro\Printables\PDFs::booking_admin_download_buttons_mb', 10, 1);
	}
	
	public static function wp_init(){
		// short-circuit pdf downloads
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'em_download_pdf' && wp_verify_nonce($_REQUEST['nonce'], 'em_download_booking_pdf') ){
			global $EM_Notices;
			$what = $_REQUEST['what'];
			$EM_Booking = em_get_booking($_REQUEST['booking_id']);
			if( $EM_Booking->can_manage() ){
				static::download_booking_pdf( $EM_Booking, $what, !empty($_REQUEST['html']) );
			}
		}
		// method to get PDFs with one-off links if user is not logged in
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'em_download_pdf_nopriv' && !empty($_REQUEST['booking_uuid']) ){
			if( !empty($_REQUEST['what']) && wp_verify_nonce($_REQUEST['nonce'], 'em_download_booking_pdf-'. $_REQUEST['booking_uuid']) ) {
				$what = $_REQUEST['what'];
				$EM_Booking = em_get_booking( $_REQUEST['booking_uuid'] );
				if( in_array($what, ['tickets', 'invoice']) && wp_verify_nonce($_REQUEST['_nonce'], 'em_download_booking_pdf_' . $what . '-'. $EM_Booking->booking_id) ) {
					static::download_booking_pdf( $EM_Booking, $what, !empty( $_REQUEST['html'] ) );
				}
			}
		}
	}
	
	public static function booking_email_attachments( $msg, $EM_Booking ){
		//add email ical attachment
		if( $EM_Booking->booking_status != 1 ) return $msg; //only email when confirmed
		if( get_option('dbem_bookings_pdf_email_invoice') ){
			$msg['user']['attachments'][] = static::get_invoice_attachment( $EM_Booking );
		}
		if( get_option('dbem_bookings_pdf_email_tickets') ){
			if( $EM_Booking instanceof EM_Multiple_Booking ){
				foreach( $EM_Booking->get_bookings() as $booking ){
					$tickets_file_array = static::get_tickets_attachment($booking);
					$msg['user']['attachments'][] = $tickets_file_array;
				}
			}else{
				$tickets_file_array = static::get_tickets_attachment($EM_Booking);
				$msg['user']['attachments'][] = $tickets_file_array;
			}
		}
		return $msg;
	}
	
	public static function get_invoice_attachment( $EM_Booking ) {
		$pdf_content = static::get_pdf_content( 'printables/pdf-invoice/pdf-invoice.php', array('EM_Booking' => $EM_Booking));
		$pdf_filename = 'invoice_'. $EM_Booking->booking_id .'.pdf';
		$pdf_attachment = EM_Mailer::add_email_attachment($pdf_filename, $pdf_content);
		$invoice_number = static::get_invoice_number($EM_Booking);
		$filename = sprintf('Invoice - %s.pdf', $invoice_number);
		$filename = static::filter_filename($filename);
		$filename = apply_filters('em_printables_pdf_invoice_email_filename', $filename, $EM_Booking, $invoice_number);
		return array('name'=> $filename, 'type'=>'application/pdf','path'=>$pdf_attachment, 'delete'=>true);
	}
	
	public static function get_invoice_number( $EM_Booking ){
		return apply_filters('em_printables_pdf_get_invoice_number', $EM_Booking->output(get_option('dbem_bookings_pdf_invoice_format')), $EM_Booking);
	}
	
	public static function get_tickets_attachment( $EM_Booking ){
		// attach a booking tickets PDF for each event booking
		$pdf_content = static::get_pdf_content( 'printables/pdf-booking/pdf-booking.php', array('EM_Booking' => $EM_Booking));
		$pdf_filename = 'booking_'. $EM_Booking->booking_id .'.pdf';
		$pdf_attachment = EM_Mailer::add_email_attachment($pdf_filename, $pdf_content);
		$filename = sprintf('Tickets - %s.pdf', $EM_Booking->get_event()->event_name . ' - ' . $EM_Booking->get_event()->event_start_date);
		$filename = static::filter_filename($filename);
		$filename = apply_filters('em_printables_pdf_tickets_email_filename', $filename, $EM_Booking);
		$tickets_file_array = array('name'=> $filename, 'type'=>'application/pdf','path'=>$pdf_attachment, 'delete'=>true);
		return $tickets_file_array;
	}
	
	public static function get_pdf_content( $template, $args ){
		// attach an invoice
		$dompdf = self::load_dompdf();
		ob_start();
		emp_locate_template($template, true, $args);
		$dompdf->loadHtml(ob_get_clean());
		// (Optional) Setup the paper size and orientation
		$dompdf->setPaper('A4');
		// Render the HTML as PDF
		$dompdf->render();
		// Output the generated PDF
		return $dompdf->output();
	}
	
	public static function booking_admin_download_buttons( $EM_Booking ){
		$action_url = esc_url(add_query_arg( array(
			'action' => 'em_download_pdf',
			'booking_id' => $EM_Booking->booking_id,
			'nonce' => wp_create_nonce('em_download_booking_pdf'),
		)));
		// only show invoice dl for mb booking or single boookings
		if( !get_option('dbem_multiple_bookings') || $EM_Booking instanceof EM_Multiple_Booking || EM_Multiple_Bookings::is_main_booking($EM_Booking) ){
			?>
			<button type="button" class="em-bookings-admin-get-invoice em-tooltip-ddm em-clickable input button-secondary" data-button-width="match" data-tooltip-class="em-bookings-admin-get-invoice-tooltip"><?php esc_html_e('View/Print Invoice', 'events-manager-pro'); ?></button>
			<div class="em-tooltip-ddm-content em-bookings-admin-get-invoice-content">
				<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=invoice" target="_blank"><?php echo esc_html__('Download (PDF)', 'events-manager-pro'); ?></a>
				<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=invoice&html=1" target="_blank"><?php echo esc_html__('View HTML', 'events-manager-pro'); ?></a>
			</div>
			<?php
		}
		// only show tickets button for single bookings
		if( !get_option('dbem_multiple_bookings') || !$EM_Booking instanceof EM_Multiple_Booking ){
			?>
			<button type="button" class="em-bookings-admin-get-invoice em-tooltip-ddm em-clickable input button-secondary" data-button-width="match" data-tooltip-class="em-bookings-admin-get-invoice-tooltip"><?php esc_html_e('View/Print Tickets', 'events-manager-pro'); ?></button>
			<div class="em-tooltip-ddm-content em-bookings-admin-get-invoice-content">
				<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=tickets" target="_blank"><?php echo esc_html__('Download (PDF)', 'events-manager-pro'); ?></a>
				<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=tickets&html=1" target="_blank"><?php echo esc_html__('View HTML', 'events-manager-pro'); ?></a>
			</div>
			<?php
		}
	}
	
	public static function booking_admin_download_buttons_mb( $EM_Booking ){
		$action_url = esc_url(add_query_arg( array(
			'action' => 'em_download_pdf',
			'booking_id' => $EM_Booking->booking_id,
			'nonce' => wp_create_nonce('em_download_booking_pdf'),
		)));
		/*
		 //This snippet will add a dl link for non-logged in users, useful for no-user bookings
		$what = $match[1];
		$reply['document'] = add_query_arg( [
			'action' => 'em_download_pdf_nopriv',
			'booking_uuid' =>  $EM_Booking->booking_uuid,
			'what' => $what,
			'nonce' => wp_create_nonce('em_download_booking_pdf-' . $EM_Booking->booking_uuid),
			'_nonce' => wp_create_nonce('em_download_booking_pdf_' . $what . '-'. $EM_Booking->booking_id),
		], get_admin_url( null, 'admin-ajax.php') );
		 */
		?>
		<button type="button" class="em-bookings-admin-get-invoice em-tooltip-ddm em-clickable input button-secondary" data-button-width="match" data-tooltip-class="em-bookings-admin-get-invoice-tooltip"><?php esc_html_e('View/Print Tickets', 'events-manager-pro'); ?></button>
		<div class="em-tooltip-ddm-content em-bookings-admin-get-invoice-content">
			<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=tickets" target="_blank"><?php echo esc_html__('Download (PDF)', 'events-manager-pro'); ?></a>
			<a class="em-a2c-download" href="<?php echo $action_url; ?>&what=tickets&html=1" target="_blank"><?php echo esc_html__('View HTML', 'events-manager-pro'); ?></a>
		</div>
		<?php
	}
	
	public static function load_dompdf(){
		// get font based on font to be used
		$fonts = emp_locate_template('printables/fonts/installed-fonts.json');
		$args = array(
			'isRemoteEnabled' => true,
			'defaultFont' => get_option('dbem_bookings_pdf_font', 'dejavusans'),
			'isFontSubsettingEnabled' => get_option('dbem_bookings_pdf_font_subset'),
		);
		if( $fonts ) {
			if( !str_contains( $fonts, EMP_DIR ) ){
				$fonts_dir = str_replace( '/installed-fonts.json', '', $fonts );
				$args['fontDir'] = $fonts_dir;
				$args['fontCache'] = $fonts_dir;
			}
		}
		include('dompdf/autoload.inc.php');
		return new Dompdf( $args );
	}
	
	public static function download_booking_pdf( $EM_Booking, $what, $output_html = false ){
		$dompdf = static::load_dompdf();
		ob_start();
		if( $what == 'invoice' ){
			$template = emp_locate_template('printables/pdf-invoice/pdf-invoice.php');
			$filename = 'booking-invoice.pdf';
		}else{
			$template = emp_locate_template('printables/pdf-booking/pdf-booking.php');
			$filename = 'booking-tickets.pdf';
		}
		include($template);
		if( $output_html ) {
			echo ob_get_clean();
			die();
		}else{
			$dompdf->loadHtml(ob_get_clean());
			// (Optional) Setup the paper size and orientation
			$dompdf->setPaper('A4');
			// Render the HTML as PDF
			$dompdf->render();
			// Output the generated PDF to Browser
			$dompdf->stream($filename);
			die();
		}
	}
	
	public static function filter_filename($filename) {
		// sanitize filename
		$filename = preg_replace(
			'~
        [<>:"/\\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://www.rfc-editor.org/rfc/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
			'-', $filename);
		// avoids ".", ".." or ".hiddenFiles"
		$filename = ltrim($filename, '.-');
		// optional beautification
		//if ($beautify) $filename = beautify_filename($filename);
		// maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
		return $filename;
	}
}
PDFs::init();