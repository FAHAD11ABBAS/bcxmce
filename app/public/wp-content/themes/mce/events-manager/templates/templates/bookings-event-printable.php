<?php
global $EM_Event, $wpdb;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <title><?php echo sprintf(__('Bookings for %s','events-manager'), $EM_Event->name); ?></title>
    <link rel="stylesheet" href="<?php echo EM_DIR_URI; ?>includes/css/events_manager.css" type="text/css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            background-color: #f9f9f9;
        }
        #container {
            max-width: 100%;
            margin: 0;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #333;
            text-align: left;
        }
        #bookings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        #bookings-table th, #bookings-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            background-color: #fff;
        }
        #bookings-table th {
            background-color: #4CAF50;
            color: white;
        }
        #bookings-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        #bookings-table tr:hover {
            background-color: #ddd;
        }
        .total-label {
            font-weight: bold;
            text-align: right;
        }
        .spaces-number {
            font-weight: bold;
            color: #4CAF50;
        }
    </style>
</head>
<body id="printable">
    <div id="container">
        <h1><?php echo sprintf(__('Bookings for %s','events-manager'), $EM_Event->name); ?></h1> 
        <p><?php echo $EM_Event->output("#d #M #Y"); ?></p>
        <p><?php echo $EM_Event->output("#_LOCATION, #_ADDRESS, #_TOWN"); ?></p>   
        <h2><?php _e('Bookings data', 'events-manager');?></h2>
        <table id="bookings-table">
            <tr>
                <th scope='col'><?php _e('Name', 'events-manager')?></th>
                <th scope='col'><?php _e('E-mail', 'events-manager')?></th>
                <th scope='col'><?php _e('Phone number', 'events-manager')?></th> 
                <th scope='col'><?php _e('Spaces', 'events-manager')?></th>
                <th scope='col'><?php _e('Additional Information', 'events-manager')?></th>
            </tr> 
            <?php foreach($EM_Event->get_bookings()->bookings as $EM_Booking) {       
                if( $EM_Booking->booking_status == 1){
            ?>
            <tr>
                <td><?php echo esc_html($EM_Booking->person->get_name()); ?></td> 
                <td><?php echo esc_html($EM_Booking->person->user_email); ?></td>
                <td><?php echo esc_html(get_user_meta($EM_Booking->person_id, 'phone_number', true)); ?></td>
                <td class='spaces-number'><?php echo esc_html($EM_Booking->get_spaces()); ?></td>
                <td>
                    <?php 
                    $booking_id = $EM_Booking->booking_id;
                    $booking_meta_table = $wpdb->prefix . 'em_bookings_meta';

                    // Fetch the booking meta data (including the serialized 'user_name' and other details)
                    $meta_results = $wpdb->get_results(
                        $wpdb->prepare("SELECT meta_key, meta_value FROM $booking_meta_table WHERE booking_id = %d", $booking_id),
                        ARRAY_A
                    );

                    $user_choices = [];

                    if (!empty($meta_results)) {
                        foreach ($meta_results as $meta) {
                            $question = str_replace('_booking|', '', $meta['meta_key']); // Extract question key
                            $value = maybe_unserialize($meta['meta_value']); // Unserialize the value (this will decode the serialized data)

                            // Ensure it's a valid selection
                            if (is_array($value)) {
                                $value = implode(', ', array_map('esc_html', $value));
                            } else {
                                $value = esc_html($value);
                            }

                            // Get the field label
                            $label = $question;

                            // Replace '%f6' with 'ö' and '%e4' with 'ä' in labels
                            $label = str_replace('%f6', 'ö', $label);
                            $label = str_replace('%e4', 'ä', $label);
                            $label = str_replace('%e5', 'å', $label);
                            $label = str_replace('%c5', 'Å', $label);
                            $label = str_replace('%f8', 'ø', $label);
                            $label = str_replace('%c4', 'Ä', $label);
                            $label = str_replace('%d6', 'Ö', $label);

                            
                            // Skip unwanted metadata (like _booking|user_name, previous_status, lang, _manual_booking|by, _registration|em_data_privacy_consent)
                            if (!empty($value) && 
                                !in_array($meta['meta_key'], [
                                    '_registration|user_name',
                                    '_booking|user_name', // Skip user_name meta key
                                    'previous_status', // Skip previous_status meta key
                                    'lang', // Skip lang meta key
                                    '_manual_booking|by', // Skip manualby meta key
                                    '_registration|em_data_privacy_consent' // Skip data privacy consent meta key
                                ]) &&
                                !strpos($value, '@')) { // Skip email addresses
                                $user_choices[] = "<strong>" . esc_html(ucwords(str_replace('_', ' ', $label))) . ":</strong> " . $value;
                            }
                        }
                    }

                    // If user_choices array is not empty, output the user choices
                    echo !empty($user_choices) ? implode('<br>', $user_choices) : __('Ei valintoja', 'events-manager');
                    ?>
                </td>
            </tr>
            <?php }} ?>
            <tr id='booked-spaces'>
                <td colspan='3'>&nbsp;</td>
                <td class='total-label'><?php _e('Booked', 'events-manager')?>:</td>
                <td class='spaces-number'><?php echo esc_html($EM_Event->get_bookings()->get_booked_spaces()); ?></td>
            </tr>
            <tr id='available-spaces'>
                <td colspan='3'>&nbsp;</td>
                <td class='total-label'><?php _e('Available', 'events-manager')?>:</td>  
                <td class='spaces-number'><?php echo esc_html($EM_Event->get_bookings()->get_available_spaces()); ?></td>
            </tr>
        </table>  
    </div>
</body>
</html>
