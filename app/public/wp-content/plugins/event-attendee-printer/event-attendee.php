<?php
/**
 * Plugin Name: Event Attendee Printer
 * Description: Adds a print button for admins to print attendee lists directly from the event page.
 * Version: 1.0
 * Author: Joonas Hiltunen
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Hook to add the print button inside "Osallistujalista"
add_action('wp_footer', 'move_print_button_to_osallistujalista');

function move_print_button_to_osallistujalista() {
    global $post;

    if (current_user_can('manage_options') && is_singular('event')) {
        // Attempt to get the correct event ID
        $event_id = get_post_meta($post->ID, '_event_id', true); // Try fetching from meta data

        // Fallback: Use Events Manager function if available
        if (empty($event_id) && function_exists('em_get_event')) {
            $event = em_get_event($post->ID, 'post_id');
            if ($event) {
                $event_id = $event->event_id;
            }
        }

       

        // Generate the correct "Tulostusnäkymä" URL
        $pdf_url = admin_url('edit.php?post_type=event&page=events-manager-bookings&action=bookings_report&event_id=' . $event_id);

      

        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var headings = document.querySelectorAll('h3'); 
                
                headings.forEach(function(heading) {
                    if (heading.innerText.includes("Osallistujalista")) {
                        var printButton = document.createElement("a");
                        printButton.innerText = "Tulosta osallistujalista";
                        printButton.className = "print-button";
                        printButton.href = "<?php echo esc_url($pdf_url, null, 'raw'); ?>";
                        printButton.target = "_blank"; // Open in new tab

                        heading.parentNode.appendChild(printButton);
                    }
                });
            });
        </script>

        <?php
    }
}