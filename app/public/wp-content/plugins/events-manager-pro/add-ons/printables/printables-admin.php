<?php
namespace EM_Pro\Printables;

use Dompdf\Dompdf, Dompdf\CanvasFactory, Dompdf\Exception, Dompdf\FontMetrics, Dompdf\Options, FontLib\Font;

class Admin {
	
	public static function init(){
		add_action('em_options_page_footer_bookings', '\EM_Pro\Printables\Admin::options');
		if( !empty($_REQUEST['action']) && $_REQUEST['action'] == 'regenerate_dompdf_fonts' && !empty($_REQUEST['nonce']) ){
			add_action('admin_init', '\EM_Pro\Printables\Admin::admin_init');
		}
	}
	
	public static function admin_init(){
		if( wp_verify_nonce($_REQUEST['nonce'], 'regenerate_dompdf_fonts') ){
			ob_start();
			static::regenerate_fonts_folder();
			$result = ob_get_clean();
			$EM_Notices = new \EM_Notices();
			$EM_Notices->add_info('<pre>'. $result . '</pre>', true);
			wp_redirect( esc_url_raw( add_query_arg(array('action'=> null, 'nonce' => null)) ).'#bookings' );
			die();
		}
	}
	
	/*
	 * --------------------------------------------
	 * Email Reminders
	 * --------------------------------------------
	 */
	/**
	 * Generates meta box for settings page
	 */
	public static function options(){
		global $save_button;
		?>
		<div  class="postbox " id="em-opt-printables" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php _e ( 'Printables (PDFs, Invoices, Tickets etc.)', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php
								_e( 'You can include invoices and tickets in PDF attachments which are added automatically to their confirmation email. Below are options on what to send, and some customization options for your headers.', 'em-pro' );
								//You can further customize all these templates, or parts of them by overriding our template files as per our %s.
								?>
							</p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), 'PDFs'), 'dbem_bookings_pdf','', '', '.booking-pdf-options');
					?>
					<tbody class="booking-pdf-options">
						<?php
						if( get_option('dbem_bookings_pdf') ){
							// get available fonts
							$dompdf = PDFs::load_dompdf();
							$fontMetrics = $dompdf->getFontMetrics();
							$fonts = $fontMetrics->getFontFamilies();
							$fonts_dir = $dompdf->getOptions()->getFontDir().'/';
							$fonts_template_file = emp_locate_template('printables/fonts/installed-fonts.json');
							$fonts_selection = array();
							foreach ($fonts as $font_key => $font_files) {
								if( is_readable($font_files['normal'] . '.ttf') ){
									$file = $font_files['normal'] . '.ttf';
								}elseif( is_readable($font_files['normal'] . '.otf') ){
									$file = $font_files['normal'] . '.otf';
								}else{
									$file = $font_files['normal'];
								}
								try {
									$font = Font::load($file);
									$records = $font->getData("name", "records");
									$fonts_selection[$font_key] = $records[1];
									$font->close();
								} catch ( \Exception $ex ) {
									$fonts_selection[$font_key] = ucwords($font_key);
								}
							}
							$msg = '';
							if( is_super_admin() && !str_contains( $fonts_template_file, EMP_DIR ) ){
								$msg = __('Site Admins : You have a template folder in %s containing custom fonts, %s to load newly added font .ttf or .otf files.', 'em-pro');
								$regenerate_url = add_query_arg(array('action' => 'regenerate_dompdf_fonts', 'nonce' => wp_create_nonce('regenerate_dompdf_fonts')));
								$msg = sprintf( $msg, "<code>$fonts_dir</code>", '<a href="'.$regenerate_url.'">'.esc_html__('Regenerate Fonts List').'</a>');
							}
							em_options_select( __('Default Font', 'em-pro'), 'dbem_bookings_pdf_font', $fonts_selection, $msg );
							em_options_radio_binary( __('Enable Font Subsetting?', 'em-pro'), 'dbem_bookings_pdf_font_subset', __('Font subsetting will drastically reduce ths size of your PDF, especially when using fonts with special character support, but will be harder to edit in other applications.', 'em-pro') );
						} else {
							echo '<tr><td colspan="2"><em>'. esc_html__('Save your settings to view font options.', 'em-pro') . '</em></td></tr>';
						}
						
						// Ticket and invoice options
						em_options_radio_binary ( __( 'Include Invoices in Emails?', 'em-pro' ), 'dbem_bookings_pdf_email_invoice',__('A PDF will be attached in the automated confirmation email containing an invoice.', 'em-pro'));
						em_options_radio_binary ( __( 'Include Tickets in Emails?', 'em-pro' ), 'dbem_bookings_pdf_email_tickets',__('A PDF will be attached in the automated confirmation email containing a booking summary as well as individual tickets. Enable QR codes in the Ticket Scanning optinos to include them in your tickets.', 'em-pro'));
						global $bookings_placeholder_tip;
						em_options_input_text ( __( 'Invoice Number Format', 'em-pro' ), 'dbem_bookings_pdf_invoice_format',__('You can modify the format of your invoice numbers such as adding prefixes, mixing numbers etc.', 'em-pro'). '<br>'.$bookings_placeholder_tip, 1);
						
						$pdf_logo = get_option('dbem_bookings_pdf_logo');
						$pdf_logo_id = get_option('dbem_bookings_pdf_logo_id');
						?>
						<tr class="form-field pdf-image-wrap">
							<th scope="row" valign="top"><label for="pdf-image"><?php esc_html_e('Logo','events-manager'); ?></label></th>
							<td>
								<div class="img-container">
									<?php if( !empty($pdf_logo) ): ?>
										<img src="<?php echo $pdf_logo; ?>" />
									<?php endif; ?>
								</div>
								<input type="text" name="dbem_bookings_pdf_logo" id="pdf-image" class="img-url" value="<?php echo esc_attr($pdf_logo); ?>" />
								<input type="hidden" name="dbem_bookings_pdf_logo_id" id="pdf-image-id" class="img-id" value="<?php echo esc_attr($pdf_logo_id); ?>" />
								<p class="hide-if-no-js">
									<input id="upload_image_button" type="button" value="<?php esc_html_e_emp('Choose/Upload Image'); ?>" class="upload-img-button button-secondary" />
									<input id="delete_image_button" type="button" value="<?php esc_html_e_emp('Remove Image'); ?>" class="delete-img-button button-secondary" <?php if( empty($pdf_logo) ) echo 'style="display:none;"'; ?> />
								</p>
								<br />
								<p class="description"><?php echo __('This image will be displayed on top of your invoice, leave blank to use text defined below instead.','em-pro'); ?></p>
							</td>
						</tr>
						<?php
						em_options_input_text ( __( 'Alternate Logo Text', 'em-pro' ), 'dbem_bookings_pdf_logo_alt',__('If no logo is defined, this text will be used instead as the heading of your invoices and tickets.', 'em-pro'), 1);
						em_options_textarea( __( 'Billing Details Text', 'em-pro' ), 'dbem_bookings_pdf_billing_details',__('This is dynamic information generated by your customer and obtained during bookings or in their user profile. HTML is accepted, line breaks are respected.', 'em-pro'). '<br>'.$bookings_placeholder_tip);
						em_options_textarea( __( 'Business Details Text', 'em-pro' ), 'dbem_bookings_pdf_business_details',__('This is your business information and appears on both invoices and ticket bookings. HTML is accepted, line breaks are respected.', 'em-pro'));
						?>
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<script>
			<?php
				wp_enqueue_media();
				wp_enqueue_script( 'em-printables-admin', '', array('jquery','media-upload','thickbox','farbtastic','wp-color-picker'), false, true );
				include(dirname(__FILE__).'/printables-pdf-admin.js');
			?>
		</script>
		<?php
	}
	
	public static function load_font_family( $fontname, $normal, $bold = null, $italic = null, $bold_italic = null) {
		
		$dompdf = PDFs::load_dompdf();
		$fontMetrics = $dompdf->getFontMetrics();
		
		// Check if the base filename is readable
		if ( !is_readable($normal) )
			throw new Exception("Unable to read '$normal'.");
		
		$dir = dirname($normal);
		$basename = basename($normal);
		$last_dot = strrpos($basename, '.');
		if ($last_dot !== false) {
			$file = substr($basename, 0, $last_dot);
			$ext = strtolower(substr($basename, $last_dot));
		} else {
			$file = $basename;
			$ext = '';
		}
		
		if ( !in_array($ext, array(".ttf", ".otf")) ) {
			throw new Exception("Unable to process fonts of type '$ext'.");
		}
		
		// Try $file_Bold.$ext etc.
		$path = "$dir/$file";
		
		$patterns = array(
			"bold"        => array("_Bold", "-Bold", "b", "B", "bd", "BD"),
			"italic"      => array("_Italic", "-Italic", "_Oblique", '-Oblique', "i", "I"),
			"bold_italic" => array("_Bold_Italic", "-Bold_Italic", "_BoldOblique", '-BoldOblique', "bi", "BI", "ib", "IB"),
		);
		
		foreach ($patterns as $type => $_patterns) {
			if ( !isset($$type) || !is_readable($$type) ) {
				foreach($_patterns as $_pattern) {
					if ( is_readable("$path$_pattern$ext") ) {
						$$type = "$path$_pattern$ext";
						break;
					}
				}
				
				if ( is_null($$type) )
					echo ("Unable to find $type face file.\n");
			}
		}
		
		$fonts = compact("normal", "bold", "italic", "bold_italic");
		$entry = array();
		
		// Copy the files to the font directory.
		foreach ($fonts as $var => $src) {
			if ( is_null($src) ) {
				//$entry[$var] = $dompdf->getOptions()->get('fontDir') . '/' . mb_substr(basename($normal), 0, -4);
				$entry[$var] = mb_substr(basename($normal), 0, -4);
				continue;
			}
			
			// Verify that the fonts exist and are readable
			if ( !is_readable($src) )
				throw new Exception("Requested font '$src' is not readable");
			
			/* skipping this, no need to copy
			$dest = $dompdf->getOptions()->get('fontDir') . '/' . basename($src);
			
			if ( !is_writeable(dirname($dest)) )
				throw new Exception("Unable to write to destination '$dest'.");
			
			echo "Copying $src to $dest...\n";
			
			if ( !copy($src, $dest) )
				throw new Exception("Unable to copy '$src' to '$dest'");
			*/
			$dest = $src; // doing this instead
			$entry_name = mb_substr($dest, 0, -4);
			
			echo "Generating Adobe Font Metrics for $entry_name...\n";
			
			$font_obj = Font::load($dest);
			$font_obj->saveAdobeFontMetrics("$entry_name.ufm");
			$font_obj->close();
			
			// START extra - we don't need full path
			$entry_name = mb_substr(basename($dest), 0, -4);
			// END extra
			$entry[$var] = $entry_name;
		}
		
		// Store the fonts in the lookup table
		$fontMetrics->setFontFamily($fontname, $entry);
		// Save the changes
		$fontMetrics->saveFontFamilies();
	}
	
	public static function regenerate_fonts_folder(){
		// read fonts from
		$fonts_json = emp_locate_template('printables/fonts/installed-fonts.json');
		if( $fonts_json && !str_contains($fonts_json, EMP_DIR) ){ // make use of WP polyfill
			$fonts_dir = str_replace( 'installed-fonts.json', '', $fonts_json );
			// glob all fonts and check if they exist in fonts json
			$files = array_merge( glob( $fonts_dir . "*.ttf"), glob( $fonts_dir . "*.otf") );
			// get Dompdf
			$dompdf = PDFs::load_dompdf();
			$fontMetrics = $dompdf->getFontMetrics();
			$fonts = array();
			foreach ($files as $file) {
				$font = Font::load($file);
				$records = $font->getData("name", "records");
				$type = $fontMetrics->getType($records[2]);
				$font_name = str_replace(array('-regular', '-boldoblique', '-bold', '-oblique'), '', mb_strtolower($records[6]));
				$fonts[$font_name][$type] = $file;
				$font->close();
			}
			if( !empty($fonts) ){
				file_put_contents($fonts_json, '{}'); // empty the JSON file
			}
			foreach ( $fonts as $family => $files ) {
				echo " >> Installing '$family'... \n";
				
				if ( !isset($files["normal"]) ) {
					echo "No 'normal' style font file\n";
				}
				else {
					try {
						static::load_font_family( $family, @$files["normal"], @$files["bold"], @$files["italic"], @$files["bold_italic"] );
						echo "Done !\n";
					}catch( \Exception $ex ){
						echo "Error loading $family : ". $ex->getMessage();
					}
				}
				
				echo "\n";
			}
		}else{
			echo 'No custom folder found!';
		}
	}
}
Admin::init();