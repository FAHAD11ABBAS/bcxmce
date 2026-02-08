<?php
namespace EM\Payments;
use EM_Options, EM_User_Fields;

class Gateways_Admin{
	
	public static function init(){
		add_action('em_create_events_submenu', array(get_called_class(), 'admin_menu'),10,1);
		if( !empty($_REQUEST['page']) && $_REQUEST['page'] == 'events-manager-gateways' ){
			add_action('admin_init', array(get_called_class(), 'handle_gateways_panel_updates'), 10, 1);
		}
		add_action('em_options_page_footer_bookings', array(get_called_class(), 'admin_options'));
		//Gateways and user fields
		add_action('admin_init', array(get_called_class(), 'customer_fields_admin_actions'),9); //before bookings
		add_action('emp_forms_admin_page', array(get_called_class(), 'customer_fields_admin'),30);
		add_action('wp_ajax_em_toggle_gateway_mode', array( static::class, 'ajax_toggle_mode'),30);
		static::legacy_check();
		
		if ( !empty($_REQUEST['page']) && $_REQUEST['page'] === 'events-manager-gateways' ) {
			add_action('admin_enqueue_scripts', array( static::class, 'admin_enqueue') );
		}
	}
	
	public static function admin_enqueue() {
		wp_enqueue_script('events-manager-gateway-admin', plugins_url('gateways-admin.js',__FILE__), array(), EMP_VERSION);
		wp_enqueue_style('events-manager-gateway-admin', plugins_url('gateways-admin.css',__FILE__), array(), EMP_VERSION);
	}
	
	public static function ajax_toggle_mode(){
		$result = array('success' => false);
		if( !empty($_REQUEST['gateway']) && !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'em_gateway_toggle_'.$_REQUEST['gateway']) ) {
			// get the gateway and status
			$Gateway = Gateways::get($_REQUEST['gateway']);
			if( class_exists($Gateway) ) {
				$result['status'] = get_option( "em_{$Gateway::$gateway}_mode" ) === 'live';
				if ( $result['status'] ) {
					$result['success'] = update_option( "em_{$Gateway::$gateway}_mode", 'sandbox' );
				} else {
					$result['success'] = update_option( "em_{$Gateway::$gateway}_mode", 'live' );
				}
				$result['status'] = get_option( "em_{$Gateway::$gateway}_mode" ) === 'live';
				if( !$result['success'] ) {
					$result['message'] = 'Unknown error trying to update option, please contact a site administrator about this issue.';
				}
				$live_status = $result['status'] ? 'live' : 'test';
				if ( $Gateway::$supports_test_mode ) {
					if ( $live_status === 'test' && get_option('em_'. $Gateway::$gateway . "_test_limited") ) {
						$live_status = 'limited';
					}
				}
				$result['mode'] = $live_status;
			} else {
				$result['message'] = 'Gateway not found.';
			}
		}else{
			$result['message'] = 'Missing gateway or invalid nonce data.';
		}
		echo json_encode($result);
		die();
	}
	
	public static function legacy_check(){
		// add option to transition out of legacy mode
		if( !empty($_REQUEST['page']) && ($_REQUEST['page'] === 'events-manager-options' || $_REQUEST['page'] === 'events-manager-gateways') && (!is_multisite() || is_super_admin()) ){
			if( EM_Options::site_get('legacy-gateways') === false || em_constant('EMP_GATEWAY_LEGACY') === false ){
				add_action('network_admin_notices', array(get_called_class(), 'legacy_notice'));
				add_action('admin_notices', array(get_called_class(), 'legacy_notice'));
				if( !empty($_REQUEST['legacy-gateway-activate']) && wp_verify_nonce($_REQUEST['legacy-gateway-activate'], 'legacy-gateway-activate') ){
					EM_Options::site_set('legacy-gateways', true);
					wp_safe_redirect( wp_get_referer() );
				}elseif( !empty($_REQUEST['legacy-gateway-dismiss']) && wp_verify_nonce($_REQUEST['legacy-gateway-dismiss'], 'legacy-gateway-dismiss') ){
					EM_Options::site_remove('legacy-gateways');
					wp_safe_redirect( wp_get_referer() );
				}
			}
		}
	}
	
	public static function legacy_notice(){
		?>
		<div class="notice notice-warning">
			<?php if( em_constant('EMP_GATEWAY_LEGACY') && EM_Options::site_get('legacy-gateways', null) === null ): ?>
				<p><em>You currently have <code>define('EMP_GATEWAY_LEGACY', false);</code></em> defined in the wp-config.php file or elsewhere in your site code, remove this line to use your site settings, or set to <code>true</code> to force-enable legacy mode again.</p>
			<?php else: ?>
				<p>You now using the new Gateway API available since Events Manager Pro 3.2! Please visit our <a href="https://eventsmanagerpro.com/downloads/" target="_blank">Downloads page</a> to get new gateway payment methods.</p>
				<p>If you are experiencing any payment issues due to the upgrade, you can revert to legacy mode by clicking the button below.</p>
				<p>If you are not experiencing any issues, you can disable this warning and complete the migration process by clicking the 'Dismiss' button below. At which point, the only way to re-enable legacy mode would be by adding this line to your <code>wp-config.php</code> file:</p>
				<p><code>define('EMP_GATEWAY_LEGACY', true);</code></p>
				<?php if( is_multisite() ): ?>
					<p><em>This action will be applied to entire network of this MultiSite installation.</em></p>
				<?php endif; ?>
				<p>
					<a class="button-primary" href="<?php echo esc_url( add_query_arg('legacy-gateway-activate', wp_create_nonce('legacy-gateway-activate')) ); ?>">Re-Activate Legacy Mode</a>
					<a class="button-secondary" href="<?php echo esc_url( add_query_arg('legacy-gateway-dismiss', wp_create_nonce('legacy-gateway-dismiss')) ); ?>">Dismiss Notice</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
	
	public static function admin_options(){
		if( current_user_can('list_users') ){
		?>
			<a name="pro-api"></a>
			<div  class="postbox " id="em-opt-gateway-options">
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3 class='hndle'><span><?php _e ( 'Payment Gateway Options', 'em-pro' ); ?> </span></h3>
			<div class="inside">
				<table class='form-table'>
					<?php 
						em_options_radio_binary ( __( 'Enable Quick Pay Buttons?', 'em-pro' ), 'dbem_gateway_use_buttons', sprintf(__( 'Only works with gateways that do not require additional payment information to be submitted (e.g. PayPal and Offline payments). If enabled, the default booking form submit button is not used, and each gateway will have a button (or image, see <a href="%s">individual gateway settings</a>) which if clicked on will submit a booking for that gateway.','em-pro' ),admin_url('edit.php?post_type='.EM_POST_TYPE_EVENT.'&page=events-manager-gateways')) );
						em_options_input_text(__('Gateway Label','em-pro'),'dbem_gateway_label', __('If you are not using quick pay buttons a drop-down menu will be used, with this label.','em-pro'));
						em_options_input_text(__('Unpaid Booking Expiry','em-pro'), 'dbem_gateway_payment_timeout', __('If a gateway supports it and expiry time is set to 0, the following default expiry time will be used. You can disable individual gateways by setting the expiry action setting to \'none\'.','em-pro'));
					?>
				</table>
			</div> <!-- . inside -->
			</div> <!-- .postbox -->
		<?php
		}
	}
	
	public static function admin_menu($plugin_pages){
		$plugin_pages[] = add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, __('Payment Gateways','em-pro'),__('Payment Gateways','em-pro'),'list_users','events-manager-gateways', array(get_called_class(), 'handle_gateways_panel'));
		return $plugin_pages;
	}

	public static function handle_gateways_panel() {
		if( !empty($_REQUEST['action']) ) {
			switch ( $_REQUEST['action'] ) {
				case 'edit':
					if ( Gateways::is_registered( $_GET['gateway'] ) ) {
						$Gateway = Gateways::get_gateway( $_GET['gateway'] );
						$Gateway::admin()::settings();
					}
					return; // break; so we don't show the list below
				case 'transactions':
					if ( Gateways::is_registered( $_GET['gateway'] ) ) {
						global $EM_Gateways_Transactions;
						$EM_Gateways_Transactions->output();
					}
					return; // break; so we don't show the list below
			}
		}
		?>
		<div class='wrap'>
			<h1><?php _e('Edit Gateways','em-pro'); ?></h1>
			<?php if ( get_option('dbem_gateway_use_buttons') && !Gateways::buttons_mode_possible() ): ?>
				<div class="gateway-notice gateway-notice-info has-icon">
					<span class="em-icon em-icon-info"></span>
					<p>
						<?php
							$settings_page = sprintf( '<a href="'.EM_ADMIN_URL.'&amp;page=events-manager-options#bookings+gateway-options">%s</a>', __('Settings', 'events-manager') );
							echo sprintf( esc_html__('You have enabled %1$s in your %2$s page, however you have enabled a gateway that is not button-enabled. %1$s will be disabled whilst these gateways remain active.', 'em-pro'), esc_html__('Quick Pay Buttons'), $settings_page );
						?>
					</p>
				</div>
			<?php endif; ?>
			<form method="post" action="" id="posts-filter">
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value=""><?php _e('Bulk Actions'); ?></option>
							<option value="toggle"><?php _e('Toggle activation'); ?></option>
							<option value="activate"><?php _e('Activate'); ?></option>
							<option value="deactivate"><?php _e('Deactivate'); ?></option>
							<option value="test-mode"><?php _e('Enable Test Mode'); ?></option>
							<option value="live-mode"><?php _e('Enable Live Mode'); ?></option>
						</select>
						<input type="submit" class="button-secondary action" value="<?php _e('Apply','em-pro'); ?>">		
					</div>		
					<div class="alignright actions"></div>		
					<br class="clear">
				</div>	
				<div class="clear"></div>	
				<?php
					wp_original_referer_field(true, 'previous'); wp_nonce_field('emp-gateways');
					$columns = array(	
						"name" => __('Gateway Name','em-pro'),
						"active" =>	__('Active','em-pro'),
						'test' => __('Mode', 'em-pro'),
						"transactions" => __('Transactions','em-pro')
					);
					$columns = apply_filters('em_gateways_columns', $columns);
				?>	
				<table class="widefat fixed">
					<thead>
					<tr>
					<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
						<?php
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
						?>
					</tr>
					</thead>	
					<tfoot>
					<tr>
					<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
						<?php
						reset($columns);
						foreach($columns as $key => $col) {
							?>
							<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
							<?php
						}
						?>
					</tr>
					</tfoot>
					<tbody>
						<?php
						$page = 'events-manager-gateways';
						$gateways = Gateways::list();
						if( !empty( $gateways ) ) {
							foreach( $gateways as $gateway => $Gateway ) {
								?>
								<tr valign="middle" class="alternate">
									<th class="check-column" scope="row"><input type="checkbox" value="<?php echo esc_attr($gateway); ?>" name="gateways[]"></th>
									<td class="column-name">
										<strong><a title="Edit <?php echo esc_attr($Gateway::$title); ?>" href="<?php echo EM_ADMIN_URL; ?>&amp;page=<?php echo $page; ?>&amp;action=edit&amp;gateway=<?php echo $gateway; ?>" class="row-title"><?php echo esc_html($Gateway::$title); ?></a></strong>
										<?php
											//Check if Multi-Booking Ready
											if( get_option('dbem_multiple_bookings') && !$Gateway::$supports_multiple_bookings ){
												echo '<br/><em>'. __('This gateway cannot be activated because it does not support multiple bookings mode.','em-pro') . '</em>';
											}
											$actions = array();
											$actions['edit'] = "<span class='edit'><a href='".EM_ADMIN_URL."&amp;page=" . $page . "&amp;action=edit&amp;gateway=" . $gateway . "'>" . __('Settings') . "</a></span>";

											if( Gateways::is_active( $gateway ) ) {
												$actions['toggle'] = "<span class='edit activate'><a href='" . wp_nonce_url(EM_ADMIN_URL."&amp;page=" . $page. "&amp;action=deactivate&amp;gateway=" . $gateway , 'toggle-gateway_' . $gateway) . "&amp;_wpnonce=". wp_create_nonce('deactivate-'.$gateway)."'>" . __('Deactivate') . "</a></span>";
											} else {
												if( !get_option('dbem_multiple_bookings') || ( get_option('dbem_multiple_bookings') && $Gateway::$supports_multiple_bookings ) ){
													$actions['toggle'] = "<span class='edit deactivate'><a href='" . wp_nonce_url(EM_ADMIN_URL."&amp;page=" . $page. "&amp;action=activate&amp;gateway=" . $gateway , 'toggle-gateway_' . $gateway) . "&amp;_wpnonce=". wp_create_nonce('activate-'.$gateway)."'>" . __('Activate') . "</a></span>";
												}
											}
										?>
										<?php
										if ( $Gateway::$legacy ) {
											$link = '<a href="https://eventsmanagerpro.com/downloads/">'. esc_html__('Download new payment methods for this gateway.').'</a>';
											echo '<br><em>'. esc_html__('Legacy Gateway', 'em-pro') . ' - ' . $link . '</em>';
										}
										?>
										<br><div class="row-actions"><?php echo implode(" | ", $actions); ?></div>
									</td>
									<td class="column-active">
										<?php
											if( Gateways::is_active( $gateway ) ) {
												echo "<strong>" . __('Active', 'em-pro') . "</strong>";
											} else {
												echo __('Inactive', 'em-pro');
											}
										?>
									</td>
									<td class="column-test">
										<?php
											$live_status = $Gateway::get_mode();
											if( $live_status === 'live' ) {
												echo '<span class="gateway-mode gateway-mode-live" data-mode="' . $live_status . '">' . esc_html__( 'Live Mode', 'em-pro' ) . '</span> ';
											} elseif( $live_status === 'limited' ) {
												echo '<span class="gateway-mode gateway-mode-test" data-mode="' . $live_status . '">' . esc_html__( 'Limited Test Mode', 'em-pro' ) . '</span> ';
											} elseif( $live_status === 'test' ) {
												echo '<span class="gateway-mode gateway-mode-live" data-mode="' . $live_status . '">' . esc_html__( 'Test Mode', 'em-pro' ) . '</span> ';
											}
										?>
									</td>
									<td class="column-transactions">
										<a href='<?php echo EM_ADMIN_URL; ?>&amp;page=<?php echo $page; ?>&amp;action=transactions&amp;gateway=<?php echo $gateway; ?>'><?php _e('View transactions','em-pro'); ?></a>
									</td>
							    </tr>
								<?php
							}
						} else {
							$columncount = count($columns) + 1;
							?>
							<tr valign="middle" class="alternate" >
								<td colspan="<?php echo $columncount; ?>" scope="row"><?php _e('No Payment gateways were found for this install.','em-pro'); ?></td>
						    </tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</form>

		</div> <!-- wrap -->
		<?php
	}
			
	public static function handle_gateways_panel_updates() {
		global $EM_Notices;
		if( !empty($_REQUEST['gateway']) && !empty($_REQUEST['action']) ){
			$gateway = $_REQUEST['gateway'];
			if ( Gateways::is_registered($gateway) ) {
				$Gateway = Gateways::get($gateway); /* @var Gateway $Gateway */
				switch ( $_REQUEST['action'] ) {
					case 'deactivate' :
						check_admin_referer ( 'deactivate-'.$Gateway::$gateway );
						if ( $Gateway::admin()::deactivate() ) {
							$EM_Notices->add_confirm(__('Gateway deactivated.', 'em-pro'), true);
						} else {
							$EM_Notices->add_error(__('Gateway not deactivated.', 'em-pro'), true);
						}
						wp_safe_redirect ( em_wp_get_referer() );
						break;
					case 'activate' :
						check_admin_referer ( 'activate-'.$Gateway::$gateway );
						if ( $Gateway::admin()::activate() ) {
							$EM_Notices->add_confirm(__('Gateway activated.', 'em-pro'), true);
						} else {
							$EM_Notices->add_error(__('Gateway not activated.', 'em-pro'), true);
						}
						wp_safe_redirect ( em_wp_get_referer() );
						break;
					case 'updated' :
						check_admin_referer ( 'updated-'.$Gateway::$gateway );
						if ( $Gateway::admin()::update() ) {
							$EM_Notices->add_confirm(esc_html__('Gateway updated.', 'em-pro'), true);
						} else {
							$EM_Notices->add_error(esc_html__('Gateway not updated.', 'em-pro'), true);
						}
						wp_safe_redirect ( em_wp_get_referer() );
						break;
				}
			}
		} elseif( !empty($_REQUEST['gateways']) && is_array($_REQUEST['gateways']) && !empty($_REQUEST['action']) ) {
			check_admin_referer( 'emp-gateways' );
			switch ( $_REQUEST['action'] ) {
				case 'toggle' :
					foreach ( $_REQUEST['gateways'] as $gateway ) {
						if ( Gateways::is_registered($gateway) ) {
							Gateways::get($gateway)::admin()::toggle();
						}
					}
					$EM_Notices->add_confirm( __('Gateway activation toggled.', 'em-pro'), true );
					break;
				case 'activate' :
					foreach ( $_REQUEST['gateways'] as $gateway ) {
						if ( Gateways::is_registered($gateway) ) {
							Gateways::get($gateway)::admin()::activate();
						}
					}
					$EM_Notices->add_confirm( __('Gateways activated.', 'em-pro'), true );
					break;
				case 'deactivate' :
					foreach ( $_REQUEST['gateways'] as $gateway ) {
						if ( Gateways::is_registered($gateway) ) {
							Gateways::get($gateway)::admin()::deactivate();
						}
					}
					$EM_Notices->add_confirm( __('Gateways deactivated.', 'em-pro'), true );
					break;
				case 'live-mode' :
					foreach ( $_REQUEST['gateways'] as $gateway ) {
						if ( Gateways::is_registered($gateway) ) {
							update_option('em_'. $gateway . '_mode', 'live');
						}
					}
					$EM_Notices->add_confirm( __('Gateways now in Live Mode.', 'em-pro'), true );
					break;
				case 'test-mode' :
					foreach ( $_REQUEST['gateways'] as $gateway ) {
						if ( Gateways::is_registered($gateway) ) {
							update_option('em_'. $gateway . '_mode', 'test');
						}
					}
					$EM_Notices->add_confirm( __('Gateways now in Test Mode.', 'em-pro'), true );
					break;
			}
			wp_safe_redirect ( em_wp_get_referer() );
		}
	}
	
	public static function customer_fields_admin_actions() {
		global $EM_Notices;
		$EM_Form = \EM_User_Fields::get_form();
		if( !empty($_REQUEST['page']) && $_REQUEST['page'] == 'events-manager-forms-editor' ){
			if( !empty($_REQUEST['form_name']) && 'gateway_customer_fields' == $_REQUEST['form_name'] && wp_verify_nonce($_REQUEST['_wpnonce'], 'gateway_customer_fields_'.get_current_user_id()) ){
				//save values
				$gateway_fields = array();
				foreach( Gateways::$customer_fields as $field_key => $field_val ){
					$gateway_fields[$field_key] = ( !empty($_REQUEST[$field_key]) ) ? $_REQUEST[$field_key]:'';
				}
				update_option('emp_gateway_customer_fields',$gateway_fields);
				$EM_Notices->add_confirm(__('Changes Saved','em-pro'));
			}
		}
		//enable dbem_bookings_tickets_single_form if enabled
	}
	
	public static function customer_fields_admin() {
		//enable dbem_bookings_tickets_single_form if enabled
		$EM_Form = EM_User_Fields::get_form();
		$current_values = get_option('emp_gateway_customer_fields');
		?>
		<a name="gateway_customer_fields"></a>
		<div id="em-booking-form-editor" class="postbox">
			<div class="handlediv" title=""><br></div>
			<h3>
				<span><?php _e ( 'Common User Fields for Gateways', 'em-pro' ); ?></span>
			</h3>
			<div class="inside">
				<p><?php _e('In many cases, customer address information is required by gateways for verification. This section connects your custom fields to commonly used customer information fields.', 'em-pro' ); ?></p>
				<p><?php _e('After creating user fields above, you should link them up in here so some gateways can make use of them when processing payments.', 'em-pro' ); ?></p>
				<form action="#gateway_customer_fields" method="post">
					<table class="form-table">
						<tr><td><?php _e('Name (first/last)','em-pro'); ?></td><td><em><?php _e('Generated accordingly from user first/last name or full name field. If a name field isn\'t provided in your booking form, the username will be used instead.','em-pro')?></em></td></tr>
						<tr><td><?php _e('Email','em-pro'); ?></td><td><em><?php _e('Uses the WordPress account email associated with the user.', 'em-pro')?></em></td></tr>
						<?php foreach( Gateways::$customer_fields as $field_key => $field_val ): ?>
							<tr>
								<td><?php echo $field_val; ?></td>
								<td>
									<select name="<?php echo $field_key; ?>">
										<option value="0"><?php esc_html_e('none selected','em-pro'); ?></option>
										<?php foreach( $EM_Form->user_fields as $field_id => $field_name ): ?>
											<option value="<?php echo $field_id; ?>" <?php echo ($field_id == $current_values[$field_key]) ?'selected="selected"':''; ?>><?php echo $field_name; ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<p>
						<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('gateway_customer_fields_'.get_current_user_id()); ?>">
						<input type="hidden" name="form_action" value="form_fields">
						<input type="hidden" name="form_name" value="gateway_customer_fields" />
						<input type="submit" name="events_update" value="<?php _e('Save Form','em-pro'); ?>" class="button-primary">
					</p>
				</form>
			</div>
		</div>
		<?php
	}
}
Gateways_Admin::init();