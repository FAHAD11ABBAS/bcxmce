<?php
/*
Plugin Name: Display Selected User Profile
Description: A plugin that displays a specific user's profile on the frontend based on a user selection from the list page.
Version: 1.1
Author: Group Molto Bene
License: GPL2
*/

// Shortcode to Display Selected User Profile
function display_selected_user_profile_shortcode() {
     //Get the username from the URL 
     $user_name = isset($_GET['user']) ? $_GET['user'] : '';
     //If the 'user' parameter is not provided, return an error message
     if ($user_name=='') {
         return '<p>User profile not found.</p>';
    }

     //Retrieve the user object by username
     $user=get_user_by('login',$user_name);
    // If the user does not exist, display an error message
    if (!$user) {
        return '<p>User profile not found.</p>';
    }

    // Get user meta information
    $profile_picture = get_user_meta($user->ID, 'profile_picture', true) ?: get_avatar_url($user->ID, ['size' => 100]);
    $department = get_user_meta($user->ID, 'department', true);
    $biographical_info = get_user_meta($user->ID, 'biographical_info', true);
    $company = get_user_meta($user->ID,'company',true);
    $motorcycle = get_user_meta($user->ID,'motorcycle',true);
    $custom_user_id= get_user_meta($user->ID,'custom_user_id',true);
    $vip_member_icon= get_user_meta($user->ID,'vip_member_icon',true)==="yes";
    $cross_icon=get_user_meta($user->ID,'cross_icon',true)==="yes";
    $profile_title = get_user_meta($user->ID, 'nimitys', true);
    $honorary_number=get_user_meta($user->ID,'honorary_number',true);
    $appointed_date=get_user_meta($user->ID,'appointed_date',true);
    $kunniajasen=get_user_meta($user->ID,'nimitys',true)==='Kunniajäsen';
    $vip_member_info=get_user_meta($user->ID,'vip_member_info',true);

    //Get first aid and tilannekoulutus:
    $first_aid=get_user_meta($user->ID,'first_aid',true);
    if (!empty($first_aid)) {
        $first_aid = date('d.m.Y', strtotime($first_aid)); 
    }
    $tilanne_koulutus= get_user_meta($user->ID,'tilanne_koulutus',true);
    if (!empty($tilanne_koulutus)) {
        $tilanne_koulutus = date('d.m.Y', strtotime($tilanne_koulutus)); 
    }
// Get the last login timestamp
    $last_login = get_user_meta($user->ID, 'last_login', true);

    // Get visibility settings
    $hide_email = get_user_meta($user->ID, 'hide_email', true) === 'yes';
    $hide_phone_number = get_user_meta($user->ID, 'hide_phone_number', true) === 'yes';

    ob_start();
    ?>
    
    <div class="user-profile">
    <a href="<?php echo esc_url(get_permalink(get_page_by_path('kaikki-profiilit'))); ?>"><p class="view-profile-button">Näytä kaikki profiilit</p></a> 
        <div class="user-avatar">
            <img src="<?php echo esc_url($profile_picture); ?>" alt="<?php echo esc_attr($user->user_login); ?>'s Profile Picture">
            <?php if ($vip_member_icon): ?>
                <span class="vip-crown">&#x1F451;</span>
                <?php endif; ?>
                <?php if ($cross_icon): ?>
                <span class="cross">&#x271D;</span>
                <?php endif; ?>
        </div>
                <?php if ($last_login) : ?>
    <span class="last-login">Viimeksi kirjautuneena: <?php echo esc_html(date('j F, Y', strtotime($last_login))); ?></span>
<?php else : ?>
    <span class="last-login">Ei kirjautumistietoja</span>
<?php endif; ?>
            
        <div class="user-details">
            <h2><?php echo esc_html($user->user_login); ?></h2>
            <?php if (!empty($vip_member_info)) : ?>
                    <div class="biography">
                    <img src="<?php echo plugin_dir_url( __DIR__ ); ?>includes/images/exe-logo-nobackround.png" alt="logo" class="biography-logo">
                        <textarea id="vip_member_info" name="vip_member_info" disabled><?php echo esc_textarea($vip_member_info); ?></textarea>   
                        </div>
                        <?php endif; ?>
            <p><strong>Nimi:</strong> <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></p>
            <p> <strong>Jäsentyyppi:</strong> <?php echo esc_attr(($profile_title)); ?></strong></p>
            <p><strong>Jäsennumero:</strong> <?php echo esc_html($custom_user_id); ?></p>
            <?php if ($kunniajasen) : ?>
                <p><strong>Kunniajäsennumero:</strong> <?php echo esc_attr($honorary_number);?></p>
                <p><strong> Nimitetty kunniajäseneksi: <?php echo esc_attr($appointed_date); ?> </strong> 
            <?php endif; ?>

            <?php if (!$hide_email) : ?>
                <p><strong>Sähköposti:</strong> <?php echo esc_html($user->user_email); ?></p>
            <?php endif; ?>     
            <?php if (!$hide_phone_number) : ?>
                <p><strong>Puhelinnumero:</strong> <?php echo esc_html(get_user_meta($user->ID, 'phone_number', true)); ?></p>
            <?php endif; ?>
            <p><strong>Alue:</strong> <?php echo esc_html($department); ?></p>
            <p><strong>Yritys:</strong> <?php echo esc_html($company);?></p>
            <p><strong>Moottoripyörä:</strong> <?php echo esc_html($motorcycle);?></p>
            <div class="biography">
                <textarea id="biographical_info" name="biographical_info" disabled><?php echo esc_textarea($biographical_info); ?></textarea>
            </div>
            <?php if (!empty($first_aid) && $first_aid !== '01.01.1970') : ?>
                <p><strong>Ensiapukoulutus suoritettu: <?php echo esc_attr($first_aid); ?> </strong> 
                <?php endif; ?>
            <?php if (!empty($tilanne_koulutus) && $tilanne_koulutus !== '01.01.1970') : ?>
                <p><strong>Tilannejohtamiskurssi suoritettu: <?php echo esc_attr($tilanne_koulutus); ?> </strong> 
                <?php endif; ?>
            <?php if (get_current_user_id() === $user->ID) : ?>
                <p><a href="<?php echo esc_url(get_permalink(get_page_by_path('oma-profiilisivu'))); ?>">Edit Profile</a></p>
            <?php endif; ?>

        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('display_selected_user_profile', 'display_selected_user_profile_shortcode');


function display_user_profile_styles() {
    echo "
    <style>
        .user-profile {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background-color: #f9f9f9;
            max-width: 300px;
            margin: 20px auto;
            text-align: center;
        }

        .user-info p {
            font-family: 'Montserrat', serif;
            margin: auto;
        }

        .user-info h1, h2, h3, h4, h5, h6 {
            font-family: 'EB Garamond', serif;
        }

        .user-avatar {
            margin-bottom: 15px;
            text-align:center;
            position: relative;
            display: inline-block;
        }
        .user-avatar img {
            border-radius: 50%;
            width: 100px;
            height: 100px;
            object-fit: cover;

        }
        .user-details h2 {
            font-size: 1.25em;
            margin: 0 0 10px;
        }
        .user-details p {
            margin: 5px 0;
        }
        .biography {
            margin-top: 10px;
        }
        .biography textarea {
            width: 100%;
            height: 80px;
            resize: both;
            font-family: 'Montserrat', serif;
        }
        .view-profile-button {
            display: inline-block;
            padding: 5px 20px;
            color: #1F2518;
            width: 12rem;
            font-weight: bold;
            background-color: #e2c275;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0.1rem 0.2rem 3px #5a6142;
            text-align: center;
            margin: 0 auto; 
            margin-bottom: 10px;

        }
        .view-profile-button:hover {
            background-color: #1F2518;
            color: #e2c275;
        }

        .user-avatar .vip-crown {
            position: absolute;
            top: -15px;
            right: -5px;
            font-size: 24px;
            color: gold;
        }
        .last-login {
            display: block;
            margin-top: 10px;
            font-size: 14px;
            color: #555;
            font-style: italic;
        }
            .regular-text, #department {
            font-family: 'Montserrat', serif;
        }

    </style>
    ";
}
add_action('wp_head', 'display_user_profile_styles');
add_action('wp_login', function($user_login, $user) {
    
    update_user_meta($user->ID, 'last_login', current_time('mysql'));
}, 10, 2);
?>