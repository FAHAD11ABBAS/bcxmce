<?php
namespace EM\Waitlist;

class Tickets {
	
	public static function init(){
		// add waitlist ticket text and tweak waitlist statuses
		add_action('em_ticket', '\EM\Waitlist\Tickets::em_ticket', 10, 2);
		// admin stuff
		if( get_option('dbem_waitlists_events') && get_option('dbem_waitlists_events_tickets') ) {
			add_action('em_ticket_edit_form_fields', '\EM\Waitlist\Tickets::em_ticket_edit_form_fields', 10, 2);
			add_action('em_ticket_get_post', '\EM\Waitlist\Tickets::em_ticket_get_post', 10, 2);
		}
	}
	
	/**
	 * Changes waiting list ticket name in case it's displayed anywhere.
	 * @param \EM_Ticket $EM_Ticket
	 * @param array $ticket_data
	 * @return void
	 */
	public static function em_ticket( $EM_Ticket, $ticket_data ){
		if( $EM_Ticket->ticket_id === 0 || $ticket_data === '0' ){
			$EM_Ticket->ticket_name = esc_html__('Waitlist', 'em-pro');
		}
	}
	
	/**
	 * Enabled whilst displaying or processing a booking form with a waitlist approved booking, we assume it's booking a wait-listed person and previous availability check isn't entirely applicable
	 * @param bool $is_available
	 * @param \EM_Ticket $EM_Ticket
	 * @return bool
	 */
	public static function em_ticket_is_available( $is_available, $EM_Ticket ){
		if( \EM_Bookings::$disable_restrictions ){
			// check that this ticket can be waitlist-booked
			if( get_option('dbem_waitlists_events') && get_option('dbem_waitlists_events_tickets') && $EM_Ticket->has_meta('waitlist_excluded') ){
				$is_available = false;
			}
			// further checks only needed if restriction already let us through
			if( $is_available ){
				// reset available value so it forces a refresh
				$EM_Ticket->is_available = null;
				// check if ticket is availble to currently logged in/out person and role
				\EM_Bookings::$disable_restrictions = false;
				$is_available = $EM_Ticket->is_available();
				\EM_Bookings::$disable_restrictions = true;
			}
		}
		return $is_available;
	}
	
	/**
	 * @param $col_count
	 * @param \EM_Ticket $EM_Ticket
	 * @return void
	 */
	public static function em_ticket_edit_form_fields( $col_count, $EM_Ticket ){
		$checked_or_intdeterminate = !empty($EM_Ticket->ticket_meta['waitlist_excluded']) ? 'checked' : ( $EM_Ticket->is_meta_overridden('waitlist_excluded') ? 'intdeterminate' : '' );
		?>
		<div class="ticket-waitlist-excluded inline-inputs em-waitlist-option">
			<label class="em-tooltip" aria-label="<?php esc_attr_e('If checked, this ticket will not be considered as bookable by waiting lists and will become available to those who are elegible to book it.','events-manager'); ?>" class="inline-right"><?php esc_html_e('Exclude from Waitlist?','em-pro') ?></label>
			<input type="checkbox" value="1" name="em_tickets[<?php echo $col_count; ?>][waitlist_excluded]" <?php echo $checked_or_intdeterminate; ?> class="ticket-waitlist-excluded">
		</div>
		<?php
	}
	
	public static function em_ticket_get_post( $EM_Ticket, $post ){
		if( isset($post['waitlist_excluded']) ) {
			$EM_Ticket->ticket_meta['waitlist_excluded'] = !empty($post['waitlist_excluded']);
		}else{
			unset($EM_Ticket->ticket_meta['waitlist_excluded']);
		}
	}
}
Tickets::init();