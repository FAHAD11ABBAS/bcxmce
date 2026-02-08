<?php
namespace EM\Automation\Triggers\Admin;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Booking_Time extends Trigger{
	
	/**
	 * @return void
	 */
	public static function options( $trigger = null ){
		// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
		$is_instance = $trigger instanceof \EM\Automation\Triggers\Booking_Time;
		$amount = $is_instance && !empty($trigger->trigger_data['time']['amount']) ? $trigger->trigger_data['time']['amount'] : 1;
		$amount_type = $is_instance && !empty($trigger->trigger_data['time']['type']) ? $trigger->trigger_data['time']['type'] : 'hours';
		$future_only = $is_instance && !empty($trigger->trigger_data['future_events']) ? $trigger->trigger_data['future_events'] : false;
		?>
		<p>
			<label class="screen-reader-text" for="em-automation-trigger-booking-time-amount"><?php esc_html_e('Amount of time', 'em-pro'); ?></label>
			<input name="trigger_data[time][amount]" id="em-automation-trigger-booking-time-amount" value="<?php echo esc_attr($amount); ?>" style="padding:5px;" maxlength="3">
			<label class="screen-reader-text" for="em-automation-trigger-booking-time-type"><?php esc_html_e('Unit of time', 'em-pro'); ?></label>
			<select name="trigger_data[time][type]" id="em-automation-trigger-booking-time-type">
				<option value="minutes" <?php if( $amount_type == 'minutes' ) echo 'selected'; ?>><?php esc_html_e('Minutes', 'em-pro'); ?></option>
				<option value="hours" <?php if( $amount_type == 'hours' ) echo 'selected'; ?>><?php esc_html_e('Hours', 'em-pro'); ?></option>
				<option value="days" <?php if( $amount_type == 'days' ) echo 'selected'; ?>><?php esc_html_e('Days', 'em-pro'); ?></option>
			</select>
			<?php esc_html_e('after the booking was made.', 'em-pro'); ?>
		</p>
		<p>
			<input type="checkbox" name="trigger_data[future_events]" id="em-automation-trigger-booking-time-future-events" value="1" <?php if( $future_only ) echo 'checked'; ?>>
			<label for="em-automation-trigger-booking-time-future-events"><?php esc_html_e('Only events that have not started yet', 'em-pro'); ?></label>
		</p>
		<?php
	}
}