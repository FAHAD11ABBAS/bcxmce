<?php
// ==========================================
// ===== Historia Page Functions Start ======
// ==========================================


// ================ Mentor Start ==================

// Add a mentor meta box to the Event post type
function add_mentormeta_box() {
    add_meta_box(
        'mentor-meta-box',
        __('Mentorit', 'text-domain'),
        'mentormeta_box_callback',
        'event',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_mentormeta_box');

// Callback to display the mentor dropdown and "Add another mentor" button
function mentormeta_box_callback($post) {
    global $wpdb;

    // Add a nonce for verification
    wp_nonce_field('save_mentordata', 'mentormeta_nonce');

    // Retrieve the current mentors
    $mentornames = get_post_meta($post->ID, '_mentor_names', true);
    if (!is_array($mentornames)) {
        $mentornames = [];
    }

    // Fetch users with first_name and last_name from the wp_usermeta table, excluding "deactivated" role
    $results = $wpdb->get_results("
    SELECT 
        u.ID AS user_id,
        um1.meta_value AS first_name,
        um2.meta_value AS last_name
    FROM 
        {$wpdb->users} AS u
    INNER JOIN 
        {$wpdb->usermeta} AS um1 ON u.ID = um1.user_id
    INNER JOIN 
        {$wpdb->usermeta} AS um2 ON u.ID = um2.user_id
    INNER JOIN 
        {$wpdb->usermeta} AS um3 ON u.ID = um3.user_id
    WHERE 
        um1.meta_key = 'first_name'
    AND 
        um2.meta_key = 'last_name'
    AND 
        um3.meta_key = 'hdYqL_capabilities' AND um3.meta_value NOT LIKE '%deactivated%'
    ORDER BY 
        um1.meta_value ASC, um2.meta_value ASC
    ");

    // Prepare dropdown options
    $options = '';
    foreach ($results as $row) {
        $full_name = trim($row->first_name . ' ' . $row->last_name);
        if (!empty($full_name)) {
            $options .= '<option value="' . esc_attr($full_name) . '">' . esc_html($full_name) . '</option>';
        }
    }

    // Generate the dropdowns with "Add another mentor" functionality
    echo '<div id="mentordropdowns">';
    foreach ($mentornames as $mentor) {
        echo '<select name="mentornames[]" class="regular-text mentor-dropdown" style="margin-bottom: 10px;">';
        echo '<option value="">' . __('Select mentor', 'text-domain') . '</option>';
        echo str_replace(
            'value="' . esc_attr($mentor) . '"',
            'value="' . esc_attr($mentor) . '" selected="selected"',
            $options
        );
        echo '</select>';
    }
    echo '</div>';

    // "Add another mentor" button
    echo '<button type="button" id="add_mentor_button" class="button">' . __('Lisää Mentori', 'text-domain') . '</button>';

    // Add JavaScript for dynamic dropdown and Select2 initialization
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Select2 on mentor dropdowns
            jQuery('.mentor-dropdown').select2({
                placeholder: "<?php echo esc_js(__('Select mentor', 'text-domain')); ?>",
                allowClear: true
            });

            const addmentorButton = document.getElementById('add_mentor_button');
            const mentorDropdowns = document.getElementById('mentordropdowns');
            const mentorOptions = `<?php echo addslashes($options); ?>`;

            addmentorButton.addEventListener('click', function () {
                const newDropdown = document.createElement('select');
                newDropdown.name = 'mentornames[]';
                newDropdown.className = 'regular-text mentor-dropdown';
                newDropdown.style.marginBottom = '10px';
                newDropdown.innerHTML = `
                    <option value="">Select mentor</option>
                    ${mentorOptions}
                `;
                mentorDropdowns.appendChild(newDropdown);

                // Reinitialize Select2 for the new dropdown
                jQuery('.mentor-dropdown').select2({
                    placeholder: "<?php echo esc_js(__('Select mentor', 'text-domain')); ?>",
                    allowClear: true
                });
            });
        });
    </script>
    <?php
}



// Save the selected mentor names when the post is saved
function save_mentormeta($post_id) {
    // Verify the nonce
    if (!isset($_POST['mentormeta_nonce']) || !wp_verify_nonce($_POST['mentormeta_nonce'], 'save_mentordata')) {
        return;
    }

    // Prevent autosave from overwriting the data
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user’s permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the mentor names
    if (isset($_POST['mentornames']) && is_array($_POST['mentornames'])) {
        $mentornames = array_filter(array_map('sanitize_text_field', $_POST['mentornames']));
        update_post_meta($post_id, '_mentor_names', $mentornames);
    } else {
        delete_post_meta($post_id, '_mentor_names');
    }
}
add_action('save_post', 'save_mentormeta');

// Shortcode to display mentors (Mentorit)
function event_mentors_shortcode() {
    if (!is_singular('event')) return ''; // Ensure it's an event page

    $mentor_names = get_post_meta(get_the_ID(), '_mentor_names', true);
    if (!empty($mentor_names) && is_array($mentor_names)) {
        global $wpdb;
        $mentor_details = [];

        foreach ($mentor_names as $mentor_name) {
            $user_id = $wpdb->get_var($wpdb->prepare("
                SELECT um1.user_id
                FROM {$wpdb->usermeta} AS um1
                INNER JOIN {$wpdb->usermeta} AS um2
                ON um1.user_id = um2.user_id
                WHERE um1.meta_key = 'first_name' AND um2.meta_key = 'last_name'
                AND CONCAT(um1.meta_value, ' ', um2.meta_value) = %s
            ", $mentor_name));

            if ($user_id) {
                $phone_number = get_user_meta($user_id, 'phone_number', true);
                $profile_url = esc_url(add_query_arg('user', get_userdata($user_id)->user_login, get_permalink(get_page_by_path('view-profile'))));
                $linked_name = '<a href="' . $profile_url . '" class="view-profile-button-custom">' . esc_html($mentor_name) . '</a>';
                
                // Wrap name and phone number in separate <p> tags
                $mentor_details[] = '<div class="mentor-detail">';
                $mentor_details[] = '<p class="mentor-name">' . $linked_name . '</p>'; // Name link in a <p>
                if (!empty($phone_number)) {
                    $mentor_details[] = '<p class="mentor-phone">Puh. ' . esc_html($phone_number) . '</p>'; // Phone number in a separate <p>
                }
                $mentor_details[] = '</div>'; // Close mentor-detail div
            } else {
                $mentor_details[] = '<p>' . esc_html($mentor_name) . ' (Käyttäjää ei löytynyt)</p>'; // Indicate user not found
            }
        }
        return implode('', $mentor_details);
    }
    return '<p>Ei mentoreita lisätty.</p>'; // No mentors added
}
add_shortcode('event_mentors', 'event_mentors_shortcode');

// ======================= Mentor Ends =======================



// ================ Responsible Start ==================

// Add a responsible meta box to the Event post type
function add_responsible_meta_box() {
    add_meta_box(
        'responsible-meta-box',
        __('Vastuulliset', 'text-domain'),
        'responsible_meta_box_callback',
        'event',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_responsible_meta_box');

// Callback to display the responsible dropdown and "Add another responsible" button
function responsible_meta_box_callback($post) {
    global $wpdb;

    // Add a nonce for verification
    wp_nonce_field('save_responsible_data', 'responsible_meta_nonce');

    // Retrieve the current responsibles
    $responsible_names = get_post_meta($post->ID, '_responsible_name', true);
    if (!is_array($responsible_names)) {
        $responsible_names = [];
    }

    // Fetch users with first_name and last_name from the wp_usermeta table, excluding "deactivated" role
    $results = $wpdb->get_results(
        "SELECT u.ID AS user_id, um1.meta_value AS first_name, um2.meta_value AS last_name
        FROM {$wpdb->users} AS u
        INNER JOIN {$wpdb->usermeta} AS um1 ON u.ID = um1.user_id
        INNER JOIN {$wpdb->usermeta} AS um2 ON u.ID = um2.user_id
        INNER JOIN {$wpdb->usermeta} AS um3 ON u.ID = um3.user_id
        WHERE um1.meta_key = 'first_name'
        AND um2.meta_key = 'last_name'
        AND um3.meta_key = 'hdYqL_capabilities' AND um3.meta_value NOT LIKE '%deactivated%'
        ORDER BY um1.meta_value ASC, um2.meta_value ASC"
    );

    // Prepare dropdown options
    $options = '';
    foreach ($results as $row) {
        $full_name = trim($row->first_name . ' ' . $row->last_name);
        if (!empty($full_name)) {
            $options .= '<option value="' . esc_attr($full_name) . '">' . esc_html($full_name) . '</option>';
        }
    }

    // Generate the dropdowns with "Add another responsible" functionality
    echo '<div id="responsible_dropdowns">';
    foreach ($responsible_names as $responsible) {
        echo '<select name="responsible_names[]" class="regular-text responsible-dropdown" style="margin-bottom: 10px;">';
        echo '<option value="">' . __('Select responsible', 'text-domain') . '</option>';
        echo str_replace(
            'value="' . esc_attr($responsible) . '"',
            'value="' . esc_attr($responsible) . '" selected="selected"',
            $options
        );
        echo '</select>';
    }
    echo '</div>';

    // "Add another responsible" button
    echo '<button type="button" id="add_responsible_button" class="button">' . __('Lisää Vastuullinen', 'text-domain') . '</button>';

    // Add JavaScript for dynamic dropdown and Select2 initialization
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Select2 on responsible dropdowns
            jQuery('.responsible-dropdown').select2({
                placeholder: "<?php echo esc_js(__('Select responsible', 'text-domain')); ?>",
                allowClear: true
            });

            const addresponsibleButton = document.getElementById('add_responsible_button');
            const responsibleDropdowns = document.getElementById('responsible_dropdowns');
            const responsibleOptions = `<?php echo addslashes($options); ?>`;

            addresponsibleButton.addEventListener('click', function () {
                const newDropdown = document.createElement('select');
                newDropdown.name = 'responsible_names[]';
                newDropdown.className = 'regular-text responsible-dropdown';
                newDropdown.style.marginBottom = '10px';
                newDropdown.innerHTML = `
                    <option value="">Select responsible</option>
                    ${responsibleOptions}
                `;
                responsibleDropdowns.appendChild(newDropdown);

                // Reinitialize Select2 for the new dropdown
                jQuery('.responsible-dropdown').select2({
                    placeholder: "<?php echo esc_js(__('Etsi tai valitse Mentori', 'text-domain')); ?>",
                    allowClear: true
                });
            });
        });
    </script>
    <?php
}


// Include Select2 CSS and JS in the admin panel
function load_select2_assets() {
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'load_select2_assets');


// Save the selected responsible names when the post is saved
function save_responsible_meta($post_id) {
    // Verify the nonce
    if (!isset($_POST['responsible_meta_nonce']) || !wp_verify_nonce($_POST['responsible_meta_nonce'], 'save_responsible_data')) {
        return;
    }

    // Prevent autosave from overwriting the data
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user’s permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the responsible names
    if (isset($_POST['responsible_names']) && is_array($_POST['responsible_names'])) {
        $responsible_names = array_filter(array_map('sanitize_text_field', $_POST['responsible_names']));
        update_post_meta($post_id, '_responsible_name', $responsible_names);
    } else {
        delete_post_meta($post_id, '_responsible_name');
    }
}
add_action('save_post', 'save_responsible_meta');

// Shortcode to display responsibles (Vastuulliset)
function event_responsibles_shortcode() {
    if (!is_singular('event')) return ''; // Ensure it's an event page

    $responsible_names = get_post_meta(get_the_ID(), '_responsible_name', true);
    if (!empty($responsible_names) && is_array($responsible_names)) {
        global $wpdb;
        $responsible_details = [];

        foreach ($responsible_names as $responsible_name) {
            $user_id = $wpdb->get_var($wpdb->prepare("
                SELECT um1.user_id
                FROM {$wpdb->usermeta} AS um1
                INNER JOIN {$wpdb->usermeta} AS um2
                ON um1.user_id = um2.user_id
                WHERE um1.meta_key = 'first_name' AND um2.meta_key = 'last_name'
                AND CONCAT(um1.meta_value, ' ', um2.meta_value) = %s
            ", $responsible_name));

            if ($user_id) {
                $phone_number = get_user_meta($user_id, 'phone_number', true);
                $profile_url = esc_url(add_query_arg('user', get_userdata($user_id)->user_login, get_permalink(get_page_by_path('view-profile'))));
                $linked_name = '<a href="' . $profile_url . '" class="view-profile-button-custom">' . esc_html($responsible_name) . '</a>';
             
                // Wrap name and phone number in separate <p> tags and inside a <div>
                $responsible_details[] = '<div class="responsible-detail">'; // Open responsible-detail div
                $responsible_details[] = '<p class="responsible-name">' . $linked_name . '</p>'; // Name link in a <p>
                if (!empty($phone_number)) {
                    $responsible_details[] = '<p class="responsible-phone">Puh. ' . esc_html($phone_number) . '</p>'; // Phone number in a separate <p>
                }
                $responsible_details[] = '</div>'; // Close responsible-detail div        
            } else {
                $responsible_details[] = '<p>' . esc_html($responsible_name) . '</p>';
            }
        }
        return implode('', $responsible_details);
    }
    return '<p>Ei vastuullisia lisätty.</p>';
}
add_shortcode('event_responsibles', 'event_responsibles_shortcode');

// ================ responsible Field End ==================



// ===================== Add Menus =========================

function theme_register_menus() {
    register_nav_menu('top-menu', __('Top Menu'));
}
add_action('init', 'theme_register_menus');

// ========= To force wordpress to take last version of style.css ==========
function my_theme_enqueue_styles() {
    wp_enqueue_style('main-styles', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');
// =========================================================================
// =========================================================================
// Modify the filter_events_by_place function to handle events by place

// START HERE

// START

function filter_events_by_place() {
    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : null;

    // Fetch all event categories
    $places = get_terms(array(
        'taxonomy' => 'event-categories',
        'hide_empty' => false,
    ));

    // Force "Valtakunnallinen perusohjelma" to be first
    usort($places, function($a, $b) {
        return ($a->name === 'Valtakunnallinen perusohjelma') ? -1 : 1;
    });

    ob_start();

    // Loop through sorted places and display events
    foreach ($places as $place) {
        $args = array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_event_start_date',
            'order' => 'ASC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'event-categories',
                    'field' => 'term_id',
                    'terms' => $place->term_id,
                ),
            ),
            'meta_query' => array(),
        );

        if ($selected_year) {
            $args['meta_query'][] = array(
                'key' => '_event_start_date',
                'value' => array($selected_year . '-01-01', $selected_year . '-12-31'),
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            );
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<h2 id="place-' . esc_attr($place->term_id) . '">' . esc_html($place->name) . '</h2>';
            echo generate_event_table($query);
            wp_reset_postdata();
        }
    }

    echo ob_get_clean();
    wp_die();
}



function filter_events(): void {
    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : null;

    $args = array(
        'post_type' => 'event',
        'posts_per_page' => -1,
        'orderby' => 'meta_value_num',
        'meta_key' => '_event_start_date',
        'order' => 'ASC',
    );

    // Filter by the selected year, if provided
    if ($selected_year) {
        $args['meta_query'] = array(
            array(
                'key' => '_event_start_date',
                'value' => array($selected_year . '-01-01', $selected_year . '-12-31'),
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            )
        );
    }

    $query = new WP_Query($args);

    // Fetch events grouped by their associated place
    $events_by_place = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $place_terms = get_the_terms(get_the_ID(), 'event-categories');

            if (!$place_terms || is_wp_error($place_terms)) {
                $events_by_place['Ei Paikkaa'][] = get_the_ID();
            } else {
                foreach ($place_terms as $place) {
                    $events_by_place[$place->name][] = get_the_ID();
                }
            }
        }
    }
    wp_reset_postdata();

    // Start preparing the response
    ob_start();

    // Ensure "Valtakunnallinen perusohjelma" is placed first
    if (isset($events_by_place['Valtakunnallinen perusohjelma'])) {
        // Move "Valtakunnallinen perusohjelma" to the top of the list
        $valtakunnallinen_perusohjelma = $events_by_place['Valtakunnallinen perusohjelma'];
        unset($events_by_place['Valtakunnallinen perusohjelma']);
        $events_by_place = ['Valtakunnallinen perusohjelma' => $valtakunnallinen_perusohjelma] + $events_by_place;
    }

    // Generate the event list by place
    if (!empty($events_by_place)) {
        foreach ($events_by_place as $place_name => $event_ids) {
            echo '<h2>' . esc_html($place_name) . '</h2>';

            $place_query_args = array(
                'post_type' => 'event',
                'post__in' => $event_ids,
                'orderby' => 'meta_value_num',
                'meta_key' => '_event_start_date',
                'order' => 'ASC',
            );

            $place_query = new WP_Query($place_query_args);
            echo generate_event_table($place_query);
            wp_reset_postdata();
        }
    } else {
        echo '<p>Tapahtumia ei löytynyt</p>';
    }

    // Fetch all places for the places list
    $places = get_terms(array(
        'taxonomy' => 'event-categories',
        'hide_empty' => false, // Include all places
    ));

    $places_output = '<ul>';
    foreach ($places as $place) {
        // Set up the query arguments for events under this place and year
        $args = array(
            'post_type' => 'event',
            'posts_per_page' => -1, // Only check if at least one event exists
            'tax_query' => array(
                array(
                    'taxonomy' => 'event-categories',
                    'field' => 'term_id',
                    'terms' => $place->term_id,
                ),
            ),
            'meta_query' => array(),
        );

        // Add year filter if a year is selected
        if ($selected_year) {
            $args['meta_query'][] = array(
                'key' => '_event_start_date',
                'posts_per_page' => -1, // Set to -1 to fetch all events
                'value' => array($selected_year . '-01-01', $selected_year . '-12-31'),
                'compare' => 'BETWEEN',
                'type' => 'DATE',
            );
        }

        // Perform the query to check if there are events for this place in the selected year
        $query = new WP_Query($args);

        // Only add the place to the list if events exist
        if ($query->have_posts()) {
            $places_output .= '<li><a href="#" class="place-link historia-filter__link" data-place="'
                . esc_attr($place->term_id) . '">' . esc_html($place->name) . '</a></li>';
        }

        wp_reset_postdata();
    }
    $places_output .= '</ul>';

    wp_send_json(array(
        'events' => ob_get_clean(),
        'places' => $places_output,
    ));

    wp_die();
}


function generate_event_table($query) {
    ob_start();

    // Extract posts from the query
    $posts = $query->posts;

    // Get the current year
    $current_year = date('Y');

    // Sort the posts by the custom meta field '_event_start_date'
    usort($posts, function($a, $b) {
        $date_a = get_post_meta($a->ID, '_event_start_date', true);
        $date_b = get_post_meta($b->ID, '_event_start_date', true);
        
        // Convert dates to timestamps for comparison
        $timestamp_a = $date_a ? strtotime($date_a) : 0;
        $timestamp_b = $date_b ? strtotime($date_b) : 0;

        return $timestamp_a - $timestamp_b; // Ascending order
    });

    // Begin table HTML
    echo '<table class="h_event_table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Päivämäärä</th>';
    echo '<th>Tapahtuma</th>';
    echo '<th>Vastuulliset</th>';
    echo '<th>Mentorit</th>';
    echo '<th>Kuvaus</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if (!empty($posts)) {
        foreach ($posts as $post) {
            setup_postdata($post);

            $start_date = get_post_meta($post->ID, '_event_start_date', true);
            $end_date = get_post_meta($post->ID, '_event_end_date', true);
            $current_date = date('Y-m-d'); // Get today's date in Y-m-d format

            // if (!empty($end_date)) {
            //     if (strtotime($end_date) >= strtotime($current_date)) {
            //         continue; // Skip this event if it hasn't ended yet
            //     }
            // }

            // If start_date and end_date are the same, set end_date to "-"
            $formatted_start_date = ($start_date && strtotime($start_date))
                ? date('d.m.Y', strtotime($start_date))
                : '-';
            $formatted_end_date = ($end_date && strtotime($end_date))
                ? date('d.m.Y', strtotime($end_date))
                : '-';

            $responsible = get_post_meta($post->ID, '_responsible_name', true);
            $mentor = get_post_meta($post->ID, '_mentor_names', true);
            $details = wp_trim_words($post->post_excerpt);

            echo '<tr>';
            echo '<td data-label="Päivämäärä">' . esc_html($formatted_start_date) . '</td>';
            echo '<td data-label="Tapahtuma"><a href="' . esc_url(get_permalink($post->ID)) . '" target="_blank">' . esc_html(get_the_title($post->ID)) . '</a></td>';
            echo '<td data-label="Vastuulliset">';
            if (is_array($responsible)) {
                foreach ($responsible as $name) {
                    echo esc_html($name) . '<br>';
                }
            } else {
                echo esc_html($responsible);
            }
            echo '</td>';
            echo '<td data-label="Mentor">';
            if (is_array($mentor)) {
                foreach ($mentor as $name) {
                    echo esc_html($name) . '<br>';
                }
            } else {
                echo esc_html($mentor);
            }
            echo '</td>';
            echo '<td data-label="Kuvaus">' . esc_html($details) . '</td>';
            echo '</tr>';
        }
        wp_reset_postdata();
    } else {
        echo '<tr><td colspan="5">Tapahtumia ei löytynyt.</td></tr>';
    }

    echo '</tbody>';
    echo '</table>';

    return ob_get_clean();
}




add_action('wp_ajax_filter_events', 'filter_events');
add_action('wp_ajax_nopriv_filter_events', 'filter_events');

add_action('wp_ajax_filter_events_by_place', 'filter_events_by_place');
add_action('wp_ajax_nopriv_filter_events_by_place', 'filter_events_by_place');



// STOP HERE

// STOP
// ENQUEUE STYLESHEET FOR HISTORIA PAGE
function custom_enqueue_styles() {
    // Enqueue the main stylesheet (style.css)
    wp_enqueue_style('main-style', get_stylesheet_uri());

    // Check if we're on the 'historia' page template
    if (is_page_template('historia-page.php')) {
        // Enqueue the historia-specific stylesheet
        wp_enqueue_style('historia-style', get_template_directory_uri() . '/style-historia.css');
    }
}
add_action('wp_enqueue_scripts', 'custom_enqueue_styles');


// ==========================================
// ===== Historia Page Functions End ======
// ==========================================

register_nav_menus(['primary' => 'Menu']);

function mcexec_assets()
{
    wp_enqueue_style('style', get_template_directory_uri()  . '/normalize.css');
    wp_enqueue_style('style', get_stylesheet_uri());
    wp_enqueue_script(
        'mcexec',
        get_template_directory_uri()  . '/script.js',
        array('jquery'),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'mcexec_assets');

function custom_login_redirect($redirect_to, $request, $user) {
    // Check if the user is logged in and is not an admin
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) {
            return admin_url(); // Keep admins in the dashboard
        } else {
            return site_url();
        }
    }

    return $redirect_to; // Default redirect
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

// Disable the default WordPress password reset email
remove_action( 'retrieve_password', 'wp_password_reset_email', 10, 2 );
remove_action( 'lostpassword_post', 'retrieve_password', 10 );

// Customize the password reset email content
function custom_password_reset_email($message, $key) {
    // Get the user object
    $user = get_user_by('login', $key);

    // Check if user is found
    if ($user) {
        $username = $user->user_login;
        $reset_url = site_url("wp-login.php?action=rp&key=$key&login=$username");

        // Customize the email message
        $message = "Hei $username,\n\n";
        $message .= "Olemme vastaanottaneet pyynnön salasanasi palauttamiseksi. Jos tämä oli virhe, voit yksinkertaisesti jättää tämän viestin huomiotta.\n\n";
        $message .= "Klikkaa alla olevaa linkkiä asettaaksesi uuden salasanan:\n";
        $message .= "$reset_url\n\n";
        $message .= "Jos et ole pyytänyt salasanan palautusta, voit jättää tämän viestin huomiotta.\n\n";
        $message .= "Ystävällisin terveisin,\n";
        $message .= "MC-Executors tiimi";
    }

    return $message;
}
add_filter('retrieve_password_message', 'custom_password_reset_email', 10, 2);

// Disable the default password reset email completely
function disable_default_reset_password_email() {
    remove_action( 'retrieve_password', 'wp_password_reset_email', 10, 2 );
}
add_action( 'init', 'disable_default_reset_password_email' );

add_filter('em_booking_get_person_phone', function($phone, $EM_Booking) {
    $user_id = $EM_Booking->person_id;  
    $custom_phone = get_user_meta($user_id, 'phone_number', true);

    if (!empty($custom_phone)) {
        return $custom_phone;
    }
    return $phone;
}, 20, 2);

function nayta_tapahtuman_varaukset( $atts ) {
    // Haetaan nykyisen tapahtuman ID
    global $EM_Event;
    if ( empty( $EM_Event ) || ! is_object( $EM_Event ) ) {
        return '<p>Tämä shortcode toimii vain tapahtumasivulla.</p>';
    }

    // Haetaan varaukset kyseiselle tapahtumalle, mutta vain hyväksytyt (booking_status = 1)
    $bookings = $EM_Event->get_bookings();
    if ( empty( $bookings ) ) {
        return '<p>Ei varauksia tällä hetkellä.</p>';
    }

    // Käytetään assosiatiivista taulukkoa (array) estämään kaksoiskirjaukset
    $attendees = [];
    
    foreach ( $bookings as $booking ) {
        // Tarkistetaan varauksen tila
        if ( $booking->booking_status == 1 ) { // 1 = Hyväksytty varaus
            $person = $booking->get_person();
            
            // Haetaan käyttäjän etu- ja sukunimi
            $first_name = get_user_meta( $person->ID, 'first_name', true );
            $last_name = get_user_meta( $person->ID, 'last_name', true );
            
            // Jos nimet puuttuvat, käytetään varanimiä
            $full_name = trim( ( $first_name ? $first_name : '' ) . ' ' . ( $last_name ? $last_name : '' ) );
            
            if ( empty( $full_name ) ) {
                $full_name = esc_html( $person->display_name );
            }
            
            $attendees[$person->ID] = esc_html( $full_name ); // Tallennetaan vain yksilölliset käyttäjät
        }
    }

    // Jos hyväksyttyjä osallistujia ei ole
    if ( empty( $attendees ) ) {
        return '<p>Ei hyväksyttyjä varauksia tällä hetkellä.</p>';
    }

    // Rakennetaan osallistujalista
    $output = '<div class="event-attendees">';
    $output .= '<h3>Osallistujalista</h3>';
    $output .= '<p>Osallistujia: ' . count($attendees) . '</p>';
    $output .= '<ul>';
    foreach ( $attendees as $attendee ) {
        $output .= '<li>' . $attendee . '</li>';
    }
    $output .= '</ul>';
    $output .= '</div>'; // Close the div
    
    return $output;
}

add_shortcode( 'tapahtuma_varaukset', 'nayta_tapahtuman_varaukset' );




// Change the "Add To Calendar" text in Events Manager to finnish
function modify_events_manager_text( $translated_text, $text, $domain ) {
    if ( $domain === 'events-manager' && $text === 'Add To Calendar' ) {
        return 'Lisää kalenteriin'; // Change this to your desired text
    }
    return $translated_text;
}
add_filter( 'gettext', 'modify_events_manager_text', 10, 3 );


// Close the mobile menu when clicking outside the <ul> or <li> elements
function my_theme_scripts() {
    wp_enqueue_script('custom-script', get_template_directory_uri() . '/js/custom-script.js', array(), null, true);
    
    $inline_script = "
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menu-toggle'); // Reference to the checkbox
            const navi = document.getElementById('navi'); // Reference to the navigation menu

            // Close the menu when clicking outside the <ul> or <li> elements
            navi.addEventListener('click', function(event) {
                if (!event.target.closest('ul') && !event.target.closest('li')) {
                    menuToggle.checked = false; // Uncheck the checkbox to close the menu
                }
            });
        });
    ";
    
    wp_add_inline_script('custom-script', $inline_script);
}
add_action('wp_enqueue_scripts', 'my_theme_scripts');


// Change the excerpt "Read more" text.
function custom_excerpt_more($more) {
    return ' <a href="' . get_permalink() . '"> . . . Lue koko artikkeli!</a>';
}
add_filter('excerpt_more', 'custom_excerpt_more');


// Tulostaosallistujalista on event page custom template (bookings-event-printable.php)
add_filter('em_locate_template', function($template, $template_name, $template_path) {
    $custom_path = get_stylesheet_directory() . '/events-manager/templates/' . $template_name;
    if (file_exists($custom_path)) {
        return $custom_path;
    }
    return $template;
}, 10, 3);


// Osallistujalista export EXCEL version edit to retrieve users phone number. 
// dbem_phone -> phone_number

// One-time migration: Sync current 'phone_number' to 'dbem_phone' for all users
function sync_existing_phone_numbers() {
    // Get all users
    $users = get_users(array('fields' => 'ids')); // Fetch only user IDs

    foreach ($users as $user_id) {
        // Get the phone number from user meta
        $phone_number = get_user_meta($user_id, 'phone_number', true);

        // If the phone_number exists, sync it to dbem_phone
        if ($phone_number) {
            update_user_meta($user_id, 'dbem_phone', $phone_number);
        }
    }
}

// Uncomment the line below to run the migration. Once done, comment it out or remove it to prevent re-running.
// sync_existing_phone_numbers();

// Sync 'phone_number' user meta with 'dbem_phone' in the background
add_action('updated_user_meta', function($meta_id, $user_id, $meta_key, $meta_value) {
    if ($meta_key === 'phone_number') {
        update_user_meta($user_id, 'dbem_phone', $meta_value); // Sync phone_number to dbem_phone
    }
}, 10, 4);

// Remove 'dbem_phone' from the WordPress user profile editor
add_filter('user_contactmethods', function($contact_methods) {
    if (isset($contact_methods['dbem_phone'])) {
        unset($contact_methods['dbem_phone']);
    }
    return $contact_methods;
});

// Hide unnecessary post boxes from "Create new event" admin panel
function remove_unwanted_event_meta_boxes() {
    remove_meta_box('commentstatusdiv', 'event', 'normal'); // Removes Comment Status box
}
add_action('add_meta_boxes', 'remove_unwanted_event_meta_boxes', 20);

function hide_event_attributes_meta_box() {
    echo '<style>#em-event-attributes { display: none !important; }</style>';
}
add_action('admin_head', 'hide_event_attributes_meta_box');


function change_login_menu_item( $items, $args ) {
    if( isset($args->theme_location) && $args->theme_location == 'primary' ) {
        // Check if the user is logged in
        if ( is_user_logged_in() ) {
            // Replace 'Kirjaudu' with 'Kirjaudu ulos'
            $items = str_replace('Kirjaudu', 'Kirjaudu ulos', $items);
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_items', 'change_login_menu_item', 10, 2 );

function enqueue_custom_scripts() {
    wp_enqueue_script('jquery');
    // Your custom scripts, if any
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');



