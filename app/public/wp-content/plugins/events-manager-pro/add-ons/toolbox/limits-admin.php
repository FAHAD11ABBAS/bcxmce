<?php
namespace EM\Toolbox;

class Limits_Admin {
	public static function init(){
		add_action('em_options_page_event_submission_after', '\EM\Toolbox\Limits_Admin::options_box', 1);
	}
	
	public static function options_box(){
		global $save_button, $wpdb;
		?>
		<div  class="postbox" id="em-opt-event-submission-limits" >
			<div class="handlediv" title="<?php esc_html_e_emp('Click to toggle'); ?>"><br /></div><h3><span><?php _e ( 'Event Submission Limits', 'em-pro'); ?></span></h3>
			<div class="inside">
				<table class="form-table">
					<tr><td colspan="2" class="em-boxheader">
							<p><?php _e('You can impose limits on how many events a user can submit, including how recurrences are limited and considered within the limitaitons.', 'em-pro')?></p>
							<p>
								<?php _e('Please note that if you allow guest event submissions, limitations are checked against the submitted email using the general limits as well as the guest default user role you assigned in the event submission settings section above.','em-pro'); ?>
								<strong>
									<?php esc_html_e('This is not a secure way to limit event submissions, since a guest user can potentially use multiple different emails.', 'em-pro'); ?>
								</strong>
							</p>
					</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Event Submission Limits', 'em-pro')), 'dbem_event_submission_limits_enabled','', '', '.em-event-submission-limits-options');
					?>
					<tbody class="em-event-submission-limits-options">
					<?php
					global $wp_roles;
					?>
					<tr class="em-subheader"><td colspan="2"><h4><?php esc_html_e('General Limits'); ?></h4></td></tr>
					<?php
					if( is_multisite() && is_network_admin() ){
						//echo em_options_radio_binary(__('Apply global capabilities?','em-pro'), 'dbem_ms_global_caps', __('If set to yes the capabilities will be applied all your network blogs and you will not be able to set custom capabilities each blog. You can select no later and visit specific blog settings pages to add/remove capabilities.','em-pro') );
					}
					em_options_input_text(esc_html__('Daily', 'em-pro'), 'dbem_event_submission_limits[daily]');
					em_options_input_text(esc_html__('Weekly', 'em-pro'), 'dbem_event_submission_limits[weekly]');
					em_options_input_text(esc_html__('Monthly', 'em-pro'), 'dbem_event_submission_limits[monthly]');
					em_options_input_text(esc_html__('Recurrences', 'em-pro'), 'dbem_event_submission_limits[recurrences]', esc_html__('The maximum number of recurrences that can occur when submitting a single recurring event.', 'em-pro'));
					em_options_radio_binary(esc_html__('Recurring events count as', 'em-pro'), 'dbem_event_submission_limits_count_recurrences', esc_html__('Recurrences can count as a single event towards submission limits, or as the amount of recurrences created by the recurring event. We recommend counting recurrences as single events and throttling how many recurrences can be created in one submission, since the individual recurrences are not created until an event is published/approved.', 'em-pro'), array(0=> esc_html__('One event', 'em-pro'), 1=> esc_html__('Multiple events', 'em-pro')));
					?>
					<tr class="em-subheader">
						<td colspan="2">
							<h4><?php esc_html_e('Role-Specific Limits', 'em-pro'); ?></h4>
							<p><?php esc_html_e('The following settings apply to each specific role a user belongs to. These settings can override the general settings according to your preference settings below. Leave blank to default to the general settings, or 0 to remove a limit.', 'em-pro'); ?></p>
						</td>
					</tr>
					<?php
					em_options_radio_binary(__('What limit takes precedence?','em-pro'), 'dbem_event_submission_limits_role_precedence', __('When merging general limits with user-specific role limits, you can choose whether the highest or lowest limit takes precedence. Number 0 means no limit and therefore is considered the highest number.','em-pro'), array(0=> esc_html__('Lowest', 'em-pro'), 1 => esc_html__('Highest', 'em-pro')) );
					?>
					<?php foreach($wp_roles->role_objects as $role): ?>
					<tr class="em-subheader"><td colspan="2"><h4><?php echo translate_user_role( $role->name ); ?></h4></td></tr>
					<?php
						em_options_input_text(esc_html__('Daily', 'em-pro'), 'dbem_event_submission_limits_roles['.$role->name.'][daily]');
						em_options_input_text(esc_html__('Weekly', 'em-pro'), 'dbem_event_submission_limits_roles['.$role->name.'][weekly]');
						em_options_input_text(esc_html__('Monthly', 'em-pro'), 'dbem_event_submission_limits_roles['.$role->name.'][monthly]');
						em_options_input_text(esc_html__('Recurrences', 'em-pro'), 'dbem_event_submission_limits_roles['.$role->name.'][recurrences]');
					?>
					<?php endforeach; ?>
					</tbody>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
	
}
Limits_Admin::init();