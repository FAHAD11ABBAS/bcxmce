<?php
namespace EM\Automation\Actions\Admin;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Email extends Action {
	
	public static $type = 'email';
	
	public static function options_extra( $action_data, $id ){
		$who = !empty($action_data['data']['who']) ? $action_data['data']['who'] : '';
		$emails = !empty($action_data['data']['emails']) ? $action_data['data']['emails'] : '';
		$subject = !empty($action_data['data']['subject']) ? $action_data['data']['subject'] : '';
		$message = !empty($action_data['data']['who']) ? $action_data['data']['message'] : '';
		?>
		<p>
			<select name="action_data[<?php echo $id; ?>][data][who]" class="em-automation-action-emails-who">
				<option value="emails" <?php if( $who === 'emails' ) echo 'selected'; ?>><?php esc_html_e('Emails', 'em-pro'); ?></option>
				<option value="booking_admins" <?php if( $who === 'booking_admins' ) echo 'selected'; ?>><?php esc_html_e('Event Booking Admins', 'em-pro'); ?></option>
				<option value="owner" <?php if( $who === 'owner' ) echo 'selected'; ?>><?php esc_html_e('Event Owner', 'em-pro'); ?></option>
				<option value="registrant" <?php if( $who === 'registrant' ) echo 'selected'; ?> data-context="booking"><?php esc_html_e('Registrant', 'em-pro'); ?> (bookings only)</option>
			</select>
		</p>
		<p class="em-automation-action-emails-emails">
			<label><?php esc_html_e('Emails'); ?></label>
			<input type="text" name="action_data[<?php echo $id; ?>][data][emails]" value="<?php echo $emails; ?>" class="widefat">
			<br><em><?php esc_html_e('For multiple addresses, separate emails with a comma.', 'em-pro'); ?></em>
		</p>
		<p>
			<strong><?php esc_html_e('Email Content'); ?></strong>
			<em><?php esc_html__('The message and subject can bth contain placeholders for the relevant object, such as an event or booking', 'em-pro'); ?></em>
		</p>
		<p>
			<label><?php esc_html_e('Subject'); ?></label>
			<input type="text" name="action_data[<?php echo $id; ?>][data][subject]" value="<?php echo $subject; ?>" class="widefat">
		</p>
		<p>
			<label><?php esc_html_e('Message'); ?></label>
			<textarea name="action_data[<?php echo $id; ?>][data][message]"><?php echo esc_attr($message, ENT_QUOTES);?></textarea>
		</p>
		<?php
	}
	
	public static function footer(){
		?>
		<script>
			jQuery('#actions-container').on('change', '.em-automation-action-emails-who', function(){
				let el = jQuery(this);
				if( el.val() === 'emails' ){
					el.parent().next().show();
				}else{
					el.parent().next().hide();
				}
			});
			jQuery('.em-automation-action-emails-who').trigger('change');
		</script>
		<?php
	}
}