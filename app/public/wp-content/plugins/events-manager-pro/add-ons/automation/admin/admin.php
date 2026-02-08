<?php
namespace EM\Automation;
use EM\Automation\Admin\Automation_List_Table, EM\Automation;

class Admin {
	
	/**
	 * @var \EM\Automation\Triggers\Trigger
	 */
	public static $trigger;
	
	public static function init(){
		add_action('em_options_page_footer', '\EM\Automation\Admin::options');
		if( get_option('dbem_automation_enabled') ) {
			add_action('admin_menu', '\EM\Automation\Admin::admin_menu');
			if( !empty($_REQUEST['page']) && $_REQUEST['page'] == 'automation' && !empty($_REQUEST['post_type']) && $_REQUEST['post_type'] == EM_POST_TYPE_EVENT ) {
				add_action('admin_init', '\EM\Automation\Admin::actions');
				add_action('admin_print_scripts', '\EM\Automation\Admin::scripts');
			}
		}
		// $booking = em_get_booking(747)->to_api(); echo '<pre>' . var_export($booking, true) . '</pre>'; die();
	}
	
	public static function admin_menu(){
		add_submenu_page('edit.php?post_type=' . EM_POST_TYPE_EVENT, __('Automation', 'em-pro'), __('Automation', 'em-pro'), 'manage_options', "automation", '\EM\Automation\Admin::automation_page');
	}
	
	public static function scripts(){
		$min = (defined('EM_DEBUG') && EM_DEBUG) || (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') || SCRIPT_DEBUG) ? '':'.min';
		wp_enqueue_style('em-automation-admin', plugins_url('automation'.$min.'.css', __FILE__), array(), EMP_VERSION );
		wp_enqueue_script('em-automation-admin', plugins_url('automation'.$min.'.js', __FILE__), array('jquery'), EMP_VERSION );
	}
	
	public static function options(){
		global $save_button;
		?>
		<div  class="postbox " id="em-opt-automation" >
			<div class="handlediv" title="<?php esc_attr_e_emp('Click to toggle', 'events-manager'); ?>"><br /></div><h3><?php esc_html_e ( 'Automation', 'em-pro' ); ?> <em>(Beta)</em></h3>
			<div class="inside">
				<table class='form-table'>
					<tr class="em-boxheader"><td colspan='2'>
							<p>
								<?php
								esc_html_e( 'Automation is a powerful feature that lets you select triggers and then act on those triggers, providing endless automation possibilities. Triggers can be things such as when an event is due to start, or when a booking status changes. Actions are things like sending emails or firing a webhook to a third party.', 'em-pro' );
								//You can further customize all these templates, or parts of them by overriding our template files as per our %s.
								?>
							</p>
						</td></tr>
					<?php
					em_options_radio_binary ( sprintf(_x( 'Enable %s?', 'Enable a feature in settings page', 'em-pro' ), esc_html__('Automation', 'em-pro')), 'dbem_automation_enabled', esc_html__('When enabled, you will see the Automations menu link under your Events admin menu.', 'em-pro'), '', '.em-automation-options');
					?>
					<?php echo $save_button; ?>
				</table>
			</div> <!-- . inside -->
		</div> <!-- .postbox -->
		<?php
	}
	
	public static function actions(){
		if( !empty($_REQUEST['action']) && !empty($_REQUEST['_wpnonce']) && !empty($_REQUEST['automation_id']) ){
			global $wpdb;
			// validate nonce or appropriate action
			$single = is_numeric($_REQUEST['automation_id']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'em-automation-'.$_REQUEST['action'].'-'.$_REQUEST['automation_id']);
			$bulk = is_array($_REQUEST['automation_id']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-event_page_automation');
			if( $single || $bulk ){
				// sanitize bulk action items
				if( $bulk ){
					$ids = array();
					foreach( $_REQUEST['automation_id'] as $id ){
						if( is_numeric($id) ) $ids[] = absint($id);
					}
					$ids = implode(',', $ids);
				}
				// perform action
				switch( $_REQUEST['action'] ){
					case 'delete':
						if( $bulk ) {
							$result = $wpdb->query('DELETE FROM '. EM_AUTOMATION_TABLE . ' WHERE id IN ('. $ids .')');
							$success = sprintf(esc_html__('%s Deleted', 'em-pro'), esc_html__('Automations', 'em-pro'));
						}else{
							$result = $wpdb->delete(EM_AUTOMATION_TABLE, array('id' => $_REQUEST['automation_id']));
							$success = sprintf(esc_html__('%s Deleted', 'em-pro'), esc_html__('Automation', 'em-pro'));
						}
						break;
					case 'activate':
						if( $bulk ) {
							$result = $wpdb->query('UPDATE '. EM_AUTOMATION_TABLE . ' SET status=1 WHERE id IN ('. $ids .')');
							$success = sprintf( esc_html__('%s Activated', 'em-pro'), esc_html__('Automations', 'em-pro') );
						}else{
							$result = $wpdb->update( EM_AUTOMATION_TABLE, array('status' => 1), array('id' => $_REQUEST['automation_id']) );
							$success = sprintf( esc_html__('%s Activated', 'em-pro'), esc_html__('Automation', 'em-pro') );
						}
						break;
					case 'deactivate':
						if( $bulk ) {
							$result = $wpdb->query('UPDATE '. EM_AUTOMATION_TABLE . ' SET status=0 WHERE id IN ('. $ids .')');
							$success = sprintf( esc_html__('%s Deactivated', 'em-pro'), esc_html__('Automations', 'em-pro') );
						}else{
							$result = $wpdb->update( EM_AUTOMATION_TABLE, array('status' => 0), array('id' => $_REQUEST['automation_id']) );
							$success = sprintf( esc_html__('%s Deactivated', 'em-pro'), esc_html__('Automation', 'em-pro') );
						}
						break;
				}
				// return confirmation if successful only
				if( !empty($result) ){
					global $EM_Notices; /* @var \EM_Notices $EM_Notices */
					$EM_Notices->add_confirm($success, true);
				}
			}
			wp_safe_redirect( add_query_arg( array('action' => null, 'nonce' => null ), wp_get_referer()));
			exit();
		}
		if( !empty($_REQUEST['view']) && $_REQUEST['view'] == 'edit' && !empty($_REQUEST['automation_nonce']) ){
			// we're saving an automation
			global $EM_Notices; /* @var \EM_Notices $EM_Notices */
			if( !empty($_REQUEST['automation_id']) && wp_verify_nonce($_REQUEST['automation_nonce'], 'em-automation-edit-'.$_REQUEST['automation_id']) ){
				// get the automation_id
				static::$trigger = Automation::get_trigger($_REQUEST['automation_id']);
				if( !static::$trigger ) {
					$EM_Notices->add_error( esc_html__('Could not find saved automation.', 'em-pro'));
				} elseif ( !empty($_REQUEST['type']) && static::$trigger::$type !== $_REQUEST['type'] ) {
					// trigger type is not matched, so we just recreate the trigger and give same automation ID
					$recreate_trigger = static::$trigger->id;
				}
			}
			if( !empty($recreate_trigger) || empty($_REQUEST['automation_id']) ){
				// build a trigger based on the submtted info
				if( !empty($recreate_trigger) || wp_verify_nonce($_REQUEST['automation_nonce'], 'em-automation-add') ) {
					$trigger_type = !empty($_REQUEST['type']) ? $_REQUEST['type']:'';
					if( Automation::trigger_exists($trigger_type) ){
						$trigger_class = Automation::get_trigger($trigger_type);
						static::$trigger = new $trigger_class();
						if( !empty($recreate_trigger) ) {
							static::$trigger->id = $recreate_trigger;
						}
					}else{
						if( empty($_REQUEST['automation_name']) ){
							$EM_Notices->add_error( esc_html__('Please enter a name for your automation.', 'em-pro') );
						}
						$EM_Notices->add_error( esc_html__('Please assign a valid trigger to create your automation.', 'em-pro') );
					}
				}else{
					$EM_Notices->add_error('Failed nonce... try again?');
				}
			}
			if( !empty(static::$trigger) ){
				$trigger = static::$trigger;
				$admin = $trigger::load_admin();
				$get_post = $admin::get_post( $trigger );
				if( $get_post === true ){
					$validate = $admin::validate( $trigger );
					if( $validate === true ){
						$save = $admin::save( $trigger );
						if( $save === true ){
							if( $trigger->status ){
								$success = esc_html__('Automation saved!', 'em-pro');
							}else{
								$success = esc_html__('Automation saved! Remember to activate your automation so it can start listening for triggers.', 'em-pro');
							}
							$EM_Notices->add_confirm( $success, true );
						}else{
							$EM_Notices->add_error( $save );
						}
					}else{
						$EM_Notices->add_error( $validate );
					}
				}else{
					$EM_Notices->add_error($get_post);
				}
				if( empty($EM_Notices->notices['errors']) ){
					wp_safe_redirect( add_query_arg( array('action' => null, 'nonce' => null, 'automation_nonce' => null, 'automation_id' => $trigger->id ), wp_get_referer()));
					exit();
				}
			}
			if( empty($EM_Notices->notices['errors']) ) {
				wp_safe_redirect(add_query_arg(array('action' => null, 'nonce' => null, 'automation_nonce' => null), wp_get_referer()));
				exit();
			}
		}
	}
	
	public static function automation_page(){
		if( empty($_REQUEST['view']) ){
			$add_new_link = esc_url(add_query_arg( array('view' => 'edit')));
			$base_url = current( explode('?', $_SERVER['REQUEST_URI']));
			$base_query_args = array_intersect_key( $_GET, array_flip(array('view','page','automation_id')));
			$base_url = add_query_arg($base_query_args, $base_url);
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">
					<?php esc_html_e('Automation', 'em-pro'); ?>
				</h1>
				<a href="<?php echo $add_new_link; ?>" class="page-title-action"><?php echo esc_html__emp('Add New', 'default'); ?></a>
				<hr class="wp-header-end">
				<?php
				include('automations.php');
				include('list-table.php');
				include('automation-list-table.php');
				$Automations_List = new Automation_List_Table();
				// $Automations_List->views(); // not using views for now, handled in filters
				?>
				<form method="post">
					<?php
					$Automations_List->prepare_items();
					$Automations_List->display();
					?>
				</form>
			</div>
			<?php
		}elseif( $_REQUEST['view'] == 'edit' ){
			if( !empty($_REQUEST['automation_id']) ){
				$trigger = Automation::get_trigger( $_REQUEST['automation_id'] );
			}elseif( !empty(static::$trigger) ) {
				$trigger = static::$trigger;
			}else{
				$trigger = null;
			}
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">
					<?php
						if( $trigger && $trigger->id ){
							echo sprintf( esc_html__('%s Automation', 'em-pro'), esc_html__emp('Edit', 'default') );
						}else{
							echo sprintf( esc_html__('%s Automation', 'em-pro'), esc_html__emp('Add', 'default') );
						}
					?>
				</h1>
				<a href="<?php echo esc_url(add_query_arg( array('view' => null, 'automation_id' => null))); ?>" class="page-title-action"><?php esc_html_e('Back to Automations', 'em-pro'); ?></a>
				<a href="<?php echo esc_url(add_query_arg( array('automation_id' => null))); ?>" class="page-title-action"><?php esc_html_e('Add New Automation', 'em-pro'); ?></a>
				<hr class="wp-header-end">
				<?php
				static::editor( $trigger );
				?>
			</div>
			<?php
		}
	}
	
	public static function editor( $trigger = null ){
		global $EM_Notices;
		?>
		<form id="em-options-form" class="em-automation-form em" action="" method="post">
			<div class="metabox-holder" style="max-width: 800px; margin:auto;">
				<?php
				if( $trigger && $trigger->id ) {
					wp_nonce_field('em-automation-edit-' . $trigger->id, 'automation_nonce', false);
					if( $trigger->status ){
						$activation_link = esc_url( add_query_arg(array('action'=>'deactivate', 'automation_id'=> $trigger->id, '_wpnonce'=> wp_create_nonce('em-automation-deactivate-'.$trigger->id))) );
						$activation_action = esc_html__('Deactivate', 'em-pro');
						$message = esc_html__('Your automation is ready and listening for triggers!', 'em-pro');
						$button = '<a href="'. $activation_link .'" class="button button-primary">'. $activation_action . '</a>';
						echo '<div class="automation-notice success has-action"><p>'. $message .'</p><p>'.$button.'</p></div>';
					}else{
						$activation_link = esc_url( add_query_arg(array('action'=>'activate', 'automation_id'=> $trigger->id, '_wpnonce'=> wp_create_nonce('em-automation-activate-'.$trigger->id))) );
						$activation_action = esc_html__('Activate', 'em-pro');
						$message = esc_html__('Your automation is ready to go! You need to activate it first though...', 'em-pro');
						$button = '<a href="'. $activation_link .'" class="button button-primary">'. $activation_action . '</a>';
						echo '<div class="automation-notice info has-action"><p>'. $message .'</p><p>'.$button.'</p></div>';
					}
				}else{
					wp_nonce_field('em-automation-add', 'automation_nonce');
				}
				$trigger_output = $trigger && !empty($trigger->trigger_data['output']) ? $trigger->trigger_data['output'] : '';
				$contexts = array('event'=>array(), 'booking'=>array(), 'location'=>array());
				?>
				<div class="postbox-container" style="width:100%;">
					
					<input name="automation_name" value="<?php if( !empty($trigger->name) ) echo esc_attr($trigger->name); ?>" placeholder="<?php esc_html_e('Name this automation, for your reference.', 'em-pro'); ?>" class="widefat" style="padding:7px; color:#555; margin:10px 0 15px;">

					<h3 class="between"><span><?php esc_html_e('When', 'em-pro'); ?></span></h3>
					
					<!-- Trigger OPTIONS -->
					<div class="postbox">
						<div class="handlediv" title=""><br></div> <h3 class="wp-heading-inline"><label for="trigger-type"><?php esc_html_e('Trigger', 'em-pro'); ?></label></h3>
						<span><?php esc_html_e('A trigger is when something happens in Events Manager', 'em-pro'); ?></span>
						<div class="inside">
							<p>
								<select name="type" id="trigger-type" class="em-selectize">
									<option value=""><?php esc_html_e('Select Trigger', 'em-pro'); ?></option>
									<?php foreach( Automation::$triggers as $trigger_type => $trigger_class ): $contexts[$trigger_class::$context][] = 'trigger-context-'.$trigger_type; ?>
										<option value="<?php echo esc_attr($trigger_type); ?>" <?php if( $trigger && $trigger::$type == $trigger_type ) echo 'selected'; ?>><?php echo esc_html($trigger_class::get_name()); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<div id="trigger-type-container"></div>
						</div>
					</div>
					
					<!-- Output Option -->
					<div class="trigger-output-contexts">
						<h3 class="between"><span><?php esc_html_e('Select', 'em-pro'); ?></span></h3>
						<div class="trigger-output-context trigger-output-context-events <?php echo implode(' ', $contexts['events']); ?>">
							<p><?php esc_html_e('The selected trigger will supply an Event object, meaning you can instead use any of the following data to pass onto your actions.', 'em-pro'); ?></p>
							<p>
								<select name="trigger_output_events" class="output-type em-selectize">
									<option value=""><?php esc_html_e('Choose what to supply to your actions', 'em-pro'); ?></option>
									<option value="bookings" <?php if( $trigger && $trigger_output == 'bookings' ) echo 'selected'; ?>><?php esc_html_e('Event Bookings', 'em-pro'); ?></option>
									<option value="event" <?php if( $trigger && $trigger_output == 'event' ) echo 'selected'; ?>><?php esc_html_e('The Event', 'em-pro'); ?></option>
								</select>
							</p>
							<p class="trigger-context-desc">
								<span class="trigger-context trigger-context-event"><?php esc_html_e('This event will be passed on to the following actions.', 'em-pro'); ?> <?php esc_html_e('You can further filter these events with the optional filters below.', 'em-pro'); ?></span>
								<span class="trigger-context trigger-context-bookings"><?php esc_html_e('Each booking belonging to this event will be passed on to the following actions.', 'em-pro'); ?> <?php esc_html_e('You can further filter these bookings with the optional filters below.', 'em-pro'); ?></span>
							</p>
						</div>
						<div class="trigger-output-context trigger-output-context-bookings <?php echo implode(' ', $contexts['bookings']); ?>">
							<p>
								<select name="trigger_output_bookings" class="output-type em-selectize">
									<option value=""><?php esc_html_e('Get the following data and...', 'em-pro'); ?></option>
									<option value="bookings" <?php if( $trigger_output === 'bookings' ) echo 'selected'; ?>><?php esc_html_e('The Booking(s)', 'em-pro'); ?></option>
									<option value="event" <?php if( $trigger_output === 'event' ) echo 'selected'; ?>><?php esc_html_e('The Event each booking belongs to', 'em-pro'); ?></option>
								</select>
							</p>
							<p class="trigger-context-desc">
								<span class="trigger-context trigger-context-event"><?php esc_html_e('The event blonging to the bookings will be passed on to the following actions.', 'em-pro'); ?> <?php esc_html_e('You can further filter these events with the optional filters below.', 'em-pro'); ?></span>
								<span class="trigger-context trigger-context-bookings"><?php esc_html_e('The bookings will be passed on to the following actions', 'em-pro'); ?> <?php esc_html_e('You can further filter these bookings with the optional filters below.', 'em-pro'); ?></span>
							</p>
						</div>
						
						<div class="trigger-context trigger-context-event">
							<div class="em-automation-context-multi em-automation-event-categories">
								<?php
								$what = isset($trigger->trigger_data['filters']['categories_include']) ? $trigger->trigger_data['filters']['categories_include'] :'include';
								?>
								<p>
									<label>
									<select name="trigger_output[event][categories_include]" class="em-automation-event-categories-include">
										<option value="include" <?php if( $what === 'include' ) echo 'selected'; ?>><?php esc_html_e('Include', 'em-pro'); ?></option>
										<option value="exclude"<?php if( $what === 'exclude' ) echo 'selected'; ?>><?php esc_html_e('Exclude', 'em-pro'); ?></option>
									</select>
									<strong><?php esc_html_e('the following categories:', 'em-pro'); ?></strong>
									</label>
								</p>
								<p>
									<label for="em-automation-event-categories" class="screen-reader-text"><?php esc_html_e_emp('Event Categories'); ?></label>
									<select name="trigger_output[event][categories][]" class="em-selectize checkboxes" id="em-automation-event-categories" multiple size="10" placeholder="<?php esc_html_e_emp('Event Categories'); ?>">
										<?php
										$args_em = apply_filters('em_automation_categories_args', array('orderby'=>'name','hide_empty'=>0));
										$categories = \EM_Categories::get($args_em);
										$selected = !empty($trigger->trigger_data['filters']['categories']) ? $trigger->trigger_data['filters']['categories'] : array();
										if( !empty($args['category']) ){
											if( !is_array($args['category']) ){
												$selected = explode(',', $args['category']);
											} else {
												$selected = $args['category'];
											}
										}
										$walker = new \EM_Walker_CategoryMultiselect();
										$args_em = apply_filters('em_automation_categories_walker_args', array('hide_empty' => 0, 'orderby' => 'name', 'name' => 'category', 'hierarchical' => true, 'taxonomy' => EM_TAXONOMY_CATEGORY, 'selected' => $selected, 'show_option_none' => esc_html__emp('Event Categories'), 'option_none_value' => 0, 'walker' => $walker,));
										echo walk_category_dropdown_tree($categories, 0, $args_em);
										?>
									</select>
								</p>
							</div>
							<div class="em-automation-context-multi em-automation-event-tags">
								<?php
								$what = isset($trigger->trigger_data['filters']['tags_include']) ? $trigger->trigger_data['filters']['tags_include'] :'include';
								?>
								<p>
									<label>
									<select name="trigger_output[event][tags_include]" class="em-automation-event-tags-include">
										<option value="include" <?php if( $what === 'include' ) echo 'selected'; ?>><?php esc_html_e('Include', 'em-pro'); ?></option>
										<option value="exclude"<?php if( $what === 'exclude' ) echo 'selected'; ?>><?php esc_html_e('Exclude', 'em-pro'); ?></option>
									</select>
									<?php esc_html_e('the following tags:', 'em-pro'); ?>
									</label>
								</p>
								<p>
									<label for="em-automation-event-tags" class="screen-reader-text"><?php esc_html_e_emp('Event Tags'); ?></label>
									<select name="trigger_output[event][tags][]" class="em-selectize checkboxes" id="em-automation-event-tags" multiple size="10" placeholder="<?php esc_html_e_emp('Event Tags'); ?>">
										<?php
										$args_em = apply_filters('em_automation_tags_args', array('orderby'=>'name','hide_empty'=>0 ));
										$tags = \EM_Tags::get($args_em);
											$selected = !empty($trigger->trigger_data['filters']['tags']) ? $trigger->trigger_data['filters']['tags'] : array();
										if( !empty($args['tag']) ){
											if( !is_array($args['tag']) ){
												$selected = explode(',', $args['tag']);
											} else {
												$selected = $args['tag'];
											}
										}
										$walker = new \EM_Walker_CategoryMultiselect();
										$args_em = apply_filters('em_automation_tags_walker_args', array('hide_empty' => 0, 'orderby' => 'name', 'name' => 'tag', 'hierarchical' => true, 'taxonomy' => EM_TAXONOMY_TAG, 'selected' => $selected, 'show_option_none' => esc_html__emp('Event Tags'), 'option_none_value' => 0, 'walker' => $walker));
										echo walk_category_dropdown_tree($tags, 0, $args_em);
										?>
									</select>
								</p>
							</div>
						</div>
						<div class="trigger-context trigger-context-bookings">
							<div class="em-automation-context-multi em-automation-event-booking-statuses">
								<?php
								$what = isset($trigger->trigger_data['filters']['booking_status_include']) ? $trigger->trigger_data['filters']['booking_status_include'] :'include';
								?>
								<p>
									<label>
										<select name="trigger_output[bookings][booking_status_include]" id="em-automation-event-bookings-include">
											<option value="include" <?php if( $what === 'include' ) echo 'selected'; ?>><?php esc_html_e('Include', 'em-pro'); ?></option>
											<option value="exclude"<?php if( $what === 'exclude' ) echo 'selected'; ?>><?php esc_html_e('Exclude', 'em-pro'); ?></option>
										</select>
										<?php esc_html_e('the following booking statuses:', 'em-pro'); ?>
									</label>
								</p>
								<?php
								// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
								$EM_Booking = new \EM_Booking();
								$booking_statuses = !empty($trigger->trigger_data['filters']['booking_status']) && is_array($trigger->trigger_data['filters']['booking_status']) ? $trigger->trigger_data['filters']['booking_status'] : array();
								?>
								<p>
									<select name="trigger_output[bookings][booking_status][]" id="em-automation-event-bookings" class="em-selectize checkboxes" multiple size="10" placeholder="<?php esc_html_e_emp('Booking Status'); ?>">
										<option value=""><?php esc_html_e('Choose booking statuses', 'em-pro'); ?></option>
										<?php foreach( $EM_Booking->status_array as $status => $status_name ): ?>
											<option value="<?php echo esc_attr($status); ?>" <?php if( in_array($status, $booking_statuses) ) echo 'selected'; ?>><?php echo esc_html($status_name); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
							</div>
							<?php if( class_exists('\EM\Payments\Gateways') ) : ?>
							<div class="em-automation-context-multi em-automation-event-booking-gateways">
								<?php
								$what = isset($trigger->trigger_data['filters']['gateways_include']) ? $trigger->trigger_data['filters']['gateways_include'] :'include';
								?>
								<p>
									<label>
										<select name="trigger_output[bookings][gateways_include]" id="em-automation-event-bookings-include">
											<option value="include" <?php if( $what === 'include' ) echo 'selected'; ?>><?php esc_html_e('Include', 'em-pro'); ?></option>
											<option value="exclude"<?php if( $what === 'exclude' ) echo 'selected'; ?>><?php esc_html_e('Exclude', 'em-pro'); ?></option>
										</select>
										<?php esc_html_e('the following gateways:', 'em-pro'); ?>
									</label>
								</p>
								<?php
								// output any sort of html options relevant to this trigger, that'll be stored in trigger_data
								$gateways = !empty($trigger->trigger_data['filters']['gateways']) && is_array($trigger->trigger_data['filters']['gateways']) ? $trigger->trigger_data['filters']['gateways'] : array();
								?>
								<p>
									<select name="trigger_output[bookings][gateways][]" id="em-automation-event-bookings" class="em-selectize checkboxes" multiple size="10" placeholder="<?php esc_html_e_emp('Gateways'); ?>">
										<option value=""><?php esc_html_e('Choose payment methods used', 'em-pro'); ?></option>
										<?php foreach( \EM\Payments\Gateways::active_gateways() as $gateway => $Gateway ): ?>
											<option value="<?php echo esc_attr($gateway); ?>" <?php if( in_array($gateway, $gateways) ) echo 'selected'; ?>><?php echo esc_html($Gateway::$title); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
							</div>
							<?php endif; ?>
						</div>
					</div>
					<div class="trigger-context trigger-context-bookings trigger-context-events">
						<div class="em-automation-context-multi em-automation-event-rsvp-policies">
							<?php
								$policy_type = !empty($trigger->trigger_data['filters']['rsvp_policy']) ? $trigger->trigger_data['filters']['rsvp_policy'] : 0;
							?>
							<p>
								<label>
									<?php esc_html_e('Include only events with these RSVP Policies:', 'em-pro'); ?>
								</label>
							</p>
							<p>
								<select name="trigger_output[any][rsvp_policy]" id="em-automation-event-bookings-include" class="em-selectize" placeholder="<?php esc_html_e_emp('RSVP Policies'); ?>">
									<option value="0" <?php if( $policy_type === 0 ) echo 'selected'; ?>><?php echo esc_html__('All', 'em-pro'); ?></option>
									<option value="none" <?php if( $policy_type === 'none' ) echo 'selected'; ?>><?php esc_html_e('No Policy', 'em-pro'); ?></option>
									<option value="open" <?php if( $policy_type === 'open' ) echo 'selected'; ?>><?php esc_html_e('Open', 'em-pro'); ?></option>
									<option value="flexible" <?php if( $policy_type === 'flexible' ) echo 'selected'; ?>><?php esc_html_e('Flexible', 'em-pro'); ?></option>
									<option value="strict" <?php if( $policy_type === 'strict' ) echo 'selected'; ?>><?php esc_html_e('Strict', 'em-pro'); ?></option>
								</select>
							</p>
						</div>
					</div>
						
					<!-- GENERAL OPTIONS -->
					<?php
						if( $trigger && !empty($trigger->action_data) ){
							$actions = $trigger->action_data;
						}else{
							$actions = array( array('type' => '', 'data' => array()) );
						}
					?>
					<div id="actions-container">
						<h3 class="between"><span><?php esc_html_e('Then', 'em-pro'); ?></span></h3>
						<?php $id = 1; ?>
						<?php foreach( $actions as $action_data ): ?>
						<div class="postbox em-automation-action" data-id="<?php echo $id; ?>">
							<div class="handlediv" title=""><br></div>
							<h3><label for="trigger-type"><?php esc_html_e('Action', 'em-pro'); ?></label></h3>
							<span><?php esc_html_e('Do something when triggered', 'em-pro'); ?></span>
							<input type="checkbox" value="1" name="action_data[<?php echo $id; ?>][enabled]" <?php if ( !isset($action_data['enabled']) || $action_data['enabled'] ) echo 'checked'; ?>>
							<div class="inside">
								<p>
									<label>
										<span class="screen-reader-text"><?php esc_html_e('Select Action', 'em-pro'); ?></span>
										<select name="action_data[<?php echo $id; ?>][type]" class="action-type">
											<option value=""><?php esc_html_e('Select Action', 'em-pro'); ?></option>
											<?php foreach( Automation::$actions as $action_type => $action ): ?>
												<option value="<?php echo esc_attr($action_type); ?>" data-description="<?php echo esc_html($action::get_description()); ?>" <?php if( $action_data['type'] == $action_type) echo 'selected'; ?>><?php echo esc_html($action::get_name()); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</p>
								<div class="action-options-container">
									<?php
									if( !empty($action_data['data']) ) {
										$action_type = $action_data['type'];
										$action = Automation::get_action($action_type);
										if( class_exists($action) ) {
											$action_admin = $action::load_admin()::options($action_data, $id);
										}
									}
									?>
								</div>
								<a href="#" class="remove-action-trigger button button-secondary"><?php echo sprintf(esc_html__('Remove %s', 'em-pro'), esc_html__('Action', 'em-pro')); ?></a>
							</div>
						</div>
						<?php $id ++; ?>
						<?php endforeach; ?>
						<?php $id = '%id%'; ?>
						<div class="postbox em-automation-action-template" data-id="<?php echo $id; ?>" style="display:none;">
							<div class="handlediv" title=""><br></div> <h3><label for="trigger-type"><?php esc_html_e('Add an Action', 'em-pro'); ?></label></h3>
							<div class="inside">
								<p>
									<label>
										<span class="screen-reader-text"><?php esc_html_e('Select Action', 'em-pro'); ?></span>
										<select name="action_data[<?php echo $id; ?>][type]" class="action-type">
											<option value=""><?php esc_html_e('Select Action', 'em-pro'); ?></option>
											<?php foreach( Automation::$actions as $action_type => $action ): ?>
												<option value="<?php echo esc_attr($action_type); ?>" data-description="<?php echo esc_html($action::get_description()); ?>"><?php echo esc_html($action::get_name()); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</p>
								<div class="action-options-container">
								</div>
								<a href="#" class="remove-action-trigger button button-secondary"><?php echo sprintf(esc_html__('Remove %s', 'em-pro'), esc_html__('Action', 'em-pro')); ?></a>
							</div>
						</div>
					</div>
					<a href="#" id="add-action-trigger" class="button button-secondary" style="max-width:50%; margin:10px auto; width:100%; text-align:center; display:block;"><?php echo esc_html__('Add Another Action', 'em-pro'); ?></a>
				</div> <!-- .postbox-container -->
				<button type="submit" href="#" id="automation-submit" class="button button-primary" style="max-width:50%; margin:10px auto; width:100%; text-align:center; display:block;"><?php echo sprintf(esc_html__('Save %s', 'em-pro'), esc_html__('Automation', 'em-pro')); ?></button>
			</div>
		</form>
		<form action="" style="display:none; visibility:hidden;">
			<div id="em-automation-trigger-types">
				<?php
					foreach( Automation::$triggers as $trigger_type => $trigger_class ){
						echo '<div id="em-automation-trigger-type-'. $trigger_type .'">';
						$trigger_class::load_admin()::options( $trigger );
						echo '</div>';
					}
				?>
			</div>
			<div id="em-automation-action-types">
				<?php
				foreach( Automation::$actions as $action_type => $action ){
					echo '<div class="em-automation-action-type-'. $action_type .'">';
					$action::load_admin()::options( $action );
					echo '</div>';
				}
				?>
			</div>
		</form>
		<?php
		foreach (Automation::$actions as $action_type => $action) {
			$action::load_admin()::footer();
		}
	}
	
	public static function automation_edit( $id ){
	
	}
}
Admin::init();