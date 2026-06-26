# Debug & Logs

A WordPress mini-plugin (part of Modulforge) that **captures PHP errors to a private log
file and lets you read it from the admin** — without FTP and **without editing
`wp-config.php` or any core file**.

## What it does

- **Error logging toggle** under **Modulforge → Debug & Logs**:
  - *Enable error logging* (master) — records PHP errors to a private log file.
  - *Show errors on screen* — optional; off by default (safe on live sites).
- **Log viewer**: level filters (Fatal / Error / Warning / Notice / Deprecated / Other),
  text search, auto-refresh, clear and download.

## How it works (no wp-config edits)

`WP_DEBUG` is read from `wp-config.php` before any plugin loads, so a plugin cannot flip it
at runtime. Instead of editing core configuration, this module manages **its own** log:

- When enabled, it calls `ini_set('log_errors', '1')`, points `ini_set('error_log', …)` at
  its own file and raises `error_reporting()` — applied early on every request (front and
  admin). No constants are defined and `wp-config.php` is never touched.
- The log lives at `wp-content/uploads/modulforge/debug-{random}.log`:
  - inside the uploads folder (the only place a plugin should write),
  - with a **randomised, hard-to-guess filename**, plus an `.htaccess` (`Require all denied`)
    and an empty `index.html` for defence in depth.
- All writes (create/harden the folder, clear the log) go through **`WP_Filesystem`**.
- For full **core** debugging (deprecation / `_doing_it_wrong` notices) the page shows the
  exact `wp-config.php` block to paste **yourself** — it is reference text only.

Because nothing is written to `wp-config.php`, there is nothing to revert: disabling the
toggle (or deactivating the module) simply stops re-applying the `ini_set()` calls.

## Security

- Everything is gated by `manage_options` + nonce.
- The log is served only through a capability-checked endpoint (no direct public link); the
  file name is randomised and the directory is protected with `.htaccess` + `index.html`.
- On uninstall (when the user opted in) the whole `uploads/modulforge` log directory is
  removed via `WP_Filesystem`.

## Files

```
debug-tools/
├── debug-tools.php        # class: lifecycle, runtime ini_set logging, AJAX, log tail
├── includes/admin-page.php
├── assets/admin.js        # settings + log viewer (vanilla JS, no build)
├── assets/admin.css
└── README.md
```

## Versions

- **v1.1.0** — re-architected for WordPress.org: no longer edits `wp-config.php` and keeps
  no wp-config backup. Captures errors to a private, randomised log inside `uploads` via
  `ini_set()`, with all writes through `WP_Filesystem`.
- **v1.0.x** — initial release (toggled debug constants by editing `wp-config.php`).
