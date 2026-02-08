<?php
namespace EM\List_Table;

class Form_Fields {
	
	public static function init(){
		add_filter('em_bookings_table_save_default_settings_current', array( static::class, 'em_bookings_table_save_default_settings_current' ), 10, 3);
		add_filter('em_bookings_table_get_current_context', array( static::class, 'em_bookings_table_get_current_context' ), 10, 3);
	}
	
	
	/**
	 * Strips any attendee data from a default view save, and separately saves the column structure based on the attendee form associated with the specific table context.
	 *
	 * @param array $cols
	 * @param string $view
	 * @param EM_Bookings_Table $EM_Bookings_Table
	 *
	 * @return array
	 */
	public static function em_bookings_table_save_default_settings_current( $settings, $EM_Bookings_Table ) {
		// we can save general view to default destination, if any attendee fields are removed, they'll get filtered out during EM_List_Table::_constructor();
		$context = $settings['context'] ?: false;
		if( $context ) {
			// firstly, determine the context of the form we're saving, if it's not an event-based context we can let the core plugin save fields
			// since we include all attendee/booking forms already
			$meta_key = static::get_default_view_meta_key( $EM_Bookings_Table );
			if( $meta_key ) {
				$view = $EM_Bookings_Table->view;
				if ( $view !== 'bookings' ) {
					$cols = $settings['views'][ $view ]['cols'];
				} else {
					$cols = $settings['cols'];
				}
				// get the custom fields for this table and see if there's anything to save
				$attendee_fields = \EM_Attendees_Form::get_bookings_table_fields( $EM_Bookings_Table ); // we do it this way to ensure we have a fresh self::$form_id
				$booking_form_fields = \EM_Booking_Form::get_bookings_table_fields( $EM_Bookings_Table );
				$attendee_cols = array_intersect( $cols, array_keys( $attendee_fields ) );
				$booking_cols = array_intersect( $cols, array_keys( $booking_form_fields ) );
				$has_custom_cols = !empty( $attendee_cols ) || !empty( $booking_cols );
				// remove all booking and attendee fields, save a 'pure' setting and override it case-by-case basis so we have accurate set of fields to show in the view
				$custom_cols = $cols; // the current cols
				$cols = array_diff( $cols, array_keys( $attendee_fields ) );
				$cols = array_diff( $cols, array_keys( $booking_form_fields ) );
				if ( $view !== 'bookings' ) {
					$settings['views'][ $view ]['cols'] = $cols;
				} else {
					$settings['cols'] = $cols;
				}
				// set default view templates
				$view_template = [ 'cols' => false ];
				$context_template = [ 'cols' => false, 'views' => [] ];
				// get current settings based on meta key
				$current_settings = get_user_meta( get_current_user_id(), $EM_Bookings_Table::$basename . $meta_key, true );
				$current_settings = $current_settings ?: [ 'contexts' => [] ];
				// create array, even if we must delete it after
				$current_settings['contexts'][ $context ] = $current_settings['contexts'][ $context ] ?? $context_template;
				// custom context
				if ( $has_custom_cols ) {
					if ( $view && $view !== 'bookings' ) {
						$current_settings['contexts'][ $context ]['views'][ $view ] = $current_settings['contexts'][ $context ]['views'][ $view ] ?? $view_template;
						$current_settings['contexts'][ $context ]['views'][ $view ]['cols'] = $custom_cols;
					} else {
						$current_settings['contexts'][ $context ]['cols'] = $custom_cols;
					}
				} else {
					if ( $view && $view !== 'bookings' ) {
						// remove the view
						unset( $current_settings['contexts'][ $context ]['views'][ $view ] );
					} else {
						// set cols to false so there are no cols
						$current_settings['contexts'][ $context ]['cols'] = false; // no cols but has other stuff
					}
				}
				// delete and clean things up going upwards
				if ( empty( $current_settings['contexts'][ $context ]['views'] ) ) {
					unset( $current_settings['contexts'][ $context ]['views'] );
					if ( empty( $current_settings['contexts'][ $context ]['cols'] ) ) {
						unset( $current_settings['contexts'][ $context ] );
						if ( empty( $current_settings['contexts'] ) ) {
							unset( $current_settings['contexts'] );
							if ( empty( $current_settings['views'] ) ) {
								unset( $current_settings['views'] );
								if ( empty( $current_settings['cols'] ) ) {
									$current_settings = []; // set to delete if exists
								}
							}
						}
					}
				}
				// save or delete settings
				if ( !empty( $current_settings ) ) {
					update_user_meta( get_current_user_id(), $EM_Bookings_Table::$basename . $meta_key, $current_settings );
				} else {
					// we don't have selected attendee cols, so we remove the meta if there is any for this uesr
					delete_user_meta( get_current_user_id(), $EM_Bookings_Table::$basename . $meta_key );
				}
				
				return $settings;
			}
		}
		return $settings;
	}
	
	public static function get_default_view_meta_key ( $EM_Bookings_Table ) {
		$context = $EM_Bookings_Table->context;
		if ( $context == 'event' || $context == 'ticket' ) {
			$meta_key = '_settings_forms'; // default key we save all contexts/views to, and fold it in as a default even for custom events without settings
			// we're in a context linked to an event, so now we determine if we're dealing with a non-default attendee/booking form for the event, if so then we need to just save this event view
			$EM_Event = $EM_Bookings_Table->get_event();
			$attendee_form_id = get_post_meta($EM_Event->post_id, '_custom_attendee_form', true) ?: 0;
			$booking_form_id = get_post_meta($EM_Event->post_id, '_custom_booking_form', true) ?: 0;
			$custom_attendee_form = $attendee_form_id !== 0 && $attendee_form_id !== get_option('em_attendee_form_fields');
			$custom_booking_form = $booking_form_id !== 0 && $booking_form_id !== get_option('em_booking_form_fields');
			if( $custom_attendee_form || $custom_booking_form ) {
				// we have a unique
				$event_id = $EM_Event->event_parent ?? $EM_Event->event_id; // future proofing and translation proofing
				$meta_key .= '-' . $event_id;
			}
		}
		return $meta_key ?? false;
	}
	
	/**
	 * Replaces the current view default columns if any attendee fields are associated with it, such as events or tickets with a specific associated attendee form
	 * @param array $cols
	 * @param string $view
	 * @param EM_Bookings_Table $EM_Bookings_Table
	 *
	 * @return array
	 */
	public static function em_bookings_table_get_current_context( $default_settings, $EM_Bookings_Table ) {
		// we can save general view to default destination, if any attendee fields are removed, they'll get filtered out during EM_List_Table::_constructor();
		if( !empty($default_settings['context']) ) {
			$context = $default_settings['context'];
			// get meta and return if we have a saved value
			$meta_key = static::get_default_view_meta_key( $EM_Bookings_Table );
			if ( $meta_key ) {
				$current_settings = get_user_meta( get_current_user_id(), $EM_Bookings_Table::$basename . $meta_key, true );
				// check the context
				$current_context = $current_settings['contexts'][$context] ?? false;
				if( $current_context ) {
					// check if we're in a view, otherwise merge defaults
					$view = $default_settings['view'] ?? 'bookings';
					if ( $view !== 'bookings' )  {
						if( !empty($current_context['views'][$view]['cols']) ) {
							$default_settings['cols'] = $current_context['views'][ $view ]['cols'];
						}
					} elseif ( !empty($current_context['cols']) ) {
						$default_settings['cols'] = $current_context['cols'];
					}
				}
			}
		}
		return $default_settings;
	}
}
Form_Fields::init();