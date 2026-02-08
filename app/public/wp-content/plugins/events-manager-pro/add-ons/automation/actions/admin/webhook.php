<?php
namespace EM\Automation\Actions\Admin;
use EM\Automation;

/**
 * Container for Trigger-specific admin outputs and post retrieval so that it is loaded and invoked only when needed
 */
class Webhook extends Action {
	
	public static $type = 'webhook';
	
	public static function options_extra( $action_data, $id ){
		$url = !empty($action_data['data']['url']) ? $action_data['data']['url'] : '';
		?>
		<p>
			<label><?php esc_html_e('Webhook URL'); ?></label>
			<input type="text" name="action_data[<?php echo $id; ?>][data][url]" value="<?php echo $url; ?>" class="em-automation-webhook-url widefat">
			<span class="em-automation-webhook-test-result"></span>
			<button type="button" class="em-automation-webhook-test-trigger button button-secondary"><?php esc_html_e('Test Webhook', 'em-pro'); ?></button>
		</p>
		<?php
	}
	
	public static function validate( $action_data ){
		$errors = array();
		$result = parent::validate($action_data);
		if( $result !== true ){
			$errors = $result;
		}
		if( !wp_http_validate_url($action_data['url']) ){
			$errors[] = esc_html__('Please enter a valid URL for your webhook.', 'em-pro');
		}
		return empty($errors) ? true : $errors;
	}
	
	public static function footer(){
		?>
		<script>
			jQuery('#actions-container').on('click', '.em-automation-webhook-test-trigger', function(){
				let el = jQuery(this);
				// alert user what will happen
				if( confirm('<?php esc_html_e('A fake test object as per your settings will be sent to your chosen webhook.', 'em-pro'); ?>') ){
					el.prev('.em-automation-webhook-test-result').remove();
					let status = jQuery('<p class="em-automation-webhook-test-result"><?php esc_html_e('Loading...', 'em-pro'); ?></p>').insertBefore(el);
					let data = {
						url : el.siblings('.em-automation-webhook-url').first().val(),
						nonce: '<?php echo wp_create_nonce('em-automation-webhook-test'); ?>',
						action:'em_automation_action_webhook_test',
						context: EM_Automation.context,
					};
					status.load( EM.ajaxurl, data );
				}
			});
			jQuery('.em-automation-action-emails-who').trigger('change');
		</script>
		<?php
	}
}