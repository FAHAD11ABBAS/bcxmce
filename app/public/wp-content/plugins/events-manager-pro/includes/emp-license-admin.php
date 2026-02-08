<?php
namespace EM_Pro;
require('em-license-admin.php');

class License_Admin extends EM_License_Admin {
	
	public static $license_class = 'EM_Pro\License';
	
	public static function init() {
		parent::init();
		$self = get_called_class();
		if( is_multisite() ){
			add_action('network_admin_notices', $self.'::admin_notices');
		}else{
			add_action('admin_notices', $self.'::admin_notices');
		}
	}
	
	public static function admin_notices(){
		$class = static::$license_class;
		if( is_super_admin() && !empty($_REQUEST['page']) && ('events-manager-options' == $_REQUEST['page']) ){
			if( defined('EMP_DEV_UPDATES') && EMP_DEV_UPDATES ){
				?>
				<div id="message" class="updated">
					<p><?php echo sprintf(__('Dev Mode active: Just a friendly reminder that you have added %s to your wp-config.php file. Only admins see this message, and it will go away when you remove that line.',$class::$lang),'<code>define(\'EMP_DEV_UPDATES\',true);</code>'); ?></p>
				</div>
				<?php
			}
		}
	}
}