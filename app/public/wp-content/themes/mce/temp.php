<?php
 // ========= greate Events plugin ===============
 function create_custom_post_type() {
     register_post_type('custom_info',
     array(
         'labels'      => array(
             'name'          => __('Tapahtumat'),
             'singular_name' => __('Tapahtuma'),
             'add_new_item'  => __('Add New Event'),
             'Add New Post'  => __('Add New Event'),
             'edit_item'     => __('Edit Info'),
             'all_items'     => __('Kaikki Tapahtumija'),
            ),
            'public'      => true,
            'has_archive' => true,
            
            'supports'    => array('title', 'editor', 'custom-fields'), // Include fields you want
            'taxonomies'  => array('category'), // This allows standard categories
            'rewrite'     => array('slug' => 'custom-info'),
         
            )
        );
    }
    add_action('init', 'create_custom_post_type');
    
// ========= great Years  ===============
// Register custom taxonomy 'Year'
function register_year_taxonomy() {
    register_taxonomy('year', 'custom_info', array(
        'label' => __('Year'),
        'rewrite' => array('slug' => 'year'),
        'hierarchical' => true,
    ));
    
    // Automatically create terms for each year from 2008 to 2024
    for ($i = 2008; $i <= 2024; $i++) {
        if (!term_exists($i, 'year')) {
            wp_insert_term($i, 'year');
        }
    }

    register_taxonomy(
        'Place',
        'custom_info',
        array(
            'label' => __('Place'),
            'rewrite' => array('slug' => 'custom-category'),
            'hierarchical' => true, // Set to true to have parent/child relationship
        )
    );

    // Add predefined categories
    $categories = array('Valtakunnallinen perusohjelma', 
    'Muut valtakunnalliset', 'Paikkalliset Uusimaa',
     'Paikkallliset Päijät-Häme', 'Paikalliset Pirkanmaa',
      'Paikalliset Pohjanmaa', 'Paikalliset Lounais-Suomi');
    
    foreach ($categories as $category) {
        if (!term_exists($category, 'Place')) {
            wp_insert_term($category, 'Place');
        }
    }
}
add_action('init', 'register_year_taxonomy');

// ================ Taxonomies Ends ==================

// ================ Add Menus ==================

function theme_register_menus() {
    register_nav_menu('top-menu', __('Top Menu'));
}
add_action('init', 'theme_register_menus');

// ========= handle AJAX =================
// Add AJAX action for logged-in and guest users
add_action('wp_ajax_filter_events', 'filter_events');
add_action('wp_ajax_nopriv_filter_events', 'filter_events');

// Fetch and display Organizer in the AJAX table
// Fetch and display Organizer and Event Details in the AJAX table

// ================ add orginaizer names ==============
// ========== Add Organizer Dropdown in Event Post Type ==========

// Define a list of organizer names
function get_organizer_list() {
    return [
        'Sami',
        'Anna',
        'John',
        'Alex',
        'Sara'
    ];
}

// Add metabox for Organizer selection
function add_organizer_metabox() {
    add_meta_box(
        'organizer_metabox',       // ID of the metabox
        __('Mentor Name'),       // Title of the metabox
        'organizer_metabox_callback', // Callback function to display content
        'custom_info',              // Post type
        'side'                      // Location of the metabox
    );
}
add_action('add_meta_boxes', 'add_organizer_metabox');

// Display the organizer dropdown in the metabox
function organizer_metabox_callback($post) {
    // Retrieve the current organizer value if it exists
    $selected_organizer = get_post_meta($post->ID, '_organizer_name', true);
    $organizers = get_organizer_list();
    ?>
    <label for="organizer_name"><?php _e('Select Mentor:', 'text_domain'); ?></label>
    <select name="organizer_name" id="organizer_name" class="postbox">
        <?php foreach ($organizers as $organizer): ?>
            <option value="<?php echo esc_attr($organizer); ?>" <?php selected($selected_organizer, $organizer); ?>>
                <?php echo esc_html($organizer); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Save the selected organizer when the post is saved
function save_organizer_metabox_data($post_id) {
    // Verify nonce and permissions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['organizer_name'])) {
        update_post_meta($post_id, '_organizer_name', sanitize_text_field($_POST['organizer_name']));
    }
}
add_action('save_post', 'save_organizer_metabox_data');

//==============================================================
// ================ add responsible names ==============
// ========== Add responsible Dropdown in Event Post Type ==========

// Define a list of responsible names
function get_responsible_list() {
    return [
        'Meri',
        'Anna',
        'Satu',
        'Oksana',
        'Natali'
    ];
}

// Add metabox for responsible selection
function add_responsible_metabox() {
    add_meta_box(
        'responsible_metabox',       // ID of the metabox
        __('Resbonsible Name'),       // Title of the metabox
        'responsible_metabox_callback', // Callback function to display content
        'custom_info',              // Post type
        'side'                      // Location of the metabox
    );
}
add_action('add_meta_boxes', 'add_responsible_metabox');

// Display the oresponsible dropdown in the metabox
function responsible_metabox_callback($post) {
    // Retrieve the current responsible value if it exists
    $selected_responsible = get_post_meta($post->ID, '_responsible_name', true);
    $responsibles = get_responsible_list();
    ?>
    <label for="responsible_name"><?php _e('Select Responsible:', 'text_domain'); ?></label>
    <select name="responsible_name" id="responsible_name" class="postbox">
        <?php foreach ($responsibles as $responsible): ?>
            <option value="<?php echo esc_attr($responsible); ?>" <?php selected($selected_responsible, $responsible); ?>>
                <?php echo esc_html($responsible); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

// Save the selected organizer when the post is saved
function save_responsible_metabox_data($post_id) {
    // Verify nonce and permissions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['responsible_name'])) {
        update_post_meta($post_id, '_responsible_name', sanitize_text_field($_POST['responsible_name']));
    }
}
add_action('save_post', 'save_organizer_metabox_data');
//==============================================================




// Modify the filter_events function to handle events by place
add_action('wp_ajax_filter_events_by_place', 'filter_events_by_place');
add_action('wp_ajax_nopriv_filter_events_by_place', 'filter_events_by_place');

// ========= To force wordpress to take last version of style.css ==========
function my_theme_enqueue_styles() {
    wp_enqueue_style('main-styles', get_stylesheet_uri(), array(), time());
}
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');
// ============================    ==========================

// Add meta boxes for start and end date
function add_event_date_meta_boxes() {
    add_meta_box(
        'event_date_meta_box', // ID of the meta box
        __('Event Date & Time'), // Title of the meta box
        'event_date_meta_box_callback', // Callback function to display content
        'custom_info', // Post type
        'normal', // Location of the meta box
        'high' // Priority
    );
}
add_action('add_meta_boxes', 'add_event_date_meta_boxes');

// Callback function to display the meta box
function event_date_meta_box_callback($post) {
    // Retrieve existing values
    $start_date = get_post_meta($post->ID, 'start_date', true);
    $end_date = get_post_meta($post->ID, 'end_date', true);
    ?>
    <label for="start_date"><?php _e('Start Date and Time:', 'text_domain'); ?></label>
    <input type="datetime-local" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" />

    <br><br>

    <label for="end_date"><?php _e('End Date and Time:', 'text_domain'); ?></label>
    <input type="datetime-local" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" />
    <?php
}

// Save the start and end date
function save_event_date_meta_box_data($post_id) {
    // Verify nonce and permissions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save the start date
    if (isset($_POST['start_date'])) {
        update_post_meta($post_id, 'start_date', sanitize_text_field($_POST['start_date']));
    }

    // Save the end date
    if (isset($_POST['end_date'])) {
        update_post_meta($post_id, 'end_date', sanitize_text_field($_POST['end_date']));
    }
}
add_action('save_post', 'save_event_date_meta_box_data');

//===========================

function filter_events_by_place() {
    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : null;

    // Fetch all places
    $places = get_terms(array(
        'taxonomy' => 'Place',
        'hide_empty' => false,
    ));

    ob_start();

    // Loop through each place and get events for each one
    foreach ($places as $place) {
        // Display the place name as a heading with a unique ID
        echo '<h2 id="place-' . esc_attr($place->term_id) . '">' . esc_html($place->name) . '</h2>';

        // Set up the query arguments for events under this place and year
        $args = array(
            'post_type' => 'custom_info',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'Place',
                    'field' => 'term_id',
                    'terms' => $place->term_id,
                ),
            ),
            'meta_query' => array(),
            'orderby' => 'meta_value_num',
            'meta_key' => 'start_date',
            'order' => 'ASC',
        );

        // Add year filter if selected
        if ($selected_year) {
            $args['meta_query'][] = array(
                'key' => 'start_date',
                'value' => array($selected_year . '-01-01', $selected_year . '-12-31'),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            );
        }

        $query = new WP_Query($args);

        // Display events table for each place
        echo '<table class="event-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Tapahtumat</th>';
        echo '<th>Kuvaus</th>';
       
        echo '<th>Alku Päivä</th>';
        echo '<th>Loppu Päivä</th>';
        echo '<th>Vastuulliset</th>';
        echo '<th>Mentor</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $start_date = get_post_meta(get_the_ID(), 'start_date', true);
                $end_date = get_post_meta(get_the_ID(), 'end_date', true);
                $formatted_start_date = date('d.m.Y', strtotime($start_date));
                $formatted_end_date = date('d.m.Y', strtotime($end_date));

                $responsible = get_post_meta(get_the_ID(), '_responsible_name', true);
                $organizer = get_post_meta(get_the_ID(), '_organizer_name', true);
                $details = get_the_content();

                echo '<tr>';
                echo '<td>' . esc_html(get_the_title()) . '</td>';
                echo '<td>' . wp_trim_words(esc_html($details), 20, '...') . '</td>';
              
                echo '<td>' . esc_html($formatted_start_date) . '</td>';
                echo '<td>' . esc_html($formatted_end_date) . '</td>';
                echo '<td>' . esc_html($responsible) . '</td>';
                echo '<td>' . esc_html($organizer) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No events found for this place.</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        wp_reset_postdata();
    }

    echo ob_get_clean();
    wp_die();
}

// =================  ==================

function filter_events(): void {
    $selected_year = isset($_POST['year']) ? intval($_POST['year']) : null;
    $selected_place_id = isset($_POST['place_id']) ? intval($_POST['place_id']) : null;

    $args = array(
        'post_type' => 'custom_info',
        'posts_per_page' => -1,
        'meta_key' => 'start_date',
        'orderby' => 'meta_value_num',
        'order' => 'ASC'
    );

    // Filter by year if a year is specified
    if ($selected_year) {
        $args['meta_query'] = array(
            array(
                'key' => 'start_date',
                'value' => array($selected_year . '-01-01', $selected_year . '-12-31'),
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            )
        );
    }

    // Filter by place if a specific place is selected
    if ($selected_place_id) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'Place',
                'field' => 'term_id',
                'terms' => $selected_place_id,
            ),
        );
    }

    $query = new WP_Query($args);
    $events_by_place = [];

    // Group events by their associated places
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $place_terms = get_the_terms(get_the_ID(), 'Place');

            if ($place_terms && !is_wp_error($place_terms)) {
                foreach ($place_terms as $place) {
                    $events_by_place[$place->name][] = array(
                        'title' => get_the_title(),
                        'details' => wp_trim_words(get_the_content(), 20, '...'),
                        'start_date' => date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'start_date', true))),
                        'end_date' => date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'end_date', true))),
                        'responsible' => get_post_meta(get_the_ID(), '_responsible_name', true),
                        'organizer' => get_post_meta(get_the_ID(), '_organizer_name', true)
                    );
                }
            }
        }
    }
    wp_reset_postdata();

    ob_start();

    // Output events grouped by place in a table format
    if (!empty($events_by_place)) {
        foreach ($events_by_place as $place_name => $events) {
            echo '<h2>' . esc_html($place_name) . '</h2>';
            echo '<table class="event-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Tapahtuma</th>';
            echo '<th>Kuvaus</th>';
            echo '<th>Alku Päivä</th>';
            echo '<th>Loppu Päivä</th>';
            echo '<th>Vastuulliset</th>';
            echo '<th>Mentor</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($events as $event) {
                echo '<tr>';
                echo '<td>' . esc_html($event['title']) . '</td>';
                echo '<td>' . esc_html($event['details']) . '</td>';
                echo '<td>' . esc_html($event['start_date']) . '</td>';
                echo '<td>' . esc_html($event['end_date']) . '</td>';
                echo '<td>' . esc_html($event['responsible']) . '</td>';
                echo '<td>' . esc_html($event['organizer']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
    } else {
        echo '<p>No events found for this selection.</p>';
    }

    // Generate the list of places for the sidebar
    $places = get_terms(array(
        'taxonomy' => 'Place',
        'hide_empty' => false,
    ));

    $places_output = '<h3>Paikkat:</h3><ul>';
    foreach ($places as $place) {
        $places_output .= '<li><a href="#" class="place-link" data-place="'
            . esc_attr($place->term_id) . '">' . esc_html($place->name) . '</a></li>';
    }
    $places_output .= '</ul>';

    // Send the output as JSON
    wp_send_json(array(
        'events' => ob_get_clean(),
        'places' => $places_output,
    ));

    wp_die();
}

// ======== wp event manager ===========

