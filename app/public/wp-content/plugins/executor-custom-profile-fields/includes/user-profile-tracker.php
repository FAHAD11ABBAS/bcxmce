<?php
/*
Plugin Name: User Profile Tracker
Description: Seuraa, milloin käyttäjän profiilia on viimeksi päivitetty, mitä muutettiin ja kuka muutokset teki.
Version: 1.1
Author: Business College Helsinki
*/

// Hook to save the profile update details
function upt_save_last_updated($user_id) {
    // Check if user exists
    if (isset($user_id)) {
        $last_updated = current_time('mysql');
        update_user_meta($user_id, '_profile_last_updated', $last_updated);

        // Get the previous data for comparison
        $user_before_update = get_user_by('id', $user_id);
        $new_data = $_POST;

        // Track changes
        $changes = [];
        foreach ($new_data as $key => $value) {
            if (isset($user_before_update->$key) && $user_before_update->$key !== $value) {
                $changes[$key] = [
                    'old' => $user_before_update->$key,
                    'new' => $value,
                ];
            }
        }
        if (!empty($changes)) {
            update_user_meta($user_id, '_profile_changes', $changes);
        } else {
            delete_user_meta($user_id, '_profile_changes'); // No changes, clean up
        }

        // Track who made the change
        $current_user = wp_get_current_user();
        $changed_by = ($current_user->ID === (int)$user_id) ? 'User' : 'Admin';
        update_user_meta($user_id, '_profile_changed_by', $changed_by);
    }
}
add_action('profile_update', 'upt_save_last_updated');

// Display the "Last Updated" field in the user profile page
function upt_show_last_updated_field($user) {
    $last_updated = get_user_meta($user->ID, '_profile_last_updated', true);
    $changes = get_user_meta($user->ID, '_profile_changes', true);
    $changed_by = get_user_meta($user->ID, '_profile_changed_by', true);

    ?>
    <h3>Viimeisimmän profiilipäivityksen tiedot</h3>
    <table class="form-table">
        <tr>
            <th><label for="last_profile_update">Viimeksi päivitetty</label></th>
            <td>
                <input type="text" name="last_profile_update" id="last_profile_update" value="<?php echo esc_attr($last_updated); ?>" class="regular-text" disabled />
            </td>
        </tr>
        <tr>
            <th><label for="last_profile_changes">Muutokset</label></th>
            <td>
                <?php if ($changes): ?>
                    <ul>
                        <?php foreach ($changes as $field => $change): ?>
                            <li><strong><?php echo esc_html($field); ?>:</strong> <?php echo esc_html($change['old']); ?> → <?php echo esc_html($change['new']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    Ei muutoksia
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="last_profile_changed_by">Muuttaja</label></th>
            <td>
                <input type="text" name="last_profile_changed_by" id="last_profile_changed_by" value="<?php echo esc_attr($changed_by); ?>" class="regular-text" disabled />
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'upt_show_last_updated_field');
add_action('edit_user_profile', 'upt_show_last_updated_field');

// Add the Last Updated field to the user profile in the admin section
function upt_admin_user_column($columns) {
    $columns['last_updated'] = 'Last Updated';
    $columns['changed_by'] = 'Changed By';
    return $columns;
}
add_filter('manage_users_columns', 'upt_admin_user_column');

// Display Last Updated and Changed By data in the users table
function upt_display_last_updated_column($value, $column_name, $user_id) {
    if ($column_name === 'last_updated') {
        $last_updated = get_user_meta($user_id, '_profile_last_updated', true);
        return $last_updated ? $last_updated : 'Never';
    }
    if ($column_name === 'changed_by') {
        $changed_by = get_user_meta($user_id, '_profile_changed_by', true);
        return $changed_by ? $changed_by : 'Unknown';
    }
    return $value;
}
add_action('manage_users_custom_column', 'upt_display_last_updated_column', 10, 3);

// Make the Last Updated and Changed By columns sortable
function upt_sortable_columns($columns) {
    $columns['last_updated'] = 'last_updated';
    $columns['changed_by'] = 'changed_by';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'upt_sortable_columns');