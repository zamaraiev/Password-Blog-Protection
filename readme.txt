=== Blog Protection Plugin ===
Contributors: zamaraievdrdmytro
Tags: security, password, content protection, access control
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-3.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

**Blog Protection Plugin** is a WordPress plugin that allows you to protect your blog content with a password. Use it to restrict access to posts and pages, ensuring content privacy for selected users.

### Key Features:
- Protect content with a password.
- Modal window for password input.
- Customizable styling via CSS.
- Built-in validation mechanisms.
- Admin panel for configuration.

== Installation ==

1. Upload `blog-protection-plugin.zip` to the `/wp-content/plugins/` directory.
2. Unzip the archive.
3. Log into your WordPress admin panel.
4. Go to the "Plugins" section and activate **Blog Protection Plugin**.
5. Navigate to **Settings → Blog Protection** to configure the plugin.

== Usage ==

Once activated, go to **Settings → Blog Protection** and set a password to protect your content.

To protect a specific page or post, use the shortcode:

`[protected_content]Your content here[/protected_content]`

== Plugin Files ==

- `password-protection.php` – Main plugin file containing authentication logic.
- `admin.php` – Admin interface for managing settings.
- `secure_content.php` – Content restriction mechanism.
- `validation.php` – Password validation module.
- `style.css` – Styles for the modal window.

== Technical Requirements ==

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- JavaScript support

== License ==

This plugin is licensed under **GPL-3.0 or later**.

== Changelog ==

= 1.3.0 =
* Initial stable release.
* Added content password protection.
* Implemented a modal password input window.
* Included password validation.
* Introduced an admin panel for managing settings.

