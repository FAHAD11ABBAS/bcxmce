<?php
namespace EM\Waitlist;
class Admin {
	
	public static function init(){
		add_action('em_options_page_footer_bookings', '\EM\Waitlist\Admin::options');
		add_action('em_options_page_footer_emails', '\EM\Waitlist\Admin::email_options');
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
		<div  class="postbox " id="em-opt-waitlists" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php _e ( 'Waitlists', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php
								esc_html_e( 'You can enable waiting lists for some or all of your events, allowing users to sign up for a waiting list and if a ticket they are ellegible for frees up, they have the opportunity to book it.', 'em-pro' );
								esc_html_e( 'If a ticket frees up that cannot be booked by the first wait-listed booking in line, the next available wait-listed booking will be allowed to bok that specific ticket.', 'em-pro' );
								//You can further customize all these templates, or parts of them by overriding our template files as per our %s.
								?>
							</p>
							<p>
								<?php
									$msg = esc_html__( 'You can customize the email templates sent to users who sign up to the waitlists in the %s tab.', 'em-pro' );
									$msg = sprintf( $msg, '<a href="#emails">'. esc_html__emp('Emails') .'</a>' );
									echo $msg;
								?>
							</p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Waiting Lists', 'em-pro')), 'dbem_waitlists','', '', '.waitlist-options');
					?>
					<tbody class="waitlist-options">
						<?php
						em_options_radio_binary ( __( 'Allow guest reservations?', 'em-pro' ), 'dbem_waitlists_guests',__('Allow users to join the waiting list supplying only their name and email without logging in. If a booking requires a registered user (such as member-only tickets, or members must register) then this setting will not have any effect.', 'em-pro'));
						em_options_input_text ( ucfirst(strtolower(esc_html__emp('Maximum Spaces Per Booking'))), 'dbem_waitlists_booking_limit',__('Restrict how many spaces someone can reserve, wait-listed bookings will remain in front of the queue until the required number of spaces free up.', 'em-pro'));
						em_options_input_text ( ucfirst(strtolower(esc_html__emp( 'Total Spaces' ))), 'dbem_waitlists_limit',__('Restrict how many spaces events will have availble for waitlisting, which can be overriden by event settings if you allow it in these settings..', 'em-pro'));
						em_options_input_text ( __( 'Waitlist approval expiry', 'em-pro' ), 'dbem_waitlists_expiry',__('Hours until an wait-listed booking can keep their reserved spot once it becomes available to them. If a booking is not completed within that time-frame the reservation is cancelled and the next wait-listed booking is allowed to book.', 'em-pro'));
						?>
						<tr class="em-header">
							<td colspan="2">
								<h4><?php esc_html_e('Event-Specific Settings', 'em-pro'); ?></h4>
								<p><?php esc_html_e('Above respective forms such as the waiting list form or when booking a wait-listed event, you can customize the text to give more information to the user about booking a wait-listed event', 'em-pro'); ?></p>
							</td>
						</tr>
						<?php
						em_options_radio_binary(esc_html__('Allow event-specific settings?', 'em-pro'), 'dbem_waitlists_events', esc_html__('You can override the default settings on a per-event basis, including exclusion of certain tickets for waitlisting, if enabled.', 'em-pro'), '', '#dbem_waitlists_events_default_row, #dbem_waitlists_events_tickets_row');
						?>
						<?php
						$event_options = array(
							0 => esc_html__('Disabled by default', 'em-pro'),
							1 => esc_html__('Enabled by default', 'em-pro'),
							2 => esc_html__('Enabled for all', 'em-pro'),
						);
						em_options_select(esc_html__('Event waitlist option', 'em-pro'), 'dbem_waitlists_events_default', $event_options, esc_html__('You can choose whether to enable waitlists as an option per event, or enable waitlists for all your events. If you choose to enable by default then all events that do not specifically have waitlists disabled will display a waitlist.', 'em-pro'));
						em_options_radio_binary(esc_html__('Allow ticket exclusions?', 'em-pro'), 'dbem_waitlists_events_tickets', esc_html__('You can allow event creators to exclude tickets from becoming available or calculated towards waitlists.', 'em-pro'));
						?>
						<tr class="em-header">
							<td colspan="2">
								<h4><?php esc_html_e('Heading Content', 'em-pro'); ?></h4>
								<p><?php esc_html_e('Above waitlist-related content such as when booking a wait-listed event or viewing a previously made waitlist reservation, you can customize the text to give more information to the user about booking a wait-listed event. HTML is accepted.', 'em-pro'); ?></p>
							</td>
						</tr>
						<?php
						global $bookings_placeholder_tip, $events_placeholder_tip;
						$waitlist_tip = static::get_waitlist_tip();
						em_options_input_text( __( 'Login text', 'em-pro' ), 'dbem_waitlists_login_text',__('Displayed when a user needs to log in to access the waiting lists.', 'em-pro'));
						em_options_textarea( __( 'Already waiting', 'em-pro' ), 'dbem_waitlists_text_already_waiting',__('If the user already has a waiting list application.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						em_options_textarea( __( 'Booking form', 'em-pro' ), 'dbem_waitlists_text_booking_form',__('If a wait-listed booking is approved and user can book their previously reserved seats. This is displayed above the regular event booking form.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						em_options_textarea( __( 'Waitlist', 'em-pro' ), 'dbem_waitlists_text_form',__('If a waitlist is active and the user can apply to the waitlist, this is displayed above the waitlist form.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						em_options_textarea( __( 'Cancelled', 'em-pro' ), 'dbem_waitlists_text_cancelled',__('If the user previously cancelled their waitlist booking.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						em_options_textarea( __( 'Expired', 'em-pro' ), 'dbem_waitlists_text_expired',__('If the waitlist reservation expired.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						em_options_textarea( __( 'Waitlist Full', 'em-pro' ), 'dbem_waitlists_text_full',__('When the waitlist is full.', 'em-pro').' '.$bookings_placeholder_tip.' '.$waitlist_tip);
						?>
						<tr class="em-header">
							<td colspan="2">
								<h4><?php esc_html_e('Feedback Messages', 'em-pro'); ?></h4>
								<p><?php esc_html_e('These are messages provided to the user during the process of joining a waitlist.', 'em-pro'); ?></p>
								<p><?php echo $events_placeholder_tip.' '.$waitlist_tip ?></p>
							</td>
						</tr>
						<?php
						em_options_input_text ( __( 'Reservation confirmed', 'em-pro' ), 'dbem_waitlists_feedback_confirmed',__('When a waitlist has been successfully joined.', 'em-pro'));
						em_options_input_text ( __( 'Already waiting', 'em-pro' ), 'dbem_waitlists_feedback_already_waiting',__('If a user attempts to join a waitlst they joined previously.', 'em-pro'));
						em_options_input_text ( __( 'Already waiting (guest)', 'em-pro' ), 'dbem_waitlists_feedback_already_waiting_guest',__('If a guest user (not logged in) attempts to join a waitlst they joined previously. This is based on a duplicate email being used.', 'em-pro') );
						em_options_input_text ( __( 'Cancelled', 'em-pro' ), 'dbem_waitlists_feedback_cancelled',__('When a user cancels their waitlist booking.', 'em-pro'));
						em_options_input_text ( __( 'Waitlist Full', 'em-pro' ), 'dbem_waitlists_feedback_full',__('When the limit of allowed spaces in a waitlist are reached, those attempting to join will receive this message.', 'em-pro'));
						em_options_input_text ( __( 'Booking limit reached', 'em-pro' ), 'dbem_waitlists_feedback_booking_limit',__('If a user attempts to book more than the allowed spaces per booking.', 'em-pro'));
						em_options_input_text ( __( 'More than available', 'em-pro' ), 'dbem_waitlists_feedback_spaces_limit',__('If a user attempts to book more spaces than are currently available (for example, if someone books just before they do with a nearly full waitlist).', 'em-pro'));
						
						em_options_input_text ( __( 'Log in first', 'em-pro' ), 'dbem_waitlists_feedback_log_in',__('Restrict how many spaces someone can reserve, wait-listed bookings will remain in front of the queue until the required number of spaces free up.', 'em-pro'));
						?>
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<script>
			// taken from admin-settings.js in events-manager/includes/js
	        jQuery('.waitlist-options textarea').on('focus', function(){
	            if( document.getElementById('em-enable-codeEditor').checked ){
	                var editor = wp.codeEditor.initialize( this );
	                editor.codemirror.on('blur', function( cm ){
	                    cm.toTextArea();
	                })
	            }
	        });
        </script>
		<?php
	}
	
	public static function get_waitlist_tip(){
		return sprintf( esc_html__('Additionally, you can also use %s placeholders.', 'em-pro'), '<a href="'.EM_ADMIN_URL .'&amp;page=events-manager-help#waitlist-placeholders">'. esc_html__('Waitlist', 'em-pro').'</a>' );
	}
	
	/**
	 * Generates meta box for settings page
	 */
	public static function email_options(){
		global $save_button;
		global $bookings_placeholder_tip;
		$waitlist_tip = static::get_waitlist_tip();
		?>
		<div  class="postbox waitlist-options" id="em-opt-waitlists" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php _e ( 'Waitlists', 'em-pro' ); ?></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader">
						<td colspan='2'>
							<p>
								<?php
								esc_html_e( 'When a user has applied to a waitlist, they will receive an email to confirm their application, and also a second email if they become elegible to complete a real booking.', 'em-pro' );
								esc_html_e( 'Additionally, if their booking expires, they will receive a custom email below. Other emails such as a regular cancellation or rejection of the booking will use the default email templates.', 'em-pro' );
								//You can further customize all these templates, or parts of them by overriding our template files as per our %s.
								?>
							</p>
							<p><em><?php echo $bookings_placeholder_tip . ' ' . $waitlist_tip; ?></em></p>
						</td>
					</tr>
					<tr class="em-subheader">
						<td colspan='2'>
							<h5><?php esc_html_e_emp('Waitlist Confirmed') ?></h5>
							<em><?php echo __( 'This will be sent to the person when they first apply for the waitlist.', 'em-pro'); ?></em>
						</td>
					</tr>
					<?php
					em_options_input_text ( __( 'Subject', 'em-pro' ), 'dbem_waitlists_emails_confirmed_subject' );
					em_options_textarea ( __( 'Message', 'em-pro' ), 'dbem_waitlists_emails_confirmed_message' );
					?>
					<tr class="em-subheader">
						<td colspan='2'>
							<h5><?php esc_html_e_emp('Waitlist Approved') ?></h5>
							<em><?php echo __( 'When a wait-listed booking becomes available for making an actual booking, this email is sent.', 'em-pro'); ?></em>
						</td>
					</tr>
					<?php
					em_options_input_text ( __( 'Subject', 'em-pro' ), 'dbem_waitlists_emails_approved_subject' );
					em_options_textarea ( __( 'Message', 'em-pro' ), 'dbem_waitlists_emails_approved_message' );
					?>
					<tr class="em-subheader">
						<td colspan='2'>
							<h5><?php _e('Expired Booking','em-pro') ?></h5>
							<em><?php echo __( 'If you enable an expiry time for wait-listed reservations, if a booking is not made in time, the reservation expires and this email is sent.', 'em-pro'); ?></em>
						</td>
					</tr>
					<?php
					em_options_input_text ( __( 'Subject', 'em-pro' ), 'dbem_waitlists_emails_expired_subject' );
					em_options_textarea ( __( 'Message', 'em-pro' ), 'dbem_waitlists_emails_expired_message' );
					?>
					<tr class="em-subheader">
						<td colspan='2'>
							<h5><?php esc_html_e_emp('Cancelled Booking') ?></h5>
							<em><?php esc_html_e_emp('This will be sent when a user cancels their booking.'); ?></em>
						</td>
					</tr>
					<?php
					em_options_input_text ( __( 'Subject', 'em-pro' ), 'dbem_waitlists_emails_cancelled_subject' );
					em_options_textarea ( __( 'Message', 'em-pro' ), 'dbem_waitlists_emails_cancelled_message' );
					?>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
}
Admin::init();