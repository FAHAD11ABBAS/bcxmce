<?php
/*
Plugin Name: Custom User Management
Description: Lisäosa joka mahdollistaa käyttäjien luonnin ja sisäänkirjautumisen, salasananpalautuksen, jäsentietolistan execl-tiedosto tallennuksen sekä sivujen rajoittamisen vain sisäänkirjautuneille käyttäjille.
Version: 1.0
Author: Business College Helsinki
*/

// Ensure direct access is not allowed
if (!defined('ABSPATH')) {
    exit;
}

require_once('includes/custom-user-management-shortcodes.php');
require_once('includes/custom-user-management-export-user-data.php');
require_once('includes/custom-user-management-restrict-content.php');
require_once('includes/custom-user-management-custom-user-id.php');

function cum_enqueue_styles() {
    wp_enqueue_style('cum-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('wp_enqueue_scripts', 'cum_enqueue_styles');


// Remove default wordpress roles
function cum_remove_default_roles() {
    // Remove the 'Subscriber' role
    remove_role('subscriber');

    // Remove the 'Contributor' role
    remove_role('contributor');

    // Remove the 'Author' role
    remove_role('author');

    // Remove the 'Editor' role
    remove_role('editor');

}
add_action('init', 'cum_remove_default_roles');



// Register a custom role
function cum_register_custom_roles() {
    add_role(
        'kokelas',
        __('Kokelas'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'edit_profile' => true,
            'edit_user' => true, // Enable profile editing
        )
    );

    add_role(
        'jäsen',
        __('Jäsen'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'edit_profile' => true,
            'edit_user' => true, // Enable profile editing
        )
    );

    // "Deactivated" role
    add_role(
        'deactivated',
        __('Deactivated'),
        array(
            'read' => false, // No read permissions
            'edit_posts' => false,
            'delete_posts' => false,
            'edit_profile' => false,
        )
    );

}
add_action('init', 'cum_register_custom_roles');

// Block login for "deactivated" users
function block_deactivated_users_login($user, $username, $password) {
    if (isset($user->roles) && in_array('deactivated', $user->roles)) {
        return new WP_Error('deactivated_user', __('Your account is deactivated.'));
    }
    return $user;
}
add_filter('authenticate', 'block_deactivated_users_login', 20, 3);

// Create admin page menu item 
function cum_add_admin_page() {
    add_menu_page(
        'Näytettävät sivut',
        'Näytettävät sivut',
        'manage_options',
        'cum-user-management',
        'cum_user_management_page',
        'dashicons-hidden',
        100
    );
}
add_action('admin_menu', 'cum_add_admin_page');


// Admin page content
function cum_user_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    $users = get_users();
    $pages = get_pages();
    $restricted_pages = get_option('cum_restricted_pages', array());

    ?>

        <!-- Restricted Pages Selection -->
        <h2>Sivut jotka näkyvät vain sisäänkirjautuneille käyttäjille</h2>
            <form method="post" action="options.php">
            <?php
                // Register settings and sections
                settings_fields('cum_user_management_options');
                do_settings_sections('cum_user_management_options');
            ?>

                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">Valitse sivut</th>
                        <td>
                            <?php foreach ($pages as $page) : ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="cum_restricted_pages[]" value="<?php echo esc_attr($page->ID); ?>"
                                <?php echo in_array($page->ID, (array) $restricted_pages) ? 'checked' : ''; ?>>
                                <?php echo esc_html($page->post_title); ?>
                            </label>
                            <?php endforeach; ?>
                            <p class="description">Valitse sivut jotka haluat näyttää vain sisäänkirjautuneille.</p>
                        </td>
                    </tr>
                </table>
            <?php submit_button(); ?>
            </form>
    </div>
<?php
}

