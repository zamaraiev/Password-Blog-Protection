<?php
/**
 * Functions that secure content 
 *
 * Functions that apply content protection and add a password 
 * form on the page.
 *
 * @package Blog_Password_Protection
 */

if (!defined('ABSPATH')) exit;

/* Additional protection functions to hide content*/
function bpp_content_secured($content) {
    // Replace content if access is restricted
    $restricted_content_message = get_option('password_protection_resctircted_content_message', 'This content is password protected. Please enter the password below to access it.');
    return '<p>' . $restricted_content_message . '</p>';
}

function bpp_apply_protection($content) {
    $password = get_option('password_protection_password', '');
    $protection_for_certain_categories = get_option('password_protection_for_certain_categories', []);
    $blog_page_protection = get_option('password_protection_for_blog_page', '0');
    $enable_protection = get_option('password_protection_enabled', '1'); // '1' for default
    $disable_protection_for_certain_user_roles = get_option('password_protection_for_certain_user_roles', []);
    $user = wp_get_current_user();
    $roles = (array)$user->roles;

    if ($enable_protection === '0' 
        || ($blog_page_protection === '0' && (!is_front_page() && is_home()))) return $content;
    foreach ($disable_protection_for_certain_user_roles as $disable_for) {
        foreach ($roles as $role) {
            if (strtolower($disable_for) == strtolower($role)) return $content;
        }
    }
    if (in_category($protection_for_certain_categories)) { // Check if it is blog or post page
        if (empty($password)) return $content;  // If no password is set, do nothing
        elseif (!bpp_validate_access_cookie()) return bpp_content_secured($content);  // Check if access is granted via cookie
        elseif (is_feed()) return bpp_content_secured($content);
    }
    return $content;
}
add_filter('the_content', 'bpp_apply_protection');
add_filter('get_the_excerpt', 'bpp_apply_protection');

/* Add password protection popup and check password*/
function bpp_add_popup() {  // Get password and settings
    $enable_protection = get_option('password_protection_enabled', '1'); // '1' for default
	$password = get_option('password_protection_password', '123qwerty');
    $is_share_access_turned_on = get_option('password_protection_share_access', '1');
    $protection_for_certain_categories = get_option('password_protection_for_certain_categories', []);
    $blog_page_protection = get_option('password_protection_for_blog_page', '0');
	$return_back_link_url = get_option('password_protection_return_back_link_url', esc_url(home_url()));
	$return_back_link_text = get_option('password_protection_return_back_link_text', 'Return to the main page');
    $disable_protection_for_certain_user_roles = get_option('password_protection_for_certain_user_roles', []);
    $error_message = get_option('password_protection_error_message', 'Incorrect password. Try again.');
    $user = wp_get_current_user();
    $roles = (array)$user->roles;

    if ( $enable_protection === '0' ) {
        return; 
    }
    foreach ($disable_protection_for_certain_user_roles as $disable_for) {
        foreach ($roles as $role) {
            if (strtolower($disable_for) == strtolower($role)) {
                return; // Do nothing if user has role that disables protection
            }
        }
    } 
    ?><script type="text/javascript">  // Check password in url
        document.addEventListener("DOMContentLoaded", function() {  // Check password from URL and cookie
            const urlPassword = window.location.hash.replace('#', '') || ' ';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {  // Validate cookie
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=check_cookie`,
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('popupBackground').style.display = 'none'; // Hide popup
                }
                else {
                    document.getElementById('popupBackground').style.display = 'block';// Show popup
                    console.log('Access denied');
                }
            })
            .catch(error => console.error('Error with cookie validation', error)); 
            <?php if($is_share_access_turned_on === '1') { ?>
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {  // Validate password from URL
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
            <?php } ?>
        });
    </script><?php
    if ($blog_page_protection === '0' && (!is_front_page() && is_home())) {
        return; 
    }
	elseif (in_category($protection_for_certain_categories)) { // Check if it is blog or post page
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
                        <a class="passwordForm__goHomeLink" href="<?php echo esc_url($return_back_link_url); ?>"><?php echo $return_back_link_text ?></a>
                    </div>
                    <p id="errorMessage" class="passwordForm__errorMessage"><?php echo $error_message ?></p>
                </form>
            </div>
	    </div>	
	    <script>
            document.getElementById("passwordForm").addEventListener("submit", function(event) {
                event.preventDefault();
                const password = document.getElementById("passwordInput").value;

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {  // Validate password
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" }, 
                    body: `action=check_password&password=${encodeURIComponent(password)}`, 
                })
                .then(response => response.json()) 
                .then(data => {
                    if (data.success) location.reload();
                    else document.getElementById("errorMessage").style.display = "block";
                })
                .catch(error => console.error('Error:', error));
            });
        </script><?php
    }
}
add_action('wp_head', 'bpp_add_popup');
