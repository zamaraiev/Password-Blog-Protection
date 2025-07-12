<?php
/**
 * Functions that secure content 
 *
 * Functions that apply content protection and add a password 
 * form on the page.
 *
 * @package Blog_Password_Protection_DMYZBP
 */

namespace Blog_Password_Protection_DMYZBP;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class SecureContent_DMYZBP {
    private $validator;
    private static $instance = null;
    private $settings;
    private $nonce;

    private function __construct() {
        add_filter( 'the_content', [ $this, 'apply_protection' ] );
        add_filter( 'get_the_excerpt', [ $this, 'apply_protection' ] );
        add_action( 'wp_footer', [ $this, 'add_popup' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'secure_content_enqueue_scripts' ] );
        $this->settings = $this->get_plugin_settings();
        $this->validator = new Validation_DMYZBP($this->settings); // Get instance of Validation class
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_plugin_settings() {
        $settings = wp_cache_get( 'dmyzbp_plugin_settings', 'options' );

        if ( $settings === false ) {
            return get_option( 'dmyzbp_plugin_settings', [] );
        }

        return $settings;
    }

    public function check_plugin_settings() {
        $user = wp_get_current_user();
        $roles = (array)$user->roles;

        if ( $this->settings['enable_protection'] === '0' ) {
            return false;
        }

        if ( !empty( $this->settings['disable_protection_for_certain_user_roles'] ) ) {
            foreach ( $this->settings['disable_protection_for_certain_user_roles'] as $disable_for ) {
                foreach ( $roles as $role ) {
                    if ( strtolower( $disable_for ) == strtolower( $role ) ) {
                        return false; // Do nothing if user has role that disables protection
                    }
                }
            } 
        }

        if ( ( $this->settings['blog_page_protection'] === '1' && ( !is_front_page() && is_home() ) ) 
	    || ( $this->settings['home_page_protection'] === '1' && is_front_page() ) ) {
            return true;
        }

        if ( !is_home() ) {
            if ( in_category( $this->settings['protection_for_certain_categories'] ) && get_post_type() === 'post' ) {
                return true;
            }
        }
    
        return false;
    }

    public function apply_protection( $content ) {
        if ( !$this->check_plugin_settings() ) {
            return $content;
        }

        if ( in_category( $this->settings['protection_for_certain_categories'] ) ) { // Check if it is blog or post page
            if ( !$this->validator->validate_access_cookie() && !is_feed() ) {  // Check if access is granted via cookie
                return '<p>' . $this->settings['restricted_message'] . '</p>';
            } 
            elseif ( !$this->validator->validate_access_cookie() && is_feed() ) {
                return '<p>' . $this->settings['restricted_message_feeds'] . '</p>';
            }
        }
        return $content;
    }

    public function secure_content_enqueue_scripts() {
        $this->nonce = wp_create_nonce('dmyzbp_ajax_nonce');
        wp_register_script( 'dmyzbp-secure-content', '', [], '1.4.1', true );

        $inline_script = '
            document.addEventListener( "DOMContentLoaded", function() {  // Check password from URL and cookie
                const urlPassword = window.location.hash.replace("#", "") || " ";
                const dmyzbpPasswordPopup = document.getElementById("popupBackground");

                function sendAjaxRequest( action, bodyData ) {
                    return fetch( "'. esc_url( admin_url("admin-ajax.php") ) .'" , {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: `action=${action}&${bodyData}&_ajax_nonce=' .esc_js( $this->nonce ) . '`
                    } )
                    .then( response => response.json() );
                }

                sendAjaxRequest( "dmyzbp_check_cookie", "" )
                .then( data => {
                    if ( !data.success ) {
                        dmyzbpPasswordPopup.style.display = "block";// Show popup
                    }
                } )
                .catch( error => console.error("Error: Cookie validation.", error) ); 
        ';

        if ( $this->settings['share_access'] === '1' ) { 
            $inline_script .= '
                sendAjaxRequest( "dmyzbp_check_password", `password=${encodeURIComponent(urlPassword)}` ) 
                .then( data => {
                    if ( data.success && dmyzbpPasswordPopup.style.display === "block") { 
                        location.reload();
                    } 
                })
                .catch(error => console.error("Error: Share access link validation error.", error)); 
                
            ';
        } 
        
        $inline_script .= '
            document.getElementById( "passwordForm" ).addEventListener( "submit", function( event ) {
                event.preventDefault();
                const passwordInput = document.getElementById( "passwordInput" ).value;

                fetch( "' . esc_url( admin_url("admin-ajax.php") ) . '", {  // Validate password
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" }, 
                    body: `action=dmyzbp_check_password&password=${encodeURIComponent( passwordInput )}&_ajax_nonce='. esc_js( $this->nonce ) .'`, 
                })
                .then( response => response.json() ) 
                .then( data => {
                    if ( data.success ) {
                        location.reload();
                    }
                    else {
                        document.getElementById( "errorMessage" ).style.display = "block";
                    }
                })
                .catch( error => console.error("Error: Password validation.", error ) );
            })
            });
        ';

        wp_add_inline_script( 'dmyzbp-secure-content', $inline_script );
        wp_enqueue_script( 'dmyzbp-secure-content' );
    }

    /* Add password protection popup and check password*/
    public function add_popup() {  // Get password and settings
        if ( !$this->check_plugin_settings() ) {
            return;
        }

        ?>
        <!-- Popup -->
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm" class="passwordForm">
                    <?php wp_nonce_field( 'dmyzbp_password_protection_popup', 'dmyzbp_nonce_password_protection_popup' ); ?>
                    <h3 class="passwordForm__title"><?php echo esc_html( $this->settings['popup_title'] ) ?></h3>
                    <label for="passwordInput" class="passwordForm__lable"><?php esc_html_e('Password:', 'blog-password-protection-dmyzbp'); ?></label>
                    <input type="password" id="passwordInput" class="passwordForm__input" required>
                    <div class="passwordForm__form-group">
                        <button type="submit" class="passwordForm__button"><?php esc_html_e('Submit', 'blog-password-protection-dmyzbp'); ?></button>
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url( $this->settings['return_back_link_url'] ); ?>"><?php echo esc_html( $this->settings['return_back_link_text'] ) ?></a>
                    </div>
                    <p id="errorMessage" class="passwordForm__errorMessage"><?php echo esc_html( $this->settings['error_message'] ) ?></p>
                </form>
            </div>
	    </div>	
        <?php
    }
}

SecureContent_DMYZBP::get_instance(); // Initialize the SecureContent class