<?php
/**
 * Plagin setings 
 *
 * Functions that add plugin settings to the admin panel 
 *
 * @package Blog_Password_Protection
 */

namespace Blog_Password_Protection;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [$this, 'add_settings_page'] );
        add_action( 'admin_init', [$this, 'register_settings'] );
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_settings_page() {
        add_options_page(
            'Password Protection Settings',
            'Password Protection',
            'manage_options',
            'password-protection',
            [$this, 'settings_page_html']
        );
    }
    
    public function register_settings() {
        register_setting('bpp_plugin_settings', 'bp_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings( $input ) {
        $settings = $this->get_settings();
        $sanitized = [];

        if ( isset( $input['password'] ) ) {
			$sanitized['encrypted_password'] = wp_hash_password( $input['password'] );                  
		}
		else {
			$sanitized['encrypted_password'] = $settings['encrypted_password'];
		}
        
        $sanitized['enable_protection'] = isset( $input['protection_enabled'] ) ? '1' : '0';
        $sanitized['cookie_lifetime'] = isset( $input['cookie_session_lifetime'] ) 
                                    ? absint( $input['cookie_session_lifetime'] ) 
                                    : 24;
        $sanitized['share_access'] = isset( $input['share_access'] ) ? '1' : '0';
        $sanitized['protection_for_certain_categories'] = isset( $input['protection_for_certain_categories'] ) 
                                                        ? array_map( 'sanitize_text_field' , $input['protection_for_certain_categories'] ) 
                                                        : [];
        $sanitized['disable_protection_for_certain_user_roles'] = isset( $input['disable_for_certain_user_roles'] ) 
                                                                ? array_map( 'sanitize_text_field' , $input['disable_for_certain_user_roles'] ) 
                                                                : [];
        $sanitized['blog_page_protection'] = isset( $input['blog_page_protection'] ) ? '1' : '0';
        $sanitized['home_page_protection'] = isset( $input['home_page_protection'] ) ? '1' : '0';
        $sanitized['restricted_message'] = isset( $input['restricted_message'] ) 
                                        ? sanitize_text_field( $input['restricted_message'] ) 
                                        : 'This content is password protected. Please enter the password below to access it.';
        $sanitized['restricted_message_feeds'] = isset( $input['restricted_message_feeds'] ) 
                                                ? sanitize_text_field( $input['restricted_message_feeds'] ) 
                                                : 'This content is password protected. Please enter the password below to access it.';
        $sanitized['popup_title'] = isset( $input['popup_title'] ) ? sanitize_text_field( $input['popup_title'] ) : 'Enter Password to Access the Blog';
        $sanitized['return_back_link_url'] = isset( $input['return_back_link_url'] ) 
                                            ? esc_url_raw( $input['return_back_link_url'] ) 
                                            : home_url();
        $sanitized['return_back_link_text'] = isset( $input['return_back_link_text'] ) 
                                            ? sanitize_text_field( $input['return_back_link_text'] ) 
                                            : 'Return to the main page';
        $sanitized['error_message'] = isset( $input['error_message'] ) 
                                    ? sanitize_text_field( $input['error_message'] ) 
                                    : 'Incorrect password. Try again.';
    
        return $sanitized;
    }

    public function get_settings() {
        return get_option( 'bp_settings', [
            'enable_protection' => '1',
            'cookie_lifetime' => '24',
            'share_access' => '1',
            'protection_for_certain_categories' => [],
            'disable_protection_for_certain_user_roles' => [],
            'blog_page_protection' => '1',
            'home_page_protection' => '0',
            'restricted_message' => 'This content is password protected. Please enter the password below to access it.',
            'restricted_message_feeds' => 'This content is password protected. Please enter the password below to access it.',
            'return_back_link_url' => home_url(),
            'return_back_link_text' => 'Return to the main',
            'error_message' => 'Incorrect password. Try again.',
            'popup_title' => 'Enter Password to Access the Blog'
        ] );
    }

    public function settings_page_html() {
        global $wp_roles;

        if ( !isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }     

        $list_of_categories = get_categories( array( 'hide_empty' => 0 ) ); // Get list of categories
        $settings = $this->get_settings();
        $active_users_roles = $wp_roles->get_names(); // Get list of 2user roles
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Password Protection Settings', 'bpp_blog_password_protection'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    //wp_nonce_field('bpp_save_settings', 'bpp_settings_nonce');
                    settings_fields( 'bpp_plugin_settings' );
                    do_settings_sections( 'bpp_plugin_settings' );
                ?>
                <p> 
                    <?php esc_html_e('This plugin provides robust content protection features for your WordPress site. 
                    It allows you to secure posts or pages with a password and customize the message 
                    displayed to users attempting to access protected content. Additionally, the plugin
                    offers a user-friendly popup for enhanced interaction, including a customizable
                    "Return Back" link to improve user navigation.', 'bpp_blog_password_protection'); ?>
                </p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable protection', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <input type="checkbox" name="bp_settings[protection_enabled]" value="1" <?php checked( '1', $settings['enable_protection'] ); ?> />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Password', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <a id="change_password_btn"><?php esc_html_e('Change password', 'bpp_blog_password_protection'); ?></a>
                            <p><?php esc_html_e('Set your password. By default "123qwerty".', 'bpp_blog_password_protection'); ?></p>
                            <script>
                                document.getElementById( 'change_password_btn' ).addEventListener( 'click', function() {
                                    let password_input = '<input id="change_password_input_btn" name="bp_settings[password]" required />';
                                    this.insertAdjacentHTML( 'beforebegin', password_input);
                                } , { once: true });
                            </script>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Cookie session lifetime', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <input type="number" name="bp_settings[cookie_session_lifetime]" value="<?php echo esc_attr( $settings['cookie_lifetime'] ); ?>" />
                            <p>
                                <?php esc_html_e('Enter the lifetime of the cookie in hours. By default set to one day. 
                                Examples: 48 hours/2 days, 72 hours/3 days, 168 hours/7 days', 'bpp_blog_password_protection'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable protection for certain categories', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <input type="checkbox" name="bp_settings[home_page_protection]" value="1" <?php checked( '1', $settings['home_page_protection'] ); ?> />
                            <p><?php esc_html_e('Enable for home page', 'bpp_blog_password_protection'); ?></p><br>
                            <input type="checkbox" name="bp_settings[blog_page_protection]" value="1" <?php checked( '1', $settings['blog_page_protection'] ); ?> />
                            <p><?php esc_html_e('Enable for blog page. Disable if blog page is the home page.', 'bpp_blog_password_protection'); ?></p><br>
                            <?php
                                if ( !empty( $list_of_categories ) ) {
                                    foreach ( $list_of_categories as $category ) { 
                                        ?>
                                        <input type="checkbox" name="bp_settings[protection_for_certain_categories][]" 
                                            value="<?php echo esc_attr( $category->cat_ID ); ?>" 
                                            <?php checked( in_array( $category->cat_ID, $settings['protection_for_certain_categories'] ) ); ?> 
                                        />
                                        <?php echo esc_html( $category->name ); ?> 
                                        <br>
                                        <?php 
                                    }
                                } 
                                else {
                                    echo '<p>' . esc_html__( 'No categories available.', 'bpp_blog_password_protection' ) . '</p>'; 
                                }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Share access to the blog without password using secure link', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <input type="checkbox" name="bp_settings[share_access]" value="1" <?php checked( '1', $settings['share_access'] ); ?> />
                            <?php
                                if ( $settings['share_access'] === '1' ) {  // Display link for sharing access
                                    echo '<b>' . esc_html__( 'You can use this URL to share access to the blog without password: ', 'bpp_blog_password_protection' ) . ' ' 
                                    . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ) . '#your password here</b>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Disable password protection for certain user roles', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <?php 
                                if ( !empty( $active_users_roles ) ) {
                                    foreach ( $active_users_roles as $role_key => $role_name ) { 
                                        ?>
                                        <input type="checkbox" name="bp_settings[disable_for_certain_user_roles][]" value="<?php echo esc_attr( $role_name ); ?>" 
                                        <?php checked( in_array( $role_name, $settings['disable_protection_for_certain_user_roles'] ) ); ?> />
                                        <?php echo esc_html( $role_name ); ?><br>
                                        <?php 
                                    }
                                } 
                                else{
                                    echo '<p>' . esc_html__( 'No roles available.', 'bpp_blog_password_protection' ) . '</p>';
                                } 
                            ?>
                        </td>
                    </tr>
				    <tr valign="top">
                        <th scope="row"><?php esc_html_e("Popup 'Return Back' Link Settings", 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <input type="text" name="bp_settings[return_back_link_url]" value="<?php echo esc_attr( $settings['return_back_link_url'] ); ?>"/>
						    <p><?php esc_html_e('"Return Back" link url', 'bpp_blog_password_protection'); ?></p>
                            <input type="text" name="bp_settings[return_back_link_text]" value="<?php echo esc_attr( $settings['return_back_link_text'] ); ?>"/>
                            <p><?php esc_html_e('"Return Back" link text', 'bpp_blog_password_protection'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Popup title', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <textarea name="bp_settings[popup_title]" rows="1" cols="30"><?php echo esc_attr( $settings['popup_title'] ); ?></textarea>
                            <p><?php esc_html_e('Title for the popup. By default "Enter Password to Access the Blog"', 'bpp_blog_password_protection'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Message shown to users when accessing password-protected content.', 'bpp_blog_password_protection'); ?></th>
                        <td>
                            <textarea name="bp_settings[restricted_message]" rows="4" cols="50"><?php echo esc_attr( $settings['restricted_message'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when accessing password-protected content.', 'bpp_blog_password_protection'); ?></p>
                            <textarea name="bp_settings[restricted_message_feeds]" rows="4" cols="50"><?php echo esc_attr( $settings['restricted_message_feeds'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when accessing password-protected content in feeds.', 'bpp_blog_password_protection'); ?></p>
                            <p><?php esc_html_e('By default "This content is password protected. Please enter the password below to access it."', 'bpp_blog_password_protection'); ?></p>
                            <textarea name="bp_settings[error_message]" rows="4" cols="50"><?php echo esc_attr( $settings['error_message'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when they entering the wrong password.', 'bpp_blog_password_protection'); ?></p>
                            <p><?php esc_html_e('By default "Incorrect password. Try again."', 'bpp_blog_password_protection'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}

Settings::get_instance();