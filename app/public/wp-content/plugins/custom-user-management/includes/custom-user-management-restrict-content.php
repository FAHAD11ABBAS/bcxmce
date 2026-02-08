<?php

// Restrict pages from logged-out users
function cum_register_settings() {
    register_setting('cum_user_management_options', 'cum_restricted_pages', array(
        'type' => 'array',   // Save as array
        'sanitize_callback' => 'cum_sanitize_restricted_pages',
        array(
            'type' => 'array', // Save as array
            'sanitize_callback' => 'cum_sanitize_restricted_pages',
            'default' => array(),
        )
    ));
}
add_action('admin_init', 'cum_register_settings');

// Sanitization callback to ensure values are integers
function cum_sanitize_restricted_pages($input) {
    return array_map('intval', $input);
}

function cum_restrict_pages() {
    if (is_page()) {
        $restricted_pages = get_option('cum_restricted_pages', array());
        
        if (in_array(get_queried_object_id(), $restricted_pages) && !is_user_logged_in()) {
            // Redirect to login page or display a message
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
    }
}
add_action('template_redirect', 'cum_restrict_pages');


// Filter menu items to hide restricted pages from non-logged-in users
function cum_filter_menu_items($items, $args) {
    // Get the restricted pages
    $restricted_pages = get_option('cum_restricted_pages', array());

    // Check if the user is not logged in
    if (!is_user_logged_in() && !empty($restricted_pages)) {
        // Create a new array to store filtered items
        $filtered_items = array();

        // Loop through each item
        foreach ($items as $item) {
            // Check if the menu item is restricted
            if (!in_array($item->object_id, $restricted_pages)) {
                // If not restricted, add to the filtered items array
                $filtered_items[] = $item;
            }
        }

        return $filtered_items;
    }

    return $items;
}
add_filter('wp_nav_menu_objects', 'cum_filter_menu_items', 10, 2);


// Hide the WordPress admin bar for specific roles
function cum_hide_admin_bar() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        // Check if the user has either 'jäsen' or 'kokelas' role
        if (in_array('jäsen', $roles) || in_array('kokelas', $roles)) {
            show_admin_bar(false); // Hide the admin bar
        }
    }
}
add_action('after_setup_theme', 'cum_hide_admin_bar');

// Post category that can be seen by non-logged-in users. The rest of the categories are restricted.
function restrict_category_access_except_public() {
    if (is_single()) {
        $public_category = 'julkinen-etusivu'; // The slug of your public category
        $current_categories = wp_get_post_categories(get_the_ID(), array('fields' => 'slugs'));

        // If the user is not logged in and the post is not in the public category
        if (!is_user_logged_in() && !in_array($public_category, $current_categories)) {
            wp_redirect(wp_login_url(get_permalink())); // Redirect to login page
            exit;
        }
    }
}
add_action('template_redirect', 'restrict_category_access_except_public');


function restrict_search_results_for_non_logged_in_users($query) {
    if (!is_admin() && $query->is_search() && !is_user_logged_in() && $query->is_main_query()) {
        $public_category = 'julkinen-etusivu'; // Allow only posts in the 'julkinen-etusivu' category
        $query->set('category_name', $public_category); // Restrict results to only the public category
    }
}
add_action('pre_get_posts', 'restrict_search_results_for_non_logged_in_users');