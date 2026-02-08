<?php
// Function to export user data to CSV
function cum_export_users_to_csv() {
    // Check if the current user has admin privileges
    if (!current_user_can('manage_options')) {
        return;
    }

    // Set the headers to force download the file as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=MC-Executor-Jäsenlista.csv');

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Write BOM (Byte Order Mark) for UTF-8 encoding
    fwrite($output, "\xEF\xBB\xBF"); // Add UTF-8 BOM

    // Define the columns for the CSV file
    $columns = array('Exectuor#', 'Alue', 'Etunimi', 'Sukunimi', 'Käyttäjätunnus', 'Sähköposti', 'Laskutussähköposti', 'Puhelinumero', 'Osoite', 'Postitoimipaikka', 'Postinumero');
    fputcsv($output, $columns);

    // Get all users
    $users = get_users();

    // Loop through each user and output their data
    foreach ($users as $user) {
        // Skip users with the role "deactivated"
        if (in_array('deactivated', $user->roles)) {
            continue;
        }

        // Fetch custom user meta (custom_user_id)
        $custom_user_id = get_user_meta($user->ID, 'custom_user_id', true);

        // Get the department meta for the user
        $department = get_user_meta($user->ID, 'department', true);

        // Get user data fields
        $user_data = array(
            $custom_user_id, // Custom user ID = Exectuor#
            $department,
            $user->first_name,
            $user->last_name,
            $user->user_login,
            $user->user_email,
            get_user_meta($user->ID, 'fennoa_email', true),
            get_user_meta($user->ID, 'phone_number', true),
            get_user_meta($user->ID, 'fennoa_address', true),
            get_user_meta($user->ID, 'fennoa_city', true),
            get_user_meta($user->ID, 'fennoa_postcode', true),
        );

        // Write the user data as a CSV row
        fputcsv($output, $user_data);
    }

    // Close the output stream
    fclose($output);
    exit(); // Important: End the script after CSV download
}

// Hook the export function to 'admin_init', which runs early in the admin lifecycle
function cum_handle_export_request() {
    if (isset($_GET['cum_export_users']) && $_GET['cum_export_users'] == '1') {
        // Call the function to export users as CSV
        cum_export_users_to_csv();
    }
}
add_action('admin_init', 'cum_handle_export_request');

// Create an admin page for exporting users
function cum_export_users_menu() {
    add_users_page('Export Users', 'Lataa käyttäjätiedot', 'manage_options', 'export-users', 'cum_export_users_page');
}
add_action('admin_menu', 'cum_export_users_menu');

// Display the export users page
function cum_export_users_page() {
    ?>
    <div class="wrap">
        <h1>Lataa käyttäjätiedot</h1>
        <p>Lataa käyttäjätiedot CSV-tiedostona napsauttamalla alla olevaa painiketta.</p>
        <form method="get" action="">
            <input type="hidden" name="cum_export_users" value="1" />
            <button type="submit" class="button button-primary">Lataa jäsenlista ja tiedot excel tiedostona</button>
        </form>
    </div>
    <?php
}
?>
