# Modulforge - Controller Plugin

Modulforge is a WordPress controller plugin that allows you to manage and individually activate/deactivate multiple mini-plugins from a centralized interface.

## 🚀 Features

- **Centralized Management**: Control all mini-plugins from a single interface
- **Individual Activation**: Activate or deactivate each mini-plugin according to your needs
- **Intuitive Interface**: Visual switches for easy management
- **Performance Optimization**: Only active mini-plugins are loaded
- **Responsive Design**: Interface adapts to different screen sizes

## 🆕 What's New in 2.0.0

- **Renamed to Modulforge** (`modulforge`): the plugin, text domain, classes, options, script/style handles, folder and main file use the unique `modulforge` prefix — required for WordPress.org. Reactivate the plugin and its modules after updating; options from older prefixes are not carried over.
- Debug & Logs now lives under the **Modulforge menu** (not Tools).

## 🆕 What's New in 1.4.0

- **New module — Debug & Logs**: toggle `WP_DEBUG` (and `WP_DEBUG_LOG` / `WP_DEBUG_DISPLAY` / `SCRIPT_DEBUG` / `SAVEQUERIES`) from **Tools → Debug & Logs** via safe `wp-config.php` editing with backup/restore, plus a `debug.log` viewer (level filters, search, auto-refresh, clear, download)
- Comment Pins reached **2.2.0**: resolve/reopen, a side panel with filters, and reply threads

## 🆕 What's New in 1.3.0

- **Comment Pins rebuilt in React** (`@wordpress/scripts` / `wp-element`, with a build step)
- **DOM-anchored pins**: pins now attach to elements (stable selector + relative offset) instead of absolute pixels, so they no longer drift across screen sizes, scroll or reflow
- Click-to-open popover replacing the fragile hover tooltip; new `anchor_selector` + `offset_x`/`offset_y` schema

## 🆕 What's New in 1.2.0

- **Mini-plugin lifecycle managed by the controller** — activating a module now reliably creates its database tables (fixes Comment Pins, whose table previously was never created)
- **Security hardening** — per-object capability checks (closes an IDOR in Page Tabs), capability gating for Comment Pins, stricter nonce/sanitisation, and consistent JSON error responses
- **Comment Pins** can now delete pins (owner or `edit_others_posts`), with XSS-safe rendering and reliable URL handling
- **Page Tabs** consolidated to a clean 1:1 page→tab model and removed N+1 queries on post-list screens
- **Single language (English), one text domain** (`modulforge`), with all UI strings localised
- **`uninstall.php`** with an opt-in switch to remove all plugin data (off by default — data is kept)
- Folders renamed to kebab-case; versions and READMEs reconciled

## 📦 Included Mini-plugins

### 1. Page State Management (v2.0.0)
Complete page state management system with:
- Status tracking (Draft, Revision, In Progress, Done)
- Auto-save notes system
- Responsive design checkboxes (Desktop, Tablet, Mobile)

### 2. Page Tabs Organizer (v1.0.8)
Page organization through tabs:
- Customizable tabs with colors
- Tab filtering in admin
- Support for multiple post types
- Visual management from page list

### 3. Comment Pins (v2.2.0)
Visual feedback system (React):
- DOM-anchored pins that stay in place across screens and scroll
- Resolve/reopen and a side panel with filters (all/open/resolved/mine)
- Reply threads on each comment

### 4. Debug & Logs (v1.0.0)
Developer debugging from the admin:
- Toggle WP_DEBUG and related constants via safe wp-config editing (backup + restore)
- debug.log viewer with level filters, search, auto-refresh, clear and download

## 🛠️ Installation

1. Upload the complete `Modulforge` folder to `/wp-content/plugins/` directory
2. Activate the plugin from WordPress admin panel
3. Go to **Modulforge** in the admin menu
4. Activate the mini-plugins you need using the switches

## 📋 Usage

### Activate/Deactivate Mini-plugins

1. Go to **Modulforge** in the WordPress menu
2. Use the switches to activate or deactivate each mini-plugin
3. Changes apply immediately without needing to reload

### State Management

- **Active**: The mini-plugin is loaded and working
- **Inactive**: The mini-plugin is not loaded, optimizing performance
- **Loading**: Temporary state during changes

## 🔧 Project Structure

```
Modulforge/
├── devtools.php              # Main controller plugin
├── uninstall.php            # Opt-in data cleanup on uninstall
├── assets/
│   ├── admin.css            # Interface styles
│   └── admin.js             # JavaScript functionality
├── languages/               # Translation template (devtools.pot)
├── page-state/              # State management mini-plugin
├── tabs/                    # Tab organization mini-plugin
├── comment-pins/              # Visual comments mini-plugin
├── debug-tools/             # Debug toggles + log viewer
└── README.md                # This file
```

## 🎯 Plugin Philosophy

Modulforge is designed as a **stable controller** that won't change frequently. Its main function is to:

- **Manage**: Activate/deactivate mini-plugins
- **Optimize**: Load only what's necessary
- **Centralize**: One interface for everything

The **individual mini-plugins** are the ones that will evolve and improve over time, while the controller remains stable.

## 🔒 Security

- Nonce verification in all AJAX operations
- Capability checks, including per-object checks (e.g. `edit_post`) to prevent privilege escalation
- Input data sanitization and output escaping
- Consistent JSON error responses (no raw `wp_die` strings)
- Prevention of direct file access

## 🌐 Compatibility

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Browsers**: Modern browsers with ES5+ support

## 🚦 System States

The plugin maintains a record of active mini-plugins in the WordPress `modulforge_active_plugins` option, allowing:

- Persistence between sessions
- Conditional resource loading
- Efficient memory management

## 📝 Development

### Adding New Mini-plugins

To add a new mini-plugin to the system:

1. Place the mini-plugin in its own folder within Modulforge
2. Update the `get_mini_plugin_definitions()` array in `devtools.php`
3. Define the necessary metadata (name, description, file, class, version, icon)
4. Optionally expose static lifecycle methods the controller will call:
   `activate()`, `deactivate()`, `uninstall()` and `maybe_install_schema()`
   (used for table creation, schema upgrades and cleanup)

### Mini-plugin Structure

```php
array(
    'name' => 'Plugin Name',
    'description' => 'Detailed description',
    'file' => 'path/to/main/file.php',
    'class' => 'MainClassName',
    'version' => '1.0.0',
    'icon' => 'dashicons-icon-name'
)
```

## 🤝 Contributing

This is a personal management project, but improvements to individual mini-plugins are welcome.

## 📄 License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

---

**Author**: JorgeML  
**Version**: 2.0.0  
**Last updated**: 2026
