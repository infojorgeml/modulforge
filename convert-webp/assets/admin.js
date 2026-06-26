/* global modulforgeWebP */
(function () {
    'use strict';

    var cfg = window.modulforgeWebP || {};
    var i18n = cfg.i18n || {};

    function $(id) {
        return document.getElementById(id);
    }

    /** POST to admin-ajax and resolve with the parsed JSON response. */
    function ajax(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', cfg.nonce);
        Object.keys(data || {}).forEach(function (key) {
            body.append(key, data[key]);
        });

        return fetch(cfg.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (res) {
            return res.json();
        });
    }

    function sprintf(template, values) {
        var out = template;
        values.forEach(function (val, idx) {
            out = out.replace('%' + (idx + 1) + '$s', val).replace('%s', val);
        });
        return out;
    }

    /* ----------------------------------------------------------------- */
    /* Settings                                                           */
    /* ----------------------------------------------------------------- */

    function saveSettings() {
        var btn = $('dtw-save');
        var msg = $('dtw-settings-msg');
        btn.disabled = true;
        msg.textContent = '';
        msg.className = 'devtools-webp-msg';

        ajax('modulforge_webp_save_settings', {
            'settings[auto_upload]': $('dtw-auto-upload').checked ? '1' : '0',
            'settings[quality]': $('dtw-quality').value
        }).then(function (res) {
            if (res && res.success) {
                msg.textContent = i18n.saved || 'Saved.';
                msg.classList.add('is-success');
            } else {
                msg.textContent = (res && res.data && res.data.message) || i18n.save_error;
                msg.classList.add('is-error');
            }
        }).catch(function () {
            msg.textContent = i18n.connect_error;
            msg.classList.add('is-error');
        }).then(function () {
            btn.disabled = false;
        });
    }

    /* ----------------------------------------------------------------- */
    /* Bulk conversion                                                    */
    /* ----------------------------------------------------------------- */

    var pendingIds = [];

    function scan() {
        var pending = $('dtw-pending');
        ajax('modulforge_webp_scan', {}).then(function (res) {
            if (res && res.success) {
                pendingIds = res.data.ids || [];
                if (pendingIds.length === 0) {
                    pending.textContent = i18n.none_pending;
                    $('dtw-convert-all').disabled = true;
                    $('dtw-confirm').disabled = true;
                } else {
                    pending.textContent = sprintf(i18n.pending, [pendingIds.length]);
                }
            } else {
                pending.textContent = (res && res.data && res.data.message) || i18n.connect_error;
            }
        }).catch(function () {
            pending.textContent = i18n.connect_error;
        });
    }

    function logLine(text, kind) {
        var log = $('dtw-log');
        log.hidden = false;
        var li = document.createElement('li');
        li.className = 'dtw-log-' + (kind || 'ok');
        li.textContent = text;
        log.appendChild(li);
        log.scrollTop = log.scrollHeight;
    }

    function setProgress(done, total) {
        var pct = total ? Math.round((done / total) * 100) : 0;
        $('dtw-progress-bar').style.width = pct + '%';
        $('dtw-progress-text').textContent = (i18n.converting || '') + ' ' + done + ' / ' + total + ' (' + pct + '%)';
    }

    function startConversion() {
        var btn = $('dtw-convert-all');
        var total = pendingIds.length;
        if (total === 0) {
            return;
        }

        btn.disabled = true;
        $('dtw-confirm').disabled = true;
        $('dtw-auto-upload').disabled = true;
        $('dtw-quality').disabled = true;
        $('dtw-progress').hidden = false;
        $('dtw-log').innerHTML = '';

        var stats = { converted: 0, skipped: 0, failed: 0, saved: 0 };
        var queue = pendingIds.slice();
        var index = 0;

        function next() {
            if (index >= queue.length) {
                finish();
                return;
            }
            var id = queue[index];
            ajax('modulforge_webp_convert', { id: id }).then(function (res) {
                var data = (res && res.data) || {};
                if (res && res.success) {
                    if (data.skipped) {
                        stats.skipped++;
                        logLine('#' + id + ' — ' + (data.message || i18n.skipped), 'skip');
                    } else {
                        stats.converted++;
                        stats.saved += (data.saved_bytes || 0);
                        logLine('#' + id + ' — ' + (data.message || ''), 'ok');
                    }
                } else {
                    stats.failed++;
                    logLine('#' + id + ' — ' + (i18n.failed) + ': ' + (data.message || ''), 'error');
                }
            }).catch(function () {
                stats.failed++;
                logLine('#' + id + ' — ' + i18n.connect_error, 'error');
            }).then(function () {
                index++;
                setProgress(index, total);
                next();
            });
        }

        function finish() {
            $('dtw-progress-text').textContent = i18n.done || 'Done.';
            logLine(
                sprintf(i18n.summary, [stats.converted, stats.skipped, stats.failed, humanSize(stats.saved)]),
                'summary'
            );
            $('dtw-auto-upload').disabled = false;
            $('dtw-quality').disabled = false;
            // Re-scan so the counter and button reflect the new state.
            scan();
        }

        setProgress(0, total);
        next();
    }

    function humanSize(bytes) {
        if (!bytes) {
            return '0 B';
        }
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        i = Math.min(i, units.length - 1);
        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + units[i];
    }

    /* ----------------------------------------------------------------- */
    /* Wire up                                                            */
    /* ----------------------------------------------------------------- */

    document.addEventListener('DOMContentLoaded', function () {
        if (!cfg.supported) {
            return;
        }

        var save = $('dtw-save');
        if (save) {
            save.addEventListener('click', saveSettings);
        }

        var confirm = $('dtw-confirm');
        if (confirm) {
            confirm.addEventListener('change', function () {
                $('dtw-convert-all').disabled = !confirm.checked || pendingIds.length === 0;
            });
        }

        var convertAll = $('dtw-convert-all');
        if (convertAll) {
            convertAll.addEventListener('click', startConversion);
        }

        scan();
    });
})();
