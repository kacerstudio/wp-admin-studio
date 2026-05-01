=== WP Admin Studio ===
Contributors: kacerstudio
Tags: admin, customization, maintenance, login, scripts
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WordPress admin toolkit: customize the admin UI, login page, maintenance mode, custom scripts, translations and file editors.

== Description ==

Admin Studio is a comprehensive WordPress administration toolkit that lets you customize and optimize your WordPress site from a single settings panel — without touching code.

**Admin Customization**

* Hide or customize items in the admin toolbar
* Remove dashboard widgets
* Customize admin page titles
* Hide WordPress version from the admin footer
* Hide "Howdy" greeting
* Hide update notifications for non-administrators
* Disable automatic update notification emails

**Scripts & Code**

* Insert custom CSS, HTML, or JavaScript into `<head>`, body start, or footer
* Custom PHP code editor (equivalent to functions.php — admin only)
* Google Maps API key manager

**Maintenance Mode**

* Enable a maintenance page for visitors while admins see the real site
* Fully customizable maintenance page HTML and styling
* Optional: show maintenance mode to all logged-in users

**Login Page**

* Customize the login page logo, background, colors, and fonts
* Custom login URL (hide the default `/wp-login.php`)
* Hide unnecessary login page elements (language switcher, "Lost your password?", etc.)

**Editor**

* robots.txt editor with backup/restore
* .htaccess editor with backup/restore

**Frontend**

* Custom archive title prefixes
* `[year]` shortcode for dynamic copyright years (compatible with Salient, Astra, OceanWP, GeneratePress, Kadence, Neve, Divi, Flatsome)
* Disable lazy loading for responsive images
* Disable big image threshold

**Media**

* SVG file upload support with automatic SVG sanitization
* Media file replacement (replace uploaded files while keeping the same URL)

**Posts & Pages**

* Duplicate posts and pages with one click
* Post status color coding in list views
* Custom frontend edit link for logged-in users
* Disable Gutenberg block editor

**Comments**

* Completely disable comments
* Remove the URL field from the comment form

**Translations**

* Visual front-end text translation — replace any text on your site without a translation plugin

**System & Security**

* Disable user enumeration (REST API and author archives)
* Custom email sender name and address for all outgoing WordPress emails
* Auto-delete unnecessary core files after updates
* WPForms country restriction

**Backup & Import/Export**

* Export and import all plugin settings as a JSON file
* Export and import custom translations

== Installation ==

1. Upload the `wp-admin-studio` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings → Admin Studio** to configure the plugin.

== Frequently Asked Questions ==

= Does this plugin modify my theme files? =

No. All customizations are applied through WordPress hooks and filters. Your theme files are never modified.

= Is the custom PHP code editor safe? =

The PHP code editor stores code in the WordPress database and executes it on every page load via `wp_init`. Access to the editor is restricted to administrators with the `manage_options` capability — the same permission required to install plugins. Treat it like a live functions.php editor: only enter code you understand and trust.

= How does SVG upload work? =

When SVG upload support is enabled, uploaded SVG files are automatically sanitized to remove potentially dangerous markup (scripts, event handlers, external references). The `unfiltered_upload` capability is granted dynamically via a WordPress filter while the plugin is active — no permanent changes are made to user roles in the database.

= Can I use this plugin on a multisite installation? =

Basic functionality works on multisite. SVG upload support is enabled per-site.

= How do I restore my .htaccess or robots.txt after an error? =

The plugin automatically creates a backup before saving. You can restore the previous version using the "Restore backup" button on the Editor settings page.

== Changelog ==

= 1.9.4 =
* Fixed: Login page password field now shows correct Feather outline eye icons (open/crossed) instead of lock icons — previous code used wrong dashicons unicode values
* Fixed: Eye icon vertical alignment in password field corrected (centered)
* Fixed: Sticky save bar no longer drifts into the left admin menu on sites with non-default menu widths — positioning is now calculated dynamically from actual menu width

= 1.9.3 =
* Security: SVG capability now granted dynamically via map_meta_cap filter — no permanent role modifications
* Security: Added data disclosure notice to bug report form
* Code: Plugin now loads via plugins_loaded hook
* Code: Added uninstall.php to clean up all plugin data on deletion
* Code: Added PHP and WordPress version checks on activation

= 1.9.2 =
* Improved SVG sanitization
* Added media replacement feature
* Various bug fixes

= 1.9.1 =
* Added WPForms country restriction
* Added custom login URL feature
* Performance improvements

= 1.9.0 =
* Added visual translation editor
* Added post status color coding
* Added duplicate posts feature
