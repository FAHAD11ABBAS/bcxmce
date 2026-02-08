<?php
namespace EM\Automation\Triggers\Admin;
use EM\Automation;
/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Booking_Status extends Trigger {
	
	/**
	 * @return void
	 */
	public static function options( $trigger = null ){
		// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
		$EM_Booking = new \EM_Booking();
		$status_from = $trigger instanceof Automation\Triggers\Booking_Status && !empty($trigger->trigger_data['status_from']) && is_array($trigger->trigger_data['status_from']) ? $trigger->trigger_data['status_from'] : array();
		?>
		<p>
			<label><?php esc_html_e('When status changes from', 'em-pro'); ?></label>
			<select name="trigger_data[status_from][]" id="em_automation_booking_status_from" class="em-selectize checkboxes" multiple>
				<option value=""><?php esc_html_e('Choose booking status (optional)', 'em-pro'); ?></option>
				<option value="new"  <?php if( in_array('new', $status_from) ) echo 'selected'; ?>><?php esc_html_e('No Status (New Booking)', 'em-pro'); ?></option>
				<?php foreach( $EM_Booking->status_array as $status => $status_name ): ?>
					<option value="<?php echo esc_attr($status); ?>" <?php if( in_array((string) $status, $status_from) ) echo 'selected'; ?>><?php echo esc_html($status_name); ?></option>
				<?php endforeach; ?>
			</select>
			<em><?php esc_html_e('Note that in some situations, such as bookings with payments, if you want a new booking that is confirmed then you should select new bookings and either/both awaiting payment statuses.', 'em-pro'); ?></em>
		</p>
		<p>
			<em><?php esc_html_e('Example : If you would like new bookings paid offline then select new bookings and awaiting offline payment statuses as well as selecting bookings further down that are confirmed and using the offline gateway.', 'em-pro'); ?></em>
		</p>
		<?php
	}
}