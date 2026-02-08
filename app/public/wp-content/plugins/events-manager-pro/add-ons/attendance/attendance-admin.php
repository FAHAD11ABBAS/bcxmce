<?php
namespace EM_Pro\Attendance;
class Admin {
	
	public static function init(){
		add_action('em_options_page_footer_bookings', '\EM_Pro\Attendance\Admin::options');
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
		<div  class="postbox " id="em-opt-attendance" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php esc_html_e ( 'Attendance (Check In/Out)', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php
								$desc = esc_html__( 'You can monitor attendance here, QR codes are not required, but can be enabled for easy scanning and check-ins via the %s settings section on this page.', 'em-pro' );
								echo sprintf($desc, '<em>'. esc_html__( 'Ticket Scanning and Frontend Management', 'em-pro' ).'<em>');
								//You can further customize all these templates, or parts of them by overriding our template files as per our %s.
								?>
							</p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Attendance Features')), 'dbem_bookings_attendance','', '', '.booking-attendance-options');
					?>
					<tbody class="booking-attendance-options">
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
}
Admin::init();