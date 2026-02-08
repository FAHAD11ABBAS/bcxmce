<?php
namespace EM\Manual_Transactions;
class Bookings_Admin {
	
	public static function init(){
		add_action('em_options_page_footer_bookings', array(get_called_class(), 'options'));
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
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php esc_html_e ( 'Manual Bookings', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php
								esc_html_e( 'Allow event admins to manually add bookings to their events on behalf of attendees.', 'em-pro' );
								?>
							</p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Manual Bookings', 'em-pro')), 'dbem_bookings_manual','', '', '.booking-manual-options');
					?>
					<tbody class="booking-manual-options">
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
}
Bookings_Admin::init();