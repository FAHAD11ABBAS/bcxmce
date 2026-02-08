<?php


// Shortcode for registration form -- [cum_registration_form]
function cum_registration_form() {
    ob_start(); ?>
    <form action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
        <p>
            <label for="username">Username</label>
            <input type="text" name="username" value="<?php echo (isset($_POST['username']) ? esc_attr($_POST['username']) : ''); ?>">
        </p>
        <p>
            <label for="email">Email</label>
            <input type="email" name="email" value="<?php echo (isset($_POST['email']) ? esc_attr($_POST['email']) : ''); ?>">
        </p>
        <p>
            <label for="password">Password</label>
            <input type="password" name="password">
        </p>
        <p><input type="submit" name="submit_registration" value="Register"/></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('cum_registration_form', 'cum_registration_form');

// Get the current page URL to use as the redirect URL
$redirect_to = esc_url($_SERVER['REQUEST_URI']);



// -- Processing registration form.
function cum_handle_registration() {
    if (isset($_POST['submit_registration'])) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];

        $errors = [];

        if (username_exists($username)) {
            $errors[] = "Username already exists.";
        }

        if (email_exists($email)) {
            $errors[] = "Email is already in use.";
        }

        if (empty($password)) {
            $errors[] = "Password cannot be empty.";
        }

        if (empty($errors)) {
            $user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($user_id)) {
                wp_new_user_notification($user_id, null, 'both');
                wp_redirect(home_url($redirect_to));
                exit;
            } else {
                echo "Error: " . $user_id->get_error_message();
            }
        } else {
            foreach ($errors as $error) {
                echo "<p>$error</p>";
            }
        }
    }
}
add_action('wp', 'cum_handle_registration');


// Force clean output
if (!headers_sent()) {
    ob_start();
}


// Shortcode for login form -- [cum_login_form]
function cum_login_form() {
    // Redirect logged-in users
    if (is_user_logged_in()) {
        // Get the current page URL for redirection
        $redirect_to = esc_url($_SERVER['REQUEST_URI']);
        // Return a message and the logout button
        return '
        <div class="cum-login-form">
            <p class="already-logged-in">' . __('Olet kirjautunut sisään.', 'custom-user-management') . '</p>
            <form action="' . wp_logout_url($redirect_to) . '" method="post">
                <button class="logout-button" type="submit">
                    Kirjaudu ulos
                </button>
            </form>
        </div>
        ';
    }
    // Initialize error message variable
    $error_message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cum_login_nonce']) && wp_verify_nonce($_POST['cum_login_nonce'], 'cum_login_action')) {
        $username = sanitize_user($_POST['log']);
        $password = $_POST['pwd'];
        // Authenticate the user
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            // Specific error messages for invalid username/password
            if ($user->get_error_code() === 'invalid_username') {
                $error_message = __('Virheellinen käyttäjätunnus!', 'custom-user-management');
            } elseif ($user->get_error_code() === 'incorrect_password') {
                $error_message = __('Virheellinen salasana!', 'custom-user-management');
            } elseif ($user->get_error_code() === 'deactivated_user') {
                $error_message = __('Käyttäjätilisi on poistettu käytöstä.', 'custom-user-management');
            } else {
                $error_message = __('Kirjautumisvirhe.', 'custom-user-management');
            }
        } else {
            // Log the user in
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            // Redirect to the same page or a specified URL
            if (!headers_sent()) {
                wp_redirect($_POST['redirect_to'] ?? home_url());
                exit;
            } else {
                $error_message = __('Headers already sent; redirection failed.', 'custom-user-management');
            }
        }
    }
    // Start output buffering for the form
    ob_start();
    $redirect_to = esc_url(home_url());

    ?>
    <!-- Login Form -->
    <form class="cum-login-form" id="login-form" method="post">
        <h2><?php _e('Kirjaudu', 'custom-user-management'); ?></h2>

        <?php if (!empty($error_message)) : ?>
            <div class="error-message">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <label for="username"><?php _e('Käyttäjätunnus / Sähköposti', 'custom-user-management'); ?></label>
            <input type="text" name="log" id="username" required>
        </p>
        <p>
            <label for="password"><?php _e('Salasana', 'custom-user-management'); ?></label>
            <input type="password" name="pwd" id="password" required>
        </p>
        <p>
            <button type="submit"><?php _e('Kirjaudu', 'custom-user-management'); ?></button>
        </p>

        <?php wp_nonce_field('cum_login_action', 'cum_login_nonce'); ?>

        <input type="hidden" name="redirect_to" value="<?php echo $redirect_to; ?>" />

        <!-- Add "Forgot Password" Link -->
        <a href="#" id="lost-password-link">
            <?php _e('Salasana hukassa?', 'custom-user-management'); ?>
        </a>
    </form>

    <!-- Password Recovery Form -->
    <div class="cum-login-form" id="password-recovery-section" style="display: none;">
        <h2><?php _e('Salasanan palautus', 'custom-user-management'); ?></h2>
        <form id="password-recovery-form">
            <!-- Error Message Section -->
            <div id="password-recovery-error" style="color: red; display: none; margin-bottom: 10px;"></div>
            <!-- Success Message Section -->
            <div id="password-recovery-success" style="color: green; display: none; margin-bottom: 10px;"></div>
            <p>
                <label for="user_email"><?php _e('Sähköposti', 'custom-user-management'); ?></label>
                <input type="email" name="user_email" id="user_email" required>
            </p>
            <p id="recovery-submit-button">
                <button type="submit"><?php _e('Lähetä palautuslinkki', 'custom-user-management'); ?></button>
            </p>
        </form>
        <button id="cancel-recovery" onclick="closePasswordRecovery()" style="cursor: pointer;"><?php _e('Peruuta', 'custom-user-management'); ?></button>



        <!-- Go Back to Login Button -->
        <button id="back-to-login" style="display: none; cursor: pointer;" onclick="backToLogin()"><?php _e('Takaisin kirjautumiseen', 'custom-user-management'); ?></button>
    </div>

    <!-- JavaScript to Toggle Forms and Handle AJAX -->
    <script>
        document.getElementById('lost-password-link').addEventListener('click', function(event) {
            event.preventDefault();
            document.getElementById('login-form').style.display = 'none'; // Hide login form
            document.getElementById('password-recovery-section').style.display = 'block'; // Show recovery form
        });

        function closePasswordRecovery() {
            document.getElementById('password-recovery-section').style.display = 'none'; // Hide recovery form
            document.getElementById('login-form').style.display = 'block'; // Show login form
        }

        // Handle the password recovery form submission
        document.getElementById('password-recovery-form').addEventListener('submit', function(event) {
            event.preventDefault();

            var userEmail = document.getElementById('user_email').value;
            var security = '<?php echo wp_create_nonce('cum_password_recovery_nonce'); ?>'; // WordPress nonce for security

            // Clear previous messages
            document.getElementById('password-recovery-error').style.display = 'none';
            document.getElementById('password-recovery-success').style.display = 'none';
            document.getElementById('back-to-login').style.display = 'none'; // Hide the "Go Back" button initially

            // Hide the "Peruuta" button and "Lähetä palautuslinkki" button
            document.getElementById('cancel-recovery').style.display = 'none'; 
            document.getElementById('recovery-submit-button').style.display = 'none'; 

            // AJAX request
            var data = new FormData();
            data.append('action', 'cum_password_recovery');
            data.append('user_email', userEmail);
            data.append('security', security);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    document.getElementById('password-recovery-success').innerText = response.data.message;
                    document.getElementById('password-recovery-success').style.display = 'block';
                    document.getElementById('back-to-login').style.display = 'inline-block'; // Show "Go Back to Login" button
                } else {
                    document.getElementById('password-recovery-error').innerText = response.data.message;
                    document.getElementById('password-recovery-error').style.display = 'block';

                    // Restore the buttons if there's an error
                    document.getElementById('cancel-recovery').style.display = 'inline-block';
                    document.getElementById('recovery-submit-button').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('password-recovery-error').innerText = 'There was an error processing your request. Please try again.';
                document.getElementById('password-recovery-error').style.display = 'block';

                // Restore the buttons if there's an error
                document.getElementById('cancel-recovery').style.display = 'inline-block';
                document.getElementById('recovery-submit-button').style.display = 'block';
            });
        });

        function backToLogin() {
            document.getElementById('password-recovery-section').style.display = 'none'; // Hide recovery form
            document.getElementById('login-form').style.display = 'block'; // Show login form
        }
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('cum_login_form', 'cum_login_form');

// AJAX handler for password recovery
function cum_handle_password_recovery() {
    // Check the nonce for security
    check_ajax_referer('cum_password_recovery_nonce', 'security');

    $email = sanitize_email($_POST['user_email']);
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => __('Syötä kelvollinen sähköpostiosoite.', 'custom-user-management')]);
    }

    // If the email exists, send password reset link
    $user = get_user_by('email', $email);
    if (!$user) {
        wp_send_json_error(['message' => __('Ei käyttäjää, joka vastaa tähän sähköpostiosoitteeseen.', 'custom-user-management')]);
    }

    // Use WordPress function to generate a reset key and send an email
    $reset_result = retrieve_password($user->user_login);

    if (is_wp_error($reset_result)) {
        wp_send_json_error(['message' => __('Virhe sähköpostin lähetyksessä. Yritä uudelleen myöhemmin.', 'custom-user-management')]);
    }

    wp_send_json_success(['message' => __('Palautuslinkki on lähetetty sähköpostiisi. Huom! Jos et saanut sähköpostia, pyydä ylläpitäjää asettamaan uusi salasana!', 'custom-user-management')]);
}

add_action('wp_ajax_cum_password_recovery', 'cum_handle_password_recovery');
add_action('wp_ajax_nopriv_cum_password_recovery', 'cum_handle_password_recovery');




// Shortcode for logout button -- [cum_logout_button] 
function cum_logout_button() {
    // Get the current page URL to use as the redirect URL after logout
    $redirect_to = esc_url($_SERVER['REQUEST_URI']);

    if (is_user_logged_in()) {
        // Display the logout button with the redirect URL set to the current page
        return '<form action="' . wp_logout_url($redirect_to) . '" method="post">
                    <button type="submit" style="padding: 10px 20px; background-color: #f00; color: #fff; border: none; cursor: pointer;">
                        Kirjaudu ulos
                    </button>
                </form>';
    }
}
add_shortcode('cum_logout_button', 'cum_logout_button');