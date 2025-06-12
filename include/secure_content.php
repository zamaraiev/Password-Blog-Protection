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

    private function __construct() {
        add_filter( 'the_content', [ $this, 'apply_protection' ] );
        add_filter( 'get_the_excerpt', [ $this, 'apply_protection' ] );
        add_action( 'wp_head', [ $this, 'add_popup' ] );
        $this->validator = new Validation(); // Get instance of Validation class
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function check_plugin_settings( $settings ) {
        $user = wp_get_current_user();
        $roles = (array)$user->roles;

        if ( $settings['enable_protection'] === '0' ) {
            return false;
        }

        if ( !empty( $settings['disable_protection_for_certain_user_roles'] ) ) {
            foreach ( $settings['disable_protection_for_certain_user_roles'] as $disable_for ) {
                foreach ( $roles as $role ) {
                    if ( strtolower( $disable_for ) == strtolower( $role ) ) {
                        return false; // Do nothing if user has role that disables protection
                    }
                }
            } 
        }

        if ( ( $settings['blog_page_protection'] === '1' && ( !is_front_page() && is_home() ) ) 
	    || ( $settings['home_page_protection'] === '1' && is_front_page() ) ) {
            return true;
        }

        if ( !( !is_front_page() && is_home() ) ) {
            if ( in_category( $settings['protection_for_certain_categories'] ) && get_post_type() === 'post' ) {
                return true;
            }
        }
    
        return false;
    }

    public function apply_protection( $content ) {
        $settings = $this->validator->get_plugin_settings(); // Get settings

        if ( !$this->check_plugin_settings( $settings ) ) {
            return $content;
        }

        if ( in_category( $settings['protection_for_certain_categories'] ) ) { // Check if it is blog or post page
            if ( !$this->validator->validate_access_cookie( $settings ) && !is_feed() ) {  // Check if access is granted via cookie
                return '<p>' . $settings['restricted_message'] . '</p>';
            } 
            elseif ( !$this->validator->validate_access_cookie( $settings ) && is_feed() ) {
                return '<p>' . $settings['restricted_message_feeds'] . '</p>';
            }
        }
        return $content;
    }

    /* Add password protection popup and check password*/
    public function add_popup() {  // Get password and settings
        $settings = $this->validator->get_plugin_settings();

        if ( !$this->check_plugin_settings( $settings ) ) {
            return;
        }

        ?>
        <!-- Popup -->
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm" class="passwordForm">
                    <h3 class="passwordForm__title"><?php echo $settings['popup_title'] ?></h3>
                    <label for="passwordInput" class="passwordForm__lable">Password:</label>
                    <input type="password" id="passwordInput" class="passwordForm__input" required>
                    <div class="passwordForm__form-group">
                        <button type="submit" class="passwordForm__button">Submit</button>
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url( $settings['return_back_link_url'] ); ?>"><?php echo $settings['return_back_link_text'] ?></a>
                    </div>
                    <p id="errorMessage" class="passwordForm__errorMessage"><?php echo $settings['error_message'] ?></p>
                </form>
            </div>
	    </div>	
	    <script>
        document.getElementById( "passwordForm" ).addEventListener( "submit", function( event ) {
            event.preventDefault();
            const passwordInput = document.getElementById( "passwordInput" ).value;

            fetch( '<?php echo admin_url( "admin-ajax.php" ); ?>', {  // Validate password
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" }, 
                body: `action=check_password&password=${encodeURIComponent( passwordInput )}`, 
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
}

SecureContent::get_instance(); // Initialize the SecureContent class