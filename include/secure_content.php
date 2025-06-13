<?php
/**
 * Functions that secure content 
 *
 * Functions that apply content protection and add a password 
 * form on the page.
 *
 * @package Blog_Password_Protection
 */

namespace Blog_Password_Protection;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class SecureContent {
    private $validator;
    private static $instance = null;
    private $settings;
    private $nonce;

    private function __construct() {
        add_filter( 'the_content', [ $this, 'apply_protection' ] );
        add_filter( 'get_the_excerpt', [ $this, 'apply_protection' ] );
        add_action( 'wp_head', [ $this, 'add_popup' ] );
        add_action( 'wp_head', [ $this, 'cookie_and_share_access' ] );
        $this->settings = $this->get_plugin_settings();
        $this->validator = new Validation($this->settings); // Get instance of Validation class
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_plugin_settings() {
        $settings = wp_cache_get( 'bp_settings', 'options' );

        if ( $settings === false ) {
            return get_option( 'bp_settings', [] );
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

        if ( !( !is_front_page() && is_home() ) ) {
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

    /* Add password protection popup and check password*/
    public function add_popup() {  // Get password and settings
        $this->nonce = wp_create_nonce('bpp_ajax_nonce');
        if ( !$this->check_plugin_settings() ) {
            return;
        }

        ?>
        <!-- Popup -->
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm" class="passwordForm">
                    <?php wp_nonce_field( 'bpp_password_protection_popup', 'bpp_nonce_password_protection_popup' ); ?>
                    <h3 class="passwordForm__title"><?php echo esc_html( $this->settings['popup_title'] ) ?></h3>
                    <label for="passwordInput" class="passwordForm__lable"><?php esc_html_e('Password:', 'blog_password_protection'); ?></label>
                    <input type="password" id="passwordInput" class="passwordForm__input" required>
                    <div class="passwordForm__form-group">
                        <button type="submit" class="passwordForm__button"><?php esc_html_e('Submit', 'blog_password_protection'); ?></button>
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url( $this->settings['return_back_link_url'] ); ?>"><?php echo esc_html( $this->settings['return_back_link_text'] ) ?></a>
                    </div>
                    <p id="errorMessage" class="passwordForm__errorMessage"><?php echo esc_html( $this->settings['error_message'] ) ?></p>
                </form>
            </div>
	    </div>	
	    <script>
        document.getElementById( "passwordForm" ).addEventListener( "submit", function( event ) {
            event.preventDefault();
            const passwordInput = document.getElementById( "passwordInput" ).value;

            fetch( '<?php echo esc_url( admin_url("admin-ajax.php") ); ?>', {  // Validate password
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" }, 
                body: `action=bpp_check_password&password=${encodeURIComponent( passwordInput )}&_ajax_nonce=<?php echo esc_js($this->nonce); ?>`, 
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
            .catch( error => console.error( 'Error: Password validation.', error ) );
        });
        </script>
        <?php
    }

    public function cookie_and_share_access() {
        ?>
        <script type="text/javascript">  // Check password in url
        document.addEventListener( "DOMContentLoaded", function() {  // Check password from URL and cookie
            const urlPassword = window.location.hash.replace('#', '') || ' ';
            const bppPasswordPopup = document.getElementById('popupBackground');

            function sendAjaxRequest( action, bodyData ) {
                return fetch( '<?php echo esc_url( admin_url("admin-ajax.php") ); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&${bodyData}&_ajax_nonce=<?php echo esc_js($this->nonce); ?>`
                } )
                .then( response => response.json() );
            }
            sendAjaxRequest( 'bpp_check_cookie', '' )
            .then( data => {
                if ( !data.success ) {
                    bppPasswordPopup.style.display = 'block';// Show popup
                }
            } )
            .catch( error => console.error('Error: Cookie validation.', error) ); 

            <?php 
            if ( $this->settings['share_access'] === '1' ) { 
                ?>
                sendAjaxRequest( 'bpp_check_password', `password=${encodeURIComponent(urlPassword)}` ) 
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
}

SecureContent::get_instance(); // Initialize the SecureContent class