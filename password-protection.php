<?php 
/*
 * Plugin Name: Password blog protection
 * Description: Add password protection for blog page or post
 * Version: 1.2
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
    $сookieSessionLifetime = get_option('password_protection_cookie_session_lifetime', '24'); // Get cookie session lifetime
    update_option('access_token', $token); // Save token to DB

    // setting cookie
    setcookie('blog_access', $token, [
        'expires' => time() + $сookieSessionLifetime * 60 * 60,
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
    $restrictedContentMessage = get_option('password_protection_resctircted_content_message', 'This content is password protected. Please enter the password below to access it.');
    return '<p>' . $restrictedContentMessage . '</p>';
}

function page_content_secured_for_feeds($content){
    // Replace content if access is restricted
    $restrictedContentMessageFeeds = get_option('password_protection_resctircted_content_message_feeds', 'This content is password protected. Please enter the password below to access it.');
    return '<p>' . $restrictedContentMessageFeeds . '</p>';
}

function apply_password_protection($content){
    $password = get_option('password_protection_password', '');
    $isDisabledForLoggedInUsers = get_option('password_protection_disabled_for_logged_in_users', '0');
    $protectionForCertainCategories = get_option('password_protection_for_certain_categories', []);
    $blogPageProtection = get_option('password_protection_for_blog_page', '0');
    $enableProtection = get_option('password_protection_enabled', '1'); // '1' for default

    if($enableProtection === '0' || ($blogPageProtection === '0' && (!is_front_page() && is_home()))){
        return $content; // Do nothing if protection isnt enabled
    }

    elseif(in_category($protectionForCertainCategories)){ // Check if it is blog or post page
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
    $protectionForCertainCategories = get_option('password_protection_for_certain_categories', []);
    $blogPageProtection = get_option('password_protection_for_blog_page', '0');
	$returnBackLinkUrl = get_option('password_protection_return_back_link_url', esc_url(home_url()));
	$returnBackLinkText = get_option('password_protection_return_back_link_text', 'Return to the main page');

    ?>
        <!-- Check password in url -->
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
    <?php

    // Check if password is set and protection is enabled
    if(empty($password) || ($isDisabledForLoggedInUsers === '1' && is_user_logged_in()) || $enableProtection === '0' || ($blogPageProtection === '0' && (!is_front_page() && is_home())) ){
        return; // Do nothing if protection isnt enabled or password is not set or disabled for logged in users(if it is enabled)
    }

	elseif(in_category($protectionForCertainCategories)){ // Check if it is blog or post page
        ?>
        <!-- Popup -->
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm" class="passwordForm">
                    <h3 class="passwordForm__title">Enter Password to Access the Blog</h3>
                    <label for="passwordInput" class="passwordForm_lable">Password:</label>
                    <input type="password" id="passwordInput" class="passwordForm__input" required>
                    <div class="passwordForm__form-group">
                        <button type="submit" class="passwordForm__button">Submit</button>
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url($returnBackLinkUrl); ?>"><?php echo $returnBackLinkText ?></a>
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
function password_protection_settings_page_html(){

    if (!current_user_can('manage_options')){
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

                //set cookie session lifetime
                $сookieSessionLifetime = intval($_POST['password_protection_cookie_session_lifetime']);
                update_option('password_protection_cookie_session_lifetime', $сookieSessionLifetime);

                //allow share access
                $shareAccess = isset($_POST['password_protection_share_access']) ? '1' : '0';
                update_option('password_protection_share_access', $shareAccess);

                //disable password protection for logged in users
                $disableForLoggedInUsers = isset($_POST['password_protection_disabled_for_logged_in_users']) ? '1' : '0';
                update_option('password_protection_disabled_for_logged_in_users', $disableForLoggedInUsers);

                // Update categories
                $enableProtectionForCertainCategoriesArray = isset($_POST['password_protection_for_certain_categories']) ? array_map('sanitize_text_field', $_POST['password_protection_for_certain_categories']) : [];
                update_option('password_protection_for_certain_categories', $enableProtectionForCertainCategoriesArray);

                // Enable protection for blog page
                $blogPageProtection = isset($_POST['password_protection_for_blog_page']) ? '1' : '0';
                update_option('password_protection_for_blog_page', $blogPageProtection);

                // Update restricted content message
                $restrictedContentMessage = sanitize_text_field($_POST['password_protection_resctircted_content_message']);
                update_option('password_protection_resctircted_content_message', $restrictedContentMessage);

                $restrictedContentMessageFeeds = sanitize_text_field($_POST['password_protection_resctircted_content_message_feeds']);
                update_option('password_protection_resctircted_content_message_feeds', $restrictedContentMessageFeeds);
				
				$returnBackLinkUrl = sanitize_text_field($_POST['password_protection_return_back_link_url']);
				update_option('password_protection_return_back_link_url', $returnBackLinkUrl);
				
				$returnBackLinkText = sanitize_text_field($_POST['password_protection_return_back_link_text']);
				update_option('password_protection_return_back_link_text', $returnBackLinkText);
            } 
            else {
                echo '<div class="error"><p>Please enter a valid password.</p></div>';
            }  
        }
    }

    // display the settings form
    $enableProtection = get_option('password_protection_enabled', '1'); // '1' for default
    $password = get_option('password_protection_password', '');
    $сookieSessionLifetime = get_option('password_protection_cookie_session_lifetime', '24'); // '24' for default
    $isShareAccessTurnedOn = get_option('password_protection_share_access', '1'); // '1' for default
    $isDisabledForLoggedInUsers = get_option('password_protection_disabled_for_logged_in_users', '0'); // '0' for default
    $enableProtectionForCertainCategories = get_option('password_protection_for_certain_categories', []);
    $enableProtectionForBlogPage = get_option('password_protection_for_blog_page', '0'); // '0' for default
	$returnBackLinkUrl = get_option('password_protection_return_back_link_url', esc_url(home_url()));
	$returnBackLinkText = get_option('password_protection_return_back_link_text', 'Return to the main page');
    $popupErrorMessage = get_option('password_protection_popup_error_message', 'Incorrect password. Try again.');
    $restrictedContentMessage = get_option('password_protection_resctircted_content_message', 'This content is password protected. Please enter the password below to access it.');
    $restrictedContentMessageFeeds = get_option('password_protection_resctircted_content_message_feeds', 'This content is password protected. Please enter the password below to access it.');  
    
    // Get list of categories
    $listOfCategories = get_categories(array('hide_empty' => 0));
    ?>
    <div class="wrap">
        <h1>Password Protection Settings</h1>
        <form method="post" action="">
            <p> 
                This plugin provides robust content protection features for your WordPress site. 
                It allows you to secure posts or pages with a password and customize the message 
                displayed to users attempting to access protected content. Additionally, the plugin
                offers a user-friendly popup for enhanced interaction, including a customizable
                "Return Back" link to improve user navigation.
            </p>
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
                    <th scope="row">Cookie session lifetime</th>
                    <td>
                        <input type="number" name="password_protection_cookie_session_lifetime" value="<?php echo esc_attr( $сookieSessionLifetime); ?>" />
                        <p>Enter the lifetime of the cookie in hours. By default set to one day. Examples: 48 hours/2 days, 72 hours/3 days, 168 hours/7 days</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable protection for certain categories</th>
                    <td>
                        <input type="checkbox" name="password_protection_for_blog_page" value="1" <?php checked('1', $enableProtectionForBlogPage); ?> />
                        Enable for blog page<br>
                        <?php if (!empty($listOfCategories)){
                            foreach ($listOfCategories as $category) { ?>
                                <input type="checkbox" 
                                       name="password_protection_for_certain_categories[]" 
                                       value="<?php echo esc_attr($category->cat_ID); ?>" 
                                       <?php checked(in_array($category->cat_ID, $enableProtectionForCertainCategories)); ?> />
                                <?php echo esc_html($category->name); ?><br>
                            <?php }
                        } 
                        else{
                            echo '<p>No categories available.</p>';
                        } ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Share access to the blog without password using secure link</th>
                    <td>
                        <input type="checkbox" name="password_protection_share_access" value="1" <?php checked('1', $isShareAccessTurnedOn); ?> />
                        <?php
                         // Display link for sharing access
                        if ($isShareAccessTurnedOn === '1' && !empty($password)) {
                            $cipheredPassword = cipher($password, 'mySecretSalt');
                            echo '<b>You can use this URL to share access to the blog without password: ' 
                                . esc_url(get_permalink(get_option('page_for_posts'))) . '#' . $cipheredPassword . '</b>';
                        }
                    ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Disable password protection for logged in users</th>
                    <td>
                        <input type="checkbox" name="password_protection_disabled_for_logged_in_users" value="1" <?php checked('1', $isDisabledForLoggedInUsers); ?> />
                    </td>
                </tr>
				<tr valign="top">
                    <th scope="row">Popup 'Return Back' Link Settings</th>
                    <td>
                        <input type="text" name="password_protection_return_back_link_url" value="<?php echo esc_attr($returnBackLinkUrl); ?>"/>
						<p>'Return Back' link url</p>
                        <input type="text" name="password_protection_return_back_link_text" value="<?php echo esc_attr($returnBackLinkText); ?>"/>
                        <p>'Return Back' link text</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Message shown to users when accessing password-protected content.</th>
                    <td>
                        <textarea name="password_protection_resctircted_content_message" rows="4" cols="50"><?php echo esc_attr($restrictedContentMessage); ?></textarea>
                        <p>Message shown to users when accessing password-protected content.<p>
                        <textarea name="password_protection_resctircted_content_message_feeds" rows="4" cols="50"><?php echo esc_attr($restrictedContentMessageFeeds); ?></textarea>
                        <p>Message shown to users when accessing password-protected content in feeds.<p>
                        <p>By default "This content is password protected. Please enter the password below to access it."<br>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}