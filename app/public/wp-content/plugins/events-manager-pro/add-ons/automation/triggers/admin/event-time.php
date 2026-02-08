<?php
namespace EM\Automation\Triggers\Admin;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Event_Time extends Trigger{
	
	/**
	 * @return void
	 */
	public static function options( $trigger = null ){
		// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
		$is_instance = $trigger instanceof \EM\Automation\Triggers\Event_Time;
		$amount = $is_instance && !empty($trigger->trigger_data['time']['amount']) ? $trigger->trigger_data['time']['amount'] : 1;
		$amount_type = $is_instance && !empty($trigger->trigger_data['time']['type']) ? $trigger->trigger_data['time']['type'] : 'hours';
		$amount_when = $is_instance && !empty($trigger->trigger_data['time']['when']) ? $trigger->trigger_data['time']['when'] : 'before';
		?>
		<p>
			<label class="screen-reader-text" for="em-automation-trigger-event-time-amount"><?php esc_html_e('Amount of time', 'em-pro'); ?></label>
			<input name="trigger_data[time][amount]" id="em-automation-trigger-event-time-amount" value="<?php echo esc_attr($amount); ?>" style="padding:5px;" maxlength="3">
			<label class="screen-reader-text" for="em-automation-trigger-event-time-type"><?php esc_html_e('Unit of time', 'em-pro'); ?></label>
			<select name="trigger_data[time][type]" id="em-automation-trigger-event-time-type">
				<option value="minutes" <?php if( $amount_type == 'minutes' ) echo 'selected'; ?>><?php esc_html_e('Minutes', 'em-pro'); ?></option>
				<option value="hours" <?php if( $amount_type == 'hours' ) echo 'selected'; ?>><?php esc_html_e('Hours', 'em-pro'); ?></option>
				<option value="days" <?php if( $amount_type == 'days' ) echo 'selected'; ?>><?php esc_html_e('Days', 'em-pro'); ?></option>
			</select>
			<label class="screen-reader-text" for="em-automation-trigger-event-time-when"><?php esc_html_e('Before or after the event', 'em-pro'); ?></label>
			<select name="trigger_data[time][when]" id="em-automation-trigger-event-time-when">
				<option value="before" <?php if( $amount_when == 'before' ) echo 'selected'; ?>><?php esc_html_e('Before', 'em-pro'); ?></option>
				<option value="after" <?php if( $amount_when == 'after' ) echo 'selected'; ?>><?php esc_html_e('After', 'em-pro'); ?></option>
			</select>
			<?php esc_html_e('the event start time.', 'em-pro'); ?>
		</p>
		<?php
	}
}