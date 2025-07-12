<?php
/**
 * Plagin setings 
 *
 * Functions that add plugin settings to the admin panel 
 *
 * @package Blog_Password_Protection_DMYZBP
 */

namespace Blog_Password_Protection_DMYZBP;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_DMYZBP {
    private static $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [$this, 'add_settings_page'] );
        add_action( 'admin_init', [$this, 'register_settings'] );
        add_action( 'admin_enqueue_scripts', [$this, 'settings_enqueue_scripts'] );
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
        register_setting('dmyzbp_plugin_settings', 'dmyzbp_plugin_settings', [
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
            if ( isset( $settings['encrypted_password'] ) && !empty( $settings['encrypted_password'] ) ) {
                $sanitized['encrypted_password'] = $settings['encrypted_password'];
            } 
            else {
                $sanitized['encrypted_password'] = wp_hash_password( '123qwerty' ); // Default password
            }
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
        return get_option( 'dmyzbp_plugin_settings', [
            'enable_protection' => '0',
            'cookie_lifetime' => '24',
            'share_access' => '0',
            'protection_for_certain_categories' => [],
            'disable_protection_for_certain_user_roles' => [],
            'blog_page_protection' => '0',
            'home_page_protection' => '0',
            'restricted_message' => 'This content is password protected. Please enter the password below to access it.',
            'restricted_message_feeds' => 'This content is password protected. Please enter the password below to access it.',
            'return_back_link_url' => home_url(),
            'return_back_link_text' => 'Return to the main',
            'error_message' => 'Incorrect password. Try again.',
            'popup_title' => 'Enter Password to Access the Blog'
        ] );
    }

    public function settings_enqueue_scripts() {
        wp_register_script( 'dmyzbp-admin-inline', '', [], '1.4.2', true );

        $inline_script = '
            document.getElementById("change_password_btn")?.addEventListener("click", function() {
                let password_input = "<input id=\"change_password_input_btn\" name=\"dmyzbp_plugin_settings[password]\" required />";
                this.insertAdjacentHTML("beforebegin", password_input);
            }, { once: true });
        ';

        wp_add_inline_script( 'dmyzbp-admin-inline', $inline_script );
        wp_enqueue_script( 'dmyzbp-admin-inline' );
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
            <h1><?php esc_html_e('Password Protection Settings', 'blog-password-protection-dmyzbp'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    //wp_nonce_field('dmyzbp_save_settings', 'dmyzbp_settings_nonce');
                    settings_fields( 'dmyzbp_plugin_settings' );
                    do_settings_sections( 'dmyzbp_plugin_settings' );
                ?>
                <p> 
                    <?php esc_html_e('This plugin provides robust content protection features for your WordPress site. 
                    It allows you to secure posts or pages with a password and customize the message 
                    displayed to users attempting to access protected content. Additionally, the plugin
                    offers a user-friendly popup for enhanced interaction, including a customizable
                    "Return Back" link to improve user navigation.', 'blog-password-protection-dmyzbp'); ?>
                </p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable protection', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <input type="checkbox" name="dmyzbp_plugin_settings[protection_enabled]" value="1" <?php checked( '1', $settings['enable_protection'] ); ?> />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Password', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <a id="change_password_btn"><?php esc_html_e('Change password', 'blog-password-protection-dmyzbp'); ?></a>
                            <p><?php esc_html_e('Set your password. By default "123qwerty".', 'blog-password-protection-dmyzbp'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Cookie session lifetime', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <input type="number" name="dmyzbp_plugin_settings[cookie_session_lifetime]" value="<?php echo esc_attr( $settings['cookie_lifetime'] ); ?>" />
                            <p>
                                <?php esc_html_e('Enter the lifetime of the cookie in hours. By default set to one day. 
                                Examples: 48 hours/2 days, 72 hours/3 days, 168 hours/7 days', 'blog-password-protection-dmyzbp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable protection for certain categories', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <input type="checkbox" name="dmyzbp_plugin_settings[home_page_protection]" value="1" <?php checked( '1', $settings['home_page_protection'] ); ?> />
                            <p><?php esc_html_e('Enable for home page', 'blog-password-protection-dmyzbp'); ?></p><br>
                            <input type="checkbox" name="dmyzbp_plugin_settings[blog_page_protection]" value="1" <?php checked( '1', $settings['blog_page_protection'] ); ?> />
                            <p><?php esc_html_e('Enable for blog page. Disable if blog page is the home page.', 'blog-password-protection-dmyzbp'); ?></p><br>
                            <?php
                                if ( !empty( $list_of_categories ) ) {
                                    foreach ( $list_of_categories as $category ) { 
                                        ?>
                                        <input type="checkbox" name="dmyzbp_plugin_settings[protection_for_certain_categories][]" 
                                            value="<?php echo esc_attr( $category->cat_ID ); ?>" 
                                            <?php checked( in_array( $category->cat_ID, $settings['protection_for_certain_categories'] ) ); ?> 
                                        />
                                        <?php echo esc_html( $category->name ); ?> 
                                        <br>
                                        <?php 
                                    }
                                } 
                                else {
                                    echo '<p>' . esc_html__( 'No categories available.', 'blog-password-protection-dmyzbp' ) . '</p>'; 
                                }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Share access to the blog without password using secure link', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <input type="checkbox" name="dmyzbp_plugin_settings[share_access]" value="1" <?php checked( '1', $settings['share_access'] ); ?> />
                            <?php
                                if ( $settings['share_access'] === '1' ) {  // Display link for sharing access
                                    echo '<b>' . esc_html__( 'You can use this URL to share access to the blog without password: ', 'blog-password-protection-dmyzbp' ) . ' ' 
                                    . esc_url( get_permalink( get_option( 'page_for_posts' ) ) ) . '#your password here</b>';
                                }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Disable password protection for certain user roles', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <?php 
                                if ( !empty( $active_users_roles ) ) {
                                    foreach ( $active_users_roles as $role_key => $role_name ) { 
                                        ?>
                                        <input type="checkbox" name="dmyzbp_plugin_settings[disable_for_certain_user_roles][]" value="<?php echo esc_attr( $role_name ); ?>" 
                                        <?php checked( in_array( $role_name, $settings['disable_protection_for_certain_user_roles'] ) ); ?> />
                                        <?php echo esc_html( $role_name ); ?><br>
                                        <?php 
                                    }
                                } 
                                else{
                                    echo '<p>' . esc_html__( 'No roles available.', 'blog-password-protection-dmyzbp' ) . '</p>';
                                } 
                            ?>
                        </td>
                    </tr>
				    <tr valign="top">
                        <th scope="row"><?php esc_html_e("Popup 'Return Back' Link Settings", 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <input type="text" name="dmyzbp_plugin_settings[return_back_link_url]" value="<?php echo esc_attr( $settings['return_back_link_url'] ); ?>"/>
						    <p><?php esc_html_e('"Return Back" link url', 'blog-password-protection-dmyzbp'); ?></p>
                            <input type="text" name="dmyzbp_plugin_settings[return_back_link_text]" value="<?php echo esc_attr( $settings['return_back_link_text'] ); ?>"/>
                            <p><?php esc_html_e('"Return Back" link text', 'blog-password-protection-dmyzbp'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Popup title', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <textarea name="dmyzbp_plugin_settings[popup_title]" rows="1" cols="30"><?php echo esc_attr( $settings['popup_title'] ); ?></textarea>
                            <p><?php esc_html_e('Title for the popup. By default "Enter Password to Access the Blog"', 'blog-password-protection-dmyzbp'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Message shown to users when accessing password-protected content.', 'blog-password-protection-dmyzbp'); ?></th>
                        <td>
                            <textarea name="dmyzbp_plugin_settings[restricted_message]" rows="4" cols="50"><?php echo esc_attr( $settings['restricted_message'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when accessing password-protected content.', 'blog-password-protection-dmyzbp'); ?></p>
                            <textarea name="dmyzbp_plugin_settings[restricted_message_feeds]" rows="4" cols="50"><?php echo esc_attr( $settings['restricted_message_feeds'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when accessing password-protected content in feeds.', 'blog-password-protection-dmyzbp'); ?></p>
                            <p><?php esc_html_e('By default "This content is password protected. Please enter the password below to access it."', 'blog-password-protection-dmyzbp'); ?></p>
                            <textarea name="dmyzbp_plugin_settings[error_message]" rows="4" cols="50"><?php echo esc_attr( $settings['error_message'] ); ?></textarea>
                            <p><?php esc_html_e('Message shown to users when they entering the wrong password.', 'blog-password-protection-dmyzbp'); ?></p>
                            <p><?php esc_html_e('By default "Incorrect password. Try again."', 'blog-password-protection-dmyzbp'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}

Settings_DMYZBP::get_instance();