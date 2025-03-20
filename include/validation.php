<?php
/**
 * Cipher and validation functions 
 *
 * Functions that validate cookies and password, update or set cookies, 
 * and they add cipher algorithm.
 *
 * @package Blog_Password_Protection
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/* Set access cookie */
function bpp_set_access_cookie() {
    $settings = get_option( 'bp_settings', [] );

    setcookie( 'blog_access', $settings['encrypted_password'], [
        'expires' => time() + $settings['cookie_lifetime'] * 60 * 60,
        'path' => '/', 
        'secure' => is_ssl(), 
        'httponly' => true, 
        'samesite' => 'Strict',
    ] );  // setting cookie
}

/* Add AJAX action to validate password */
function bpp_ajax_validate_password() {
    $settings = get_option( 'bp_settings', [] );
    $entered_password = $_POST['password']; // Get password from AJAX request

    if ( wp_check_password( $entered_password, $settings['encrypted_password'] ) ) { 
        bpp_set_access_cookie();
        wp_send_json_success( 'Access granted.' );
    } 
    else {
        wp_send_json_error( 'Incorrect password.' );
    }
    wp_die();
}
add_action( 'wp_ajax_check_password', 'bpp_ajax_validate_password' );
add_action( 'wp_ajax_nopriv_check_password', 'bpp_ajax_validate_password' );

/* Add function to validate access cookie */
function bpp_validate_access_cookie() {
    $settings = get_option( 'bp_settings', [] );
    $cookie_value = isset( $_COOKIE['blog_access'] ) ? $_COOKIE['blog_access'] : '';
	
    if ( $settings['encrypted_password'] === $cookie_value ) {  // An encrypted password is a unique website token
        return true;
    }
    return false; // Cookie is not valid
}

/* Add AJAX action to validate access cookie */
function bpp_ajax_validate_access_cookie() {
    $settings = get_option( 'bp_settings', [] );
    $cookie_value = isset( $_COOKIE['blog_access'] ) ? $_COOKIE['blog_access'] : '';

    if ( $settings['encrypted_password'] === $cookie_value ) {  // An encrypted password is a unique website token
        wp_send_json_success( 'Access granted.' ); // Cookie is valid
    }
    else {
        wp_send_json_error( 'Access denied.' ); // Cookie is not valid
    }
    wp_die();
}
add_action( 'wp_ajax_check_cookie', 'bpp_ajax_validate_access_cookie' );
add_action( 'wp_ajax_nopriv_check_cookie', 'bpp_ajax_validate_access_cookie' );

function bpp_share_access_and_url_password() {
    $settings = get_option( 'bp_settings', [] );

    ?>
    <script type="text/javascript">  // Check password in url
        document.addEventListener( "DOMContentLoaded", function() {  // Check password from URL and cookie
            const urlPassword = window.location.hash.replace('#', '') || ' ';
            const bppPasswordPopup = document.getElementById('popupBackground');

            function sendAjaxRequest( action, bodyData ) {
                return fetch( '<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&${bodyData}`
                } )
                .then( response => response.json() );
            }

            sendAjaxRequest( 'check_cookie', '' )
            .then( data => {
                if ( !data.success ) {
                    bppPasswordPopup.style.display = 'block';// Show popup
                }
            } )
            .catch( error => console.error('Error: Cookie validation.', error) ); 

            <?php 
            if ( $settings['share_access'] === '1' ) { 
                ?>
                sendAjaxRequest( 'check_password', `password=${encodeURIComponent(urlPassword)}` ) 
                .then( data => {
                    if ( data.success && bppPasswordPopup.style.display === 'block') { 
                        location.reload();
                    } 
                })
                .catch(error => console.error('Error: Share access link validation error.', error)); 
                <?php  
            } 
            ?>
        });
    </script>
    <?php
}
add_action( 'wp_head', 'bpp_share_access_and_url_password' );
