<?php 
/*
 * Plugin Name: Blog Password Protection - BPP
 * Description: Add password protection for blog page or post.
 * Version: 1.3
 * Author: zamaraievdrdmytro
 * Requires PHP: 7.2
 * Requires at least: 6.6
 * License: GPL-3.0
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PASSWORD_PROTECTION_PATH', plugin_dir_path(__FILE__) );
define( 'PASSWORD_PROTECTION_URL', plugin_dir_url(__FILE__) );

 /* Add styles */
function bpp_enqueue_password_protection_styles() {
    wp_enqueue_style( 'bpp_password_protection_style', PASSWORD_PROTECTION_URL . 'assets/style.min.css' );
}
add_action( 'wp_enqueue_scripts', 'bpp_enqueue_password_protection_styles' );

require_once PASSWORD_PROTECTION_PATH . 'include/plugin_settings_page.php';
require_once PASSWORD_PROTECTION_PATH . 'include/validation.php';
require_once PASSWORD_PROTECTION_PATH . 'include/secure_content.php';
