<?php 
/*
 * Plugin Name: Password blog protection
 * Description: Add pasword protection for blog page or post
 * Version: 1.1
 * Author: Zamaraiev Dmytro
 * WC requires at least: 5.7
 * Requires at least: 5.5
 */
function add_password_protection_popup() {
	$password = get_option('password_protection_password', '');

	if ( !is_front_page() && is_home() || get_post_type() === 'post') {
        ?>
        <script type="text/javascript">
            const cipher = salt => {
                const textToChars = text => text.split('').map(c => c.charCodeAt(0));
                const byteHex = n => ("0" + Number(n).toString(16)).substr(-2);
                const applySaltToChar = code => textToChars(salt).reduce((a, b) => a ^ b, code);

                return text => text.split('')
                    .map(textToChars)
                    .map(applySaltToChar)
                    .map(byteHex)
                    .join('');
            };

            const decipher = salt => {
                const textToChars = text => text.split('').map(c => c.charCodeAt(0));
                const applySaltToChar = code => textToChars(salt).reduce((a, b) => a ^ b, code);
                return encoded => encoded.match(/.{1,2}/g)
                    .map(hex => parseInt(hex, 16))
                    .map(applySaltToChar)
                    .map(charCode => String.fromCharCode(charCode))
                    .join('');
            };

            const salt = 'mySecretSalt'; 
            const myCipher = cipher(salt);
            const dechiperPassword = "<?php echo $password; ?>";
            const cipherPassword = myCipher(dechiperPassword); 

            document.addEventListener("DOMContentLoaded", function() {
                const urlPassword = window.location.hash.replace('#', '') || '   ';
                const myDecipher = decipher(salt);

                (document.cookie.includes("blog_access=true") || myDecipher(urlPassword) === dechiperPassword) || (document.getElementById("popupBackground").style.display = "block");

                (myDecipher(urlPassword) === dechiperPassword) && (
                    document.cookie = "blog_access=true; path=/; max-age=" + 7 * 24 * 60 * 60,
                    location.hash = '',
                    location.reload()
                );
            });
        </script>
    
	    <div id="popupBackground">
		    <div id="passwordPopup">
                <form id="passwordForm">
                    <h3>Enter Password to Access the Blog</h3>
                    <label for="passwordInput">Password:</label>
                    <input type="password" id="passwordInput" class="form-control" required>
                    <button type="submit">Submit</button>
                    <p id="errorMessage">Incorrect password. Try again.</p>
                </form>
            </div>
	    </div>	

	    <script>
            document.getElementById("passwordForm").addEventListener("submit", function(event) {
                event.preventDefault();
                const password = document.getElementById("passwordInput").value;

                password === "<?php echo $password; ?>" 
                    ? (document.getElementById("popupBackground").style.display = "none",
                        document.cookie = "blog_access=true; path=/; max-age=" + 7 * 24 * 60 * 60,
                        location.reload())
                    : (document.getElementById("errorMessage").style.display = "block");
            });
        </script>
    <?php
	}
}
add_action('wp_head', 'add_password_protection_popup');

function enqueue_password_protection_styles() {
    wp_enqueue_style('password-protection-style', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_password_protection_styles');

function password_protection_settings_page() {
    add_options_page(
        'Password Protection Settings',
        'Password Protection',
        'manage_options',
        'password-protection',
        'password_protection_settings_page_html'
    );
}
add_action('admin_menu', 'password_protection_settings_page');

function password_protection_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['password_protection_password'])) {
        update_option('password_protection_password', sanitize_text_field($_POST['password_protection_password']));
        echo '<div class="updated"><p>Password updated successfully.</p></div>';
    }

    $password = get_option('password_protection_password', '');
    ?>
    <div class="wrap">
        <h1>Password Protection Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Password</th>
                    <td><input type="password" name="password_protection_password" value="<?php echo esc_attr($password); ?>" /></td>
                </tr>
            </table>
            <?php submit_button('Save Password'); ?>
        </form>
    </div>
    <?php
}