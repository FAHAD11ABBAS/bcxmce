<?php
namespace EM\Toolbox;
use EM_DateTime;

class Limits {
	public static function init(){
		add_filter('em_event_validate_meta', '\EM\Toolbox\Limits::em_event_validate_meta', 100, 2);
	}
	
	/**
	 * @param bool $result
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function em_event_validate_meta( $result, $EM_Event ){
		global $wpdb;
		if( $result ){
			// first we get the limits the user is subject to, so we know what to count
			$limits_default = array('monthly'=>0, 'weekly'=>0, 'daily'=>0, 'recurrences' => 0); // TODO make default all 0
			$limits = get_option('dbem_event_submission_limits', $limits_default);
			$limits = array_merge($limits_default, $limits);
			
			if( is_user_logged_in() || get_option('dbem_events_anonymous_submissions') ){
				$user_id = is_user_logged_in() ? get_current_user_id() : get_option('dbem_events_anonymous_user');
				// get role-based limits
				$role_limits_array = get_option('dbem_event_submission_limits_roles', array());
				$user = new \WP_User( $user_id );
				$precedence = get_option('dbem_event_submission_limits_role_precedence', 0); // 0 = lower, 1 = highest
				foreach( $user->roles as $role ){
					if( !empty($role_limits_array[$role]) ){
						$role_limits = $role_limits_array[$role];
						foreach( array('daily', 'weekly', 'monthly', 'recurrences') as $limit ){
							if( isset($role_limits[$limit]) && $role_limits[$limit] !== '' ){
								if( $role_limits[$limit] == 0 ){ // 0 = no limit, blank = default
									// no limit, so does higher get precedence or not?
									if( $precedence ){
										$limits[$limit] = 0;
									}
								}elseif( $role_limits[$limit] > $limits[$limit] && $limits[$limit] && $precedence ){
									// higher and highest takes precedence
									$limits[$limit] = $role_limits[$limit];
								}elseif( ($role_limits[$limit] < $limits[$limit] || !$limits[$limit]) && !$precedence ){
									// lower but lowest takes precedence
									$limits[$limit] = $role_limits[$limit];
								}
							}
						}
					}
				}
				
				// check how many events user has submitted in the past month, week and day
				if( is_user_logged_in() ){
					$sql = 'SELECT COUNT(event_id) FROM '.EM_EVENTS_TABLE.' WHERE event_owner='. absint($user_id) .' AND event_date_created BETWEEN %s AND %s';
				}else{
					// Join and find email user used. Not reliable as a security measure, site owner must be made aware on settings page.
					$sql = 'SELECT COUNT(event_id) FROM '.EM_EVENTS_TABLE.' e JOIN '. $wpdb->postmeta ." p ON e.post_id=p.post_id WHERE meta_key='_event_owner_email' AND meta_value=%s AND recurrence != 1 AND event_owner=%d";
					$sql = $wpdb->prepare($sql, $EM_Event->event_owner_email, $user_id);
					$sql .= ' AND event_date_created BETWEEN %s AND %s';
				}
				if( get_option('dbem_event_submission_limits_count_recurrences') ){
					$sql .= ' AND recurrence != 1';
				}else{
					$sql .= ' AND recurrence_id IS NULL';
				}
				// get the vars to build search dates
				$EM_DateTime = new EM_DateTime();
				$year = $EM_DateTime->format('Y');
				$month = $EM_DateTime->format('m');
				$day = $EM_DateTime->format('d');
				$last_day = $EM_DateTime->format('t');
				// month
				$month_count = 0;
				if( $limits['monthly'] ){ // anything positive is an integer > 0 or not blank
					$sql_month = $wpdb->prepare($sql, "$year-$month-1 00:00:00", "$year-$month-$last_day 23:59:59");
					$month_count = $wpdb->get_var( $sql_month );
				}
				// day
				$day_count = 0;
				if( $limits['daily'] ){ // anything positive is an integer > 0 or not blank
					$sql_day = $wpdb->prepare($sql, "$year-$month-$day 00:00:00", "$year-$month-$day 23:59:59");
					$day_count = $wpdb->get_var( $sql_day );
				}
				// week
				$week_count = 0;
				if( $limits['weekly'] ){ // anything positive is an integer > 0 or not blank
					$start_of_week = get_option('start_of_week');
					$today_weekday = $EM_DateTime->format('w');
					$weekdays_forward = 6;
					$weekdays_back = 0;
					if( $today_weekday > $start_of_week ) {
						$weekdays_back = $today_weekday - $start_of_week;
						$weekdays_forward = $start_of_week - $today_weekday + 6;
					}elseif( $today_weekday < $start_of_week ){
						$weekdays_back = $today_weekday - $start_of_week + 7;
						$weekdays_forward = $start_of_week - $today_weekday - 1;
					}
					$week_start_datetime = $weekdays_back != 0 ? $EM_DateTime->copy()->sub('P'.$weekdays_back.'D') : $EM_DateTime->copy();
					$week_start_datetime->setTime(0,0,0);
					$week_end_datetime = $weekdays_forward != 0 ? $EM_DateTime->copy()->add('P'.$weekdays_forward.'D') : $EM_DateTime->copy();
					$week_end_datetime->setTime(23,59,59);
					$sql_week = $wpdb->prepare($sql, $week_start_datetime->getDateTime(), $week_end_datetime->getDateTime());
					$week_count = $wpdb->get_var($sql_week);
				}
				
				// account for recurring events, how many events are we creating?
				$events = 1; // default
				if( $EM_Event->is_recurring() && get_option('dbem_event_submission_limits_count_recurrences', 1) ){
					$event_recurrences = $EM_Event->get_recurrence_days(); //Get days where events recur
					$events = count($event_recurrences);
				}
				
				// finally we merge it all together and make sure the user is within the limits
				if( !empty($limits['monthly']) && $month_count + $events > $limits['monthly'] ){
					// over the limit
					$error = esc_html__('You have reached your monthly limit of event submissions.', 'em-pro');
					$error = get_option('dbem_event_submission_limits_error_monthly', $error);
					if( $month_count + 1 <= $limits['monthly'] ){
						// let user know how many they're creating
						$error_more = esc_html__('You can create up to %d events this month.', 'em-pro');
						$error .= ' ' . sprintf($error_more, $limits['monthly'] - $month_count);
					}
					$EM_Event->add_error( $error );
					$result = false;
				}
				if( !empty($limits['weekly']) && $week_count + $events > $limits['weekly'] ){
					// over the limit
					$error = esc_html__('You have reached your weekly limit of event submissions.', 'em-pro');
					$error = get_option('dbem_event_submission_limits_error_weekly', $error);
					if( $week_count + 1 <= $limits['weekly'] ){
						// let user know how many they're creating
						$error_more = esc_html__('You can create up to %d events this week.', 'em-pro');
						$error .= ' ' . sprintf($error_more, $limits['weekly'] - $week_count);
					}
					$EM_Event->add_error( $error );
					$result = false;
				}
				if( !empty($limits['daily']) && $day_count + $events > $limits['daily'] ){
					// over the limit
					$error = esc_html__('You have reached your daily limit of event submissions.', 'em-pro');
					$error = get_option('dbem_event_submission_limits_error_daily', $error);
					if( $day_count + 1 <= $limits['daily'] ){
						// let user know how many they're creating
						$error_more = esc_html__('You can create up to %d events today.', 'em-pro');
						$error .= ' ' . sprintf($error_more, $limits['daily'] - $day_count);
					}
					$EM_Event->add_error( $error );
					$result = false;
				}
			}
			
			// also, validate recurrences throttle
			if( !empty($limits['recurrences']) ){
				if( empty($event_recurrences) ){ // in case we did it earlier
					$event_recurrences = $EM_Event->get_recurrence_sets()->get_recurrence_days(); //Get days where events recur
				}
				$recurrence_count = count($event_recurrences);
				if( $recurrence_count > $limits['recurrences'] ){
					$error = esc_html__('You are trying to create %1$d recurrences with this event. You can create up to %2$d recurrences in a single recurring event.', 'em-pro');
					$error = sprintf($error, $recurrence_count, $limits['recurrences']);
					$EM_Event->add_error( $error );
				}
			}
		}
		
		return $result;
	}
	
}
Limits::init();