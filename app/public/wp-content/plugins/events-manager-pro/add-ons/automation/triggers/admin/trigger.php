<?php
namespace EM\Automation\Triggers\Admin;
use EM\Automation;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Trigger {
	
	/**
	 * @return void
	 */
	public static function options( $trigger = null ){
		// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
		?>
		<input type="hidden" name="trigger_data[option]" value="trigger">
		<?php
	}
	
	public static function get_post( $trigger ){
		// get trigger name
		$trigger->name = !empty($_REQUEST['automation_name']) ? sanitize_text_field($_REQUEST['automation_name']) : '';
		// get_trigger
		$result_trigger = static::get_post_trigger_data($trigger);
		$errors = array();
		if( is_array($result_trigger) ){
			$errors = $result_trigger;
		}
		// get actions
		$result_actions = static::get_post_action_data($trigger);
		if( is_array($result_actions) ){
			$errors = array_merge($errors, $result_actions);
		}
		return empty($errors) ? true : $errors;
	}
	
	public static function get_post_trigger_data( $trigger ){
		// get trigger data
		if( !empty($_REQUEST['trigger_data']) ){
			$trigger_data = $_REQUEST['trigger_data'];
			foreach( $trigger_data as $key => $value ){
				if( is_array($value) ){
					foreach( $value as $k => $v ){
						$value[$k] = sanitize_text_field($v);
					}
					$trigger_data[$key] = $value;
				}else{
					$trigger_data[$key] = sanitize_text_field($value);
				}
			}
			if( !empty($_REQUEST['trigger_output_' . $trigger::$context]) ){
				$trigger_data['output'] = $trigger_output = $_REQUEST['trigger_output_' . $trigger::$context];
				$trigger_data['filters'] = array();
				foreach( array( $trigger_output, 'any' ) as $trigger_output_name ) {
					if ( !empty( $_REQUEST['trigger_output'][ $trigger_output_name ] ) ) {
						foreach ( $_REQUEST['trigger_output'][ $trigger_output_name ] as $key => $value ) {
							if ( is_array( $value ) ) {
								foreach ( $value as $k => $v ) {
									$value[ sanitize_key( $k ) ] = sanitize_text_field( $v );
								}
								$trigger_data['filters'][ sanitize_key( $key ) ] = $value;
							} else {
								$trigger_data['filters'][ sanitize_key( $key ) ] = sanitize_text_field( $value );
							}
						}
					}
				}
			}
			$trigger->trigger_data = $trigger_data;
		}
		return true;
	}
	
	public static function get_post_action_data( $trigger ){
		$trigger->action_data = array();
		if( !empty($_REQUEST['action_data']) ){
			foreach( $_REQUEST['action_data'] as $key => $action_data ){
				$action_data = wp_unslash( $action_data );
				if( $key !== '%id%' ){
					$action_type = $action_data['type'];
					if( $action_type ){
						$action = Automation::get_action($action_type);
						if( $action ){
							$trigger->action_data[] = array(
								'type' => $action_type,
								'enabled' => !empty($action_data['enabled']),
								'data' => $action::load_admin()::get_post( $action_data['data'] ),
							);
						} else {
							return array( sprintf(esc_html__('Action type %s not found. Please make sure it is enabled.', 'em-pro'), "<code>$action_type</code>") );
						}
					}
				}
			}
		}
		return true;
	}
	
	public static function validate( $trigger ){
		$errors = array();
		if( empty($trigger->name) ){
			$errors[] = esc_html__('Please enter a name for your automation.', 'em-pro');
		}
		if( empty($trigger->trigger_data['output']) ){
			$errors[] = esc_html__('Please select what to output to your actions.', 'em-pro');
		}
		if( empty($trigger->action_data) ){
			$errors[] = esc_html__('You must create at least one action.', 'em-pro');
		}
		foreach( $trigger->action_data as $action_data ){
			$action_type = $action_data['type'];
			$action = Automation::get_action($action_type);
			$action_admin = $action::load_admin();
			$action_validation = $action_admin::validate($action_data['data']);
			if( $action_validation !== true ){
				$errors = array_merge($errors, $action_validation);
			}
		}
		return empty($errors) ? true:$errors;
	}
	
	public static function save( $trigger ){
		global $wpdb;
		$data = array(
			'name' => $trigger->name,
			'type' => $trigger::$type,
			'trigger_data' => serialize($trigger->trigger_data),
			'action_data' => serialize($trigger->action_data),
		);
		if( $trigger->id ){
			// update
			$result = $wpdb->update(EM_AUTOMATION_TABLE, $data, array('id'=>$trigger->id));
		}else{
			// insert
			$result = $wpdb->insert(EM_AUTOMATION_TABLE, $data);
			if( $result !== false ) {
				$trigger->id = $wpdb->insert_id;
			}
		}
		if( $result !== false ){
			return true;
		}else{
			return array( esc_html__('There was a problem saving your automation:', 'em-pro') . '<code>'. $wpdb->last_error .'</code>');
		}
	}
}