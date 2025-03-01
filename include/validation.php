<?php
/**
 * Cipher and validation functions 
 *
 * Functions that validate cookies and password, update or set cookies, 
 * and they add cipher algorithm.
 *
 * @package Blog_Password_Protection
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Add cipher function */
function bpp_cipher($text, $salt){
    // Convert text to array of ASCII codes
    $text_to_chars = function($text) {
        return array_map('ord', str_split($text));
    };

    // Convert number to hexadecimal string
    $byte_hex = function($n) {
        return str_pad(dechex($n), 2, "0", STR_PAD_LEFT);
    };

    // XOR each character's ASCII code with the salt
    $apply_salt_to_char = function($code) use ($text_to_chars, $salt) {
        return array_reduce($text_to_chars($salt), function($a, $b) {
            return $a ^ $b;
        }, $code);
    };

    // Encrypt text
    $text_chars = $text_to_chars($text);
    $encrypted = array_map(function($char_code) use ($apply_salt_to_char, $byte_hex) {
        return $byte_hex($apply_salt_to_char($char_code));
    }, $text_chars);

    return implode('', $encrypted);
}

function bpp_update_cookie_token() {
    $password = get_option('password_protection_password', '');
	$unique_site_token = get_option('password_protection_unique_site_token', ''); 
	
    if(empty($unique_site_token)){
        $unique_site_token = bin2hex(random_bytes(16)); // Generate unique token
        update_option('password_protection_unique_site_token', $unique_site_token); // Update token in DB
    }
	
    $new_cookie_token = $password . $unique_site_token; // Create token
    if($new_cookie_token !== get_option('password_protection_access_token')){ // Update token if it is changed
        update_option('password_protection_access_token', $new_cookie_token);
    }
}
add_action('init', 'bpp_update_cookie_token');

/* Set access cookie */
function bpp_set_access_cookie(){
	$token = get_option('password_protection_access_token');
    $cookie_session_lifetime = get_option('password_protection_cookie_session_lifetime', '24'); // Get cookie session lifetime

    // setting cookie
    setcookie('blog_access', $token, [
        'expires' => time() + $cookie_session_lifetime * 60 * 60,
        'path' => '/', 
        'secure' => is_ssl(), 
        'httponly' => true, 
        'samesite' => 'Strict',
    ]);
}

/* Add AJAX action to validate password */
function bpp_ajax_validate_password(){
    $password = get_option('password_protection_password', '');
    $input_password = sanitize_text_field($_POST['password']); // Get password from AJAX request

    if ($password === $input_password){ // Check if password is correct and set cookie
        bpp_set_access_cookie();
        wp_send_json_success('Access granted.');
    } 
    else{
        wp_send_json_error('Incorrect password.');
    }

    wp_die();
}
add_action('wp_ajax_check_password', 'bpp_ajax_validate_password');
add_action('wp_ajax_nopriv_check_password', 'bpp_ajax_validate_password');

/* Add function to validate access cookie */
function bpp_validate_access_cookie(){
    $cookie_value = isset($_COOKIE['blog_access']) ? $_COOKIE['blog_access'] : '';
    $stored_token = get_option('password_protection_access_token'); // Get stored token from DB
	
    if ($stored_token === $cookie_value){
        return true; // Cookie is valid
    }
    return false; // Cookie is not valid
}

/* Add AJAX action to validate access cookie */
function bpp_ajax_validate_access_cookie(){
    $cookie_value = isset($_COOKIE['blog_access']) ? $_COOKIE['blog_access'] : '';
    $stored_token = get_option('password_protection_access_token');

    if ($stored_token === $cookie_value){
        wp_send_json_success('Access granted.'); // Cookie is valid
    }
    else{
        wp_send_json_error('Access denied.'); // Cookie is not valid
    }

    wp_die();
}
add_action('wp_ajax_check_cookie', 'bpp_ajax_validate_access_cookie');
add_action('wp_ajax_nopriv_check_cookie', 'bpp_ajax_validate_access_cookie');

