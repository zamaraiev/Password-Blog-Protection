<?php 
/*
 * Plugin Name: Blog Password Protection - DMYZBP
 * Description: Add password protection for blog page or post.
 * Version: 1.4.1
 * Author: dmytro1zamaraiev
 * Requires PHP: 7.2
 * Requires at least: 6.6
 * Tested up to: 6.8.1
 * Text Domain: blog-password-protection-dmyzbp
 * License: GPL-3.0
 */

namespace Blog_Password_Protection_DMYZBP;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DMYZBP_PASSWORD_PROTECTION_PATH', plugin_dir_path(__FILE__) );
define( 'DMYZBP_PASSWORD_PROTECTION_URL', plugin_dir_url(__FILE__) );

/* Add styles */
function enqueue_password_protection_styles() {
    wp_enqueue_style( 'dmyzbp_password_protection_style', DMYZBP_PASSWORD_PROTECTION_URL . 'assets/style.min.css', [], '1.4.0' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_password_protection_styles' );

require_once DMYZBP_PASSWORD_PROTECTION_PATH . 'include/plugin_settings_page.php';
require_once DMYZBP_PASSWORD_PROTECTION_PATH . 'include/validation.php';
require_once DMYZBP_PASSWORD_PROTECTION_PATH . 'include/secure_content.php';