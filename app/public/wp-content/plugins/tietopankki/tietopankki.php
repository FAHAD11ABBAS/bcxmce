<?php
/*
Plugin Name: Tietopankki
Description: PDF- ja DOCX-ohjeiden hallinta ylläpitäjille sekä julkinen tiedostopankki.
Version: 1.5
Author: Waltteri Heino
Author URI: https://waltteriheino.com
*/

if (!defined('ABSPATH')) {
    exit;
}

// Lisää valikko hallintapaneeliin
function tp_add_admin_menu() {
    add_menu_page(
        'Tietopankki', 
        'Tietopankki', 
        'manage_options', 
        'tietopankki', 
        'tp_admin_page',
        'dashicons-media-document',
        25
    );
}
add_action('admin_menu', 'tp_add_admin_menu');

// Hallintapaneelin sivu
function tp_admin_page() {
    ?>
    <div class="wrap">
        <h1>Tietopankki</h1>
        
        <?php
        if (isset($_GET['upload_success'])) {
            echo '<div class="updated"><p>Tiedosto ladattu onnistuneesti!</p></div>';
        }
        if (isset($_GET['delete_success'])) {
            echo '<div class="updated"><p>Tiedosto poistettu onnistuneesti!</p></div>';
        }
        ?>

        <h2>Ohjeet ylläpitäjille ja tapahtuman järjestäjille</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="pdf_admin_upload" accept=".pdf,.docx">
            <button type="submit" name="upload_admin_pdf" class="button button-primary">Lataa Tiedosto</button>
        </form>
        <ul>
            <?php
            $admin_pdfs = get_option('tp_admin_pdfs', []);
            foreach ($admin_pdfs as $file) {
                echo '<li><a href="' . esc_url($file) . '" target="_blank">' . esc_html(basename($file)) . '</a> 
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="delete_pdf" value="' . esc_attr($file) . '">
                        <input type="hidden" name="pdf_type" value="tp_admin_pdfs">
                        <button type="submit" name="delete_pdf_submit" class="button button-link-delete" onclick="return confirm(\'Haluatko varmasti poistaa tämän tiedoston?\');">Poista</button>
                    </form>
                </li>';
            }
            ?>
        </ul>

        <hr>

        <h2>Sivuston PDF-tiedostot</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="pdf_public_upload" accept=".pdf,.docx">
            <button type="submit" name="upload_public_pdf" class="button button-primary">Lataa Tiedosto</button>
        </form>
        <ul>
            <?php
            $public_pdfs = get_option('tp_public_pdfs', []);
            foreach ($public_pdfs as $file) {
                echo '<li><a href="' . esc_url($file) . '" target="_blank">' . esc_html(basename($file)) . '</a> 
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="delete_pdf" value="' . esc_attr($file) . '">
                        <input type="hidden" name="pdf_type" value="tp_public_pdfs">
                        <button type="submit" name="delete_pdf_submit" class="button button-link-delete" onclick="return confirm(\'Haluatko varmasti poistaa tämän tiedoston?\');">Poista</button>
                    </form>
                </li>';
            }
            ?>
        </ul>
    </div>

    <script>
        if (window.location.href.indexOf("action_success") > -1) {
            window.location.href = window.location.href.split("?")[0];
        }
    </script>
    <?php

    if (isset($_POST['upload_admin_pdf']) && !empty($_FILES['pdf_admin_upload']['name'])) {
        tp_handle_upload('pdf_admin_upload', 'tp_admin_pdfs');
    }
    if (isset($_POST['upload_public_pdf']) && !empty($_FILES['pdf_public_upload']['name'])) {
        tp_handle_upload('pdf_public_upload', 'tp_public_pdfs');
    }

    if (isset($_POST['delete_pdf_submit']) && isset($_POST['delete_pdf']) && isset($_POST['pdf_type'])) {
        tp_delete_pdf($_POST['delete_pdf'], $_POST['pdf_type']);
    }
}

// Funktio tiedostojen lataamiseen
function tp_handle_upload($input_name, $option_name) {
    $uploaded_file = $_FILES[$input_name];
    $upload_dir = wp_upload_dir();
    $target_path = $upload_dir['path'] . '/' . basename($uploaded_file['name']);
    $allowed_types = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (in_array($uploaded_file['type'], $allowed_types)) {
        if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
            $uploads = get_option($option_name, []);
            $uploads[] = $upload_dir['url'] . '/' . basename($uploaded_file['name']);
            update_option($option_name, $uploads);
            wp_safe_redirect(admin_url('admin.php?page=tietopankki&upload_success=1'));
            exit;
        } else {
            echo '<p style="color: red;">Tiedoston lataaminen epäonnistui.</p>';
        }
    } else {
        echo '<p style="color: red;">Vain PDF- ja DOCX-tiedostot sallittu.</p>';
    }
}

// Funktio tiedoston poistamiseen
function tp_delete_pdf($file_url, $option_name) {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sinulla ei ole oikeuksia suorittaa tätä toimintoa.', 'textdomain'));
    }

    $uploads = get_option($option_name, []);
    $index = array_search($file_url, $uploads);

    if ($index !== false) {
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        unset($uploads[$index]);
        update_option($option_name, array_values($uploads));

        wp_safe_redirect(admin_url('admin.php?page=tietopankki&delete_success=1'));
        exit;
    }
}

// Shortcode julkisten PDF-tiedostojen näyttämiseen
function tp_display_public_pdfs() {
    $public_pdfs = get_option('tp_public_pdfs', []);
    if (empty($public_pdfs)) {
        return '<p>Ei saatavilla olevia tiedostoja.</p>';
    }

    $output = '<ul>';
    foreach ($public_pdfs as $file) {
        $output .= '<li><a href="' . esc_url($file) . '" target="_blank">' . esc_html(basename($file)) . '</a></li>';
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode('tietopankki_pdf_list', 'tp_display_public_pdfs');
