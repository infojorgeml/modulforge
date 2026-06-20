# Page Tabs Organizer

A WordPress mini-plugin (part of SuiteWP) that organizes **pages, posts and any
public Custom Post Type (CPT)** with customizable tabs, improving content
management in the admin.

## Features

- **🌐 Universal**: works with Pages, Posts and any public CPT (including ACF)
- **🎨 Customizable tabs**: create, edit and delete tabs with their own color
- **📋 One tab per item (1:1)**: each page belongs to at most one tab
- **⚡ Filtering**: filter content by tab from the post-list screen
- **⚙️ Ordering**: control each tab's position and description

## File structure

```
tabs/
├── page-tabs-organizer.php   # Main mini-plugin
├── includes/
│   ├── admin-page.php        # Management page
│   └── tab-modal.php         # Create/Manage tab modals
├── assets/
│   ├── admin.css             # Admin styles
│   └── admin.js              # AJAX functionality
└── README.md                 # This file
```

## Supported post types

Tabs are **per post type** — Pages tabs are independent from Posts tabs, and each
CPT has its own set. The "Create New Tab" button appears on every supported
post-type list screen.

## Usage

1. **Manage tabs**: go to **Pages → Tabs**. Create a tab with a name (required),
   optional description, color and position.
2. **Assign pages**: in the same screen, pick a tab from the dropdown for each
   page. Each page can be in **one** tab at a time.
3. **Filter**: on the Pages list, tabs appear as links at the top with a count;
   click one to filter, or use the "All tabs" dropdown.

## Technical notes

### Database
- `{prefix}page_tabs` — tab definitions
- `{prefix}page_tab_relations` — page↔tab relations, with a **UNIQUE key on
  `page_id`** enforcing the 1:1 model

Tables are created/upgraded through the SuiteWP lifecycle (`maybe_install_schema`,
version-gated via the `pto_db_version` option). They are removed on uninstall only
when SuiteWP's "Delete all data" opt-in is enabled.

### Security
- Nonce verification on all AJAX operations
- Per-object capability checks (`edit_post`) for page assignment — not just a
  generic capability — closing privilege-escalation gaps
- Tab existence validated before creating relations
- Input sanitization, output escaping, and direct-access prevention

### Performance
- Tab counts use a single aggregate query (no per-tab `COUNT`)
- The post-list column caches tabs and relations per request (no N+1)

## Compatibility

- WordPress 5.0+
- PHP 7.4+

## Versions

- **v1.0.8** (current)
  - Consolidated to a 1:1 page→tab model with a unique index
  - Closed an IDOR in page-assignment AJAX (per-object `edit_post`)
  - Removed N+1 queries on list screens
  - English UI under the `suitewp` text domain
  - Removed legacy "force DB update" emergency tooling (superseded by the lifecycle)

---

**Author**: JorgeML
**Requires**: WordPress 5.0+
