<?php
namespace EM\Automation\Actions;
use EM_Mailer;

class Email extends Action {
	
	public static $type = 'email';
	public static $supported_contexts = array('event','booking');
	
	// these may be null if there isn't a specific person to email and triggered to email a dynamic 'thing' such as booking, or those booked to an event
	/**
	 * @var string Who to send emails to. This could be 'emails' which denotes a list of emails stored in the 'emails' property, or a relative reference such as 'registrants' for bookigns belonging to an event.
	 */
	public $who = 'emails';
	/**
	 * @var array Array of emails that would receive this action email. Leave empty for relative values such as sending to an event owner, attendees etc.
	 */
	public $emails = array();
	// these must always be present
	public $subject;
	public $message;
	// additional options to an email
	public $reply_to;
	public $reply_to_name;
	public $attachments;
	
	public function __construct($action = array()) {
		if( parent::__construct($action) && !empty($action['data']) ){
			$data = $action['data'];
			if( !empty($data['who']) ) $this->who = $data['who'];
			if( $this->who === 'emails' && !empty($data['emails']) ){
				// this will be either an array of emails (1 or more), or a string represnting a relative email or set of emails to send to (e.g. owners of an event, registrants of booking, etc.)
				$emails = $data['emails'];
				if( !is_array($data['emails']) ){
					// convert to array, split if necessary
					if( strstr(',', $data['emails']) ){
						$emails = explode(',', $data['emails']);
					}else{
						$emails = array($data['emails']);
					}
				}
				// clean the emails up, remove invalid emails
				foreach( $emails as $email ){
					if( is_email($email) ){
						$this->emails[] = $email;
					}
				}
			}
			// check if we're dealing with one or more emails, or instructions on who to relatively send to
			if( !empty($data['subject']) ) $this->subject = $data['subject'];
			if( !empty($data['message']) ) $this->message = $data['message'];
			if( !empty($data['reply_to']) ) $this->reply_to = $data['reply_to'];
			if( !empty($data['attachments']) ) $this->attachments = $data['attachments'];
		}
	}
	
	public static function handle( $object, $action_data = array(), $runtime_data = array() ){
		global $wpdb;
		$action = new Email($action_data);
		$emails = array();
		if( $object instanceof \EM_Event ){
			$EM_Event = $object; /* @var \EM_Event $EM_Event */
			$event_id = $EM_Event->event_id;
			$booking_id = 0;
			$subject = $EM_Event->output($action->subject);
			$message = $EM_Event->output($action->message);
			if( $action->who == 'emails' ){
				// We're directly emailing one or more people
				$emails = $action->emails;
			}elseif( $action->who === 'booking_admins') {
				// same as how EM_Booking->email() determines admin emails, so we need to make a fake booking with the right event attached so that we can allow the filter below to take hold such as with custom emails
				$EM_Booking = new \EM_Booking();
				$EM_Booking->event_id = $EM_Event->event_id;
				$admin_emails = str_replace(' ','',get_option('dbem_bookings_notify_admin'));
				$emails = apply_filters('em_booking_admin_emails', explode(',', $admin_emails), $EM_Booking); //supply emails as array
			}elseif( $action->who === 'owner' ){
				// email event owner
				$emails = array($EM_Event->get_contact()->user_email);
			}
		}elseif( $object instanceof \EM_Booking ) {
			$EM_Booking = $object;
			$event_id = $EM_Booking->get_event()->event_id;
			$booking_id = $EM_Booking->booking_id;
			$subject = $EM_Booking->output($action->subject);
			$message = $EM_Booking->output($action->message);
			if( $action->who == 'emails' ){
				// We're directly emailing one or more people
				$emails = $action->emails;
			}elseif( $action->who === 'booking_admins' ){
				// same as how EM_Booking->email() determines admin emails
				$admin_emails = str_replace(' ','',get_option('dbem_bookings_notify_admin'));
				$emails = apply_filters('em_booking_admin_emails', explode(',', $admin_emails), $EM_Booking); //supply emails as array
			}elseif( $action->who === 'registrant') {
				// the person who booked
				$emails = array($EM_Booking->get_person()->user_email);
			}elseif( $action->who === 'owner' ){
				// email event owner
				$emails = array($EM_Booking->get_event()->get_contact()->user_email);
			}
		}
		if( !empty($emails) ){
			// send the email, at this point we should have a subject, message and email(s) to send to, plus extra options for the general action
			foreach($emails as $email){
				$attachments = !empty($action->attachments) ? $action->attachments : ''; // make not null so to avoid any null errors, in case dbdelta didn't change the previous not null field desc
				$wpdb->insert(EM_EMAIL_QUEUE_TABLE, array('email'=>$email, 'subject'=>$subject, 'body'=>$message, 'attachment'=> $attachments, 'event_id'=> $event_id, 'booking_id'=>$booking_id));
			}
		}
	}
	
	public static function get_name(){
		return esc_html__('Email', 'em-pro');
	}
	
	public static function get_description(){
		return esc_html__('Sends an email to a specific set of addresses or emails related to the passed object (such as the registrant of a booking).', 'em-pro');
	}
}
Email::init();