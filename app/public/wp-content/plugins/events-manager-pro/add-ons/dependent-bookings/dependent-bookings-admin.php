<?php
namespace EM;
class Dependent_Bookings_Admin {
	public static function init(){
		add_action('em_options_page_footer_bookings', '\EM\Dependent_Bookings_Admin::options_box', 1);
	}
	
	public static function options_box(){
		global $save_button, $wpdb;
		?>
		<div  class="postbox" id="em-opt-event-submission-limits" >
			<div class="handlediv" title="<?php esc_html_e_emp('Click to toggle'); ?>"><br /></div><h3><span><?php _e ( 'Dependent Events', 'em-pro'); ?></span></h3>
			<div class="inside">
				<table class="form-table">
					<tr><td colspan="2" class="em-boxheader">
							<p><?php _e('Enable the ability to require that specific events be booked before a certain event can be subsequently booked. If enabled, dependent event options will appear within the booking options of an event.', 'em-pro')?></p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Dependent Events', 'em-pro')), 'dbem_bookings_dependent_events','', '', '.em-bookings-dependent-events');
					?>
					<tbody class="em-bookings-dependent-events">
					<tr class="em-header"><td colspan='2'><h4><?php esc_html_e_emp('Booking form feedback messages'); ?></h4></td></tr>
					<?php
					em_options_input_text ( __( 'Dependent Booking Required', 'em-pro'), 'dbem_booking_feedback_dependent', __( 'When a user has not booked the reuqired event.', 'em-pro') );
					em_options_input_text ( __( 'Dependent Booking Required (Guests)', 'em-pro'), 'dbem_booking_feedback_dependent_guest', __( 'When a user is not logged in.', 'em-pro') );
					?>
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
}
Dependent_Bookings_Admin::init();