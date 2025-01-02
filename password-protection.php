<?php 
/*
 * Plugin Name: Password blog protection
 * Description: Add password protection for blog page or post
 * Version: 1.11
 * Author: Zamaraiev Dmytro
 * WC requires at least: 5.7
 * Requires at least: 5.5
 */

  /* ******************************************* */
 /*      Cipher and validation functions        */
/* ******************************************* */

/* Add cipher function */
function cipher($text, $salt){
    // Convert text to array of ASCII codes
    $textToChars = function($text) {
        return array_map('ord', str_split($text));
    };

    // Convert number to hexadecimal string
    $byteHex = function($n) {
        return str_pad(dechex($n), 2, "0", STR_PAD_LEFT);
    };

    // XOR each character's ASCII code with the salt
    $applySaltToChar = function($code) use ($textToChars, $salt) {
        return array_reduce($textToChars($salt), function($a, $b) {
            return $a ^ $b;
        }, $code);
    };

    // Encrypt text
    $textChars = $textToChars($text);
    $encrypted = array_map(function($charCode) use ($applySaltToChar, $byteHex) {
        return $byteHex($applySaltToChar($charCode));
    }, $textChars);

    return implode('', $encrypted);
}

/* Set access cookie */
function set_access_cookie(){
    $token = bin2hex(random_bytes(16)); // Generate random token
    update_option('access_token', $token); // Save token to DB

    // setting cookie
    setcookie('blog_access', $token, [
        'expires' => time() + 7 * 24 * 60 * 60,
        'path' => '/', 
        'secure' => is_ssl(), 
        'httponly' => true, 
        'samesite' => 'Strict',
    ]);
}

/* Add AJAX action to validate password */
function ajax_validate_password(){
    $password = get_option('password_protection_password', '');
    $inputPassword = sanitize_text_field($_POST['password']); // Get password from AJAX request

    if ($password === $inputPassword){ // Check if password is correct and set cookie
        set_access_cookie();
        wp_send_json_success('Access granted.');
    } 
    else{
        wp_send_json_error('Incorrect password.');
    }

    wp_die();
}
add_action('wp_ajax_check_password', 'ajax_validate_password');
add_action('wp_ajax_nopriv_check_password', 'ajax_validate_password');

/* Add function to validate access cookie */
function validate_access_cookie(){
    $cookie_value = isset($_COOKIE['blog_access']) ? $_COOKIE['blog_access'] : '';
    $stored_token = get_option('access_token'); // Get stored token from DB

    if (!empty($cookie_value) && hash_equals($stored_token, $cookie_value)){
        return true; // Cookie is valid
    }
    return false; // Cookie is not valid
}

/* Add AJAX action to validate access cookie */
function ajax_validate_access_cookie(){
    $cookie_value = isset($_COOKIE['blog_access']) ? $_COOKIE['blog_access'] : '';
    $stored_token = get_option('access_token');

    if (!empty($cookie_value) && hash_equals($stored_token, $cookie_value)){
        wp_send_json_success('Access granted.'); // Cookie is valid
    }
    else{
        wp_send_json_error('Access denied.'); // Cookie is not valid
    }

    wp_die();
}
add_action('wp_ajax_check_cookie', 'ajax_validate_access_cookie');
add_action('wp_ajax_nopriv_check_cookie', 'ajax_validate_access_cookie');

  /* ******************************************* */
 /*       Functions that secure content         */
/* ******************************************* */

/* Additional protection functions to hide content*/
function page_content_secured($content){
    // Replace content if access is restricted
    return '<p>This content is password protected. Please enter the password below to access it.</p>';
}

function page_content_secured_for_feeds($content){
    // Replace content if access is restricted
    return '<p>This content is password protected. Please enter the blog page to access it.</p>';
}

function apply_password_protection($content){
    $password = get_option('password_protection_password', '');
    $isDisabledForLoggedInUsers = get_option('password_protection_disabled_for_logged_in_users', '0');

    if((!is_front_page() && is_home()) || get_post_type() === 'post' ){ // Check if it is blog or post page
        // If no password is set, do nothing
        if (empty($password) || ($isDisabledForLoggedInUsers === '1' && is_user_logged_in())) {
            return $content;
        }
        // Check if access is granted via cookie
        elseif(!validate_access_cookie()) {
            return page_content_secured($content);
        }
        elseif(is_feed()){
            return page_content_secured_for_feeds($content);
        }
    }
    return $content;
}
add_filter('the_content', 'apply_password_protection');
add_filter('get_the_excerpt', 'apply_password_protection');

/* Add password protection popup and check password*/
function add_password_protection_popup(){
    // Get password and settings
    $enableProtection = get_option('password_protection_enabled', '1'); // '1' for default
	$password = get_option('password_protection_password', '');
    $isShareAccessTurnedOn = get_option('password_protection_share_access', '1');
    $isDisabledForLoggedInUsers = get_option('password_protection_disabled_for_logged_in_users', '0');

    if(empty($password) || ($isDisabledForLoggedInUsers === '1' && is_user_logged_in()) || $enableProtection === '0'){
        return; // Do nothing if protection isnt enabled or password is not set or disabled for logged in users(if it is enabled)
    }

	elseif(((!is_front_page() && is_home()) && ) || get_post_type() === 'post' ){ // Check if it is blog or post page
        ?>
        <script type="text/javascript">
            // decipher function
            const decipher = salt => {
                const textToChars = text => text.split('').map(c => c.charCodeAt(0));
                const applySaltToChar = code => textToChars(salt).reduce((a, b) => a ^ b, code);
                return encoded => encoded.match(/.{1,2}/g)
                    .map(hex => parseInt(hex, 16))
                    .map(applySaltToChar)
                    .map(charCode => String.fromCharCode(charCode))
                    .join('');
            };

            // Check password from URL and cookie
            document.addEventListener("DOMContentLoaded", function() {
                const salt = 'mySecretSalt'; 
                const urlKey = window.location.hash.replace('#', '') || ' ';
                const myDecipher = decipher(salt);
                const urlPassword = myDecipher(urlKey);

                // Validate cookie
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=check_cookie`,
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('popupBackground').style.display = 'none'; // Hide popup
                    }
                    else{
                        document.getElementById('popupBackground').style.display = 'block';// Show popup
                    }
                })
                .catch(error => console.error('Error with cookie validation', error)); 

                <?php if($isShareAccessTurnedOn === '1') {
                    ?>
                    // Validate password from URL
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded' }, 
                        body: `action=check_password&password=${encodeURIComponent(urlPassword)}`, 
                    })
                    .then(response => response.json()) 
                    .then(data => {
                        if (data.success) { 
                            if(document.getElementById('popupBackground').style.display === 'block'){
                                location.reload();
                            }
                        } 
                    })
                    .catch(error => console.error('Error:', error)); 
                <?php
                }
                ?>
            });
        </script>
        <!-- Popup -->
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm" class="passwordForm">
                    <h3 class="passwordForm__title">Enter Password to Access the Blog</h3>
                    <label for="passwordInput" class="passwordForm_lable">Password:</label>
                    <input type="password" id="passwordInput" class="passwordForm__input" required>
                    <div class="passwordForm__form-group">
                        <button type="submit" class="passwordForm__button">Submit</button>
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url(home_url()); ?>">Return to the main page</a>
                    </div>
                    <p id="errorMessage" class="passwordForm__errorMessage">Incorrect password. Try again.</p>
                </form>
            </div>
	    </div>	

	    <script>
            document.getElementById("passwordForm").addEventListener("submit", function(event) {
                event.preventDefault();
                const password = document.getElementById("passwordInput").value;

                // Validate password
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" }, 
                    body: `action=check_password&password=${encodeURIComponent(password)}`, 
                })
                .then(response => response.json()) 
                .then(data => {
                    if (data.success) { 
                        location.reload();
                    } 
                    else { 
                        document.getElementById("errorMessage").textContent = data.data; 
                        document.getElementById("errorMessage").style.display = "block";
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        </script>
    <?php
    }
}
add_action('wp_head', 'add_password_protection_popup');

/* Add styles */
function enqueue_password_protection_styles() {
    wp_enqueue_style('password-protection-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_password_protection_styles');

  /* *************************** */
 /*      Plagin setings         */
/* *************************** */

/* Add settings page */
function password_protection_settings_page() {
    add_options_page(
        'Password Protection Settings',
        'Password Protection',
        'manage_options',
        'password-protection',
        'password_protection_settings_page_html'
    );
}
add_action('admin_menu', 'password_protection_settings_page');

/* Add settings page */
function password_protection_settings_page_html() {

    if (!current_user_can('manage_options')) {
        return;
    }

    // process form data
    if(isset($_POST['password_protection_enabled'])){
        //enable protection
        $enableProtection = isset($_POST['password_protection_enabled']) ? '1' : '0';
        update_option('password_protection_enabled', $enableProtection);

        if(isset($_POST['password_protection_password'])) {
            // validate password
            if(!empty($_POST['password_protection_password'])) {
                $password = sanitize_text_field($_POST['password_protection_password']);
                update_option('password_protection_password', $password);
                echo '<div class="updated"><p>Password updated successfully.</p></div>';

                //allow share access
                $shareAccess = isset($_POST['password_protection_share_access']) ? '1' : '0';
                update_option('password_protection_share_access', $shareAccess);

                //disable password protection for logged in users
                $disableForLoggedInUsers = isset($_POST['password_protection_disabled_for_logged_in_users']) ? '1' : '0';
                update_option('password_protection_disabled_for_logged_in_users', $disableForLoggedInUsers);
            } 
            else {
                echo '<div class="error"><p>Please enter a valid password.</p></div>';
            }  
        }
    }

    // display the settings form
    $enableProtection = get_option('password_protection_enabled', '1'); // '1' for default
    $password = get_option('password_protection_password', '');
    $isShareAccessTurnedOn = get_option('password_protection_share_access', '1'); // '1' for default
    $isDisabledForLoggedInUsers = get_option('password_protection_disabled_for_logged_in_users', '1'); // '1' for default
    ?>
    <div class="wrap">
        <h1>Password Protection Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable protection</th>
                    <td>
                        <input type="checkbox" name="password_protection_enabled" value="1" <?php checked('1', $enableProtection); ?> />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Password</th>
                    <td>
                        <input type="password" name="password_protection_password" value="<?php echo esc_attr($password); ?>" required />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Share access to the blog without password using secure link</th>
                    <td>
                        <input type="checkbox" name="password_protection_share_access" value="1" <?php checked('1', $isShareAccessTurnedOn); ?> />
                    </td>
                    <?php
                        // Display link for sharing access
                        if ($isShareAccessTurnedOn === '1' && !empty($password)) {
                            $cipheredPassword = cipher($password, 'mySecretSalt');
                            echo '<div><p>You can use this URL to share access to the blog without password: ' 
                                . esc_url(get_permalink(get_option('page_for_posts'))) . '#' . $cipheredPassword . '</p></div>';
                        }
                    ?>
                </tr>
                <tr valign="top">
                    <th scope="row">Disable password protection for logged in users</th>
                    <td>
                        <input type="checkbox" name="password_protection_disabled_for_logged_in_users" value="1" <?php checked('1', $isDisabledForLoggedInUsers); ?> />
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}