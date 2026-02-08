<?php
namespace EM\Automation\Actions\Admin;
use EM\Automation;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Action {
	
	public static $type = 'action';
	
	/**
	 * @return void
	 */
	public static function options( $action_data = array(), $id = '%id%' ){
		// output any sort of html options relevant to this trigger, that'll be stored in action_data
		?>
		<input type="hidden" name="action_data[<?php echo $id; ?>][type]" value="<?php echo esc_attr(static::$type); ?>">
		<?php
		static::options_extra( $action_data, $id);
	}
	
	public static function options_extra( $action_data, $id ){
		if( !empty($action_data['data']['option']) ){
			?>
			<input type="hidden" name="action_data[<?php echo $id; ?>][data][option]" value="<?php echo esc_attr($action_data['data']['option']); ?>">
			<?php
		}
	}
	
	public static function get_post( $action_data ){
		// format any relevant post data, which is supplied in submitted format of action_data[]
		return $action_data;
	}
	
	public static function validate( $action_data ){
		return true;
	}
	
	public static function footer(){
		// output things like JS
	}
}