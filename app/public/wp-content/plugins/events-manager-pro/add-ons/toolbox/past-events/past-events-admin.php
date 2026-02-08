<?php
namespace EM\Toolbox;

class Past_Events_Admin {
	public static function init(){
		global $pagenow;
		add_action('em_settings_general_events_footer', array( static::class, 'options' ), 1);
		if( $pagenow === 'edit.php' && !empty($_REQUEST['post_type']) && $_REQUEST['post_type'] === EM_POST_TYPE_EVENT && !empty($_REQUEST['post_status']) && empty($_REQUEST['scope']) ) {
			if( $_REQUEST['post_status'] === 'past' ) {
				$_REQUEST['scope'] = $_GET['scope'] = 'past';
			} elseif (  $_REQUEST['post_status'] === 'upcoming' ) {
				$_REQUEST['scope'] = $_GET['scope'] = 'future';
			}
		}
		add_action('em_pro_updated', array( static::class, 'install' ) );
	}
	
	public static function install() {
		include_once('past-events-update.php');
	}
	
	public static function options(){
		$msg = __('You can trash, delete or change the status of an event to "Past", which will hide these events from the front-end but ramain in your admin records. An event is considered as past according to the %s option in %s.');
		$archives = sprintf(esc_html__emp('%s List/Archives'),esc_html__emp('Event'));
		$msg = sprintf( $msg, '<em><strong>' . emp__('Are current events past events?') . '</strong></em>', '<a href="#pages+event-archives"><code>'. esc_html__emp('Pages') . ' > '. $archives . '</code></a>');
		em_options_select( esc_html__('Past Events Action', 'em-pro'), 'dbem_past_events', array(
			'published' => esc_html__('Keep Published', 'em-pro'),
			'past' => sprintf( esc_html__("Change status to %s", 'em-pro'), esc_html__('Past', 'em-pro') ),
			'trash' => esc_html__('Trash', 'em-pro'),
			'delete' => esc_html__('Delete', 'em-pro'),
		), $msg);
	}
	
}
Past_Events_Admin::init();