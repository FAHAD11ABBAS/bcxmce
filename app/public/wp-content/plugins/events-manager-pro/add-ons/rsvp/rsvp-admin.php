<?php
namespace EM\Bookings\RSVP;
class Admin {
	
	public static function init(){
		add_action('em_options_bookings_rsvp_footer', array(static::class, 'options'));
		// save when updated
		add_action('update_option_dbem_bookings_rsvp_endpoint_url', function(){
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		});
		
		add_action('em_options_save', array(static::class, 'validate_options'));
	}
	
	public static function validate_options(){
		global $EM_Notices;
		if( get_option('dbem_bookings_rsvp_policy') ) {
			$time = get_option( 'dbem_bookings_rsvp_policy_deadline' );
			if( !static::validate_dateinterval( $time) ) {
				$error = sprintf( esc_html__('Please add a valid number or DateInterval time interval format for the %s setting.', 'events-manager'), '<code>'. esc_html__('Default policy deadline', 'em-pro') . '</code>');
				$EM_Notices->add_error( $error, true );
			}
		}
	}
	
	public static function validate_dateinterval( $interval ) {
		if( $interval ) {
			$EM_DateTime = new \EM_DateTime();
			if ( is_numeric( $interval ) ) {
				$EM_DateTime = $EM_DateTime->sub( 'PT' . absint( $interval ) . 'H' );
				if ( $EM_DateTime->valid ) {
					return true;
				}
			} elseif ( \EM_Booking::is_dateinterval_string( $interval ) ) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Generates meta box for settings page
	 */
	public static function options(){
		?>
		<tr class="em-boxheader">
			<td colspan='2'>
				<h4><?php echo sprintf(__('%s Pages','events-manager'),__('RSVP Endpoint','events-manager')); ?></h4>
				<p>
					<?php _e( 'Enable a frontend RSVP area which can be easily accessed with a special link by booking guests who can confirm that booking before attending. This sort of front-end manager is especially convenient for fast confirmation such as from users clicking on an email or SMS via their phone.', 'em-pro' );  ?>
				</p>
			</td>
		</tr>
		<?php
		$desc = esc_html__('Allows you to access the frontend bookings management pages via a special URL which you can customize below.', 'em-pro');
		em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), __('RSVP Endpoint','em-pro')), 'dbem_bookings_rsvp_endpoint', $desc, '', '.rsvp-endpoint-options');
		?>
		<tbody class="rsvp-endpoint-options">
			<?php
			$desc = esc_html__('This is the path used to define an custom url where you can access the RSVP manager which will be located under %s. User letters, numbers and dashes and underscores are permitted. You can output a link to unique booking confirmations using the %s placeholder in your booking email formats (such as for your emails).', 'em-pro');
			$desc = sprintf( $desc, '<code>'. get_home_url('', 'your-endpoint') .'</code>', '<code>#_BOOKING_RSVP_URL</code>');
			em_options_input_text( __( 'RSVP Endpiont', 'em-pro' ), 'dbem_bookings_rsvp_endpoint_url', $desc );
			$desc = esc_html__( 'Require user to log in before being able to confirm a booking. Note that if you have guest bookings enabled, this setting will not apply for bookings belonging to a non-registered user.', 'em-pro' );
			em_options_radio_binary( __( 'Require login?', 'em-pro' ), 'dbem_bookings_rsvp_endpoint_login', $desc );
			?>
		</tbody>
		<tr class="em-boxheader">
			<td colspan='2'>
				<h4><?php echo __('RSVP Policies','events-manager'); ?></h4>
				<p>
					<?php esc_html__('If a booking has an approved/confirmed status but not confirmed by the user via RSVP, enabling an RSVP policy can open bookings for new bookings and subsequently cancel the booking of the unconfirmed user.', 'em-pro');  ?>
				</p>
			</td>
		</tr>
		<?php
		em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), __('RSVP Policy','em-pro')), 'dbem_bookings_rsvp_policy', '', '', '.rsvp-policy-options');
		?>
		<tbody class="rsvp-policy-options">
		<?php
			$cancellation_hours_desc = __( 'Enter the number of hours before an event starts for when users must RSVP a booking.', 'events-manager');
			$cancellation_hours_desc_2 = __('%s are also accepted, for example %s equals 1 month and 12 hours before the event starts.', 'events-manager');
			$cancellation_hours_desc .= ' '. sprintf($cancellation_hours_desc_2, '<a href="https://www.php.net/manual/en/dateinterval.construct.php" target="_blank">'.esc_html_x('PHP date intevals', 'Refer to PHP docs for translation.', 'events-manager').'</a>', '<code>P1MT12H</code>');
			$interval = get_option('dbem_bookings_rsvp_policy_deadline');
			if( $interval && !static::validate_dateinterval( $interval ) ){
				$error = sprintf( esc_html__('Please add a valid number or DateInterval time interval format for the %s setting.', 'events-manager'), '<code>'. esc_html__('Default RSVP cut-off time.', 'em-pro') . '</code>');
				$cancellation_hours_desc .= '<br><strong style="color:red;">'. $error . '</strong>';
			}
			em_options_input_text( __( 'Default policy deadline', 'em-pro' ), 'dbem_bookings_rsvp_policy_deadline', $cancellation_hours_desc );
			$policies = array(
				'open' => esc_html__('Open', 'em-pro') . ' - ' . esc_html__( 'RSVPing attendance is optional, but no action is taken in any case.', 'em-pro' ),
				'flexible' => esc_html__('Flexible', 'em-pro') . ' - ' . esc_html__('Bookings are not cancelled, but spaces available for booking. If booked, unconfirmed bookings get cancelled.', 'em-pro'),
				'strict' =>  esc_html__('Strict', 'em-pro') . ' - ' . esc_html__('Bookings are cancelled after cutoff time is reached.', 'em-pro'),
			);
			em_options_select( __( 'Default cancellation policy', 'em-pro' ), 'dbem_bookings_rsvp_policy_type', $policies, '', 'open' );
			
			em_options_radio_binary( __( 'Enable policies by event?', 'em-pro' ), 'dbem_bookings_rsvp_policy_events', esc_html__('Events can override the default policy type or disable it entirely.', 'em-pro'), '', '#dbem_bookings_rsvp_policy_default_row' );
			em_options_radio_binary( __( 'Enable policy by default?', 'em-pro' ), 'dbem_bookings_rsvp_policy_default', esc_html__('Events that do not specifically choose to enable or disable this feature will inherit the following setting.', 'em-pro') );
		?>
		</tbody>
		<tbody class="rsvp-policy-options">
		<?php
	}
}
Admin::init();