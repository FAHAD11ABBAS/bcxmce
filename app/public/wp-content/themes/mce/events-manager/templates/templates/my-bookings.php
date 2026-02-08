<?php do_action('em_template_my_bookings_header'); ?>
<?php
	global $wpdb, $current_user, $EM_Notices, $EM_Person;
	if( is_user_logged_in() ) :
		$EM_Person = new EM_Person( get_current_user_id() );
		$EM_Bookings = $EM_Person->get_bookings();
		$bookings_count = count($EM_Bookings->bookings);
		if($bookings_count > 0){
			// Get event IDs here in one query to speed things up
			$event_ids = array();
			foreach($EM_Bookings as $EM_Booking){
				$event_ids[] = $EM_Booking->event_id;
			}
		}
		$limit = ( !empty($_GET['limit']) ) ? $_GET['limit'] : 20;
		$page = ( !empty($_GET['pno']) ) ? $_GET['pno']:1;
		$offset = ( $page > 1 ) ? ($page-1)*$limit : 0;
		echo $EM_Notices;
		?>
		<div class='<?php em_template_classes('my-bookings'); ?>'>
			<?php if ( $bookings_count >= $limit ) : ?>
			<div class='tablenav'>
				<?php 
				if ( $bookings_count >= $limit ) {
					$link = em_add_get_params($_SERVER['REQUEST_URI'], array('pno'=>'%PAGE%'), false);
					$bookings_nav = em_paginate( $link, $bookings_count, $limit, $page);
					echo $bookings_nav;
				}
				?>
				<div class="clear"></div>
			</div>
			<?php endif; ?>
			<div class="clear"></div>
			<?php if( $bookings_count > 0 ): ?>
			<div class='table-wrap'>
			<table id='dbem-bookings-table-my-bookings' class='widefat post fixed'>
				<thead>
					<tr>
						<th class='manage-column'><?php _e('Event', 'events-manager'); ?></th>
						<th class='manage-column'><?php _e('Date', 'events-manager'); ?></th>
						<th class='manage-column'><?php _e('Spaces', 'events-manager'); ?></th>
						<th class='manage-column'><?php _e('Status', 'events-manager'); ?></th>
						<?php if( get_option('dbem_bookings_rsvp') && get_option('dbem_bookings_rsvp_my_bookings') ): ?>
						<th class='manage-column'><?php _e('RSVP', 'events-manager'); ?></th>
						<?php endif; ?>
						<th class='manage-column'><?php _e('Varauksen tiedot', 'events-manager'); ?></th>
						<th class='manage-column'>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					$rowno = 0;
					$event_count = 0;
					$nonce = wp_create_nonce('booking_cancel');
					$rsvp_nonce = wp_create_nonce('booking_rsvp');
					foreach ($EM_Bookings as $EM_Booking) {
						// 🚫 Skip cancelled bookings (by status code or label)
						if( $EM_Booking->booking_status == 3 || $EM_Booking->booking_status == 'cancelled' ) continue;

						$EM_Event = $EM_Booking->get_event();						
						if( ($rowno < $limit || empty($limit)) && ($event_count >= $offset || $offset === 0) ) {
							$rowno++;
							?>
							<tr>
								<td><?php echo $EM_Event->output("#_EVENTLINK"); ?></td>
								<td><?php echo $EM_Event->start()->i18n( get_option('dbem_date_format') ); ?></td>
								<td><?php echo $EM_Booking->get_spaces(); ?></td>
								<td><?php echo $EM_Booking->get_status(); ?></td>
								<?php if( get_option('dbem_bookings_rsvp') && get_option('dbem_bookings_rsvp_my_bookings') ): ?>
								<td><?php echo $EM_Booking->get_rsvp_status( true ); ?></td>
								<?php endif; ?>
								<td>
									<?php
									// Check if there is booking meta data
									$booking_meta = $EM_Booking->booking_meta;

									if ( !empty($booking_meta['booking']) ) {
										foreach( $booking_meta['booking'] as $key => $value ) {

											// Exclude user_name and user_email fields
											if ( in_array($key, ['user_name', 'user_email']) ) continue;

											// Extract question key
											$question = str_replace('_booking|', '', $key);

											// Unserialize the value (this will decode the serialized data if applicable)
											$value = maybe_unserialize($value);

											// Ensure it's a valid selection (if value is an array, convert it to a string)
											if (is_array($value)) {
												$value = implode(', ', array_map('esc_html', $value));
											} else {
												$value = esc_html($value);
											}

											// Replace any encoded characters (for instance, '%f6' -> 'ö')
											$question = str_replace('%f6', 'ö', $question);
											$question = str_replace('%e4', 'ä', $question);
											$question = str_replace('%e5', 'å', $question);
											$question = str_replace('%c5', 'Å', $question);
											$question = str_replace('%f8', 'ø', $question);
											$question = str_replace('%c4', 'Ä', $question);
											$question = str_replace('%d6', 'Ö', $question);
											$question = str_replace('_', ' ', $question);

											// Capitalize the first letter of the question
											$question = ucfirst($question);

											// Display the decoded question and its value
											echo '<strong>' . esc_html($question) . ':</strong> ' . $value . '<br>';
										}
									} else {
										echo __('No information provided', 'events-manager');
									}
									?>
								</td>
								<td>
									<?php
									$cancel_links = array();
									$show_rsvp = get_option('dbem_bookings_rsvp') && get_option('dbem_bookings_rsvp_my_bookings_buttons');
									$show_cancel_rsvp = $EM_Booking->can_rsvp(0) && get_option('dbem_bookings_rsvp_sync_cancel');

									if( !$show_cancel_rsvp && (!in_array($EM_Booking->booking_status, array(2,3)) && $EM_Booking->can_cancel()) ){
										$cancel_url = em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'booking_cancel', 'booking_id'=>$EM_Booking->booking_id, '_wpnonce'=>$nonce));
										$cancel_links[] = '<a class="em-bookings-cancel" href="'. esc_url($cancel_url) .'" onclick="if( !confirm(EM.booking_warning_cancel) ){ return false; }">'.__('Cancel','events-manager').'</a>';
									}
									if ( $show_rsvp ) {
										if( $EM_Booking->can_rsvp(1) ) {
											$url = em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'booking_rsvp_change', 'status' => 1, 'booking_id'=>$EM_Booking->booking_id, '_wpnonce'=>$rsvp_nonce));
											$cancel_links[] = '<a class="em-bookings-rsvp-confirm" href="'.esc_url($url).'">'. EM_Booking::get_rsvp_statuses(1)->label_action .'</a>';
										}
										if( $EM_Booking->can_rsvp(0) ) {
											$url = em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'booking_rsvp_change', 'status' => 0, 'booking_id'=>$EM_Booking->booking_id, '_wpnonce'=>$rsvp_nonce));
											$cancel_links[] = '<a class="em-bookings-rsvp-cancel" href="'.esc_url($url).'">'. EM_Booking::get_rsvp_statuses(0)->label_action .'</a>';
										}
										if( $EM_Booking->can_rsvp(2) ) {
											$url = em_add_get_params($_SERVER['REQUEST_URI'], array('action'=>'booking_rsvp_change', 'status' => 2, 'booking_id'=>$EM_Booking->booking_id, '_wpnonce'=>$rsvp_nonce));
											$cancel_links[] = '<a class="em-bookings-rsvp-maybe" href="'.esc_url($url).'">'. EM_Booking::get_rsvp_statuses(2)->label_action .'</a>';
										}
									}
									$action_links = apply_filters('em_my_bookings_booking_action_links', $cancel_links, $EM_Booking, $cancel_links);
									$action_text = '';
									if( !empty($action_links) ) {
										if (is_array($action_links) ) {
											?>
											<button type="button" class="em-tooltip-ddm em-clickable input button-secondary" data-button-width="match" data-tooltip-class="em-my-bookings-actions-tooltip"><?php esc_html_e('Actions', 'events-manager'); ?></button>
											<div class="em-tooltip-ddm-content em-my-bookings-actions-content">
												<?php foreach( $action_links as $link ): ?>
													<?php echo $link; ?>
												<?php endforeach; ?>
											</div>
											<?php
										} else {
											$action_text = $action_links;
										}
									}
									echo apply_filters('em_my_bookings_booking_actions', $action_text, $EM_Booking, $cancel_links);
									do_action( 'em_my_bookings_booking_actions_bottom', $EM_Booking );
									?>
								</td>
							</tr>											
							<?php
						}
						do_action('em_my_bookings_booking_loop', $EM_Booking);
						$event_count++;
					}
					?>
				</tbody>
			</table>
			</div>
			<?php else: ?>
				<?php _e('You do not have any bookings.', 'events-manager'); ?>
			<?php endif; ?>
			<?php if( !empty($bookings_nav) && $bookings_count >= $limit ) : ?>
			<div class='tablenav'>
				<?php echo $bookings_nav; ?>
				<div class="clear"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php do_action('em_template_my_bookings_footer', $EM_Bookings); ?>
<?php else: ?>
	<p><?php echo sprintf(__('Please <a href="%s">Log In</a> to view your bookings.','events-manager'),site_url('wp-login.php?redirect_to=' . urlencode(get_permalink()), 'login'))?></p>
<?php endif; ?>
