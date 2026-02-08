<?php
namespace EM\Payments\Paypal\Legacy;

class Gateway_Admin extends \EM\Payments\Gateway_Admin {
	
	public static function init() {
		parent::init();
		static::$api_cred_fields = array(
			'username' => __('API Username', 'em-pro'),
			"password" => __('API Password', 'em-pro'),
			"signature" => __('API Signature', 'em-pro'),
		);
	}
	
	public static function settings_tabs( $custom_tabs = array() ){
		$tabs = array(
			'options' => array(
				'name' => sprintf( esc_html__emp('%s Options'), 'PayPal' ),
				'callback' => array( static::class, 'settings_options'),
			),
		);
		return parent::settings_tabs( $tabs );
	}
	
	public static function settings_options(){
		?>
		<h2><?php echo sprintf(__('%s Options','em-pro'),'PayPal'); ?></h2>
		<table class="form-table">
			<tbody>
			<?php
				em_options_input_text( esc_html__('PayPal Email', 'em-pro'), 'em_'. static::$gateway . '_email' );
				em_options_radio_binary(__('Include Taxes In Itemized Prices', 'em-pro'), 'em_'. static::$gateway .'_inc_tax', __('If set to yes, taxes are not included in individual item prices and total tax is shown at the bottom. If set to no, taxes are included within the individual prices.','em-pro'). ' '. __('We strongly recommend setting this to No.','em-pro') .' <a href="http://wp-events-plugin.com/documentation/events-with-paypal/paypal-displaying-taxes/">'. __('Click here for more information.','em-pro').'</a>');
			?>
			<tr valign="top">
				<th scope="row"><?php _e('PayPal Language', 'em-pro') ?></th>
				<td>
					<select name="em_paypal_lc">
						<option value=""><?php _e('Default','em-pro'); ?></option>
						<?php
						$ccodes = em_get_countries();
						$paypal_lc = get_option('em_'.static::$gateway.'_lc', 'US');
						foreach($ccodes as $key => $value){
							if( $paypal_lc == $key ){
								echo '<option value="'.$key.'" selected="selected">'.$value.'</option>';
							}else{
								echo '<option value="'.$key.'">'.$value.'</option>';
							}
						}
						?>

					</select>
					<br />
					<i><?php _e('PayPal allows you to select a default language users will see. This is also determined by PayPal which detects the locale of the users browser. The default would be US.','em-pro') ?></i>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('PayPal Page Logo', 'em-pro') ?></th>
				<td>
					<input type="text" name="em_paypal_format_logo" value="<?php esc_attr_e(get_option('em_'. static::$gateway . "_format_logo" )); ?>" style='width: 40em;' /><br />
					<em>
						<?php _e('Add your logo to the PayPal payment page. It\'s highly recommended you link to a https:// address.', 'em-pro'); ?>
						<?php _e('PayPal requires this logo to be a maximum size of 150 x 50 pixels, larger images will be cropped.', 'em-pro'); ?>
					</em>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Border Color', 'em-pro') ?></th>
				<td>
					<input type="text" name="em_paypal_format_border" class="em-colorpicker em-paypal-border-color" value="<?php esc_attr_e(get_option('em_'. static::$gateway . "_format_border" )); ?>" style='width: 40em;' /><br />
					<em><?php _e('Provide a hex value color to change the color from the default blue to another color (e.g. #CCAAAA).','em-pro'); ?></em>
				</td>
			</tr>
			</tbody>
		</table>
		<script>
			jQuery(document).ready( function($){
				$('.em-paypal-border-color').wpColorPicker();
			});
		</script>
		<?php
	}
	
	public static function settings_credentials(){
		?>
		<h2><?php echo sprintf(__('%s Credentials','em-pro'),'PayPal'); ?></h2>
		<p><strong><?php _e('Important:','em-pro'); ?></strong> <?php echo __('In order to connect PayPal with your site, you need to enable IPN on your account.'); echo " ". sprintf(__('Your return url is %s','em-pro'),'<code>'.static::gateway()::get_payment_return_url().'</code>'); ?></p>
		<p><?php echo sprintf(__('Please visit the <a href="%s">documentation</a> for further instructions.','em-pro'), 'http://wp-events-plugin.com/documentation/events-with-paypal/'); ?></p>
		<table class="form-table">
			<tbody>
			<?php
			$status_modes = array('live' => __('Live Site', 'em-pro'), 'test' => __('Test Mode (Sandbox)', 'em-pro') );
			em_options_select(esc_html__('PayPal Mode', 'em-pro'), 'em_'. static::$gateway . "_status", $status_modes);
			?>
			<tr>
				<td colspan="2">
					<p><?php esc_html_e('The following API credentials are optional, but recommended. They will allow us to check whether payments have gone through in the event that IPN notifications fail to reach us.', 'em-pro'); ?>
						<?php esc_html_e('These credentials are required if you enable deleting bookings that remain unpaid after x minutes.', 'em-pro'); ?></p>
				</td>
			</tr>
			<?php
			$is_sandbox = get_option('em_'. static::$gateway . "_status") == 'test';
			static::settings_sensitive_credentials($api_cred_fields, $is_sandbox);
			?>
			</tbody>
		</table>
		<?php
	}

	/* 
	 * Run when saving PayPal settings, saves the settings available in EM_Gateway_Paypal::mysettings()
	 */
	public static function update( $options = array() ) {
		$gateway_options = array();
		$gateway_options[] = 'em_'. static::$gateway . '_email';
		$gateway_options[] = 'em_'. static::$gateway . '_inc_tax';
		$gateway_options[] = 'em_'. static::$gateway . '_lc';
		$gateway_options[] = 'em_'. static::$gateway . '_format_logo';
		$gateway_options[] = 'em_'. static::$gateway . '_format_border';
		//add wp_kses sanitization filters for relevant options
		add_filter('gateway_update_'.'em_'. static::$gateway . '_email', 'trim');
		//pass options to parent which handles saving
		return parent::update($gateway_options);
	}
}
?>