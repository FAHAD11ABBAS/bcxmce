<?php
namespace EM\Bookings\Minimum_Capacity;

// when saving, if value changes remove flag
class Admin {
	public static function init(){
		add_action('em_options_page_bookings_general_bottom', array( static::class, 'em_options_page_bookings_general_bottom' ));
		add_action('em_pro_updated', array( static::class, 'install' ) );
	}
	
	public static function install() {
		include_once('minimum-capacity-update.php');
	}
	
	public static function em_options_page_bookings_general_bottom(){
		?>
		<tr class="em-header"><td colspan='2'><h4><?php echo sprintf(__( '%s Options', 'events-manager'),__('Minimum Capacity','events-manager')); ?></h4></td></tr>
		<tr><td colspan="2" class="em-boxheader">
				<p><?php _e('Minimum Capacity allows you to choose per event the minimum number of spaces required by a specified date for the event to take place, otherwise the event is cancelled and . When enabled, you can set default values for new events here, previously saved events will need to be updated manually.', 'em-pro')?></p>
				<p>
					<?php
						$msg = esc_html__('You must also enable the %s setting in order for events to be cancelled, bookings will be handled according to your %s settings.', 'em-pro'); //#general+general
						//#general+event-cancellation
						echo sprintf( $msg, '<a href="#general+general">'. esc_html__emp('Event Status') .'</a>', '<a href="#general+event-cancellation">'. esc_html__emp('Event Cancellation') .'</a>')
					?>
				</p>
			</td></tr>
		<?php
		em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Event Submission Limits', 'em-pro')), 'dbem_minimum_capacity','', '', '.em-minimum-capacity-options');
		?>
		<tbody class="em-minimum-capacity-options">
		<?php
			em_options_input_text( esc_html__('Default Spaces', 'em-pro'), 'dbem_minium_capacity_spaces', esc_html__('Leave blank for no default minimum requirements.', 'em-pro') );
			$cancellation_hours_desc = esc_html__('The amount of time before an event starts that the minimum capacity must be met.', 'em-pro');
			$cancellation_hours_desc .= esc_html__emp('%s are also accepted, for example %s equals 1 month and 12 hours before the event starts.');
			$cancellation_hours_desc = sprintf($cancellation_hours_desc, '<a href="https://www.php.net/manual/en/dateinterval.construct.php" target="_blank">'.esc_html__emp('PHP date intevals').'</a>', '<code>P1MT12H</code>');
			$cancellation_hours_desc .= ' '. esc_html__emp('Add a negative number or minus sign to the start of the date interval to allow cancellations after events have started.');;
			em_options_input_text(esc_html__('Default Cut-Off', 'em-pro'), 'dbem_minium_capacity_time', $cancellation_hours_desc);
		?>
		</tbody>
		<?php
	}
}
Admin::init();