<?php
namespace EM\Emails;
use EM_Booking, EM_Mailer;

class Email_Bookings {
	
	static $attachments_directory;
	
	public static function init(){
		//Add options and tables to EM admin pages
		if( current_user_can('manage_others_bookings') ){
			add_action('wp_ajax_email_event_bookings_estimate', '\EM\Emails\Email_Bookings::estimate');
			add_action('em_admin_event_booking_options_buttons', '\EM\Emails\Email_Bookings::event_booking_options_buttons', 10);
			add_action('em_admin_event_booking_options', '\EM\Emails\Email_Bookings::event_booking_options', 10);
			add_action('em_bookings_email_attendees', '\EM\Emails\Email_Bookings::email_page',1,1);
			self::actions();
		}
	}
	
	public static function actions(){
		if( !empty($_POST['email_event_bookings']) && !empty($_POST['event_id']) && wp_verify_nonce($_POST['email_event_bookings'],'email_event_bookings_'.$_POST['event_id']) ){
			global $EM_Notices; /* @var \EM_Notices $EM_Notices */
			//get the event
			$EM_Event = em_get_event($_POST['event_id']);
			list($booking_statuses, $ticket_types) = self::get_filters();
			if( empty($_POST['subject']) ){
				$EM_Notices->add_error('Please add an email subject.', 'events-manager-emails');
			}else{
				$subject = sanitize_text_field($_POST['subject']);
			}
			if( empty($_POST['message']) ){
				$EM_Notices->add_error('Please provide some email message content to send.', 'events-manager-emails');
			}else{
				$message = sanitize_text_field($_POST['message']);
			}
			if( !empty($subject) && !empty($message) && !empty($booking_statuses) && !empty($ticket_types) ){
				//get bookings of a certain status
				global $wpdb;
				$sql = 'SELECT booking_id FROM '.EM_BOOKINGS_TABLE.' WHERE booking_status IN ('. implode(',', $booking_statuses) .') AND booking_id IN (SELECT booking_id FROM '.EM_TICKETS_BOOKINGS_TABLE.' WHERE ticket_id IN ('. implode(',', $ticket_types) .')) AND event_id='.absint($EM_Event->event_id);
				$booking_ids = $wpdb->get_col($sql);
				if( !empty($booking_ids) ){
					//build array of attachments
					$attachments = array();
					$batch_id = null;
					if( !empty($_FILES['email_attachments']) ){
						// prep upload and get upload dir
						if( empty($_REQUEST['send_direct']) || !current_user_can('manage_others_bookings') ) {
							static::$attachments_directory = EM_Mailer::get_attachments_dir();
							add_filter('upload_dir', '\EM\Emails\Email_Bookings::get_attachments_dir');
							require_once(ABSPATH . "wp-admin" . '/includes/file.php');
							require_once(ABSPATH . "wp-admin" . '/includes/image.php');
							require_once(ABSPATH . 'wp-admin/includes/media.php');
							$overrides = apply_filters('em_email_users_upload_overrides', array('test_form' => false, 'test_size' => false));
						}
						// split files into array as if files were submitted with different name values
						$files = array();
						foreach( $_FILES['email_attachments'] as $file_att => $file_vals ){
							foreach( $file_vals as $k => $v ){
								if( empty($files[$k]) ) $files[$k] = array();
								$files[$k][$file_att] = $v;
							}
						}
						// go through files and prepare attachment
						foreach( $files as $k => $file ){
							if( !empty($file['size']) && empty($file['error']) ){
								if( !empty($_REQUEST['send_direct']) && current_user_can('manage_others_bookings') ){
									// prepare for inclusion, deletion will happen by PHP automatically
									$attachment = array( 'name' => $file['name'], 'type' => $file['type'], 'path' => $file['tmp_name'], 'delete' => false );
								}else{
									// prepare to add as a batch attachment
									$upload = wp_handle_upload( $file, $overrides);
									if( empty($upload['error']) ) {
										$attachment = array('name' => $file['name'], 'type' => $upload['type'], 'path' => $upload['file'], 'delete' => true);
									}else{
										$EM_Notices->add_error( $file['name'] .' - ' . $upload['error'] );
									}
								}
								$attachments[] = $attachment;
							}elseif( !empty($file['error']) ){
								$EM_Notices->add_error( $file['error'][$k] );
							}else{
								$EM_Notices->add_error( $file['error'][$k] );
							}
						}
						remove_filter('upload_dir', '\EM\Emails\Email_Bookings::get_attachments_dir');
						if( $EM_Notices->count_errors() > 0 ) return;
						// add attachments to the batch
						if( empty($_REQUEST['send_direct']) || !current_user_can('manage_others_bookings') ){
							// queue for sending
							$wpdb->insert( EM_META_TABLE, array( 'meta_key' => 'email-batch', 'meta_value' => serialize(array('attachments' => $attachments)), 'object_id' => 0) );
							$batch_id = $wpdb->insert_id;
						}
					}
					//send mail
					$EM_Mailer = new EM_Mailer();
					$emails_sent = $email_errors = array();
					$messages_failed = $messages_sent = 0;
					$inserts = array(); // for batch sending
					foreach( $booking_ids as $booking_id ){
						$EM_Booking = new EM_Booking($booking_id);
						$email = $EM_Booking->get_person()->user_email;
						if( !empty($_POST['avoid_duplicates']) && in_array($email, $emails_sent) ) continue;
						$subject_email = $EM_Booking->output($subject, 'email');
						$message_email = $EM_Booking->output($message, 'email');
						if( !empty($_REQUEST['send_direct']) && current_user_can('manage_others_bookings') ){
							// send directly
							if( !$EM_Mailer->send( $subject_email, $message_email, $EM_Booking->get_person()->user_email, $attachments ) ){
								$messages_failed++;
								$email_errors = array_merge($email_errors, $EM_Mailer->errors);
							}else{
								$messages_sent++;
								$emails_sent[] = $email;
							}
						}else{
							$inserts[] = $wpdb->prepare( '(%d, %d, %d, %s, %s, %s)', array( 'event_id' => $EM_Booking->event_id, 'booking_id' => $EM_Booking->booking_id, 'batch_id' => $batch_id, 'email' => $EM_Booking->get_person()->user_email, 'subject' => $subject_email, 'body' => $message_email) );
						}
					}
					if( !empty($_REQUEST['send_direct']) && current_user_can('manage_others_bookings') ){
						if( $messages_sent && !$messages_failed ){
							//total success
							$msg = sprintf( esc_html__('Successfully sent %d email(s).', 'events-manager-emails'), $messages_sent );
							$EM_Notices->add_confirm( $msg );
						}elseif( $messages_sent && $messages_failed ){
							$msg = sprintf( esc_html__('Successfully sent %d email(s), however, %d could not be sent.', 'events-manager-emails'), $messages_sent, $messages_failed );
							$EM_Notices->add_info($msg);
						}else{
							//total failure
							$fail_msg = sprintf( esc_html__('Email sending failed. Please see error messages below:', 'events-manager-emails'), $messages_sent );
						}
						if( $messages_failed ){
							if( !$fail_msg ){
								$fail_msg =  sprintf( esc_html__('We encountered errors sending emails. Please see error messages below:', 'events-manager-emails'), $messages_sent );
							}
							$error_list = '<ul>';
							foreach( $email_errors as $error ){
								$error_list .= '<li>'.esc_html($error).'</li>';
							}
							$error_list .= '</ul>';
							$EM_Notices->add_error( array($fail_msg, $error_list) );
						}
					}else{
						// insert it all now
						if( !empty($inserts) ) {
							$insert_result = $wpdb->query('INSERT INTO '. EM_EMAIL_QUEUE_TABLE .' (event_id, booking_id, batch_id, email, subject, body) VALUES '. implode(',', $inserts));
							if( $insert_result !== false ){
								$msg = sprintf( esc_html__('Successfully queued %d email(s) for sending. These will be sent within a few minutes.', 'events-manager-emails'), $insert_result );
								$EM_Notices->add_confirm($msg);
							}else{
								if( current_user_can('manage_others_bookings') ){
									$fail_msg = sprintf( esc_html__('Email sending failed. Please see error messages below:', 'events-manager-emails'), $messages_sent );
									$fail_msg .= '<br>'. $wpdb->last_error;
								}else{
									$fail_msg = sprintf( esc_html__('Email sending failed. Please contact site admins for assistance.', 'events-manager-emails'), $messages_sent );
								}
								$EM_Notices->add_error( $fail_msg );
							}
						}else{
							$EM_Notices->add_error('No bookings to email according to chosen options.', 'events-manager-emails');
						}
					}
				}else{
					$EM_Notices->add_error('No bookings to email according to chosen options.', 'events-manager-emails');
				}
			}
		}
	}
	
	public static function get_filters(){
		global $EM_Notices; /* @var \EM_Notices $EM_Notices */
		$booking_statuses = array();
		$ticket_types = array();
		if( empty($_POST['booking_status']) ){
			$EM_Notices->add_error('Please select at least one booking status to send emails to.', 'events-manager-emails');
		}else{
			foreach($_POST['booking_status'] as $status ){
				$booking_statuses[] = absint($status);
			}
		}
		if( empty($_POST['ticket_types']) ){
			$EM_Notices->add_error('Please select bookings containing at least one ticket type to send emails to.', 'events-manager-emails');
		}else{
			foreach($_POST['ticket_types'] as $ticket_id ){
				$ticket_types[] = absint($ticket_id);
			}
		}
		return array( $booking_statuses, $ticket_types );
	}
	
	public static function estimate(){
		global $EM_Notices; /* @var \EM_Notices $EM_Notices */
		if( !empty($_POST['email_event_bookings_estimate']) && !empty($_POST['event_id']) && wp_verify_nonce($_POST['email_event_bookings_estimate'],'email_event_bookings_'.$_POST['event_id']) ){
			list($booking_statuses, $ticket_types) = self::get_filters();
			if( !empty($booking_statuses) && !empty($ticket_types) ){
				//get bookings of a certain status
				global $wpdb;
				$EM_Event = em_get_event($_POST['event_id']);
				$sql = 'SELECT booking_id FROM '.EM_BOOKINGS_TABLE.' WHERE booking_status IN ('. implode(',', $booking_statuses) .') AND booking_id IN (SELECT booking_id FROM '.EM_TICKETS_BOOKINGS_TABLE.' WHERE ticket_id IN ('. implode(',', $ticket_types) .')) AND event_id='.absint($EM_Event->event_id);
				$booking_ids = $wpdb->get_col($sql);
				if( $booking_ids !== false ){
					$emails_sent = array();
					foreach( $booking_ids as $booking_id ){
						$EM_Booking = new EM_Booking($booking_id);
						$email = $EM_Booking->get_person()->user_email;
						if( !empty($_POST['avoid_duplicates']) && in_array($email, $emails_sent) ) continue;
						$emails_sent[] = $email .' - '. $EM_Booking->get_person()->get_name(). ' (<a href="'.$EM_Booking->get_admin_url().'">'. esc_html__emp('view') .'</a>)';
						sort($emails_sent);
					}
					$string = '<p>'. sprintf(esc_html__('We would email %d emails.', 'em-pro'), count($emails_sent));
					$string .= ' <a href="#" id="em-event-emails-preview-trigger-show">'. esc_html__('View Recipients', 'em-pro').'</a>';
					$string .= '<a href="#" id="em-event-emails-preview-trigger-hide" style="display:none;">'. esc_html__('Hide Recipients', 'em-pro').'</a>';
					$string .= '</p><p id="em-event-emails-preview-emails" style="display:none;">'.implode('<br>', $emails_sent);
					$EM_Notices->add_confirm( $string );
				}
			}
			echo $EM_Notices;
		}
		die();
	}
	
	public static function get_attachments_dir(){
		return array(
			'path' => static::$attachments_directory,
			'error' => false,
			'url' => '', // not needed for this hack
			'subdir' => static::$attachments_directory,
			'basedir' => static::$attachments_directory,
			'baseurl' => '', // not needed for this hack
		);
	}
	
	/**
	 * Adds an add send email button to admin pages
	 */
	public static function event_booking_options_buttons(){
		global $EM_Event;
		?> <a href="<?php echo em_add_get_params($EM_Event->get_bookings_url(), array('action'=>'email_attendees','event_id'=>$EM_Event->event_id)); ?>" class="button button-secondary"><?php esc_html_e('Send Emails','events-manager-email'); ?></a><?php
	}
	
	/**
	 * Adds a link to send attendee emails in admin pages
	 */
	public static function event_booking_options(){
		global $EM_Event;
		?><a href="<?php echo em_add_get_params($EM_Event->get_bookings_url(), array('action'=>'email_attendees','event_id'=>$EM_Event->event_id)); ?>"><?php esc_html_e('Send Emails','events-manager-email'); ?></a><?php
	}
	
	/**
	 * Generates the Email options page for an event booking admin
	 */
	public static function email_page(){
		global $EM_Notices, $EM_Event;
		if( !is_object($EM_Event) ) { return; }
		$EM_Booking = new EM_Booking();
		/* Taken from EM settings page */
		$events_placeholders = '<a href="'.EM_ADMIN_URL .'&amp;page=events-manager-help#event-placeholders">'. __('Event Related Placeholders','events-manager') .'</a>';
		$locations_placeholders = '<a href="'.EM_ADMIN_URL .'&amp;page=events-manager-help#location-placeholders">'. __('Location Related Placeholders','events-manager') .'</a>';
		$bookings_placeholders = '<a href="'.EM_ADMIN_URL .'&amp;page=events-manager-help#booking-placeholders">'. __('Booking Related Placeholders','events-manager') .'</a>';
		$bookings_placeholder_tip = " ". sprintf(__('This accepts %s, %s and %s placeholders.','events-manager'), $bookings_placeholders, $events_placeholders, $locations_placeholders);
		$header_button_classes = is_admin() ? 'page-title-action':'button add-new-h2';
		
		$subject = !empty($_POST['subject']) ? $_POST['subject'] : get_option('dbem_email_bookings_default_subject');
		$message = !empty($_POST['message']) ? $_POST['message'] : get_option('dbem_email_bookings_default_body');
		?>
		<div class='wrap'>
			<?php if( is_admin() ): ?>
				<h1 class="wp-heading-inline"><?php esc_html_e('Email Attendees','events-manager-emails'); ?></h1>
				<a href="<?php echo esc_url($EM_Event->get_bookings_url()); ?>" class="<?php echo $header_button_classes; ?>"><?php echo esc_html(sprintf(__('Go back to &quot;%s&quot; bookings','em-pro'), $EM_Event->name)) ?></a>
				<hr class="wp-header-end" />
				<?php $h = 'h2'; ?>
			<?php else: ?>
				<h2>
					<?php esc_html_e('Email Attendees','events-manager-emails'); ?>
					<a href="<?php echo esc_url($EM_Event->get_bookings_url()); ?>" class="<?php echo $header_button_classes; ?>"><?php echo esc_html(sprintf(__('Go back to &quot;%s&quot; bookings','em-pro'), $EM_Event->name)) ?></a>
				</h2>
				<?php $h = 'h3'; ?>
			<?php endif; ?>
			<?php if( !is_admin() ) echo $EM_Notices; ?>
			<div>
				<p><strong><?php esc_html_e('Event Name','events-manager'); ?></strong> : <?php echo esc_html($EM_Event->event_name); ?></p>
				<p>
					<strong><?php esc_html_e('Availability','events-manager'); ?></strong> :
					<?php echo $EM_Event->get_bookings()->get_booked_spaces() . '/'. $EM_Event->get_spaces() ." ". __('Spaces confirmed','events-manager'); ?>
					<?php if( get_option('dbem_bookings_approval_reserved') ): ?>
						, <?php echo $EM_Event->get_bookings()->get_available_spaces() . '/'. $EM_Event->get_spaces() ." ". __('Available spaces','events-manager'); ?>
					<?php endif; ?>
				</p>
				<p>
					<strong><?php esc_html_e('Date','events-manager'); ?></strong> :
					<?php echo $EM_Event->output_dates(false, " - "). ' @ ' . $EM_Event->output_times(false, ' - '); ?>
				</p>
				<p>
					<strong><?php esc_html_e('Location','events-manager'); ?></strong> :
					<?php if( $EM_Event->location_id == 0 ): ?>
					<em><?php esc_html_e('No Location', 'events-manager'); ?></em>
					<?php else: ?>
					<a class="row-title" href="<?php echo admin_url(); ?>post.php?action=edit&amp;post=<?php echo $EM_Event->get_location()->post_id ?>"><?php echo ($EM_Event->get_location()->location_name); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<form action="" method="post" enctype="multipart/form-data" class="em-email-event-bookings">
				<?php echo "<$h>". esc_html__('Email Filters', 'events-manager-emails') ."</$h>"; ?>
				<p><?php esc_html_e('Choose from the set of options below to limit which attendees will receive this email. By default, all users who have an approved/confirmed ticket will receive this message.', 'events-manager-emails'); ?></p>
				<fieldset>
					<legend><?php esc_html_e('Booking Statuses', 'events-manager-emails'); ?></legend>
					<?php
					if( !empty($_POST['booking_status']) ){
						$booking_statuses = $_POST['booking_status'];
					}else{
						$booking_statuses = apply_filters('em_event_booking_emails_status_default', array(1));
					}
					foreach( $EM_Booking->status_array as $status => $label ){
						?>
						<label><input type="checkbox" name="booking_status[]" class="booking-status-filter" value="<?php echo esc_attr($status); ?>" <?php if( in_array($status, $booking_statuses) ) echo 'checked'; ?>> <?php echo esc_html($label); ?></label><br>
						<?php
					}
					?>
				</fieldset>
				<fieldset>
					<legend><?php esc_html_e('Ticket Types', 'events-manager-emails'); ?></legend>
					<?php foreach( $EM_Event->get_tickets() as $EM_Ticket ): /* @var \EM_Ticket $EM_Ticket */ ?>
						<label><input type="checkbox" name="ticket_types[]" class="ticket-types-filter" value="<?php echo esc_attr($EM_Ticket->ticket_id); ?>" <?php if( empty($_POST['ticket_types']) || in_array($EM_Ticket->ticket_id, $_POST['ticket_types']) ) echo 'checked'; ?>> <?php echo esc_html($EM_Ticket->ticket_name); ?></label><br>
					<?php endforeach; ?>
				</fieldset>
				<?php echo "<$h>". esc_html__('Email Content', 'events-manager-emails') ."</$h>"; ?>
				<p><?php esc_html_e('The template below will be sent to all users matching the filters above. You can also add multiple attachments below.', 'events-manager-emails'); ?></p>
				<p><em><?php echo $bookings_placeholder_tip; ?></em></p>
				<p>
					<input name="subject" type="text" class="widefat" placeholder="<?php esc_attr_e('Email Subject', 'events-manager-emails'); ?>" aria-label="<?php esc_attr_e('Email Subject', 'events-manager-emails'); ?>" value="<?php echo esc_attr($subject); ?>">
				</p>
				<p>
					<textarea name="message" type="text" class="widefat" placeholder="<?php esc_attr_e('Email Message', 'events-manager-emails'); ?>" aria-label="<?php esc_attr_e('Email Message', 'events-manager-emails'); ?>"><?php echo esc_html($message); ?></textarea>
				</p>
				<div class="em-uploads-box" data-input-name="email_attachments" data-max="<?php echo wp_max_upload_size(); ?>" data-max-error="<?php echo sprintf(esc_html__('You have exceeded the permitted upload amount, maximum file uploads cannot exceed %s.', 'events-manager'), size_format(wp_max_upload_size())); ?>">
					<div class="dragover-placeholder flex-center"><?php esc_html_e('Drop Files Here', 'events-manager-emails'); ?></div>
					<div class="uploads-box-ui flex-center">
						<p class="upload-item-drop-instructions"><?php esc_html_e('Drop files to upload', 'events-manager-emails'); ?></p>
						<p class="upload-item-drop-instructions"><?php esc_html_e('or', 'events-manager-emails'); ?></p>
						<label class="uploads-box-add button-secondary">
							<?php esc_html_e('Select Files', 'events-manager-emails'); ?>
							<input type="file" class="button-secondary" multiple>
						</label>
					</div>
					<div class="uploads-box"></div>
				</div>
				<p>
					<input type="checkbox" name="avoid_duplicates" value="1" <?php echo !isset($_POST['avoid_duplicates']) || $_POST['avoid_duplicates'] ? 'checked':''; ?>> <?php esc_html_e('Send one message per email address if there are multiple bookings.', 'events-manager-emails'); ?>
				</p>
				<?php if( current_user_can('manage_others_bookings') ): ?>
				<p>
					<input type="checkbox" name="send_direct" value="1" <?php if( !empty($_POST['send_direct']) ) echo 'checked'; ?>> <?php esc_html_e('Send messages immediately and skip queueing, not advised for large list of emails as this could fail during the process but after having sent some emails already.', 'events-manager-emails'); ?>
				</p>
				<?php endif; ?>
				<div class="email-estimation"></div>
				<p>
					<?php
					$estimate = esc_attr__('Estimate', 'events-manager-emails');
					$estimating = esc_attr__('Estimating ...', 'events-manager-emails');
					$confirm = esc_attr__('Confirm and Send', 'events-manager-emails');
					?>
					<button type="submit" class="button-primary submit" data-confirm="<?php echo $confirm; ?>" data-estimating="<?php echo $estimating; ?>" data-estimate="<?php echo $estimate ?>"><?php echo $estimate; ?></button>
					<input type="hidden" name="email_event_bookings" value="<?php echo wp_create_nonce('email_event_bookings_'. $EM_Event->event_id); ?>">
					<input type="hidden" name="event_id" value="<?php echo esc_attr($EM_Event->event_id); ?>">
				</p>
			</form>
		</div>
		<style type="text/css">
			.em-uploads-box { position: relative; display: flex; width:100%; clear:both; border:1px solid #dedede; background-color:#efefef; margin:20px 0; }
			.em-uploads-box .flex-center { display: flex; justify-content: center; align-items: center; flex-direction: column; text-align:center; }
			.em-uploads-box .dragover-placeholder { visibility: hidden; position:absolute; top:-4px; left:-4px; width:100%; height:100%; border:4px dashed #cdcdcd; background-color:#ededed; color:#aaa; font-size:20px; text-align:center; }
			.em-uploads-box.dragover .dragover-placeholder { visibility:visible; z-index:100; }
			.em-uploads-box .uploads-box-ui { width:200px; border-right: 1px solid #cdcdcd; margin:15px; }
			.em-uploads-box .uploads-box-ui p { font-size:12px; margin:8px 0 10px; color:#666666; }
			.em-uploads-box .uploads-box-ui p:first-child { font-size:16px; margin:0 5px; padding:0; }
			.em-uploads-box .uploads-box-ui label.uploads-box-add { position:relative; z-index:1; margin-bottom: 2px; }
			.em-uploads-box .uploads-box-ui label.uploads-box-add input { position: absolute; top:0; left: 0; opacity: 0;  width:100%; height:100%; }
			.em-uploads-box .uploads-box { margin:15px; }
			.em-uploads-box .uploads-box .upload-item { margin:5px 0; color:#666666; font-style: italic; display: flex; align-items: center; }
			.em-uploads-box .uploads-box .upload-item:first-child { margin-top:0; }
			.em-uploads-box .uploads-box .upload-item:last-child { margin-top:0; }
			.em-uploads-box .uploads-box .upload-item span { padding-left:18px; }
			.em-uploads-box .uploads-box .upload-item input { display:none; }
			.em-uploads-box .uploads-box .upload-item a { color:#666; }
			.em-email-event-bookings fieldset { clear:both; margin-bottom: 20px; }
			.em-email-event-bookings fieldset legend { font-weight: bold; float: left; display: inline-block; width:130px; margin:0; padding:0; }
			.em-email-event-bookings fieldset label:first-of-type { margin-left:20px; }
			.em-email-event-bookings fieldset label { float: left; margin-left:150px; display: block; width:400px;}
			.em-email-event-bookings textarea { min-height:300px; }
		</style>
		<script type="text/javascript">
			jQuery(document).ready( function($){
				// size calculator
				let calculateSize = function( uploads ){
					let size = 0;
					uploads.find('.upload-item').each( function(){
						if( this.getAttribute('data-file-size') ) {
							size += parseInt(this.getAttribute('data-file-size'));
						}
					});
					return size;
				}
				// attachments
				$('.em-uploads-box .uploads-box-add').on('change', 'input', function(){
					var el = $(this);
					var uploader = el.closest('label');
					if( this.files.length > 0 ){
						var parent = el.closest('.em-uploads-box');
						// first calculate size to make sure we're ok
						let max_size = parseInt(parent.attr('data-max'));
						let current_size = calculateSize(parent);
						let this_size = 0;
						for( var i = 0; i < this.files.length; i++ ) {
							this_size += this.files[i].size;
						}
						if( this_size + current_size > max_size ){
							alert( parent.attr('data-max-error') );
							return false;
						}
						// proceed if we can uplaod size
						let input_name = parent.attr('data-input-name')+'[]'
						var attachments = parent.find('.uploads-box')
						var attachment_template = $('<p class="upload-item"><a href="#" class="dashicons dashicons-no remove-item"></a><span></span></p>');
						for( var i = 0; i < this.files.length; i++ ) {
							let filename = this.files[i].name.split('\\').pop();
							let attachment = attachment_template.clone();
							attachment.attr('data-file-size', this.files[i].size );
							attachment.attr('data-file-key', i);
							attachment.find('span').html( filename );
							// send input into uploads box so we have a separate input per file
							let file_input = $('<input type="file" name="'+input_name+'">');
							let dt = new DataTransfer()
							dt.items.add(this.files[i]);
							file_input[0].files = dt.files;
							attachment.prepend( file_input );
							attachments.append(attachment);
						}
						let uploader_button = $('<input type="file" multiple>');
						uploader.append( uploader_button );
					}
					$('.em-uploads-box').removeClass('dragover dragovermultiple');
				});
				let uploads_box = $('.em-uploads-box');
				uploads_box.on('click', '.upload-item a.remove-item', function( e ){
					e.preventDefault();
					$(this).closest('.upload-item').remove();
					
				});
				uploads_box.on('dragover dragenter', function(e){
					e.preventDefault();
					e.stopPropagation();
					$(this).addClass('dragover');
				});
				uploads_box.on('dragleave', function(e){
					if( e.relatedTarget.classList.contains('dragover-placeholder') ){
						return true;
					}
					e.preventDefault();
					e.stopPropagation();
					$(this).removeClass('dragover');
				});
				uploads_box.on('drop', function( e ){
					e.preventDefault();
					var fileInput = $(this).find('.uploads-box-add input').first();
					fileInput[0].files = e.originalEvent.dataTransfer.files;
					fileInput.trigger('change');
					$(this).removeClass('dragover');
				});

				// estimation AJAX
				$('.em-email-event-bookings input, .em-email-event-bookings textarea').change( function(){
					var submit = $(this).closest('form').find('button');
					submit.html( submit.data('estimate') );
					$(this).closest('form').find('.email-estimation').empty();
				});
				$('.email-estimation').on('click', '#em-event-emails-preview-trigger-show', function(){
					document.getElementById('em-event-emails-preview-emails').style.display = 'block';
					document.getElementById('em-event-emails-preview-trigger-hide').style.display = 'inline';
					this.style.display = 'none';
					return false;
				}).on('click', '#em-event-emails-preview-trigger-hide', function(){
					document.getElementById('em-event-emails-preview-emails').style.display = 'none';
					document.getElementById('em-event-emails-preview-trigger-show').style.display = 'inline';
					this.style.display = 'none';
					return false;
				});
				$('.em-email-event-bookings button.submit').click( function( e ){
					var submit = $(this);
					if( submit.html() == submit.data('confirm') ){
						return true;
					}
					e.preventDefault();
					submit.html( submit.data('estimating') );
					var avoid_duplicates = $('.em-email-event-bookings input[name=avoid_duplicates]').prop('checked') ? 1:0;
					var data = {
						action : 'email_event_bookings_estimate',
						ticket_types : $.map( $('.em-email-event-bookings input[name="ticket_types[]"]:checked'), function(c){return c.value; }),
						booking_status : $.map( $('.em-email-event-bookings input[name="booking_status[]"]:checked'), function(c){return c.value; }),
						avoid_duplicates : avoid_duplicates,
						email_event_bookings_estimate : $('.em-email-event-bookings input[name=email_event_bookings]').val(),
						event_id : $('.em-email-event-bookings input[name=event_id]').val(),
					};
					$(this).closest('form').find('.email-estimation').load( EM.ajaxurl, data, function(){
						submit.html( submit.data('confirm') );
					});
				});
			});
		</script>
		<?php
	}
	
}
Email_Bookings::init();