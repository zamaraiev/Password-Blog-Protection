<?php
/**
 * Cipher and validation functions 
 *
 * Functions that validate cookies and password, update or set cookies, 
 * and they add cipher algorithm.
 *
 * @package Blog_Password_Protection
 */

namespace Blog_Password_Protection;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Validation {
    private $settings;
    /* Set access cookie */
    public function set_access_cookie() {
        setcookie( 'blog_access', $this->settings['encrypted_password'], [
            'expires' => time() + $this->settings['cookie_lifetime'] * 60 * 60,
            'path' => '/', 
            'secure' => is_ssl(), 
            'httponly' => true, 
            'samesite' => 'Strict',
        ] );  // setting cookie
    }

    public function __construct($plugin_settings) {
        add_action( 'wp_ajax_bpp_check_password', [ $this, 'ajax_validate_password' ] );
        add_action( 'wp_ajax_nopriv_bpp_check_password', [ $this, 'ajax_validate_password' ] );
        add_action( 'wp_ajax_bpp_check_cookie', [ $this, 'ajax_validate_access_cookie' ] );
        add_action( 'wp_ajax_nopriv_bpp_check_cookie', [ $this, 'ajax_validate_access_cookie' ] );
        $this->settings = $plugin_settings;
    }

    /* Add AJAX action to validate password */
    public function ajax_validate_password() {
        check_ajax_referer('bpp_ajax_nonce');

        $entered_password = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // Get password from AJAX request

        if ( wp_check_password( $entered_password, $this->settings['encrypted_password'] ) ) { 
            $this->set_access_cookie();
            wp_send_json_success( 'Access granted.' );
        } 
        else {
            wp_send_json_error( 'Incorrect password.' );
        }
        wp_die();
    }

    /* Add function to validate access cookie */
    public function validate_access_cookie() {
        $cookie_value = isset( $_COOKIE['blog_access'] ) ? wp_unslash($_COOKIE['blog_access']) : '';

        if ( $this->settings['encrypted_password'] === $cookie_value ) {  // An encrypted password is a unique website token
            return true;
        }
        return false; // Cookie is not valid
    }

    /* Add AJAX action to validate access cookie */
    public function ajax_validate_access_cookie() {
        check_ajax_referer('bpp_ajax_nonce');
        $cookie_value = isset( $_COOKIE['blog_access'] ) ? wp_unslash($_COOKIE['blog_access']) : '';

        if ( $this->settings['encrypted_password'] === $cookie_value ) {  // An encrypted password is a unique website token
            wp_send_json_success( 'Access granted.' ); // Cookie is valid
        }
        else {
            wp_send_json_error( 'Access denied.' ); // Cookie is not valid
        }
        wp_die();
    }
}