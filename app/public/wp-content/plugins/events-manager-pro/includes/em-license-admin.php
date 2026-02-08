<?php
namespace EM_Pro;
include('pxl-license-admin.php');

class EM_License_Admin extends PXL_License_Admin {
	
	/**
	 * @var PXL_License
	 */
	public static $license_class = 'EM_License';
	
	public static function init() {
		parent::init();
		$license_class = static::$license_class;
		if( empty($license_class::$depends_on) ){
			$self = get_called_class();
			if( is_multisite() ){
				add_action('em_ms_options_page_tabs', $self.'::admin_tab_label');
				add_action('em_ms_options_page_tab_license', $self.'::admin_tab_content');
			}else{
				add_action('em_options_page_tabs', $self.'::admin_tab_label');
				add_action('em_options_page_tab_license', $self.'::admin_tab_content');
			}
			//Modify EM_Admin_Notice objects on the fly
			add_action('em_admin_notice_'.$license_class::$slug.'-activation', array($self, 'admin_notice_activation'));
			add_action('em_admin_notice_'.$license_class::$slug.'-error', array($self, 'admin_notice_error'));
			add_action('em_admin_notice_pxl-dev-license', array($self, 'admin_notice_dev_license'));
			if( defined('EM_VERSION') && EM_VERSION <= 5.95 ){
				if( is_multisite() ){
					add_action('em_ms_options_page_footer', array($self, 'admin_tab_content'));
				}else{
					add_action('em_options_page_footer', array($self, 'admin_tab_content'));
				}
			}
		}
	}
	
	public static function admin_notice_activation( $Admin_Notice ){
		$license_class = static::$license_class;
		$msg = esc_html__('%s has been installed! Please %s to enable the plugin features as well as access to automated updates and support for this site.', $license_class::$lang);
		$notice = sprintf($msg, $license_class::$plugin_name, '<a href="'. $license_class::get_license_admin_url() .'">'.esc_html__('activate your license',$license_class::$lang).'</a>');
		$notice .= '</p><p>'.
			'<a class="button-primary" href="'. esc_url(static::get_activation_url()) .'">'. esc_html__('Activate', $license_class::$lang) .'</a>'.
			'<a class="button-secondary" href="'. esc_url(static::get_activation_url(true)) .'" style="margin-left:10px">'. esc_html__('Activate Dev/Staging License', $license_class::$lang) .'</a>';
		$Admin_Notice->message = $notice;
		$Admin_Notice->dismissible = false;
		$Admin_Notice->where = 'all';
	}
	
	public static function admin_notice_error( $Admin_Notice ){
		$license_class = static::$license_class;
		$msg = esc_html__('Uh Oh! We experienced an error verifying your license for %1$s. Please %2$s for continued access to automated updates and support for this site.', $license_class::$lang);
		$notice = sprintf($msg, $license_class::$plugin_name, '<a href="'. $license_class::get_license_admin_url() .'">'.esc_html__('check your license',$license_class::$lang).'</a>');
		$Admin_Notice->message = $notice;
		$Admin_Notice->where = 'all';
	}
	
	public static function admin_notice_dev_license( $Admin_Notice ){
		$license_class = static::$license_class;
		$msg_p1 = esc_html__('You are using a dev/staging license (%s) instead of a production license, please make sure you are using the plugin on this site according to our %s.', $license_class::$lang);
		$notice = sprintf($msg_p1, '<strong><em>'.esc_html__('for development purposes only',$license_class::$lang).'</em></strong>', '<a href="https://eventsmanagerpro.com/terms-conditions/">'.esc_html__('usage policy',$license_class::$lang).'</a>') .'</p><p>'
			.esc_html__('If you are moving this site to a production environment, please deactivate this license and activate again as a regular license.');
		$Admin_Notice->message = $notice;
		$Admin_Notice->dismissible = false;
		$Admin_Notice->where = 'plugin';
	}
	
	
	
	public static function admin_tab_label( $array ){
		$class = static::$license_class;
		if( empty($array['license']) ){
			$array['license'] = __('Licenses',$class::$lang);
		}
		return $array;
	}
	
	public static function admin_tab_content() {
		$class = static::$license_class;
		if( !is_super_admin() ) return;
		?>
		<div class="postbox always-open" id="em-opt-pro-license">
			<div class="handlediv" title="<?php __('Click to toggle', $class::$lang); ?>"><br /></div><h3><span><?php echo sprintf(esc_html__('%s License', $class::$lang), $class::$plugin_name); ?> </span></h3>
			<div class="inside">
				<?php parent::admin_settings(); ?>
			</div>
		</div>
		<?php
	}
}