<?php
namespace EM\Toolbox;
	
class Past_Events {
	
	public static function init() {
		
		if( get_option('dbem_past_events') !== 'published' ) {
			if( !wp_next_scheduled('emp_cron_process_past_events') ){
				wp_schedule_event( time(), 'em_minute', 'emp_cron_process_past_events');
			}
			add_action('emp_cron_process_past_events', array( static::class, 'process' ) );
			
			// register post status
			if( get_option('dbem_past_events') === 'past' ) {
				add_action( 'init', array( static::class, 'post_status' ) );
				add_filter( 'wp_count_posts', array( static::class, 'wp_count_posts' ), 10, 3 );
				add_action( 'admin_footer-post.php', [ static::class, 'admin_footer_post_js' ] );
				add_action( 'admin_footer-edit.php', [ static::class, 'admin_footer_edit_js' ] );
				add_filter( 'display_post_states', [ static::class, 'display_post_states' ] );
				add_action( 'pre_get_posts', [ static::class, 'admin_future_events_query' ] );
				add_filter('em_get_post_status', [static::class, 'em_get_post_status'], 10, 2);
			}
			add_filter( 'em_event_get_status', array( static::class, 'event_get_status' ), 10, 2 );
		}
	}
	
	public static function process(){
		// get all past published events, 100 at a time
		$args = array( 'scope' => 'past', 'status' => 1, 'limit' => 100, 'recurring' => null, 'private'=> 1 ); // all events inc recurring
		$EM_Events = \EM_Events::get( $args );
		add_filter('em_object_can_manage', '__return_true');
		do {
			foreach ( $EM_Events as $EM_Event ) {
				// double-check event is really past
				$is_past = get_option( 'dbem_events_current_are_past' ) && !$EM_Event->is_recurring( true ) ? $EM_Event->start()->getTimestamp() < time() : $EM_Event->end()->getTimestamp() < time();
				if ( !$is_past ) {
					break;
				}
				// trash or change status
				$status_action = get_option( 'dbem_past_events' );
				if ( $status_action === 'past' ) {
					// change status
					$EM_Event->set_status( 2, true );
					if ( !did_action( 'em_get_post_status' ) ) {
						// backwards compatible change here
						global $wpdb;
						$wpdb->update( $wpdb->posts, array( 'post_status' => 'past' ), array( 'ID' => $EM_Event->post_id ) );
					}
				} elseif ( $status_action === 'trash' ) {
					// trash
					wp_trash_post( $EM_Event->post_id );
				} elseif ( $status_action === 'delete' ) {
					$EM_Event->delete( true );
				}
			}
			$EM_Events = \EM_Events::get( $args );
		} while ( count( $EM_Events ) > 0 );
		remove_filter('em_object_can_manage', '__return_true');
	}
	
	public static function event_get_status( $status, $EM_Event ){
		if( $EM_Event->post_status === 'past' ) {
			$status = $EM_Event->event_status = 2; // give it its own status so we don't confuse with pending/drafts
		}
		return $status;
	}

	public static function em_get_post_status( $post_status, $event_status ){
		if( $event_status === 2 ) {
			$post_status = 'past';
		}
		return $post_status;
	}
	
	public static function post_status(){
		register_post_status( 'past', array(
			'label'                     => emp__( 'Past' ),
			'public'                    => is_admin(),
			'publicly_queryable' 	    => false,
			'protected'                 => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Past <span class="count">(%s)</span>', 'Past <span class="count">(%s)</span>'),
		) );
		register_post_status( 'upcoming', array(
			'label'                     => emp__( 'Future' ),
			'public'                    => is_admin(),
			'publicly_queryable' 	    => false,
			'protected'                 => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Future <span class="count">(%s)</span>', 'Future <span class="count">(%s)</span>'),
		) );
	}

	public static function admin_future_events_query ( $query ) {
		global $pagenow;
		// Define allowed post types
		$allowed_post_types = [ EM_POST_TYPE_EVENT, 'event-recurring' ];

		// Only target the admin, main query, edit.php, future status, and allowed post types
		if ( is_admin() && $query->is_main_query() && $pagenow === 'edit.php' && isset( $query->query_vars['post_type'], $query->query_vars['post_status'] ) && in_array( $query->query_vars['post_type'], $allowed_post_types, true ) && $query->query_vars['post_status'] === 'upcoming' ) {
			// Set to published status
			$query->set( 'post_status', 'publish' );
		}
	}

	/**
	 * Tricks the future posts count because the status isn't really a future post, just a published post in the future.
	 * @param $counts
	 * @param $type
	 * @param $perm
	 *
	 * @return false|mixed
	 */
	public static function wp_count_posts( $counts, $type, $perm ) {
		// we've rejigged this so we just show published events as future events, because a future event will in theory be published vs past being past status
		// leaving old code commented out in case we find this needs reverting for some reason
		// recount future events if we haven't cached a count already
		if ( $type === EM_POST_TYPE_EVENT ) {
			$counts->upcoming = $counts->publish ?? 0;
		}
		return $counts;
	}

	public static function admin_footer_post_js() {
		global $post; // Make sure to get the global post object
		$allowed_post_types = [ EM_POST_TYPE_EVENT, 'event-recurring' ];
		// Check if the post type is yours and its status is 'featured'
		if ( in_array( $post->post_type, $allowed_post_types ) ) {
		     $status = esc_attr__( 'Past' , 'events-manager' );
			?>
			<script>
				document.addEventListener( 'DOMContentLoaded', function () {
					// add our custom post status
					let postStatus = document.getElementById( 'post_status' );
					if ( postStatus ) {
						let option = document.createElement( 'option' );
						option.value = 'past';
						option.text = '<?php echo $status; ?>';
						postStatus.appendChild( option );
					}
					<?php if( 'past' === get_post_status() ) : ?>
					let statusDisplay = document.getElementById( 'post-status-display' );
					if ( statusDisplay ) {
						statusDisplay.textContent = '<?php echo $status; ?>';
					}
					if ( postStatus ) {
						postStatus.value = 'past';
					}
					<?php endif; ?>
				} );
			</script>
			<?php
		}
	}

	public static function admin_footer_edit_js() {
		$screen = get_current_screen();
		// Check if the current screen is the edit screen for your custom post type
		if ( in_array( $screen->id, [ 'edit-'. EM_POST_TYPE_EVENT ] ) ) {
			?>
			<script>
				document.addEventListener( 'DOMContentLoaded', function () {
					document.querySelectorAll( 'select[name="_status"]' ).forEach( function ( select ) {
						let option = document.createElement( 'option' );
						option.value = 'past';
						option.textContent = '<?php esc_attr_e( 'Past' , 'events-manager' ); ?>';
						select.appendChild( option );
					});
				} );
			</script>
			<?php
		}
	}

	public static function display_post_states( $states ) {
		global $post; // Make sure to get the global post object
		// Check if the post type is yours and its status is 'featured'
		if ( in_array( $post->post_type, [ EM_POST_TYPE_EVENT ] ) && 'past' === get_post_status( $post->ID ) ) {
			$states[] = esc_html__( 'Past' , 'events-manager' ); // Replace 'Featured' with your status label
		}
		return $states;
	}

}
Past_Events::init();