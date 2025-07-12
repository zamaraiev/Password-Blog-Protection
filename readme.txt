=== Blog Password Protection - DMYZBP ===
Contributors: dmytro1zamaraiev
Tags: password, protection, secure blog, user role restriction, category restriction
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.4.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

A simple and secure plugin to protect your entire blog or specific categories with a password popup.

== Description ==

**Blog Password Protection - DMYZBP** is a lightweight, easy-to-use plugin that helps you protect your entire WordPress blog or specific parts of it with a password. Whether you’re running a private journal, members-only site, or internal project blog — this plugin allows you to manage visibility and access without any complex setup.

It includes a customizable popup login prompt, the ability to restrict content by category, bypass access via secure URLs, and selectively disable protection for logged-in users by role.

🛡️ **Core Features:**

- Password-protect the entire blog or specific categories.
- Popup login box with custom title, message, and return button.
- Shareable secure access link (no password needed).
- Role-based exclusion (e.g. allow Editors or Admins without login).
- Feed protection and error messages for incorrect passwords.
- Flexible cookie session lifetime (e.g. 24h, 72h, 7d).
- Fully managed via a settings page in the WordPress admin panel.

== Plugin Structure ==

The plugin consists of the following core files:

1. **plugin_settings_page.php**  
   - Registers the plugin settings page under `Settings > Password Protection`.
   - Provides UI controls for all plugin options, such as:
     - Password setting
     - Cookie session lifetime
     - Category protection checkboxes
     - Secure link sharing toggle
     - User role bypass
     - Popup customization (title, text, return link)

2. **secure_content.php**  
   - Implements content filtering logic.
   - Hooks into the page rendering process to:
     - Detect protected categories or homepage
     - Display the popup login interface
   - Injects the popup modal HTML and JavaScript dynamically into the frontend.

3. **validation.php**  
   - Handles password validation via `POST`.
   - Sets encrypted cookies with the correct password session.
   - Supports error messaging for incorrect attempts.
   - Provides content masking when accessed through RSS feeds.
   - Validate session status

4. **Screenshots (screenshot-1.png, screenshot-2.png, screenshot-3.jpeg)**  
   - Preview the admin panel settings interface.
   - Show how the popup form appears on the frontend.
   - Example of protected content in action.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory or install via the WordPress plugin installer.
2. Activate the plugin through the ‘Plugins’ menu in WordPress.
3. Navigate to `Settings > Password Protection`.
4. Set your desired password and configuration options.
5. Save your settings and visit the blog frontend to test.

== Frequently Asked Questions ==

= Can I protect only certain categories? =
Yes, you can enable protection only for specific categories using checkboxes in the settings.

= How do I share access without a password? =
Enable the secure access link and share the generated URL. This URL includes a secure hash that bypasses the password screen.

= Can I allow Admins or Editors to bypass protection? =
Yes, the plugin allows you to disable password protection based on WordPress user roles.

= What happens on RSS feeds? =
RSS feed content is also protected. You can customize the feed message displayed to unauthorized users.

= How long does the password session last? =
You can configure cookie session duration in hours (e.g. 24 for 1 day, 168 for 7 days).

= Can I customize the login popup text and title? =
Yes, you can change the title of the popup, main message, error message, and "Return" button label and destination URL.

== Screenshots ==

1. Admin settings interface for configuring password, category filters, secure links, and messages.
2. Popup modal shown to visitors when password protection is enabled.
3. Blog frontend with protected content and password request.

== Changelog ==

= 1.4.0 =
* Initial release
* Added full settings page in admin panel
* Support for global and category-specific password protection
* User role exclusion (Admin, Editor, etc.)
* Secure sharing URL functionality
* Popup-based password input form
* Customizable messages and session duration
* Frontend and feed content protection

== Upgrade Notice ==

= 1.4.0 =
First public version. No upgrade issues expected.

== Credits ==

This plugin was created by Dmytro Zamaraiev for easy blog protection.

== License ==

This plugin is licensed under the GNU General Public License v3.0.