=== Modulforge ===
Contributors: jorgemml
Tags: development, admin, debug, webp, tabs
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Controller that bundles developer mini-tools you can toggle individually: page state, tabs, comment pins, debug & logs, and WebP conversion.

== Description ==

Modulforge is a lightweight controller that groups several developer-oriented mini-tools as modules you can activate or deactivate individually from a single screen. Each module is only loaded when active, so you only pay for what you use.

Included modules:

* **Page State Management** — status tracking, notes and responsive-design checkboxes on the pages list.
* **Page Tabs Organizer** — organize pages with customizable tabs in the admin.
* **Comment Pins** — leave visual comment pins anywhere on a page.
* **Debug & Logs** — toggle WordPress debugging and read the debug log from the admin, without FTP.
* **Convert to WebP** — bulk-convert the media library and auto-convert new uploads from JPEG/PNG to WebP.

Manage everything from **Modulforge → Tools**: flip a switch to activate or deactivate each module.

== Installation ==

1. Upload the `modulforge` folder to `/wp-content/plugins/`, or install the ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **Modulforge** through the **Plugins** screen.
3. Go to **Modulforge → Tools** and enable the modules you want.

== Frequently Asked Questions ==

= Does it load every module all the time? =

No. Each mini-tool is loaded only while it is active, so inactive modules add no overhead.

= Is "Convert to WebP" reversible? =

No. Bulk conversion replaces and deletes the original JPEG/PNG files (and their generated sizes). The screen shows a warning and requires you to confirm before running. Back up your uploads first if you want a safety net.

= What happens to my data when I uninstall? =

By default your data is kept. You can opt in (from the Modulforge screen) to delete all plugin data — including custom tables — on uninstall.

== Source Code ==

The complete source ships inside the plugin. The only compiled asset is the Comment Pins block, a React component built with [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts). Its unminified source is included under `comment-pins/src/`, alongside the compiled `comment-pins/build/`.

To rebuild the block from source (requires Node.js):

`cd comment-pins && npm install && npm run build`

== Changelog ==

= 1.0.0 =
* Initial public release on the WordPress.org plugin directory.
* Controller that bundles five developer modules — Page State Management, Page Tabs Organizer, Comment Pins, Debug & Logs, and Convert to WebP — each toggled individually and loaded only while active.

== Upgrade Notice ==

= 1.0.0 =
Initial public release.
