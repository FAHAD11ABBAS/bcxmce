<?php 
/* Tämä koodi vielä kehitysvaiheessa */ 


function membership_card_shortcode() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $profile_picture = get_user_meta($current_user->ID, 'profile_picture', true) ?: get_avatar_url($current_user->ID, ['size' => 100]);
        $first_name = $current_user->user_firstname;
        $last_name = $current_user->user_lastname;
        $department = $current_user->department;
        $membership_id = get_user_meta($current_user->ID, 'custom_user_id', true); // Custom user ID
        
        ob_start();
        ?>
        <div id="membership-card" style="width: 350px; border: 2px solid goldenrod; padding: 20px; text-align: center; font-family: Arial, sans-serif; background-color: black; color: goldenrod;">
            <img src="<?php echo esc_url($profile_picture); ?>" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 15px; border: 2px solid goldenrod;">
            <h3 style="margin: 0;"><?php echo esc_html($first_name . ' ' . $last_name); ?></h3>
            <p style="margin: 5px 0; color: goldenrod;">Jäsennumero: <?php echo esc_html($membership_id); ?></p>
            <p style="margin: 5px 0; color: goldenrod;">Alue: <?php echo esc_html($department); ?></p>
            <button onclick="window.print()" style="margin-top: 10px; padding: 10px 20px; background: goldenrod; color: black; border: none; cursor: pointer; border-radius: 5px;">Tulosta jäsenyyskortti</button>
        </div>
        <style>
            @media print {
                body * {
                    visibility: hidden;
                }
                #membership-card, #membership-card * {
                    visibility: visible;
                }
                #membership-card {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 350px;
                    border: 2px solid goldenrod;
                    padding: 20px;
                    background-color: black;
                    color: goldenrod;
                    font-family: Arial, sans-serif;
                }
                #membership-card img {
                    width: 100px;
                    height: 100px;
                    border-radius: 50%;
                    margin-bottom: 15px;
                    border: 2px solid goldenrod;
                }
                #membership-card h3, #membership-card p {
                    color: goldenrod;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    } else {
        return '<p>Please log in to view your membership card.</p>';
    }
}
add_shortcode('membership_card', 'membership_card_shortcode');