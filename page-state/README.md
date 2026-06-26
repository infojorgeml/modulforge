# Page State Management

A WordPress mini-plugin (part of Modulforge) that improves page management by adding status, notes and responsive-design checks to the Pages list.

## ✨ Features

### 📊 Status management
- **Draft** (⚪)
- **Revision** (🟡)
- **In Progress** (🔵)
- **Done** (🟢)

### 📝 Notes
- Auto-saving textarea with auto-resize
- Real-time save with visual feedback

### 📱 Responsive checks
- **Desktop** (🖥️), **Tablet** (📱), **Mobile** (📲)

## 📋 Columns added

The plugin adds three columns to the WordPress pages list:

| Column | Description | Width |
|--------|-------------|-------|
| **Status** | Page status dropdown | 130px |
| **Notes** | Auto-saving notes textarea | 350px |
| **Responsive** | Responsive review checkboxes | 120px |

## 🎨 Row colors

Rows are tinted by status: Draft `#f0f0f0`, Revision `#fff9db`, In Progress `#e7f1ff`, Done `#e6ffed`.

## 💻 Technical notes

- **AJAX** auto-save without reloading
- **Security**: `check_ajax_referer` + per-object `edit_page` capability checks
- A single nonce is provided via `wp_localize_script` (no per-row nonces)
- Styles are enqueued from `page-state.css` (no inline `admin_head` CSS)

## 📦 File structure

```
page-state/
├── page-state.php        # Main mini-plugin
├── page-state-plugin.js  # AJAX functionality
├── page-state.css        # Admin styles
└── README.md             # This file
```

## 🔄 Versions

- **v2.0.0** (current)
  - Unified text domain (`devtools`) and English UI
  - Hardened AJAX handlers (per-object capability, single localised nonce)
  - Styles moved from inline `admin_head` to an enqueued stylesheet
  - Optional data cleanup on uninstall (via Modulforge's opt-in)

## 👤 Author

**JorgeML**

## 📄 License

GPL2 — https://www.gnu.org/licenses/gpl-2.0.html

## 🔧 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
