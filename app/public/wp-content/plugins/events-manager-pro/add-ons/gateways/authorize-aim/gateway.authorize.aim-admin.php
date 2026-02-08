<?php
namespace EM\Payments\Authorize_AIM;

use EM_Booking, EM_Event, EM_Pro, EM;
use WP_REST_Response, WP_REST_Request, WP_Error;

class Gateway_Admin extends EM\Payments\Gateway_Admin {
	
	public static $webhook_events = array(
		'net.authorize.payment.void.created',
		'net.authorize.payment.refund.created'
	);
	public static $documentation_url_api = 'http://wp-events-plugin.com/documentation/event-bookings-with-authorize-net-aim/';
	public static $webhook_admin_urls = array(
		'https://sandbox.authorize.net/UI/themes/sandbox/Settings/Webhooks.aspx',
		'https://authorize.net/UI/themes/sandbox/Settings/Webhooks.aspx',
	);
	
	public static function init() {
		parent::init();
		static::$api_cred_fields = array(
			'login' => __('API Login ID', 'em-pro'),
			"key" => __('Transaction Key', 'em-pro'),
			"signature" => __('Signature Key', 'em-pro'),
		);
	}
	
	public static function settings_tabs( $custom_tabs = array() ){
		$tabs = array();
		$tabs['options'] = array(
			'name' => esc_html__('Gateway Options', 'em-pro'),
			'callback' => array( static::class, 'settings_options'),
		);
		return parent::settings_tabs( $tabs );
	}
	
	/**
	 * Add extra info about silent posts to default notificaiton info
	 * @return void
	 */
	public static function settings_api_notifications( $test_mode = false ) {
		parent::settings_api_notifications( $test_mode );
		?>
		<p><?php _e('If you would like to receive notifications from Authorize.net and handle refunds or voided transactions automatically, you need to enable either Silent Posts or Webhooks. We recommend using Webhooks instead of Silent Posts.','em-pro'); ?></p>
		<p><?php echo sprintf(__('Your Silent Posts url is %s.','em-pro'),'<code>'.static::gateway()::get_payment_return_url().'</code>'); ?></p>
		<?php
	}
	
	public static function settings_options(){
		?>
		<h3><?php echo sprintf(esc_html__emp( '%s Options'),esc_html__emp('Gateway')); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e('Email Customer (on success)', 'em-pro') ?></th>
				<td>
					<select name="em_<?php echo static::$gateway ?>_email_customer">
					  	<?php $selected = get_option('em_'.static::$gateway.'_email_customer'); ?>
						<option value="1" <?php echo ($selected) ? 'selected="selected"':''; ?>><?php esc_html_e_emp('Yes','events-manager'); ?></option>
						<option value="0" <?php echo (!$selected) ? 'selected="selected"':''; ?>><?php esc_html_e_emp('No','events-manager'); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Customer Receipt Email Header', 'em-pro') ?></th>
				<td><input type="text" name="em_<?php echo static::$gateway ?>_header_email_receipt" value="<?php esc_attr_e(get_option( 'em_'. static::$gateway . "_header_email_receipt", __("Thanks for your payment!", "em-pro"))); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Customer Receipt Email Footer', 'em-pro') ?></th>
				<td><input type="text" name="em_<?php echo static::$gateway ?>_footer_email_receipt" value="<?php esc_attr_e(get_option( 'em_'. static::$gateway . "_footer_email_receipt", "" )); ?>" /></td>
			</tr>
		</table>
		<?php
	}

	/* 
	 * Run when saving settings, saves the settings available in EM_Gateway_Authorize_AIM::mysettings()
	 */
	public static function update( $options = array() ) {
	    $gateway_options = $options_wpkses = array();
		$gateway_options[] = 'em_'.static::$gateway . "_email_customer";
		$options_wpkses[] = 'em_'. static::$gateway . "_header_email_receipt";
		$options_wpkses[] = 'em_'. static::$gateway . "_footer_email_receipt";
		foreach( $options_wpkses as $option_wpkses ) add_filter('gateway_update_'.$option_wpkses,'wp_kses_post');
		$gateway_options = array_merge($gateway_options, $options_wpkses);
		//pass options to parent which handles saving
		return parent::update( $gateway_options );
	}
}
Gateway::init();
?>