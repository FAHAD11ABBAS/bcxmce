<?php
namespace EM\Payments;
/**
 * This class is a parent class which gateways should extend. There are various variables and functions that are automatically taken care of by
 * EM_Gateway, which will reduce redundant code and unecessary errors across all gateways. You can override any function you want on your gateway,
 * but it's advised you read through before doing so.
 *
 */
class Gateway_Admin {
	
	/**
	 * @var string
	 */
	public static $gateway = 'unknown';
	/**
	 * @var Gateway
	 */
	public static $gateway_class = false;
	/**
	 * @var array Associative array of key => label for storing API credentials, this should be assigned in init() so that labels are translated
	 */
	public static $api_cred_fields = array();
	public static $webhook_events = array();
	public static $webhook_admin_urls = array();
	public static $documentation_url_api = '';
	
	/**
	 * @deprecated Use Gateway::$api_option_name instead
	 * @var string
	 */
	public static $api_cred_name;
	
	public static function init(){
		// set the right Gateway static class reference if not set hard-coded (and if it even exists)
		static::$gateway = static::gateway()::$gateway;
		if ( empty( static::$api_cred_name ) ) {
			static::$api_cred_name = 'em_' . static::$gateway . '_api';
		}
		if ( static::gateway()::$api_option_name ) {
			static::$api_cred_name = static::gateway()::$api_option_name;
		}
	}
	
	public static function is_mode( $mode ){
		return static::gateway()::is_mode($mode);
	}
	
	public static function get_mode(){
		return static::gateway()::get_mode();
	}
	
	/**
	 * @return Gateway|string
	 */
	public static function gateway(){
		// set the right Gateway static class reference if not set hard-coded (and if it even exists)
		if( !static::$gateway_class ){
			$Gateway = str_replace('_Admin', '', static::class);
			if( class_exists($Gateway) ) {
				return $Gateway;
			}
			return '\EM\Payments\Gateway';
		}
		return static::$gateway_class;
	}
	
	/**
	 * Toggles gateway on/off.
	 * @return bool
	 */
	public static function toggle() {
		$active = get_option('em_payment_gateways');
		if ( array_key_exists(static::$gateway, $active) ) {
			unset($active[static::$gateway]);
			update_option('em_payment_gateways',$active);
			return true;
		} else {
			$active[static::$gateway] = true;
			update_option('em_payment_gateways',$active);
			return true;
		}
	}
	
	/**
	 * @deprecated
	 * @see Gateway_Admin::toggle()
	 * @return bool
	 */
	public static function toggleactivation(){
		return static::toggle();
	}
	
	public static function activate() {
		$active = get_option('em_payment_gateways', array());
		if ( array_key_exists(static::$gateway, $active) ) {
			return true;
		} else {
			$active[static::$gateway] = true;
			update_option('em_payment_gateways', $active);
			return true;
		}
	}
	
	public static function deactivate() {
		$active = get_option('em_payment_gateways');
		if ( array_key_exists(static::$gateway, $active) ) {
			unset($active[static::$gateway]);
			update_option('em_payment_gateways', $active);
			return true;
		} else {
			return true;
		}
	}
	
	public static function settings_tabs( $custom_tabs = array() ){
		$settings = array(
			'general' => array(
				'name' => esc_html__emp('General Options'),
				'callback' => array(static::class, 'settings_general'),
			),
		);
		if( !empty( static::$api_cred_fields ) ){
			$pre = '';
			if( static::gateway()::$supports_test_mode ) {
				$live_status = static::get_mode();
				$pre = '<span class="gateway-mode gateway-mode-live" data-mode="' . $live_status . '">[' . ucwords( esc_html__( 'Live', 'em-pro' ) ) . ']</span> ';
			}
			$settings['api'] = array(
				'name' => $pre . esc_html__('API Keys/Notifications', 'em-pro'),
				'callback' => array(static::class, 'settings_api'),
			);
			if( static::gateway()::$supports_test_mode ) {
				$pre = '<span class="gateway-mode gateway-mode-test" data-mode="'. $live_status .'">['. ucwords(esc_html__('Test', 'em-pro')) . ']</span> ';
				$settings['api-test'] = array(
					'name' => $pre . esc_html__('API Keys/Notifications', 'em-pro'),
					'callback' => array(static::class, 'settings_api_test'),
				);
			}
		}
		$settings = array_merge( $settings, $custom_tabs );
		return apply_filters('em_gateway_settings_tabs', $settings, static::gateway());
	}
	
	/**
	 * Generates a settings pages.
	 * @uses EM_Gateway::mysettings()
	 */
	public static function settings() {
		if( static::gateway()::$legacy ){
			$link = '<a href="https://eventsmanagerpro.com/downloads/">'. esc_html__('Download new payment methods for this gateway.').'</a>';
			echo '<div class="notice notice-warning"><p>'. sprintf(esc_html__('This is a legacy payment method, and has been discontinued by the payment provider itself, whilst it may work for the time being, we cannot guarantee when the payment gatewway provider will completely stop supporting it.', 'em-pro')) . ' ' . $link . '</p></div>';
		}
		$enabling_test = sprintf( esc_html__( '%s Test Mode', 'events-manager' ), esc_html__( 'Enabling', 'em-pro' ) ). '...';
		$disabling_test = sprintf( esc_html__( '%s Test Mode', 'events-manager' ), esc_html__( 'Disabling', 'em-pro' ) ) . '...';
		
		// tab links
		$api_test_tab = '<code><a href="#api-test" data-tab="api-test">[' . ucwords(esc_html__('Test', 'em-pro')) . '] ' . esc_html__('API Keys/Notifications', 'em-pro') . '</a></code>';
		$api_tab = '<code><a href="#api" data-tab="api">[' . ucwords( esc_html__( 'Live', 'em-pro' ) ) . '] ' . esc_html__( 'API Keys/Notifications', 'em-pro' ) . '</a></code>';
		?>
	    <script type="text/javascript" charset="utf-8"><?php include(EM_DIR.'/includes/js/admin-settings.js'); ?></script>
		<div class='wrap nosubsub tabs-active'>
			<h1 class="wp-heading-inline"><?php echo sprintf(__('Edit &quot;%s&quot; settings','em-pro'), esc_html(static::gateway()::$title) ); ?></h1>
			<hr class="wp-header-end">
			<?php if( static::gateway()::$supports_test_mode ) : ?>
			<div class="notice notice-warning gateway-status-info gateway-status-info-test <?php if( !static::is_mode('test') ) echo "hidden"; ?>">
				<div class="gateway-status-content">
					<div>
						<p>
							<?php
								echo sprintf( esc_html__('Test Mode Enabled. Gateway credentials are obtained from the %s tab.', 'events-manager'), $api_test_tab );
							?>
						</p>
					</div>
					<a href="#" class="page-title-action gateway-status-togggle" data-nonce="<?php echo wp_create_nonce('em_gateway_toggle_'.static::$gateway); ?>" data-gateway="<?php echo esc_attr(static::$gateway); ?>" data-loading="<?php echo $disabling_test; ?>">
						<?php echo sprintf( esc_html__('%s Live Mode', 'events-manager'), esc_html__('Enable', 'events-manager') ); ?>
					</a>
				</div>
			</div>
			<div class="notice notice-info gateway-status-info gateway-status-info-limited <?php if( !static::is_mode('limited') ) echo "hidden"; ?>">
				<div class="gateway-status-content">
					<div>
						<p>
							<?php
								echo sprintf( esc_html__('%s is in Live Mode for regular visitors and will make real payments using credentials from the %s tab. Limited Test Mode is active for some visitors or events as per the settings in the %s tab.', 'events-manager'), '<em>' . static::gateway()::$title . '</em>', $api_tab, $api_test_tab );
							?>
						</p>
					</div>
					<a href="#" class="page-title-action gateway-status-togggle" data-nonce="<?php echo wp_create_nonce('em_gateway_toggle_'.static::$gateway); ?>" data-gateway="<?php echo esc_attr(static::$gateway); ?>" data-loading="<?php echo $disabling_test; ?>">
						<?php echo sprintf( esc_html__('%s Live Mode', 'events-manager'), esc_html__('Enable', 'events-manager') ); ?>
					</a>
				</div>
			</div>
			<div class="notice notice-success gateway-status-info gateway-status-info-live <?php if( !static::is_mode('live') ) echo "hidden"; ?>">
				<div class="gateway-status-content">
					<p>
						<?php
							echo sprintf( esc_html__( 'Gateway is in Live Mode, real payments are accepted using the credentials from the %s tab.', 'events-manager' ), $api_tab );
						?>
					</p>
					<a href="#" class="page-title-action gateway-status-togggle" data-nonce="<?php echo wp_create_nonce('em_gateway_toggle_'.static::$gateway); ?>" data-gateway="<?php echo esc_attr(static::$gateway); ?>" data-loading="<?php echo $enabling_test; ?>">
						<?php echo sprintf( esc_html__('%s Test Mode', 'events-manager'), esc_html__('Enable', 'events-manager') ); ?>
					</a>
				</div>
			</div>
			<?php endif; ?>
			<h2 class="nav-tab-wrapper">
				<?php
				$settings_tabs = static::settings_tabs();
				foreach( $settings_tabs as $tab_key => $tab ){
					$tab_name = is_array($tab) ? $tab['name'] : $tab;
					$tab_link = !empty($tabs_enabled) ? esc_url(add_query_arg( array('em_tab'=>$tab_key))) : '';
					$active_class = !empty($tabs_enabled) && !empty($_GET['em_tab']) && $_GET['em_tab'] == $tab_key ? 'nav-tab-active':'';
					echo "<a href='$tab_link#$tab_key' id='em-menu-$tab_key' class='nav-tab $active_class'>{$tab_name}</a>";
				}
				?>
			</h2>
			<form action='' method='post' name='gatewaysettingsform' class="em-gateway-settings">
				<input type='hidden' name='action' id='action' value='updated' />
				<input type='hidden' name='gateway' id='gateway' value='<?php echo static::$gateway; ?>' />
				<?php wp_nonce_field('updated-' . static::$gateway); ?>
				<?php
				foreach( $settings_tabs as $tab_key => $tab ){
					$display = $tab_key == 'general' ? '':'display:none;';
					?>
					<div class="em-menu-<?php echo esc_attr($tab_key) ?> em-menu-group" style="<?php echo $display; ?>">
						<?php
						if( !empty($tab['callback']) ) {
							call_user_func( $tab['callback'], static::gateway() );
						}
						do_action('em_gateway_settings_tab_'. $tab_key, static::gateway());
						?>
						<p class="submit">
							<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
						</p>
					</div>
					<?php
				}
				?>
			</form>
		</div> <!-- wrap -->
		<?php
	}
	
	public static function settings_general(){
		$gateway_link = '<a href="'.admin_url('edit.php?post_type='.EM_POST_TYPE_EVENT.'&page=events-manager-options#bookings+gateway-options').'">'. strtolower(esc_html__('Settings', 'em-pro')) . '</a>';
		static::settings_general_header();
		?>
		<h3><?php echo sprintf(esc_html__emp( '%s Options', 'events-manager'),esc_html__emp('Booking Form','events-manager')); ?></h3>
		<table class="form-table">
			<tbody>
			<?php
			//Gateway Title
			$desc = sprintf(__('Only if you have not enabled quick pay buttons in your %s page.', 'em-pro'), $gateway_link);
			$desc .= ' ' . __('The user will see this as the text option when choosing a payment method.','em-pro');
			em_options_input_text(__('Gateway Title', 'em-pro'), 'em_' . static::$gateway.'_option_name', $desc);
			
			//Gateway booking form info
			$desc = sprintf(__('Only if you have not enabled quick pay buttons in your %s page.','em-pro'), $gateway_link);
			$desc .= ' ' . __('If a user chooses to pay with this gateway, or it is selected by default, this message will be shown just below the selection.', 'em-pro');
			em_options_textarea(__('Booking Form Information', 'em-pro'), 'em_' . static::$gateway.'_form', $desc);
			
			if(static::gateway()::$button_enabled) {
				$desc = sprintf( __( 'If you have chosen to only use quick pay buttons in your %s page, this button text will be used.', 'em-pro' ), $gateway_link );
				$desc .= ' ' . sprintf( __( 'Choose the button text. To use an image instead, enter the full url starting with %s or %s.', 'em-pro' ), '<code>http://...</code>', '<code>https://...</code>' );
				em_options_input_text( __( 'QuickPay Payment Button', 'em-pro' ), 'em_' . static::$gateway . '_button', $desc );
			}
			?>
			</tbody>
		</table>
		<?php static::settings_general_feedback(); ?>
		<?php static::settings_general_cancellation(); ?>
		<?php static::settings_general_footer(); ?>
		<?php
		do_action( 'em_gateway_settings_footer', static::gateway() );
	}
	
	public static function settings_general_footer(){}
	public static function settings_general_header(){}
	
	/**
	 * Called by $this->settings(), override this to output your own gateway options on this gateway settings page
	 */
	public static function settings_general_feedback(){
		?>
		<h3><?php esc_html_e('Booking Actions - Payment Complete', 'em-pro'); ?></h3>
		<table class="form-table">
			<tbody>
			<?php
				if( !empty(static::gateway()::$payment_flow['redirect']) ) {
					$feedback_message = sprintf(esc_html__('The message that is shown to a user when a booking is successful whilst being redirected to %s for payment.','em-pro'), 'Paypal');
					em_options_input_text( esc_html__('Success Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback', $feedback_message );
				}elseif( empty(static::gateway()::$payment_flow['redirect-success']) ) {
					$feedback_message = esc_html__('The message that is shown to a user when a payment is successful and booking is complete.','em-pro');
					em_options_input_text( esc_html__('Success Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback', $feedback_message );
				}
				if( !empty(static::gateway()::$payment_flow['redirect-success']) ) {
					if( static::gateway()::$payment_flow['redirect-success'] === 'optional' ) {
						$feedback_message = esc_html__('The message that is shown to a user when a payment is successful and booking is complete.','em-pro');
						em_options_input_text( esc_html__('Success Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback', $feedback_message );
					}
					em_options_input_text( esc_html__('Return URL', 'em-pro'), 'em_'. static::$gateway . '_return', esc_html__('Once a payment is completed, users are redirected back to your site. If blank, user is not redirected and just shown the success message.', 'em-pro') );
					$extra_feedback = '';
					if( static::gateway()::$payment_flow['redirect-success'] === 'optional' ) {
						add_filter('pre_option_em_'. static::$gateway.'_return', '__return_empty_string');
						$return_url = add_query_arg('payment_complete', null, static::gateway()::get_return_url());
						$extra_feedback = '<br><em>'. sprintf( esc_html__('Your default thank you page is %s.','em-pro'), '<code>'. $return_url .'</code>' ) .'</em>';
						remove_filter('pre_option_em_'. static::$gateway.'_return', '__return_empty_string');
					}
					em_options_input_text( esc_html__('Thank You Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback_completed', sprintf(esc_html__('If you choose to return users to the default Events Manager thank you page after a user has paid via %s, you can customize the thank you message here.','em-pro'), static::gateway()::$title) . $extra_feedback );
				}
			?>
			<?php if ( static::gateway()::$count_pending_spaces ) : ?>
				<tr valign="top">
					<th scope="row"><?php esc_html_e_emp('Reserved unconfirmed spaces?') ?></th>
					<td>
						<?php $v = get_option('em_' . static::$gateway . '_reserve_pending'); ?>
						<select name="em_<?php echo static::$gateway; ?>_reserve_pending">
							<option value="1" <?php if( $v ) echo 'selected="selected"'; ?>><?php esc_html_e_emp('Yes'); ?></option>
							<option value="0" <?php if( !$v ) echo 'selected="selected"'; ?>><?php esc_html_e_emp('No'); ?></option>
						</select>
						<br>
						<em><?php echo esc_html__('If set to "Yes", spaces will be reserved once a user submits a booking and proceeds to payment, whilst payment is still pending.' ,'em-pro'); ?></em>
			
						<?php if( static::gateway()::$has_timeout ): ?>
						<br>
						<em><?php echo sprintf(esc_html__('We recommend setting this to "Yes" and automatically expiring upaid bookings after at least %s minutes in the %s setting below','em-pro'), '<strong>15</strong>', '<strong>'.esc_html__('Unpaid Bookings Expiry', 'em-pro').'</strong>'); ?></em>
						<?php endif; ?>
					</td>
				</tr>
			<?php endif; ?>
			<?php if( static::gateway()::$has_timeout ): ?>
				<tr valign="top">
					<th scope="row"><?php _e('Unpaid Bookings Expiry', 'em-pro') ?></th>
					<td>
						<input type="text" name="em_<?php echo static::$gateway; ?>_booking_timeout" style="width:50px;" value="<?php esc_attr_e(get_option('em_'. static::$gateway . "_booking_timeout" )); ?>" style='width: 40em;' /> <?php _e('minutes','em-pro'); ?><br>
						<em><?php esc_html_e('Once a booking is initially submitted, Events Manager stores a booking record in the database to identify the incoming payment. If you would like these bookings to expire after x minutes without payment confirmation, please enter a value above.','em-pro'); ?></em>
						<br>
						<em><?php esc_html_e('If a booking remains unpaid after this time, the booking will expire, and the payment method cancelled.','em-pro'); ?></em>
						<br>
						<em>
							<?php
							$gateway_link = '<a href="'.admin_url('edit.php?post_type='.EM_POST_TYPE_EVENT.'&page=events-manager-options#bookings+gateway-options').'">'. strtolower(esc_html__('Settings', 'em-pro')) . '</a>';
							echo sprintf( esc_html__('If set to 0, this booking will take its options from the %s page.','em-pro'), $gateway_link );
							?>
						</em>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Expired Booking Action', 'em-pro') ?></th>
					<td>
						<?php $v = get_option('em_' . static::$gateway . '_booking_timeout_action', 'delete'); ?>
						<select name="em_<?php echo static::$gateway; ?>_booking_timeout_action">
							<option value="none" <?php if( $v === 'none' ) echo 'selected="selected"'; ?>><?php esc_html_e_emp('No Action'); ?></option>
							<option value="delete" <?php if( $v === 'delete' ) echo 'selected="selected"'; ?>><?php esc_html_e_emp('Delete'); ?></option>
							<option value="cancel" <?php if( $v === 'cancel' ) echo 'selected="selected"'; ?>><?php esc_html_e_emp('Cancel'); ?></option>
						</select><br>
						<em><?php esc_html_e('Once a booking has expired, decide whether to cancel or delete it.','em-pro'); ?></em>
					</td>
				</tr>
			<?php endif; ?>
			<?php if( static::gateway()::$can_manually_approve ): ?>
			<tr valign="top">
				<th scope="row"><?php _e('Manually approve completed transactions?', 'em-pro') ?></th>
				<td>
					<input type="checkbox" name="em_<?php echo static::$gateway; ?>_manual_approval" value="1" <?php echo (get_option('em_'. static::$gateway . "_manual_approval" )) ? 'checked="checked"':''; ?> /><br>
					<em><?php _e('By default, when someone pays for a booking, it gets automatically approved once the payment is confirmed. If you would like to manually verify and approve bookings, tick this box.','em-pro'); ?></em><br>
					<em><?php echo sprintf(__('Approvals must also be required for all bookings in your <a href="%s">settings</a>.','em-pro'),EM_ADMIN_URL.'&amp;page=events-manager-options'); ?></em>
				</td>
			</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
	
	public static function settings_general_cancellation(){
		if( !empty(static::gateway()::$payment_flow['redirect-cancel']) ) {
			?>
			<h3><?php esc_html_e('Payment Cancellation', 'em-pro'); ?></h3>
			<table class="form-table">
				<p>
					<?php esc_html_e('If a user cancels before the payment process completes, such as by clicking the cancel/back button on the payment page (not the back button on a browser), they will be redirected to a specific page of your choosing and display a customized message.', 'em-pro'); ?>
				</p>
				<p>
					<?php esc_html_e('Cancelling a payment would result in their temporary booking also being cancelled and deleted.','em-pro'); ?>
				</p>
				<tbody>
				<?php
				em_options_input_text( esc_html__('Cancel URL', 'em-pro'), 'em_'. static::$gateway . '_cancel_return', esc_html__('If left blank the user is redirected to the event or checkout page.', 'em-pro') );
				em_options_input_text( esc_html__('Payment Cancelled Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback_cancelled' );
				?>
				</tbody>
			</table>
			<?php
		} elseif ( !empty(static::gateway()::$payment_flow['direct-cancel']) ) {
			?>
			<h3><?php esc_html_e('Payment Cancellation', 'em-pro'); ?></h3>
			<table class="form-table">
				<p>
					<?php esc_html_e('If a user cancels before the payment process completes, the following message will be displayed to the user and the booking form would be displayed again.', 'em-pro'); ?>
				</p>
				<p>
					<?php esc_html_e('Cancelling a payment would result in their temporary booking also being cancelled and deleted.','em-pro'); ?>
				</p>
				<tbody>
				<?php
					em_options_input_text( esc_html__('Payment Cancelled Message', 'em-pro'), 'em_'. static::$gateway . '_booking_feedback_cancelled' );
				?>
				</tbody>
			</table>
			<?php
		}
	}
	
	/*
	 * PayPal
	 * PayPal Checkout/Advanced
	 *  - warn users of new integration methods
	 * Authorize AIM
	 * Authorize API
	 * Stripe El / Checkout
	 */
	
	public static function settings_api(){
		static::settings_api_notices();
		static::settings_api_credentials();
		static::settings_api_notifications();
	}
	
	public static function settings_api_test(){
		static::settings_api_notices( true );
		static::settings_api_test_limiting();
		static::settings_api_test_hiding();
		static::settings_api_credentials( true );
		static::settings_api_notifications( true );
	}
	
	public static function settings_api_test_limiting() {
		$api_live_tab = '<code><a href="#api" data-tab="api">[' . ucwords(esc_html__('Live', 'em-pro')) . '] ' . esc_html__('API Keys/Notifications', 'em-pro') . '</a></code>';
		?>
		<p style="margin: 20px 0 10px;"><?php echo sprintf( esc_html__('When Test Mode is enabled and active, the following API keys will replace those used in the %s tab and assume you are using the gateway test/sandbox features. Booking forms will display a prominent notice when this gateway is selected for payment in test mode','em-pro'), $api_live_tab); ?></p>
		<h3><?php esc_html_e('Limited Test Mode Settings','em-pro'); ?></h3>
		<p><?php esc_html_e('You can limit Test Mode to certain scenarios so that you can test on a live site whilst still accepting payments using live credentials for customers.','em-pro'); ?></p>
		<table class="form-table">
			<?php
				$mb_mode_msg = esc_html__('Note that this limitation will not apply in Multiple Bookigns Mode.', 'em-pro');
				em_options_radio_binary( esc_html__('Enable Limited Test Mode?', 'em-pro'), 'em_'. static::$gateway . '_test_limited', '', '', '.gateway-limited-test-mode-options' );
			?>
			<tbody class="gateway-limited-test-mode-options">
			<?php
				static::settings_api_test_limiting_notices();
				em_options_input_text( esc_html__('Limit to IPs', 'em-pro'), 'em_'. static::$gateway . '_test_ips', sprintf(esc_html__('Limit test mode to one or more %s, separated by comma. Leave blank for no limitations.', 'em-pro'), esc_html__('IP addresses', 'em-pro')) );
				em_options_input_text( esc_html__('Limit to Users', 'em-pro'), 'em_'. static::$gateway . "_test_users", sprintf(esc_html__('Limit test mode to one or more %s, separated by comma. Leave blank for no limitations.', 'em-pro'), esc_html__('User IDs', 'em-pro')) );
				em_options_input_text( esc_html__('Limit to Events', 'em-pro'), 'em_'. static::$gateway . "_test_events", sprintf(esc_html__('Limit test mode to one or more %s, separated by comma. Leave blank for no limitations.', 'em-pro') . ' ' . $mb_mode_msg, esc_html__emp('Event IDs')) );
			?>
			</tbody>
		</table>
		<?php
	}
	
	public static function settings_api_test_hiding() {
		?>
		<h3><?php esc_html_e('Hide Test Mode Gateway','em-pro'); ?></h3>
		<p><?php echo sprintf( esc_html__('If %s is in Test Mode, you can choose to hide it from visitors and only display it to users that match any of the following settings.','em-pro'), '<em>'. static::gateway()::$title . '</em>' ); ?></p>
		<table class="form-table">
			<?php
				em_options_input_text( esc_html__('Display only to IPs', 'em-pro'), 'em_'. static::$gateway . '_test_hide_ips', sprintf(esc_html__('Display gateway in test mode only to one or more %s, separated by comma. Leave blank for no limitations.', 'em-pro'), esc_html__('IP addresses', 'em-pro')) );
				em_options_input_text( esc_html__('Display only to Users', 'em-pro'), 'em_'. static::$gateway . '_test_hide_users', sprintf(esc_html__('Limit test mode to one or more %s, separated by comma. Leave blank for no limitations.', 'em-pro'), esc_html__('User IDs', 'em-pro')) );
			?>
		</table>
		<?php
	}
	
	public static function settings_api_test_limiting_notices(){
		?>
		<tr class="gateway-limited-test-mode-notice">
			<td colspan="2" class="em-boxheader">
				<div class="gateway-notice gateway-notice-info has-icon">
					<em class="em-icon em-icon-info"></em>
					<p><?php esc_html_e('Test Mode will only be active if any of the following conditions below are matched, otherwise Live Mode credentials will be used. For example, if limiting by IPs and Uer IDs, test mode is only enabled when the site visitor is using a matched IP', 'em-pro'); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}
	
	public static function settings_api_notices( $test_mode = false ) {
		
		$enabling_test = sprintf( esc_html__( '%s Test Mode', 'events-manager' ), esc_html__( 'Enabling', 'em-pro' ) ). '...';
		$api_test_tab = '<code><a href="#api-test" data-tab="api-test">[' . ucwords(esc_html__('Test', 'em-pro')) . '] ' . esc_html__('API Keys/Notifications', 'em-pro') . '</a></code>';
		$api_tab = '<code><a href="#api" data-tab="api">[' . ucwords(esc_html__('Live', 'em-pro')) . '] ' . esc_html__('API Keys/Notifications', 'em-pro') . '</a></code>';
		$title = '<em>' . static::gateway()::$title . '</em>';
		
		if( $test_mode ) {
			?>
			<div class="gateway-notice has-icon gateway-status-info gateway-status-info-live <?php if( !static::is_mode('live') ) echo 'hidden'; ?>">
				<span class="em-icon em-icon-warning"></span>
				<div class="gateway-status-content">
					<p>
						<?php
							echo sprintf( esc_html__('%s is using live credentials from the %s tab, enable Test Mode for these credentials to be used instead.', 'em-pro'), $title, $api_tab );
						?>
					</p>
					<a href="#" class="page-title-action gateway-status-togggle" data-nonce="<?php echo wp_create_nonce('em_gateway_toggle_'.static::$gateway); ?>" data-gateway="<?php echo esc_attr(static::$gateway); ?>" data-loading="<?php echo $enabling_test; ?>">
						<?php echo sprintf( esc_html__('%s Test Mode', 'events-manager'), esc_html__('Enable', 'events-manager') ); ?>
					</a>
				</div>
			</div>
			<div class="gateway-notice gateway-notice-info has-icon gateway-status-info gateway-status-info-limited <?php if( !static::is_mode('limited') ) echo 'hidden'; ?>">
				<span class="em-icon em-icon-warning"></span>
				<p>
					<?php
						echo sprintf( esc_html__('%s is in Limited Test Mode, if the Limited Test Mode conditions below are matched, the following credentials will be used, otherwise Live Mode credentials are used from the %s tab.', 'em-pro'), $title, $api_tab );
					?>
				</p>
			</div>
			<div class="gateway-notice gateway-notice-confirm has-icon gateway-status-info gateway-status-info-test <?php if( !static::is_mode('test') ) echo 'hidden'; ?>">
				<span class="em-icon em-icon-checkmark-circle"></span>
				<p>
					<?php
						echo sprintf( esc_html__('%s is in Test Mode, the following credentials are being used for all users.', 'em-pro'), $title );
					?>
				</p>
			</div>
			<?php
		} else {
			if( static::gateway()::$supports_test_mode ){
				?>
				<div class="gateway-notice gateway-notice-confirm has-icon gateway-status-info gateway-status-info-live <?php if( !static::is_mode('live') ) echo 'hidden'; ?>">
					<span class="em-icon em-icon-checkmark-circle"></span>
					<div>
						<p>
							<?php
								echo sprintf( esc_html__('%s is in Live Mode, the following credentials are being used for all users.', 'events-manager'), $title );
							?>
						</p>
					</div>
				</div>
				<div class="gateway-notice has-icon gateway-status-info gateway-status-info-limited <?php if( !static::is_mode('limited') ) echo 'hidden'; ?>">
					<span class="em-icon em-icon-info"></span>
					<div>
						<p>
							<?php
								echo sprintf( esc_html__('%s is in Live Mode for regular visitors, with Limited Test Mode activated for some visitors or events as per the settings in the %s tab.', 'events-manager'), $title, $api_tab );
							?>
						</p>
					</div>
				</div>
				<div class="gateway-notice has-icon gateway-status-info gateway-status-info-test <?php if( !static::is_mode('test') ) echo 'hidden'; ?>">
					<span class="em-icon em-icon-warning"></span>
					<p>
						<?php
							echo sprintf( esc_html__('%s is in Test Mode and is using credentials from the %s tab above.', 'em-pro'), $title, $api_test_tab );
						?>
					</p>
				</div>
				<?php
			}
		}
	}
	
	public static function settings_api_credentials( $test_mode = false ){
		?>
		<h3><?php echo sprintf(__('%s Credentials','em-pro'), static::gateway()::$title ); ?></h3>
		<?php if( static::$documentation_url_api ): ?>
		<p><?php echo sprintf(__('Please visit the <a href="%s">documentation</a> for further instructions.','em-pro'), static::$documentation_url_api); ?></p>
		<?php endif; ?>
		<?php
		if( static::gateway()::$requires_ssl ) {
			$ajax_url = str_replace( 'http://', 'https://', admin_url( 'admin-ajax.php' ) );
			$verify   = @wp_remote_get( $ajax_url );
			if ( is_wp_error( $verify ) ) {
				/* @public static $verify WP_Error */
				foreach ( $verify->get_error_messages() as $error ) {
					if ( preg_match( '/SSL/', $error ) ) {
						echo '<div class="em-gateway-ssl-warning" style="color:red">';
						echo sprintf( esc_html__( 'A valid SSL certificate is required for live payments using this gateway. We are not able to connect to this URL: %s.', 'em-pro' ), '<a href="' . $ajax_url . '"><code>' . $ajax_url . '</code></a>' );
						echo '</div>';
					}
				}
			}
		}
		?>
		<table class="form-table">
			<tbody>
			<?php
			if ( !static::gateway()::$supports_test_mode ) {
				$status_modes = array('live' => __('Live Site', 'em-pro'), 'sandbox' => __('Test Mode (Sandbox)', 'em-pro') );
				em_options_select(esc_html__('Gateway Mode', 'em-pro'), 'em_'. static::$gateway . "_mode", $status_modes);
				$is_sandbox = get_option('em_'.static::$gateway.'_mode') == 'sandbox';
			}
			static::settings_sensitive_credentials( static::$api_cred_fields, static::is_mode('test') || $test_mode , $test_mode );
			?>
			</tbody>
		</table>
		<?php
	}
	
	public static function settings_sensitive_credentials( $api_cred_fields, $is_sandbox, $test_mode = false ){
		if( !is_ssl() && !$test_mode ){
			?>
			<tr>
				<td colspan="2">
					<?php
					echo '<p style="color:red;">';
					echo sprintf( esc_html__('Your site is not using SSL! Whilst not a requirement, if you\'re going to submit API information for a live %s account, we recommend you do so over a secure connection. If this is not possible, consider an alternative option of submitting your API information as covered in our %s.', 'em-pro'),
						static::gateway()::$title, '<a href="http://wp-events-plugin.com/documentation/events-with-paypal/safe-encryption-api-keys/">'.esc_html__('documentation','events-manager').'</a>');
					echo '</p>';
					if( !em_constant('EMP_GATEWAY_SSL_OVERRIDE') && ($is_sandbox && empty($_REQUEST['show_keys'])) ){
						echo '<p>'.esc_html__('If you are only using testing credentials, you can display and save them safely.', 'em-pro');
						echo ' <a href="'. esc_url(add_query_arg('show_keys', wp_create_nonce('show_'. static::$gateway . '_creds'))) .'" class="button-secondary">'. esc_html__('Show API Keys', 'em-pro') .'</a>';
						echo '</p>';
					}
					?>
				</td>
			</tr>
			<?php
		}
		$d = $test_mode ? '_test':'';
		$api_options = get_option(static::$api_cred_name . $d);
		if ( static::settings_show_settings_credentials( $is_sandbox ) ) {
			foreach ( $api_cred_fields as $api_cred_opt => $api_cred_label ) {
				$api_cred_value = !empty($api_options[$api_cred_opt]) && $api_options[$api_cred_opt] !== $api_cred_label ? $api_options[$api_cred_opt] : '';
				?>
				<tr valign="top" id='<?php echo static::$api_cred_name . $d . '_' . esc_attr($api_cred_opt); ?>_row'>
					<th scope="row"><?php echo esc_html($api_cred_label); ?></th>
					<td>
						<input value="<?php echo esc_attr($api_cred_value); ?>" name="<?php echo static::$api_cred_name . $d . '_'. esc_attr($api_cred_opt) ?>" type="text" id="<?php echo static::$api_cred_name . $d . esc_attr($api_cred_opt) ?>" style="width: 95%" size="45" />
					</td>
				</tr>
				<?php
			}
		} else {
			foreach ( $api_cred_fields as $api_cred_opt => $api_cred_label ) {
				$api_cred_value = !empty($api_options[$api_cred_opt]) && $api_options[$api_cred_opt] !== $api_cred_label ? $api_options[$api_cred_opt] : '';
				?>
				<tr valign="top">
					<th scope="row"><?php echo esc_html($api_cred_label); ?></th>
					<td>
						<?php
						$chars = '';
						for( $i = 0; $i < strlen($api_cred_value); $i++ ) $chars = $chars . '*';
						echo esc_html(str_replace( substr($api_cred_value, 1, -1), $chars, $api_cred_value) );
						?>
					</td>
				</tr>
				<?php
			}
		}
	}
	
	public static function settings_api_notifications( $test_mode = false ){
		$force_mode = $test_mode ? static::gateway()::force_mode('test') : static::gateway()::force_mode('live');
		$verify_webhook = static::verify_webhook();
		?>
		<h3><?php esc_html_e('Payment Notifications', 'em-pro'); ?></h3>
		<p>
			<em>
				<?php
				if( $verify_webhook === true ){
					echo '<span style="color:green;">';
					esc_html_e('We have verified your credentials, and a valid Webhook was set up correctly, please do not delete this webhook so that we can detect updates to a payment, such as refunds and disputes.', 'em-pro');
					echo '</span>';
				}elseif( $verify_webhook === false ){
					echo '<span style="color:red;">';
					esc_html_e('You do not currently have a valid Webhook assigned. This is needed for detecting updates to a payment, such as refunds and disputes. Please re-save your settings and a webhook should be automatically created for you.', 'em-pro');
					echo '</span>';
				}
				?>
			</em>
		</p>
		<p><?php echo esc_html__('If you would like to receive notifications from %s and handle events such as voided transactions, refunds and chargebacks automatically, you need to create a Webhook.','em-pro'); ?></p>
		<p><?php echo sprintf(__('Your Webhooks Endpoint url is %s.','em-pro'),'<code>'.static::gateway()::get_api_notify_url() . '</code>'); ?></p>
		<?php if( !empty(static::$webhook_events) ): ?>
		<p><?php echo sprintf(__('Supported webhook events: %s.','em-pro'),'<code>' . implode('</code><code>', static::$webhook_events) . '</code>'); ?></p>
		<?php endif; ?>
		<?php
		if( !empty( static::$webhook_admin_urls) ) {
			if ( count( static::$webhook_admin_urls ) === 1 ) {
				$webhooks_url = '<a href="' . static::$webhook_admin_urls[0] . '">' . esc_html__( 'here', 'em-pro' ) . '</a>';
				?>
				<p><?php echo sprintf(__('You can create your webhook %1$s.','em-pro'), $webhooks_url); ?></p>
				<?php
			} else {
				$sandbox_webhooks_url = '<a href="' . static::$webhook_admin_urls[0] . '">' . esc_html__('sandbox', 'em-pro') . '</a>';
				$production_webhooks_url = '<a href="' . static::$webhook_admin_urls[1] . '">' . esc_html__('production', 'em-pro') . '</a>';
				?>
				<p><?php echo sprintf(__('You can create your webhook in %1$s or %2$s environments.','em-pro'), $sandbox_webhooks_url, $production_webhooks_url); ?></p>
				<?php
			}
		}
		static::gateway()::$force_mode = $force_mode;
	}
	
	/**
	 * Run by EM_Gateways_Admin::handle_gateways_panel_updates() if this gateway has been updated. You should capture the values of your new fields above and save them as options here.
	 * @param $options array of option names that get updated when this gateway settings page is saved
	 * return boolean
	 * @todo add $options as a parameter to method, and update all extending classes to prevent strict errors
	 */
	public static function update( $options = array() ) {
		//default action is to return true
		if ( static::gateway()::$button_enabled ) {
			$options_wpkses[] = 'em_' . static::$gateway . '_button';
			add_filter( 'update_em_' . static::$gateway . '_button', 'wp_kses_post' );
		}
		if ( ! empty( static::gateway()::$payment_flow['redirect'] ) || empty( static::gateway()::$payment_flow['redirect-success'] ) || static::gateway()::$payment_flow['redirect-success'] === 'optional' ) {
			$options_wpkses[] = 'em_' . static::$gateway . '_booking_feedback';
		}
		if ( ! empty( static::gateway()::$payment_flow['redirect-success'] ) ) {
			$default_options[] = 'em_' . static::$gateway . '_return';
			$options_wpkses[] = 'em_' . static::$gateway . '_booking_feedback_completed';
		}
		if ( ! empty( static::gateway()::$payment_flow['redirect-cancel'] ) ) {
			$default_options[] = 'em_' . static::$gateway . '_cancel_return';
			$options_wpkses[] = 'em_' . static::$gateway . '_booking_feedback_cancelled';
		} elseif ( ! empty( static::gateway()::$payment_flow['direct-cancel'] ) ) {
			$options_wpkses[] = 'em_' . static::$gateway . '_booking_feedback_cancelled';
		}
		if ( ! empty( static::$api_cred_fields ) ) {
			if( static::settings_show_settings_credentials( get_option( 'em_' . static::$gateway . '_mode' ) == 'sandbox' ) ) {
				$default_options[ static::$api_cred_name ] = array_keys(static::$api_cred_fields);
				$default_options[ static::$api_cred_name . '_test' ] = array_keys(static::$api_cred_fields);
			}
			if ( static::gateway()::$supports_test_mode ) {
				$default_options[] = 'em_' . static::$gateway . '_test_limited';
				$default_options[] = 'em_' . static::$gateway . '_test_ips';
				$default_options[] = 'em_' . static::$gateway . '_test_events';
				$default_options[] = 'em_' . static::$gateway . '_test_users';
				$default_options[] = 'em_' . static::$gateway . '_test_hide_users';
				$default_options[] = 'em_' . static::$gateway . '_test_hide_ips';
			} else {
				$default_options[] = 'em_' . static::$gateway . '_mode'; // mode isn't relevant anymore, handled by gateways button
			}
		}
		if ( static::gateway()::$count_pending_spaces ) {
			$default_options[] = 'em_' . static::$gateway . '_reserve_pending';
		}
		if( static::gateway()::$has_timeout ) {
			$default_options[] = 'em_' . static::$gateway . '_booking_timeout';
			$default_options[] = 'em_' . static::$gateway . '_booking_timeout_action';
		}
		if( static::gateway()::$can_manually_approve ) {
			$default_options[] = 'em_' . static::$gateway . '_manual_approval';
		}
		// general options
		$options_wpkses[] = 'em_' . static::$gateway . '_option_name';
		$options_wpkses[] = 'em_' . static::$gateway . '_form';
		
		//add filters for all $option_wpkses values so they go through wp_kses_post
		foreach( $options_wpkses as $option_wpkses ) add_filter('gateway_update_'.$option_wpkses,'wp_kses_post');
		$options = array_merge($default_options, $options_wpkses, $options);
		
		//go through the options, grab them from $_REQUEST, run them through a filter for sanitization and save
		foreach( $options as $option_index => $option_name ){
			if( is_array( $option_name ) ){
				$option_values = array();
				foreach( $option_name as $option_key ){
					$option_value_raw = !empty($_REQUEST[$option_index.'_'.$option_key]) ? stripslashes($_REQUEST[$option_index.'_'.$option_key]) : '';
					$option_values[$option_key] = apply_filters('gateway_update_'.$option_index.'_'.$option_key, $option_value_raw);
				}
				update_option($option_index, $option_values);
			}else{
				$option_value_raw = !empty($_REQUEST[$option_name]) ? stripslashes($_REQUEST[$option_name]) : '';
				$option_value = apply_filters('gateway_update_'.$option_name, $option_value_raw);
				update_option($option_name, $option_value);
			}
		}
		//multilingual, same as above
		if( \EM_ML::$is_ml ) {
			foreach ( $options as $option_name ) {
				if ( ! empty( $_REQUEST[ $option_name . '_ml' ] ) && is_array( $_REQUEST[ $option_name . '_ml' ] ) ) {
					$option_ml_value = array();
					foreach ( $_REQUEST[ $option_name . '_ml' ] as $lang => $option_value_raw ) {
						if ( ! empty( $option_value_raw ) ) {
							$option_ml_value[ $lang ] = apply_filters( 'gateway_update_' . $option_name, stripslashes( $option_value_raw ) );
						}
					}
					update_option( $option_name . '_ml', $option_ml_value );
				}
			}
		}
		
		do_action('em_updated_gateway_options', $options, static::gateway());
		do_action('em_gateway_update', static::gateway());
		return true;
	}
	
	/**
	 * Override and return true or false if gateway supports a webhook and if detected. If gateway supports webhooks but has no API for auto-creating or verifying, leave this as is and add instructions.
	 * @return bool|null
	 */
	public static function verify_webhook(){
		return null;
	}
	
	public static function settings_show_settings_credentials( $is_sandbox = false ){
		return is_ssl() || em_constant('EMP_GATEWAY_SSL_OVERRIDE') || ($is_sandbox && !empty($_REQUEST['show_keys']) && wp_verify_nonce($_REQUEST['show_keys'], 'show_'. static::$gateway . '_creds'));
	}
}
?>