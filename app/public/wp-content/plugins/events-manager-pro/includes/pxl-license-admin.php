<?php
namespace EM_Pro;
class PXL_License_Admin {
	
	/**
	 * @var PXL_License
	 */
	public static $license_class = 'PXL_License';
	
	public static function init(){
		$license_class = static::$license_class;
		$self = get_called_class();
		add_action( 'in_plugin_update_message-'.$license_class::$slug,  $self.'::plugin_message');
		add_action('wp_ajax_pxl-license-error-details', function(){
			if( !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'pxl-license-error-details') ){
				$license = License::get_license();
				if( !empty($license->error_response) ){
					echo '<p>The following is the response object returned after attempting to check your license: </p>';
					echo '<pre>'.print_r($license->error_response, true). '</pre>'; die();
				}
			}
			die();
		});
		add_action('wp_ajax_pxl-license-get-own-ip', function() {
			if (!empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'pxl-license-get-own-ip')) {
				echo $_SERVER['REMOTE_ADDR'];
				die();
			}
		});
	}
	
	public static function get_activation_url( $dev = false ){
		$license_class = static::$license_class;
		$license_class::switch_blog();
		$args = array(
			'site' => get_bloginfo('url'),
			'plugin' => $license_class::$slug,
			'callback' => urlencode(static::get_recheck_url()),
			'dev' => $dev ? 1:0,
		);
		$license_class::restore_blog();
		return add_query_arg( $args, $license_class::$activation_url );
	}
	
	public static function get_deactivation_url(){
		$license_class = static::$license_class;
		$license_class::switch_blog();
		$args = array(
			'key' => $license_class::get_license_key(),
			'plugin' => $license_class::$slug,
			'callback' => urlencode(static::get_recheck_url()),
		);
		$license_class::restore_blog();
		return add_query_arg( $args, $license_class::$deactivation_url );
	}
	
	public static function get_reset_url( $redirect = true ){
		$license_class = static::$license_class;
		$url = admin_url('admin-ajax.php?nonce='.wp_create_nonce('reset-license-'.$license_class::$plugin).'&action=pxl_reset_'.$license_class::$slug);
		if( $redirect ){
			$url .= '&redirect=1';
		}
		return $url;
	}
	
	public static function get_recheck_url( $redirect = true ){
		$license_class = static::$license_class;
		$url = admin_url('admin-ajax.php?nonce='.wp_create_nonce('recheck-license-'.$license_class::$plugin).'&action=pxl_recheck_'.$license_class::$slug);
		if( $redirect ){
			$url .= '&redirect=1';
		}
		return $url;
	}
	
	/**
	 * Add an extra update message to the update plugin notification.
	 */
	public static function plugin_message() {
		$class = static::$license_class;
		$license = $class::get_license();
		if( $class::is_active() && !$license->valid ){
			echo '</p><p style="font-style:italic;">'.sprintf(esc_html__('Please %s to enable updates.',$class::$lang), '<a href="$license_class::$purchase_url" target="_blank">'.esc_html__('renew your license', $class::$lang).'</a>');
		}elseif( !$class::is_active() ){
			echo '</p><p style="font-style:italic;">'.sprintf(esc_html__('Please %s to enable updates.',$class::$lang), '<a href="'.$class::get_license_admin_url().'" target="_blank">'.esc_html__('activate your license', $class::$lang).'</a>');
		}
		//update server-served notices
		if( $license->update_notices ){
			if( is_array($license->update_notices) ){
				echo implode('</p><p style="font-style:italic;">', $license->update_notices);
			}else{
				echo '</p><p style="font-style:italic;">' . $license->update_notices;
			}
		}
	}
	
	public static function admin_settings(){
		$class = static::$license_class;
		if( !is_super_admin() ) return;
		$node_mode = defined($class::$constant_prefix.'_SITE') && defined($class::$constant_prefix.'_SITE_KEY') && defined($class::$constant_prefix.'_NODE_SITE') && defined($class::$constant_prefix.'_NODE_SITE_KEY');
		?>
		<div class="<?php echo $class::$slug; ?>-license pxl-license">
			<?php
			$license = $class::get_license();
			if( !$class::is_active() ){
				if( $license->deactivated ){
					?>
					<p><em><strong><?php echo sprintf(esc_html__('Your %s license has been deactivated on this site, please reactivate it in order to access updates and support, or deactivate this plugin from your site.', $class::$lang), $class::$plugin_name); ?></strong></em></p>
					<?php
				}elseif( $license->error ){
					?>
					<div style="padding:10px; background-color:#f2dede; border:1px solid #ebccd1; color: #a94442;">
						<?php if( $license->wp_error_code ): ?>
							<p>
								<strong><?php esc_html_e('Your license key was rejected for the following reason below. This may be a temporary error so please try again in a few minutes, otherwise get in touch with our support team.',$class::$lang); ?></strong>
							</p>
						<?php endif; ?>
						<p><?php echo $license->error; ?></p>
						<?php static::error_info( $license ); ?>
					</div>
					<?php
				}
				?>
				<p>
					<?php
					$msg = esc_html__('%s has been installed! Please %s to enable the plugin features as well as access to automated updates and support for this site.', $class::$lang);
					echo sprintf($msg, $class::$plugin_name, esc_html__('activate your license',$class::$lang));
					?>
				</p>
				<?php if( $node_mode ): ?>
					<p>
						<em style="color:#a94442">
							<?php echo sprintf(esc_html__('You have added node license constants to your wp-config.php file with pre-supplied keys, this site is active for %s on behalf of %s.', $class::$lang), '<code>'.constant($class::$constant_prefix.'_NODE_SITE').'</code>', '<code>'.constant($class::$constant_prefix.'_SITE').'</code>') ?>
						</em>
					</p>
					<p>
						<em style="color:#a94442">
							<?php echo sprintf(esc_html__('These license keys will have had to be activated already on your %s.', $class::$lang), '<a href="https://eventsmanagerpro.com/account/licenses/" target="_blank">'.esc_attr__('license management page', $class::$lang).'</a>') ?>
						</em>
					</p>
					<p>
						<a class="button-secondary" href="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Verify License Keys', $class::$lang); ?></a>
					</p>
				<?php else: ?>
					<p>
						<a class="button-primary" href="<?php echo esc_url(static::get_activation_url()); ?>"><?php esc_html_e('Activate', $class::$lang); ?></a>
						<a class="button-secondary" href="<?php echo esc_url(static::get_activation_url( true )); ?>"><?php esc_html_e('Activate Dev/Staging License', $class::$lang); ?></a>
					</p>
					<p>
						<em><a href="#" class="pxl-license-key-trigger"><?php esc_html_e("Enter license key manually",$class::$lang); ?></a></em> | <em><a href="https://eventsmanagerpro.com/gopro/" target="_blank"><?php esc_html_e("I don't have a license key",$class::$lang); ?></a></em>
					</p>
					<div class="pxl-license-key" style="display:none;">
						<p>
							<input type="text" class="pxl-license-key-input" placeholder="<?php esc_html_e('Enter your license key', $class::$lang); ?>" value="<?php echo esc_attr($class::get_license_key()); ?>" class="widefat"><br>
							<em><?php echo sprintf( __("If automatic activation isn't working for you, you can get your license key from <a href=\"%s\">here</a>.", $class::$lang), 'http://eventsmanagerpro.com/account/sites/' ); ?></em>
						</p>
						<p>
							<a class="button-primary pxl-license-key-save" href="#" data-url="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Save License Key', $class::$lang); ?></a> &nbsp;&nbsp;
							<a class="button-secondary" href="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Re-check Key', $class::$lang); ?></a>
						</p>
					</div>
				<?php endif;
			}elseif( !$license->valid && $license->error ){
				?>
				<div style="padding:10px; background-color:#f2dede; border:1px solid #ebccd1; color: #a94442;">
					<p>
						<?php esc_html_e('We are experiencing difficulties verifying your license key. Below is the error message, we suggest you trigger a recheck or try reactivating your site again below.',$class::$lang); ?>
					</p>
					<p>
						<?php
						$error_desc = esc_html__('If rechecking or reactivating doesn\'t work, please get in touch with our %s and provide this message so we can help you fix this issue.',$class::$lang);
						echo sprintf( $error_desc, '<a href="https://eventsmanagerpro.com/support/add-a-new-question/">'.esc_html__('Support Team', $class::$lang).'</a>');
						?>
					</p>
					<p><em><strong>Error : <?php echo $license->error; ?></strong></em></p>
					<?php static::error_info( $license ); ?>
				</div>
				<p>
					<a class="button-primary" href="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Re-check', $class::$lang); ?></a> or
					<a class="button-secondary" href="<?php echo esc_url(static::get_activation_url()); ?>"><?php esc_html_e('Reactivate Site License', $class::$lang); ?></a>
					<?php $warning = esc_attr__('Are you sure you want to delete your license data? This will remove license data from your site, but will not deactivate your license on our own servers.', $class::$lang); ?>
					<a class="button-secondary pxl-reset-license" href="<?php echo esc_url( static::get_reset_url() ); ?>" data-warning="<?php echo $warning; ?>"><?php esc_attr_e('Delete/Reset License Key', $class::$lang); ?></a>
				</p>
				<?php
			}else{
				if( $license->error ){
					?>
					<div style="padding:10px; background-color:#f2dede; border:1px solid #ebccd1; color: #a94442;" id="pxl-license-unknown-error">
						<p><em><strong><?php echo $license->error; ?></strong></em></p>
						<?php static::error_info( $license ); ?>
					</div>
					<?php
				}
				?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e('License Status', $class::$lang); ?></th>
						<td>
							<?php
							esc_html_e('Active', $class::$lang);
							if( $license->dev ){
								echo ' <strong><em>('.esc_html__('for development purposes only',$class::$lang).')</em></strong>';
							}
							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e('Supports and Updates Until', $class::$lang); ?></th>
						<td>
							<?php
							$date = date('Y-m-d', $license->until );
							if( $license->until > time() ){
								echo '<em style="color:#006400">'. $date .'</em>';
							}elseif( $license->until ){
								echo '<em style="color:#a94442">'. sprintf(esc_html__('Expired on %s', $class::$lang), $date) .'</em>';
							}else{
								echo '<em style="color:#a94442">'. sprintf(esc_html__('Expired', $class::$lang)) .'</em>';
							}
							?>
						</td>
					</tr>
					<?php if( $node_mode ): ?>
						<tr valign="top">
							<th scope="row"><?php esc_html_e('Node License', $class::$lang); ?></th>
							<td>
								<em style="color:#a94442">
									<?php echo sprintf(esc_html__('You have added node license constants to your wp-config.php file with pre-supplied keys, this site is active for %s on behalf of %s.', $class::$lang), '<code>'.constant($class::$constant_prefix.'_NODE_SITE').'</code>', '<code>'.constant($class::$constant_prefix.'_SITE').'</code>') ?>
								</em>
							</td>
						</tr>
					<?php endif; ?>
					<?php if( defined('EMP_DEBUG') && EMP_DEBUG ): ?>
						<tr>
							<th><?php esc_html_e('License Key', $class::$lang); ?></th>
							<td>
								<?php if( $node_mode ): ?>
									<p>
										<strong><?php echo esc_html(constant($class::$constant_prefix.'_SITE')); ?></strong>
										- <em>(<?php echo esc_html__('shared license key', $class::$lang); ?>)</em>
										<br>
										<code><?php echo esc_html( constant($class::$constant_prefix.'_SITE_KEY') ); ?></code>
									</p>
									<p>
										<strong><?php echo esc_html(constant($class::$constant_prefix.'_NODE_SITE')); ?></strong>
										- <em>(<?php echo esc_html__('this server license key', $class::$lang); ?>)</em>
										<br>
										<code><?php echo esc_html( constant($class::$constant_prefix.'_NODE_SITE_KEY') ); ?></code>
									</p>
								<?php else: ?>
									<em class="pxl-license-key-toggle"><a href="#" class="pxl-license-key-trigger"><?php echo $class::get_license_key(); ?></a></em>
									<div class="pxl-license-key" style="display:none;">
										<p>
											<input type="text" placeholder="<?php esc_html_e('Enter your license key', $class::$lang); ?>" value="<?php echo esc_attr($class::get_license_key()); ?>" class="widefat">
											<em>This is for debugging purposes only, you should deactivate and reactivate licenses either via this interface or on your account page unless instructed by the Events Manager support team.</em>
										</p>
										<p>
											<a class="button-primary pxl-license-key-save" href="#" data-url="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Save License Key', $class::$lang); ?></a>
											<a href="#" class="button-secondary pxl-license-key-trigger"><?php esc_attr_e('Cancel', $class::$lang); ?></a>
										</p>
									</div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
					<foot>
						<tr>
							<td colspan="2">
								<a class="button-secondary" href="<?php echo esc_url( static::get_recheck_url() ); ?>"><?php esc_attr_e('Recheck', $class::$lang); ?></a>&nbsp;&nbsp;
								<a class="button-secondary" href="<?php echo esc_url( static::get_deactivation_url() ); ?>"><?php esc_attr_e('Deactivate License', $class::$lang); ?></a>
								<?php $warning = esc_attr__('Are you sure you want to delete your license data? This will remove license data from your site, but will not deactivate your license on our own servers.', $class::$lang); ?>
								<a class="button-secondary pxl-reset-license" href="<?php echo esc_url( static::get_reset_url() ); ?>" data-warning="<?php echo $warning; ?>"><?php esc_attr_e('Delete/Reset License Key', $class::$lang); ?></a>
							</td>
						</tr>
					</foot>
				</table>
				<?php if( has_filter('pxl_updates_depend_on_'.$class::$slug) ): ?>
					<div style="margin-left:10px;">
						<p><strong><?php esc_html_e('The following products are also linked to this license:', $class::$lang); ?></strong></p>
						<ul>
							<?php foreach( apply_filters('pxl_updates_depend_on_'.$class::$slug, array()) as $plugin_slug => $plugin_info ): ?>
								<li><?php echo esc_html($plugin_info['name']); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif;
			}
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('.<?php echo $class::$slug; ?>-license.pxl-license').each( function(){
						var parent = $(this);
						parent.find('a.pxl-license-key-trigger').on('click', function(e){
							e.preventDefault()
							parent.find('.pxl-license-key-toggle').toggle();
							parent.find('.pxl-license-key').toggle();
						});
						parent.find('a.pxl-license-key-save').on('click', function(e){
							e.preventDefault();
							$('<form action="'+$(this).data('url')+'" method="post"><input type="hidden" name="key" value="'+ parent.find('.pxl-license-key input').val() +'"></form>').appendTo('body').submit();
						});
						$('.nav-tab').on('click', function(){
							if( $(this).attr('id') === 'em-menu-license' ){
								$('#notice-<?php echo $class::$slug; ?>-activation').hide();
							}else{
								$('#notice-<?php echo $class::$slug; ?>-activation').show();
							}
						});
						if( $('.nav-tab.nav-tab-active').attr('id') === 'em-menu-license' ){
							$('#notice-<?php echo $class::$slug; ?>-activation').hide();
						}
						parent.find('.pxl-reset-license').on('click', function(e){
							if( !confirm($(this).data('warning')) ){
								e.preventDefault();
								return false;
							}
							return true;
						});
					});
				});
			</script>
		</div>
		<?php
	}
	
	public static function error_info( $license ){
		if( $license->error_response ){
			?>
			<a href="#" id="pxl-license-error-info" onclick="let el = document.getElementById('pxl-license-error-details'); el.style.display = el.style.display === 'none'  ? 'block':'none';">Show/Hide Details</a>
			<div id="pxl-license-error-details" style="display:none; width:100%;">
				<iframe style="width:100%; min-height:400px;" src="<?php echo add_query_arg(array('action' => 'pxl-license-error-details', 'nonce' => wp_create_nonce('pxl-license-error-details')), admin_url('admin-ajax.php')); ?>"></iframe>
				<?php
				$curl_timeout = is_wp_error($license->error_response) && preg_match('/cURL error 28/', implode(', ', $license->error_response->get_error_messages()));
				if( wp_remote_retrieve_response_code($license->error_response) == 403 || $curl_timeout ){
					$regex = '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/';
					// get own server IP
					$request = wp_remote_post( get_admin_url(null, 'admin-ajax.php'), array('action' => 'pxl-license-get-own-ip', 'nonce' => wp_create_nonce('pxl-license-get-own-ip')) );
					if( !is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ){
						$ip = wp_remote_retrieve_body( $request );
					}
					if( empty($ip) || !preg_match($regex, $ip) ){
						// try ipfy
						$request = wp_remote_request('https://api.ipify.org');
						if( !is_wp_error( $request ) && wp_remote_retrieve_response_code( $request ) == 200 ){
							$ip = wp_remote_retrieve_body( $request );
						}
					}
					if ( !empty($ip) ){
						?>
						<p>The following errors may mean that your server firewall is blocking outgoing connections, or possibly that ours are blocking yours. Please, <a href="https://www.abuseipdb.com/check/<?php echo $ip; ?>">check if your server IP is blacklisted via this link</a> or any blacklist lookup service (your server IP is <code><?php echo $ip; ?></code>), if so then our server firewalls will be refusing your connection since your server is potentially compromised.</p>
						<?php
					}
					?>
					<p><b>This does not mean your site has been hacked</b>, but likely means the IP/machine your site is hosted on has been used for malicious purposes recently and has possibly been compromised in some way. This is entirely possible if you are on shared hosting or do not have a dedicated IP, or if you recently set up your server with a new IP provided to you.</p>
					<p>In all these cases, please contact your hosts, we strongly suggest for your own security and to ensure you don't experience this issue for other services you connect to that you switch server IPs (and probably the machine your site is hosted on!).</p>
					<?php
				}
				?>
			</div>
			<?php
		}
	}
}