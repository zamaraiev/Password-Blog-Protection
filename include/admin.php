<?php
/**
 * Plagin setings 
 *
 * Functions that add plugin settings to the admin panel 
 *
 * @package Blog_Password_Protection
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Add settings page */
function bpp_settings_page() {
    add_options_page(
        'Password Protection Settings',
        'Password Protection',
        'manage_options',
        'password-protection',
        'bpp_settings_page_html'
    );
}
add_action('admin_menu', 'bpp_settings_page');

/* Add settings page */
function bpp_settings_page_html() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bpp_settings_nonce'])) {
        if (!isset($_POST['bpp_settings_nonce'])
            || !wp_verify_nonce($_POST['bpp_settings_nonce'], 'bpp_update_settings')) {
            die('Security check failed');
        }
    }

    if (isset($_POST['password_protection_password'])) {
        $enable_protection = isset($_POST['password_protection_enabled']) ? '1' : '0';

        update_option('password_protection_enabled', $enable_protection);
        if (!empty($_POST['password_protection_password'])) {  // validate password
            $encrypted_password = wp_hash_password($_POST['password_protection_password']);
            $cookie_session_lifetime = absint($_POST['password_protection_cookie_session_lifetime']);
            $share_access = isset($_POST['password_protection_share_access']) ? '1' : '0';
            $enable_protection_for_certain_categories_array = isset($_POST['password_protection_for_certain_categories']) 
            ? array_map('sanitize_text_field', $_POST['password_protection_for_certain_categories']) : [];
            $disable_protection_for_certain_user_roles_array = isset($_POST['password_protection_for_certain_user_roles']) 
            ? array_map('sanitize_text_field', $_POST['password_protection_for_certain_user_roles']) : [];
            $blog_page_protection = isset($_POST['password_protection_for_blog_page']) ? '1' : '0';
            $restricted_message = sanitize_text_field($_POST['password_protection_resctircted_content_message']);
            $restricted_message_feeds = sanitize_text_field($_POST['password_protection_resctircted_content_message_feeds']);
            $return_back_link_url = sanitize_text_field($_POST['password_protection_return_back_link_url']);
            $return_back_link_text = sanitize_text_field($_POST['password_protection_return_back_link_text']);
            $error_message_field = sanitize_text_field($_POST['password_protection_error_message']);

            update_option('password_protection_password', $encrypted_password);
            update_option('password_protection_cookie_session_lifetime', $cookie_session_lifetime);  // Set cookie session lifetime.
            update_option('password_protection_share_access', $share_access);  // Allow share access.
            update_option('password_protection_for_certain_categories', $enable_protection_for_certain_categories_array);   // Update categories.
            update_option('password_protection_for_certain_user_roles', $disable_protection_for_certain_user_roles_array);  // Update certain user roles.
            update_option('password_protection_for_blog_page', $blog_page_protection);  // Enable protection for blog page.
            update_option('password_protection_resctircted_content_message', $restricted_message); // Update restricted content message.
            update_option('password_protection_resctircted_content_message_feeds', $restricted_message_feeds);  // Update restricted content message for feeds.
			update_option('password_protection_return_back_link_url', $return_back_link_url);  // Update 'Return Back' link settings.
			update_option('password_protection_return_back_link_text', $return_back_link_text);  // Update 'Return Back' link text.
            update_option('password_protection_error_message', $error_message_field);  // Update incorect password(error) message.

            echo '<div class="updated"><p>Updated successfully.</p></div>';
        } 
        else echo '<div class="error"><p>Please enter a valid password.</p></div>';
    }

    global $wp_roles;
    $list_of_categories = get_categories(array('hide_empty' => 0)); // Get list of categories
    if (!isset( $wp_roles )) $wp_roles = new WP_Roles();
    $active_users_roles = $wp_roles->get_names(); // Get list of user roles

    // display the settings form
    $enable_protection = get_option('password_protection_enabled', '1'); // '1' for default
    $password = get_option('password_protection_password', '123qwerty'); // 123qwerty for default
    $cookie_session_lifetime = get_option('password_protection_cookie_session_lifetime', '24'); // '24' for default
    $is_share_access_turned_on = get_option('password_protection_share_access', '1'); // '1' for default
    $enable_protection_for_certain_categories = get_option('password_protection_for_certain_categories', []);
    $disable_protection_for_certain_user_roles = get_option('password_protection_for_certain_user_roles', []);
    $enable_protection_for_blog_page = get_option('password_protection_for_blog_page', '0'); // '0' for default
	$return_back_link_url = get_option('password_protection_return_back_link_url', esc_url(home_url())); // home url for default
	$return_back_link_text = get_option('password_protection_return_back_link_text', 'Return to the main page'); // 'Return to the main page' for default
    $restricted_message = get_option('password_protection_resctircted_content_message', 
    'This content is password protected. Please enter the password below to access it.'); 
    // 'This content is password protected. Please enter the password below to access it.' for default
    $restricted_message_feeds = get_option('password_protection_resctircted_content_message_feeds', 
    'This content is password protected. Please enter the password below to access it.');  
    // 'This content is password protected. Please enter the password below to access it.' for default
    $error_message = get_option('password_protection_error_message', 'Incorrect password. Try again.');
    ?>
    <div class="wrap">
        <h1>Password Protection Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('bpp_update_settings', 'bpp_settings_nonce'); ?>
            <p> 
                This plugin provides robust content protection features for your WordPress site. 
                It allows you to secure posts or pages with a password and customize the message 
                displayed to users attempting to access protected content. Additionally, the plugin
                offers a user-friendly popup for enhanced interaction, including a customizable
                "Return Back" link to improve user navigation.
            </p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable protection</th>
                    <td>
                        <input type="checkbox" name="password_protection_enabled" value="1" <?php checked('1', $enable_protection); ?> />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Password</th>
                    <td>
                        <input type="password" id="change_password_input_btn" style="display:none;" name="password_protection_password" required />
                        <button type="button" id="change_password_btn">Change password</button>
                        <p>Set your password. By default "123qwerty".</p>
                        <script>
                            document.getElementById("change_password_btn").addEventListener("click", function() {
                                document.getElementById("change_password_input_btn").style.display = "block";
                            })
                        </script>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Cookie session lifetime</th>
                    <td>
                        <input type="number" name="password_protection_cookie_session_lifetime" 
                        value="<?php echo esc_attr( $cookie_session_lifetime); ?>" />
                        <p>Enter the lifetime of the cookie in hours. By default set to one day. 
                            Examples: 48 hours/2 days, 72 hours/3 days, 168 hours/7 days</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable protection for certain categories</th>
                    <td>
                        <input type="checkbox" name="password_protection_for_blog_page" 
                        value="1" <?php checked('1', $enable_protection_for_blog_page); ?> />
                        Enable for blog page<br>
                        <?php if (!empty($list_of_categories)) {
                            foreach ($list_of_categories as $category) { ?>
                                <input type="checkbox" 
                                       name="password_protection_for_certain_categories[]" 
                                       value="<?php echo esc_attr($category->cat_ID); ?>" 
                                       <?php checked(in_array($category->cat_ID, $enable_protection_for_certain_categories)); ?> />
                                <?php echo esc_html($category->name); ?><br>
                            <?php }
                        } 
                        else echo '<p>No categories available.</p>'; ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Share access to the blog without password using secure link</th>
                    <td>
                        <input type="checkbox" name="password_protection_share_access" 
                        value="1" <?php checked('1', $is_share_access_turned_on); ?> />
                        <?php
                         // Display link for sharing access
                        if ($is_share_access_turned_on === '1') {
                            echo '<b>You can use this URL to share access to the blog without password: ' 
                                . esc_url(get_permalink(get_option('page_for_posts'))) . '#your password here</b>';
                        }
                        ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Disable password protection for certain user roles</th>
                    <td>
                    <?php if (!empty($active_users_roles)) {
                            foreach ($active_users_roles as $role_key => $role_name) { ?>
                                <input type="checkbox" 
                                       name="password_protection_for_certain_user_roles[]" 
                                       value="<?php echo esc_attr($role_name); ?>" 
                                       <?php checked(in_array($role_name, $disable_protection_for_certain_user_roles)); ?> />
                                <?php echo esc_html($role_name); ?><br>
                            <?php }
                        } 
                        else{
                            echo '<p>No roles available.</p>';
                        } ?>
                    </td>
                </tr>
				<tr valign="top">
                    <th scope="row">Popup 'Return Back' Link Settings</th>
                    <td>
                        <input type="text" name="password_protection_return_back_link_url" 
                        value="<?php echo esc_attr($return_back_link_url); ?>"/>
						<p>"Return Back" link url</p>
                        <input type="text" name="password_protection_return_back_link_text" 
                        value="<?php echo esc_attr($return_back_link_text); ?>"/>
                        <p>"Return Back" link text</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Message shown to users when accessing password-protected content.</th>
                    <td>
                        <textarea name="password_protection_resctircted_content_message" rows="4" cols="50"><?php echo esc_attr($restricted_message); ?></textarea>
                        <p>Message shown to users when accessing password-protected content.</p>
                        <textarea name="password_protection_resctircted_content_message_feeds" rows="4" cols="50"><?php echo esc_attr($restricted_message_feeds); ?></textarea>
                        <p>Message shown to users when accessing password-protected content in feeds.</p>
                        <p>By default "This content is password protected. Please enter the password below to access it."</p>
                        <textarea name="password_protection_error_message" rows="4" cols="50"><?php echo esc_attr($error_message); ?></textarea>
                        <p>Message shown to users when they entering the wrong password.</p>
                        <p>By default "Incorrect password. Try again."</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}
