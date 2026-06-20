# Debug & Logs

A WordPress mini-plugin (part of DevTools) to **turn WordPress debugging on/off and read
`debug.log` from the admin**, without FTP or server access.

## What it does

- **Toggle debug constants** from a page under **Tools → Debug & Logs**: `WP_DEBUG`
  (master), `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG`, `SAVEQUERIES`.
  Defaults to **log on / on-screen off**, which is safe even on a visible site.
- **Log viewer** for `wp-content/debug.log`: level filters (Fatal / Error / Warning /
  Notice / Deprecated / Other), text search, auto-refresh, clear and download.

## How activation works (and why it edits wp-config)

`WP_DEBUG` is read from `wp-config.php` before any plugin loads, so a plugin cannot enable
it at runtime — the constants must live in `wp-config.php`. This module edits it safely:

- Locates `wp-config.php` the way WordPress core does.
- Keeps a one-time pristine **backup** in `wp-content/uploads/devtools-debug/`
  (protected with `.htaccess`), restorable from the page.
- Writes a delimited block (`/* BEGIN/END DevTools Debug */`) just above the
  "stop editing" line, and **comments out** any pre-existing `define()` of the managed
  constants (in PHP the first `define()` wins, so duplicates must be avoided).
- If `wp-config.php` isn't writable, the page shows the exact block to paste manually.
- Turning debug off — or deactivating/uninstalling the module — **reverts** the block and
  restores the original defines, so the site is never left in debug mode without the tool.

## Security

- Everything is gated by `manage_options` + nonce (it edits site configuration).
- The log is served only through a capability-checked endpoint (no direct public link),
  and the backup directory is protected.

## Files

```
debug-tools/
├── debug-tools.php        # class: lifecycle, wp-config editing, AJAX, log tail
├── includes/admin-page.php
├── assets/admin.js        # settings + log viewer (vanilla JS, no build)
├── assets/admin.css
└── README.md
```

## Versions

- **v1.0.0** — initial release: debug toggles via safe wp-config editing + log viewer
  (filters, search, auto-refresh, clear, download).
