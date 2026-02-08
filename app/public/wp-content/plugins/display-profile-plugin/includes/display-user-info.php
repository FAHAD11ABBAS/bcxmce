<?php
/*
Plugin Name: Display User Info
Description: A plugin that displays and allows editing of user information on the frontend, including a profile picture, first-aid course checkbox, company, and motorcycle fields.
Version: 1.4
Author: Group Molto Bene
License: GPL2
*/




// Shortcode to Display and Edit User Info
function display_user_info_shortcode() {
    $current_user = wp_get_current_user();
    if (!is_user_logged_in()) {
        return '<p>You need to be logged in to see your profile information.</p>';
    }
    $success_message = '';
    if (isset($_GET['profile_updated']) && $_GET['profile_updated'] == '1') {
        $success_message = '<div id="update-success" class="update-success">Profiili päivitetty onnistuneesti!</div>';
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
        save_custom_user_profile_fields($current_user->ID);

        // Redirect to avoid resubmission and show success message
        wp_redirect(add_query_arg('profile_updated', '1', get_permalink()));
        exit;
    }

$style = "
<style>
    .user-info p,h1, h2, h3, h4, h5, h6 {
        font-family: 'Montserrat', serif;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 15px;
        padding: 20px;
        max-width: 600px;
        margin: 20px auto;
        border: 1px solid #ddd;
        border-radius: 8px;
        text-decoration: none;
        background-color: #f3f3f3;
        box-shadow: 0.1rem 0.2rem 5px #5a6142;
    }

    /* Avatar section */
    .user-avatar {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }

    .user-avatar img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
    }
    .user-avatar .vip-crown {
        position: absolute;
        top: -15px;
        right: 230px;
        font-size: 24px;
        color: gold;
    }

    /* User details and input fields */
    .user-details p {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
        font-size: 12px;
        margin-bottom: 8px;
        padding-left: 0;
    }
    .user-details p strong {
        font-weight: bold;
        color: #555;
    }
    .user-details input[type='text'],
    .user-details select,
    .user-details textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 12px;
        background-color: #fafafa;
        color: #333;
        box-sizing: border-box;
    }

    .user-details input:focus,
    .user-details select:focus,
    .user-details textarea:focus {
        outline: none;
        border-color: #0073aa;
        box-shadow: 0 0 5px rgba(0, 115, 170, 0.3);
    }

    /* Biography section */
    .biography {
        border-top: 1px solid #ddd;
        padding-top: 10px;
        margin-top: 15px;
        font-size: 12px;

    }
    .biography label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
        color: #555;
    }
    .biography textarea {
        resize: vertical;
        min-height: 80px;
        font-family: 'Montserrat', serif;
    }
    .biography .visibility-options h4 {
        font-family: 'Montserrat', serif;
        font-weight: bold;
        font-size: 12px;
        color: #555;
        padding-left: 0;
        text-decoration: underline;
    }

    /* Visibility options */
    .visibility-options {
        border-top: 1px solid #ddd;
        padding-top: 0px;
        margin-top: 15px;
 
    }
    .visibility-options label {
        display: block;
        margin: 5px 0;
        color: #444;
        font-weight: normal;    
    }

    /* Action buttons */
    .update-button,
    .reset-password-button {
        display: inline-block;
        padding: 5px 20px;
        color: #e7e6da;
         width: 12rem;
        font-weight: bold;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 12px;
        margin-top: 15px;
        width: calc(50% - 10px);
        box-sizing: border-box;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
    }
    .reset-password-button {
        background-color: #734e30;
         color: #e7e6da;
    }
    .reset-password-button:hover {
        background-color: #1f2518;
        color: #e2c275;
    }
    .update-button {
        background-color: #e2c275;
         color: #1F2518;
    }
    .update-button:hover {
         background-color: #1f2518;
        color: #e2c275;
    }
    .update-success {
           margin: 20px auto;
        padding: 10px;
        max-width: 600px;
        background-color: #a4ac86;
        color: #5a6142;
        border: 1px solid #5a6142;
        border-radius: 5px;
        font-size: 12px;
        text-align: center;
    }
    .success-message {
        color: #5a6142;
        font-weight: bold;
        margin-top: 20px;
    }
    input[type='submit'] {
        font-family: 'Montserrat', serif;
        padding: 5px 20px;
        background-color: #e2c275;
        color: #1F2518;
        border-radius: 8px;
        font-weight: bold;
        font-size: 12px;
        border: none;
        cursor: pointer;
        font-family: 'Montserrat', serif;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
    }   
    
    #change-password-form h4 {
        font-size: 12px;  
    }
    .cross {
    font-size: 1.5em; 
    color: #000000;
    margin-left: 5px;
    vertical-align: middle;}

</style>
";
  // Retrieve the profile picture, phone number, department, and biographical info
    $profile_picture = get_user_meta($current_user->ID, 'profile_picture', true) ?: get_avatar_url($current_user->ID, ['size' => 100]);
    $honorary_number = get_user_meta($current_user->ID, 'honorary_number', true);
    $phone_number = get_user_meta($current_user->ID, 'phone_number', true);
    $home_address = get_user_meta($current_user->ID, 'fennoa_address', true);
    $zipcode = get_user_meta($current_user->ID, 'fennoa_postcode', true);
    $city = get_user_meta($current_user->ID, 'fennoa_city', true);
    $fennoa_email =get_user_meta($current_user->ID,'fennoa_email',true);
    $live_abroad = get_user_meta($current_user->ID, 'live_abroad', true);
    $country_code = get_user_meta($current_user->ID, 'fennoa_country_code', true);

    $profile_title = get_user_meta($current_user->ID, 'nimitys', true);
    $department = get_user_meta($current_user->ID, 'department', true);
    $company = get_user_meta($current_user->ID, 'company', true);
    $motorcycle = get_user_meta($current_user->ID, 'motorcycle', true);
    $biographical_info=get_user_meta($current_user->ID,'biographical_info',true);
    $custom_user_id = get_user_meta($current_user->ID, 'custom_user_id', true);
    $vip_member_icon= get_user_meta($current_user->ID,'vip_member_icon',true)==='yes';
    $kunniajasen=get_user_meta($current_user->ID,'nimitys',true)==='Kunniajäsen';


    $first_aid= get_user_meta($current_user->ID,'first_aid',true);
    if (!empty($first_aid)) {
        $first_aid = date('d.m.Y', strtotime($first_aid)); 
    }

    $tilanne_koulutus= get_user_meta($current_user->ID,'tilanne_koulutus',true);
    if (!empty($tilanne_koulutus)) {
        $tilanne_koulutus = date('d.m.Y', strtotime($tilanne_koulutus)); 
    }
    $appointed_date= get_user_meta($current_user->ID,'appointed_date',true);
    if (!empty($appointed_date)) {
        $appointed_date = date('Y', strtotime($appointed_date)); 
    }
    

    // Retrieve visibility options
    $hide_email = get_user_meta($current_user->ID, 'hide_email', true);
    $hide_phone_number = get_user_meta($current_user->ID, 'hide_phone_number', true);

    
    ob_start();
    echo $style;
    echo $success_message;
    ?>
    <script>
    // Check if the success message exists and set a timeout to hide it after 5 seconds
    document.addEventListener("DOMContentLoaded", function() {
        const successMessage = document.getElementById("update-success");
        if (successMessage) {
            setTimeout(function() {
                successMessage.style.display = "none";
            }, 5000); // Hide the message after 5 seconds
        }
    });
</script>
    <form method="POST" enctype="multipart/form-data">
        <div class="user-info">
       <a href="<?php echo esc_url(get_permalink(get_page_by_path('kaikki-profiilit'))); ?>"><p class="view-profile-button">Näytä kaikki profiilit</p></a> 
            <div class="user-avatar">
                <img src="<?php echo esc_url($profile_picture); ?>" alt="Profile Picture">
               
                <p><label for="profile_picture">Vaihda profiilikuvaa:</label></p>
                <input type="file" name="profile_picture" id="profile_picture" accept="image/*">
                <?php if ($vip_member_icon): ?>
                        <span class="vip-crown">&#x1F451;</span>
                <?php endif; ?>
            </div>
            
            <div class="user-details">
                <p><strong>Käyttäjänimi:</strong> <?php echo esc_html($current_user->display_name); ?></p>
                <p><strong>Etunimi:</strong> <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" class="regular-text"></p>
                <p><strong>Sukunimi:</strong> <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" class="regular-text"></p>
                <p> <strong>Jäsentyyppi:</strong> <?php echo esc_attr(($profile_title)); ?></strong></p>
                <p><strong>Jäsennumero: <?php echo esc_attr(($custom_user_id)); ?></strong></p>
                <?php if ($kunniajasen):?>
                <p><strong>Kunniajäsennumero:</strong> <?php echo esc_attr($honorary_number);?></p>
                <p><strong> Nimitetty kunniajäseneksi: <?php echo esc_attr($appointed_date); ?> </strong> 
                <?php endif; ?>
                
                <p> <strong>Alue:</strong>  <select name="department" id="department">
                <option value="Pirkanmaa" <?php selected($department, 'Pirkanmaa'); ?>>Pirkanmaa</option>
                    <option value="Pohjanmaa" <?php selected($department, 'Pohjanmaa'); ?>>Pohjanmaa</option>
                    <option value="Päijät-Häme&Kaakkois-Suomi" <?php selected($department, 'Päijät-Häme&Kaakkois-Suomi'); ?>>Päijät-Häme&Kaakkois-Suomi</option>
                    <option value="Uusimaa" <?php selected($department, 'Uusimaa',true); ?>>Uusimaa</option>
                    <option value="Varsinais-Suomi" <?php selected($department, 'Varsinais-Suomi',true); ?>>Varsinais-Suomi</option></select></p>
                <p><strong>Sähköposti:</strong> <input type="text" name="user_email" value="<?php echo esc_attr($current_user->user_email); ?>" class="regular-text"></p>
                <p><strong>Puhelinnumero:</strong> <input type="text" name="phone_number" value="<?php echo esc_attr($phone_number); ?>" class="regular-text"></p>
                <p><strong>Yritys:</strong> <input type="text" name="company" value="<?php echo esc_attr($company); ?>" class="regular-text"></p>
                <p><strong>Moottoripyörä:</strong> <input type="text" name="motorcycle" value="<?php echo esc_attr($motorcycle); ?>" class="regular-text"></p>
                <div class="biography">
                        <label for="biographical_info"> Kerro muille hieman itsestäsi:</label>
                        <textarea id="biographical_info" name="biographical_info" ><?php echo esc_textarea($biographical_info); ?></textarea>    
                <p style="text-decoration: underline;"><strong>Koulutukset</strong></p> 
                  <?php if (!empty($first_aid) && $first_aid !== '01.01.1970') : ?>
                    <p><strong> Ensiapukoulutus suoritettu: <?php echo esc_attr($first_aid); ?> </strong> 
                    <?php endif; ?>
                <?php if (!empty($tilanne_koulutus) && $tilanne_koulutus !== '01.01.1970') : ?>
                <p><strong>Tilannejohtamiskurssi suoritettu: <?php echo esc_attr($tilanne_koulutus); ?> </strong> 
                <?php endif; ?>
                <p style="text-decoration: underline;"><strong>Laskutustiedot</strong></p>
                <p><strong>Laskutus-sähköposti:</strong> <input type="text" name="fennoa_email" value="<?php echo esc_attr($fennoa_email); ?>" class="regular-text"></p>
                <p><strong>Osoite:</strong> <input type="text" name="fennoa_address" value="<?php echo esc_attr($home_address); ?>" class="regular-text"></p>
                <p><strong>Postinumero:</strong> <input type="text" name="fennoa_postcode" value="<?php echo esc_attr($zipcode); ?>" class="regular-text"></p>
                <p><strong>Postitoimipaikka:</strong> <input type="text" name="fennoa_city" value="<?php echo esc_attr($city); ?>" class="regular-text"></p>
             <!-- Live Abroad Checkbox and Country Code -->
             <div class="live-abroad-section">
    <label>
        <input type="checkbox" name="live_abroad" id="live_abroad" value="1" <?php checked( get_user_meta($current_user->ID, 'live_abroad', true ), '1' ); ?> />
        Laskutusosoitteeni on ulkomailla
    </label>

    <div id="country-code-section" <?php echo (get_user_meta($current_user->ID, 'live_abroad', true) == '1') ? '' : 'style="display:none;"'; ?>>
        <p><strong>Maakoodi:</strong> <input type="text" name="fennoa_country_code" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'fennoa_country_code', true)); ?>" class="regular-text">Kirjoita maan maakoodi ISO 3166-1 alpha-2 -muodossa (esim. FI = Suomi,SE= Ruotsi).Jos epäröit,pyydä jäsenvastaavaa käydä tarkistamassa</p>
    </div>
</div>
        
                <div class="visibility-options">
                    <h4>Profiilin näkyvyysasetukset</h4>
                    <label><input type="checkbox" name="hide_email" value="yes" <?php checked($hide_email, 'yes'); ?>> Piilota sähköpostini muilta käyttäjiltä</label>
                    <label><input type="checkbox" name="hide_phone_number" value="yes" <?php checked($hide_phone_number, 'yes'); ?>> Piilota puhelinnumeroni muilta käyttäjiltä</label>
                    
                </div>
 <!-- Reset Password Button -->
 <button type="button" name="reset_password-button" class="reset-password-button" onclick="toggleChangePasswordForm()">Aseta uusi salasana:</button>

<!-- Update Profile Button -->
<button type="submit" name="update_profile" class="update-button">Päivitä profiilia</button>
</div>
</div>
</form>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
    var liveAbroadCheckbox = document.getElementById('live_abroad');
    var countryCodeSection = document.getElementById('country-code-section');

    // Check initial state
    if (liveAbroadCheckbox.checked) {
        countryCodeSection.style.display = 'block';
    } else {
        countryCodeSection.style.display = 'none';
    }

    // Toggle visibility when checkbox is changed
    liveAbroadCheckbox.addEventListener('change', function() {
        if (this.checked) {
            countryCodeSection.style.display = 'block';
        } else {
            countryCodeSection.style.display = 'none';
        }
    });
});
</script>
<div id="change-password-form" class="user-info" style="display: none; margin-top: 20px;">
        <h4>Vaihda salasanaa</h4>
        <form method="post" action="">
            <?php wp_nonce_field('custom_password_change', 'custom_password_change_nonce'); ?>
            <p><label for="current_password">Nykyinen salasana</label><br><input type="password" name="current_password" id="current_password" required></p>
            <p><label for="new_password">Uusi salasana</label><br><input type="password" name="new_password" id="new_password" required></p>
            <p><label for="confirm_password">Vahvista uusi salasana</label><br><input type="password" name="confirm_password" id="confirm_password" required></p>
            <p><input type="submit" value="Vaihda salasanaa"></p>
        </form>
    </div>

    <script>
function toggleChangePasswordForm() {
    const form = document.getElementById('change-password-form');
    const updateButton = document.querySelector('button[name="update_profile"]');
    const resetButton = document.querySelector('button[name="reset_password-button"]');

    // Check current form display status and toggle
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block'; // Show the form
        updateButton.disabled = true; // Disable update profile button
        resetButton.textContent = 'Peruuta'; // Change button text to "Cancel"
    } else {
        form.style.display = 'none'; // Hide the form
        updateButton.disabled = false; // Re-enable update profile button
        resetButton.textContent = 'Vaihda salasanaa'; // Reset button text to "Change Password"
    }
}

</script>

<?php 
return ob_get_clean();
}
    
add_shortcode('display_user_info', 'display_user_info_shortcode');
// Hide unnecessary fields on the admin profile page





function custom_password_change_form() {
    if (!is_user_logged_in()) {
        echo '<p>You must be logged in to change your password.</p>';
        return;
    }
    $password_change_message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_password_change_nonce'])) {
        if (!wp_verify_nonce($_POST['custom_password_change_nonce'], 'custom_password_change')) {
            echo '<p>Error: Invalid nonce.</p>';
            return;
        }

        $current_user = wp_get_current_user();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Check if the current password is correct
        if (!wp_check_password($current_password, $current_user->user_pass, $current_user->ID)) {
            echo '<p>Error: Current password is incorrect.</p>';
            return;
        }

        // Check if the new passwords match
        if (empty($new_password) || $new_password !== $confirm_password) {
            echo '<p>Error: New passwords do not match or are empty.</p>';
            return;
        }

        // Update the password
        $update_result = wp_update_user(['ID' => $current_user->ID, 'user_pass' => $new_password]);
        if (is_wp_error($update_result)) {
            echo '<p>Error: Failed to update password. Please try again.</p>';
        } else {
            echo '<p>Success: Your password has been updated!</p>';
        }
    }
    // Display the password change form and message
    echo $password_change_message;
    ?>
    <div id="change-password-form" style="display: none; margin-top: 20px;">
        <h4>Vaihda salasanaa</h4>
        <form method="post" action="">
            <?php wp_nonce_field('custom_password_change', 'custom_password_change_nonce'); ?>
            <p><label for="current_password">Nykyinen salasana</label><br><input type="password" name="current_password" id="current_password" required></p>
            <p><label for="new_password">Uusi salasana</label><br><input type="password" name="new_password" id="new_password" required></p>
            <p><label for="confirm_password">Vahvista uusi salasana</label><br><input type="password" name="confirm_password" id="confirm_password" required></p>
            <p><input type="submit" value="Vaihda salasanaa"></p>
        </form>
    </div>

    <button type="button" onclick="toggleChangePasswordForm()">Vaihda salasanaa</button>

    <script>
        function toggleChangePasswordForm() {
            const form = document.getElementById('change-password-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>

    <?php
}
add_shortcode('custom_password_change_form', 'custom_password_change_form');
