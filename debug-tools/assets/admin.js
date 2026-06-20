/**
 * SuiteWP Debug & Logs — admin page behaviour (vanilla JS, no build step).
 */
(function () {
    'use strict';

    var cfg = window.suitewpDebug || {};
    var i18n = cfg.i18n || {};
    var entries = [];
    var autoTimer = null;

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('suitewp-debug-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', saveSettings);
        bindMasterToggle(form);

        on('suitewp-debug-restore', 'click', restoreBackup);
        on('suitewp-debug-refresh', 'click', loadLog);
        on('suitewp-debug-clear', 'click', clearLog);
        on('suitewp-debug-download', 'click', function () {
            window.location.href = cfg.download_url;
        });

        var search = document.getElementById('suitewp-debug-search');
        if (search) {
            search.addEventListener('input', render);
        }
        document.querySelectorAll('.suitewp-debug-filters input').forEach(function (cb) {
            cb.addEventListener('change', render);
        });

        var auto = document.getElementById('suitewp-debug-autorefresh');
        if (auto) {
            auto.addEventListener('change', function () {
                if (auto.checked) {
                    autoTimer = window.setInterval(loadLog, 5000);
                } else if (autoTimer) {
                    window.clearInterval(autoTimer);
                    autoTimer = null;
                }
            });
        }

        loadLog();
    });

    function on(id, evt, fn) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener(evt, fn);
        }
    }

    function post(data) {
        return fetch(cfg.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: data.toString(),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json();
        });
    }

    function notice(message, type) {
        var box = document.getElementById('suitewp-debug-notice');
        if (!box) {
            return;
        }
        box.className = 'notice notice-' + (type === 'error' ? 'error' : 'success');
        box.style.display = '';
        box.querySelector('p').textContent = message;
    }

    /* ----- Settings ----- */

    function bindMasterToggle(form) {
        var master = form.querySelector('input[name="wp_debug"]');
        var subs = form.querySelectorAll('.suitewp-debug-sub input');
        if (!master) {
            return;
        }
        var sync = function () {
            subs.forEach(function (cb) {
                cb.disabled = !master.checked;
            });
        };
        master.addEventListener('change', sync);
        sync();
    }

    function saveSettings(e) {
        e.preventDefault();
        var form = e.currentTarget;
        var data = new URLSearchParams();
        data.append('action', 'suitewp_debug_save_settings');
        data.append('nonce', cfg.nonce);
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            data.append('settings[' + cb.name + ']', cb.checked ? '1' : '0');
        });

        post(data)
            .then(function (res) {
                var manual = document.getElementById('suitewp-debug-manual');
                if (res && res.success) {
                    if (manual) {
                        manual.style.display = 'none';
                    }
                    notice((res.data && res.data.message) || i18n.saved, 'success');
                    updateRuntime(res.data && res.data.state);
                } else {
                    var block = res && res.data && res.data.manual_block;
                    if (block && manual) {
                        manual.querySelector('textarea').value = block;
                        manual.style.display = '';
                    }
                    notice((res && res.data && res.data.message) || i18n.save_error, 'error');
                }
            })
            .catch(function () {
                notice(i18n.connect_error, 'error');
            });
    }

    function restoreBackup() {
        if (!window.confirm(i18n.confirm_restore)) {
            return;
        }
        var data = new URLSearchParams();
        data.append('action', 'suitewp_debug_restore_backup');
        data.append('nonce', cfg.nonce);
        post(data)
            .then(function (res) {
                if (res && res.success) {
                    notice((res.data && res.data.message) || i18n.restored, 'success');
                    updateRuntime(res.data && res.data.state);
                } else {
                    notice((res && res.data && res.data.message) || i18n.save_error, 'error');
                }
            })
            .catch(function () {
                notice(i18n.connect_error, 'error');
            });
    }

    function updateRuntime(state) {
        if (!state) {
            return;
        }
        var el = document.getElementById('suitewp-debug-runtime');
        if (el) {
            // Saved intent differs from runtime until pages reload; keep it informative.
            el.dataset.active = state.runtime && state.runtime.wp_debug ? '1' : '0';
        }
        var restore = document.getElementById('suitewp-debug-restore');
        if (restore) {
            restore.style.display = state.has_backup ? '' : 'none';
        }
    }

    /* ----- Log viewer ----- */

    function loadLog() {
        var data = new URLSearchParams();
        data.append('action', 'suitewp_debug_get_log');
        data.append('nonce', cfg.nonce);
        post(data)
            .then(function (res) {
                if (!res || !res.success) {
                    return;
                }
                entries = parseLog(res.data.raw || '');
                renderMeta(res.data);
                render(true);
            })
            .catch(function () {});
    }

    function clearLog() {
        if (!window.confirm(i18n.confirm_clear)) {
            return;
        }
        var data = new URLSearchParams();
        data.append('action', 'suitewp_debug_clear_log');
        data.append('nonce', cfg.nonce);
        post(data)
            .then(function (res) {
                if (res && res.success) {
                    notice(i18n.cleared, 'success');
                    loadLog();
                }
            })
            .catch(function () {
                notice(i18n.connect_error, 'error');
            });
    }

    var START_RE = /^\[[^\]]+\]/;

    function classify(line) {
        if (/Fatal error|Parse error/i.test(line)) {
            return 'fatal';
        }
        if (/Deprecated/i.test(line)) {
            return 'deprecated';
        }
        if (/Warning/i.test(line)) {
            return 'warning';
        }
        if (/Notice/i.test(line)) {
            return 'notice';
        }
        if (/error/i.test(line)) {
            return 'error';
        }
        return 'other';
    }

    function parseLog(raw) {
        var out = [];
        var current = null;
        raw.split('\n').forEach(function (line) {
            if (START_RE.test(line)) {
                if (current) {
                    out.push(current);
                }
                current = { text: line, level: classify(line) };
            } else if (current) {
                current.text += '\n' + line;
            } else if (line.trim()) {
                current = { text: line, level: classify(line) };
            }
        });
        if (current) {
            out.push(current);
        }
        return out;
    }

    function activeLevels() {
        var set = {};
        document.querySelectorAll('.suitewp-debug-filters input').forEach(function (cb) {
            if (cb.checked) {
                set[cb.value] = true;
            }
        });
        return set;
    }

    function render(scrollToEnd) {
        var logEl = document.getElementById('suitewp-debug-log');
        if (!logEl) {
            return;
        }
        var levels = activeLevels();
        var searchEl = document.getElementById('suitewp-debug-search');
        var q = searchEl ? searchEl.value.trim().toLowerCase() : '';

        var filtered = entries.filter(function (e) {
            return levels[e.level] && (!q || e.text.toLowerCase().indexOf(q) !== -1);
        });

        logEl.textContent = '';

        if (entries.length === 0) {
            logEl.appendChild(emptyRow(i18n.empty_log));
            return;
        }
        if (filtered.length === 0) {
            logEl.appendChild(emptyRow(i18n.no_match));
            return;
        }

        var frag = document.createDocumentFragment();
        filtered.forEach(function (e) {
            var row = document.createElement('div');
            row.className = 'suitewp-debug-entry lvl-' + e.level;
            row.textContent = e.text;
            frag.appendChild(row);
        });
        logEl.appendChild(frag);

        if (scrollToEnd) {
            logEl.scrollTop = logEl.scrollHeight;
        }
    }

    function emptyRow(text) {
        var div = document.createElement('div');
        div.className = 'suitewp-debug-empty';
        div.textContent = text || '';
        return div;
    }

    function renderMeta(data) {
        var meta = document.getElementById('suitewp-debug-meta');
        if (!meta) {
            return;
        }
        if (!data.exists) {
            meta.textContent = i18n.empty_log;
            return;
        }
        var parts = [];
        parts.push(formatBytes(data.size));
        if (data.mtime) {
            parts.push(new Date(data.mtime * 1000).toLocaleString());
        }
        if (data.truncated) {
            parts.push('· showing latest');
        }
        meta.textContent = parts.join('  ·  ');
    }

    function formatBytes(bytes) {
        if (!bytes) {
            return '0 B';
        }
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + units[i];
    }
})();
