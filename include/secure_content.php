<?php
/**
 * Functions that secure content 
 *
 * Functions that apply content protection and add a password 
 * form on the page.
 *
 * @package Blog_Password_Protection
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

function bpp_check_plagin_settings( $settings ) {
    $user = wp_get_current_user();
    $roles = (array)$user->roles;

    if ( $settings['enable_protection'] === '0' 
        || ( $settings['blog_page_protection'] === '0' && ( !is_front_page() && is_home() ) ) ) {
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
    return true;
}

function bpp_apply_protection( $content ) {
    $settings = get_option('bp_settings', []);

    if ( !bpp_check_plagin_settings( $settings ) ) {
        return $content;
    }

    if ( in_category( $settings['protection_for_certain_categories'] ) ) { // Check if it is blog or post page
        if ( !bpp_validate_access_cookie() && !is_feed() ) {  // Check if access is granted via cookie
            return '<p>' . $settings['restricted_message'] . '</p>';
        } 
        elseif ( !bpp_validate_access_cookie() && is_feed() ) {
            return '<p>' . $settings['restricted_message_feeds'] . '</p>';
        }
    }
    return $content;
}
add_filter( 'the_content', 'bpp_apply_protection' );
add_filter( 'get_the_excerpt', 'bpp_apply_protection' );

/* Add password protection popup and check password*/
function bpp_add_popup() {  // Get password and settings
    $settings = get_option( 'bp_settings', [] );

    if ( !bpp_check_plagin_settings( $settings ) ) {
        return;
    }

	if ( in_category( $settings['protection_for_certain_categories'] ) ) { // Check if it is blog or post page
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
add_action( 'wp_head', 'bpp_add_popup' );
