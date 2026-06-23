/**
 * DevTools Admin JavaScript
 * Handles switch functionality and AJAX calls
 */

(function($) {
    'use strict';

    const SELECTORS = {
        card: '.devtools-plugin-card',
        checkbox: '.devtools-plugin-checkbox',
        notice: '.devtools-notice'
    };

    const ADDITIONAL_CSS = `
        .devtools-plugin-card.success-flash {
            border-color: #2271b1 !important;
            box-shadow: 0 0 10px rgba(34, 113, 177, 0.3) !important;
            transition: all 0.3s ease !important;
        }

        .devtools-plugin-card.error-flash {
            border-color: #dc3232 !important;
            box-shadow: 0 0 10px rgba(220, 50, 50, 0.3) !important;
            transition: all 0.3s ease !important;
        }

        .devtools-notice {
            animation: devtools-slide-down 0.3s ease-out;
        }

        @keyframes devtools-slide-down {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;

    const DevToolsAdmin = {
        noticeTimer: null,

        init() {
            this.appendAdditionalStyles();
            this.decorateCards();
            this.bindEvents();
        },

        bindEvents() {
            $(document).on('change', SELECTORS.checkbox, (event) => this.handleToggle(event));
            $(document).on('change', '#devtools-delete-data', (event) => this.handleUninstallPref(event));
        },

        handleUninstallPref(event) {
            const enabled = $(event.currentTarget).is(':checked');

            $.ajax({
                url: dev_tools_ajax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'dev_tools_set_uninstall_pref',
                    nonce: dev_tools_ajax.nonce,
                    enabled: enabled ? '1' : '0'
                }
            }).done((response) => {
                if (response && response.success) {
                    this.renderNotice(response.data.message, 'success');
                } else {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : dev_tools_ajax.strings.generic_error;
                    this.renderNotice(message, 'error');
                }
            }).fail(() => {
                this.renderNotice(dev_tools_ajax.strings.generic_error, 'error');
            });
        },

        decorateCards() {
            $(SELECTORS.card).each(function() {
                const $card = $(this);
                const pluginName = $card.find('h3').text();
                $card.attr('title', DevToolsAdmin.formatToggleHint(pluginName));
            });
        },

        handleToggle(event) {
            const $checkbox = $(event.currentTarget);
            const $card = $checkbox.closest(SELECTORS.card);
            const pluginKey = $checkbox.data('plugin');
            const desiredState = $checkbox.is(':checked');

            if ($card.hasClass('loading')) {
                event.preventDefault();
                return false;
            }

            this.setLoadingState($card, true);

            $.ajax({
                url: dev_tools_ajax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'dev_tools_toggle_plugin',
                    plugin: pluginKey,
                    nonce: dev_tools_ajax.nonce,
                    should_activate: desiredState ? '1' : '0'
                }
            }).done((response) => {
                if (response && response.success && response.data) {
                    this.updateCardState($card, response.data.status);
                    this.renderNotice(response.data.message, 'success');
                } else {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : dev_tools_ajax.strings.generic_error;
                    this.handleError($card, $checkbox, message, desiredState);
                }
            }).fail((jqXHR) => {
                const message = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message
                    ? jqXHR.responseJSON.data.message
                    : dev_tools_ajax.strings.generic_error;
                this.handleError($card, $checkbox, message, desiredState);
            }).always(() => {
                this.setLoadingState($card, false);
            });
        },

        setLoadingState($card, isLoading) {
            const $checkbox = $card.find(SELECTORS.checkbox);
            $card.toggleClass('loading', isLoading);
            $checkbox.prop('disabled', isLoading);
        },

        updateCardState($card, status) {
            const isActive = status === 'active';

            $card.toggleClass('active', isActive).toggleClass('inactive', !isActive);
            $card.find(SELECTORS.checkbox).prop('checked', isActive);

            $card.removeClass('error-flash');
            $card.addClass('success-flash');
            window.setTimeout(() => $card.removeClass('success-flash'), 1000);
        },

        handleError($card, $checkbox, message, desiredState) {
            $checkbox.prop('checked', !desiredState);
            $card.removeClass('success-flash');
            $card.addClass('error-flash');
            window.setTimeout(() => $card.removeClass('error-flash'), 1000);
            this.renderNotice(message, 'error');
        },

        renderNotice(message, type) {
            if (this.noticeTimer) {
                window.clearTimeout(this.noticeTimer);
                this.noticeTimer = null;
            }

            $(SELECTORS.notice).remove();

            const $notice = $('<div />', {
                class: `devtools-notice ${type}`,
                text: message
            });

            $('.wrap h1').after($notice);

            this.noticeTimer = window.setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                    this.noticeTimer = null;
                });
            }, 5000);

            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
        },

        appendAdditionalStyles() {
            if ($('#devtools-admin-effects').length) {
                return;
            }

            $('<style>', {
                id: 'devtools-admin-effects',
                text: ADDITIONAL_CSS
            }).appendTo('head');
        },

        formatToggleHint(pluginName) {
            if (!dev_tools_ajax.strings.toggle_hint) {
                return pluginName;
            }

            return dev_tools_ajax.strings.toggle_hint.replace('%s', pluginName);
        }
    };

    $(document).ready(() => {
        DevToolsAdmin.init();
        window.DevToolsAdmin = DevToolsAdmin;
    });
})(jQuery);
