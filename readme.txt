=== DevTools ===
Contributors: jorgemml
Tags: development, admin, debug, webp, tabs
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controller that bundles developer mini-tools you can toggle individually: page state, tabs, comment pins, debug & logs, and WebP conversion.

== Description ==

DevTools is a lightweight controller that groups several developer-oriented mini-tools as modules you can activate or deactivate individually from a single screen. Each module is only loaded when active, so you only pay for what you use.

Included modules:

* **Page State Management** — status tracking, notes and responsive-design checkboxes on the pages list.
* **Page Tabs Organizer** — organize pages with customizable tabs in the admin.
* **Comment Pins** — leave visual comment pins anywhere on a page.
* **Debug & Logs** — toggle WordPress debugging and read the debug log from the admin, without FTP.
* **Convert to WebP** — bulk-convert the media library and auto-convert new uploads from JPEG/PNG to WebP.

Manage everything from **DevTools → Tools**: flip a switch to activate or deactivate each module.

== Installation ==

1. Upload the `dev-tools` folder to `/wp-content/plugins/`, or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **DevTools** through the **Plugins** screen.
3. Go to **DevTools → Tools** and enable the modules you want.

== Frequently Asked Questions ==

= Does it load every module all the time? =

No. Each mini-tool is loaded only while it is active, so inactive modules add no overhead.

= Is "Convert to WebP" reversible? =

No. Bulk conversion replaces and deletes the original JPEG/PNG files (and their generated sizes). The screen shows a warning and requires you to confirm before running. Back up your uploads first if you want a safety net.

= What happens to my data when I uninstall? =

By default your data is kept. You can opt in (from the DevTools screen) to delete all plugin data — including custom tables — on uninstall.

== Changelog ==

= 2.1.4 =
* WordPress.org readiness: added GPL license headers and a readme, removed manual textdomain loading (auto-loaded since WP 4.6), and applied input-sanitization / output-escaping and filesystem fixes flagged by Plugin Check.

= 2.1.3 =
* Renamed the first submenu to "Tools" and added a "Settings" action link on the plugins list.

= 2.1.2 =
* Fixed early textdomain loading notice on WordPress 6.7+.

= 2.1.1 =
* Simplified the module cards UI (removed status badge and icons, blue active state).

= 2.1.0 =
* Added the "Convert to WebP" module.

= 2.0.0 =
* Rebranded to DevTools.

== Upgrade Notice ==

= 2.1.4 =
Housekeeping release that prepares the plugin for the WordPress.org directory. No functional changes to the modules.
