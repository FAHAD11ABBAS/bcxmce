<?php
namespace EM\Bookings;
use EM_Booking, EM_Ticket_Booking;
use EM_Person;

/**
 * Handles uploads for bookings, including an endpoint url for retrieving booking uploads, and serving uploaded booking files by verifying user has permission to view the file.
 */
class Uploads {
	
	public static function init() {
		// rule flushes are handled by EM itself, we can just add our endpoints
		add_action( 'em_forms_uploads_file_bookings', array( static::class, 'serve_booking_file' ) );
		add_action( 'em_forms_uploads_file_bookings/attendees', array( static::class, 'serve_attendee_file' ) );
		add_action( 'em_forms_uploads_file_users', array( static::class, 'serve_user_file' ) );
	}
	
	public static function serve_booking_file( $path ) {
		static::serve_file( 'booking', $path );
	}
	
	public static function serve_attendee_file( $path ) {
		static::serve_file( 'attendee', $path );
	}

	public static function serve_user_file( $path ) {
		static::serve_file( 'user', $path );
	}
	
	public static function serve_file( $object, $path ) {
		if ( preg_match( '/^([a-zA-Z0-9\-_]+)\/([a-zA-Z0-9\-_]+)\/([a-zA-Z0-9\-_]+)$/', $path ) ) {
			$path_parts = explode( '/', $path );
			// get booking/attendee UUID, field_id, file UUID, verify requesting user, return if valid
			$uuid = $path_parts[0];
			$field_id = $path_parts[1];
			$file_id = $path_parts[2];
			if ( $object == 'attendee' ) {
				$EM_Ticket_Booking = new EM_Ticket_Booking( $uuid );
				if ( $EM_Ticket_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
					// get the file details
					if ( !empty( $EM_Ticket_Booking->meta[ $field_id ]['files'][ $file_id ] ) ) {
						$file = $EM_Ticket_Booking->meta[ $field_id ]['files'][ $file_id ];
					}
				} else {
					header( "HTTP/1.1 403 Forbidden" );
					exit;
				}
			} elseif ( $object == 'user' ) {
				global $wpdb;
				if ( preg_match('/0_bk_([a-zA-Z0-9]+)$/', $uuid, $match ) ) {
					// no-user booking, get file details from a different method
					$booking_uuid = $match[1];
					$EM_Booking = em_get_booking( $booking_uuid );
					if ( $EM_Booking->can_manage('manage_bookings','manage_others_bookings') ) {
						if ( !empty( $EM_Booking->booking_meta['registration'][$field_id]['files'][$file_id] ) ) {
							$file = $EM_Booking->booking_meta['registration'][$field_id]['files'][$file_id];
						}
					} else {
						header( "HTTP/1.1 403 Forbidden" );
						exit;
					}
				} else {
					$user_id = $wpdb->get_var('SELECT user_id FROM '. $wpdb->usermeta . ' WHERE meta_key="uuid" AND meta_value="'.$uuid.'"');
					$can_access_user = $user_id === get_current_user_id() || current_user_can('manage_others_bookings');
					if ( !$can_access_user ) {
						// check if current user has access to this person
						$EM_Person = new EM_Person( $user_id );
						foreach($EM_Person->get_bookings() as $EM_Booking){
							if($EM_Booking->can_manage('manage_bookings','manage_others_bookings')){
								$can_access_user = true;
							}
						}
					}
					if ( $can_access_user ) {
						// get the file details
						$user = new \WP_User( $user_id );
						if ( !empty( $user->{$field_id}['files'][ $file_id ] ) ) {
							$file = $user->{$field_id}['files'][ $file_id ];
						}
					} else {
						header( "HTTP/1.1 403 Forbidden" );
						exit;
					}
				}
			} else {
				$EM_Booking = new EM_Booking( $uuid );
				if ( $EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
					// get the file details
					if ( !empty( $EM_Booking->booking_meta['booking'][ $field_id ]['files'][ $file_id ] ) ) {
						$file = $EM_Booking->booking_meta['booking'][ $field_id ]['files'][ $file_id ];
					}
				} else {
					header("HTTP/1.1 403 Forbidden");
					exit;
				}
			}
			if ( !empty($file['file']) && file_exists( $file['file'] ) ) {
				header( 'Content-Type: ' . ( function_exists( 'mime_content_type' ) ? mime_content_type( $file['file'] ) : 'application/octet-stream' ) );
				header( 'Content-Disposition: attachment; filename="' . $file['name'] . '"' );
				header( 'Content-Length: ' . filesize( $file['file'] ) );
				readfile( $file['file'] );
				exit;
			}
		}
	}
	
}
Uploads::init();