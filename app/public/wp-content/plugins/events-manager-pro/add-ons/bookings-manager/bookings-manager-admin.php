<?php
namespace EM_Pro;
class Bookings_Manager_Admin {
	
	public static function init(){
		add_action('em_options_page_footer_bookings', '\EM_PRo\Bookings_Manager_Admin::options');
		// save when updated
		add_action('update_option_dbem_bookings_manager', function(){
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		});
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
		<div  class="postbox " id="em-opt-bookings-manager" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php esc_html_e( 'Ticket Scanning and Frontend Management', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php _e( 'This feature will enable a frontend booking management area which can be easily accessed with a phone by scanning QR codes to complete tasks such as checking in attendees or viewing their ticket information.', 'em-pro' );  ?>
							</p>
						</td></tr>
					<?php
					$desc = esc_html__('Allows you to access the frontend bookings management pages via a special URL which you can customize below.', 'em-pro');
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), __('Frontend Booking Management','em-pro')), 'dbem_bookings_manager', $desc, '', '.bookings-manager-options');
					?>
					<tbody class="bookings-manager-options">
					<?php
					$desc = esc_html__('This is the path used to define an custom url where you can access the bookings manager which will be located under %s. User letters, numbers and dashes and underscores are permitted.', 'em-pro');
					$desc = sprintf( $desc, '<code>'. get_home_url('', 'your-endpoint') .'</code>');
					em_options_input_text ( __( 'Bookings Manager Endpiont', 'em-pro' ), 'dbem_bookings_manager_endpoint', $desc);
					// check for GD or Imagick for generating QR codes
					if( !extension_loaded('gd') && class_exists('Imagick') ){
						?>
						<tr>
							<td colspan="2" style="color:red;">
								<?php
									$error = esc_html('You must have either the GD PHP extesnion or Imagick installed in your PHP environment in order to locally generate QR codes. You can also use external QR code API generators by supplying a URL in the text box below.');
								?>
							</td>
						</tr>
						<?php
					}
					$desc = esc_html__('You can generate a QR code for tickets, which are automatically included in placeholders such as %s and also in printable invoices/tickets (including email PDFs). Scan this code with your phone to access the frontend bookings manager and complete tasks such as checking in a user.', 'em-pro');
					$desc = sprintf( $desc, '<code>#_BOOKINGATTENDEES</code>');
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), __('QR Codes','em-pro')), 'dbem_bookings_qr', $desc);
					
					$desc_1 = esc_html__('Leave blank for local QR generation. We recommend doing this only if you cannot generate QR codes locally, or if you are using a paid/reliable service.', 'em-pro');
					$desc = esc_html__('If you prefer you can externally generate QR codes using an external API service. Here are some examples of URLs you could use, remember to include the %%s placeholder so we can send data to the URL: %s.');
					$desc = sprintf( $desc, '<br><br>Google (discontinued service) <a href="https://chart.googleapis.com/chart?cht=qr&chl=%s&chs=100x100" target="_blank"><code>https://chart.googleapis.com/chart?cht=qr&chl=%s&chs=100x100</code></a><br><br>goqr.me <a href="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=%s" target="_blank"><code>https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=%s</code></a>');
					em_options_input_text( __('QR Code Generator URL','em-pro'), 'dbem_bookings_qr_url', $desc_1.'<br><br>'.$desc);
					?>
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
}
Bookings_Manager_Admin::init();