/**
 * Page Tabs Organizer - Admin JavaScript
 */

(function($) {
    'use strict';

    var PTO = {

        init: function() {
            this.bindEvents();
            this.initializeSelects();
            this.initializeColumnSelectors();
        },

        strings: function(key, fallback) {
            if (window.pto_ajax && pto_ajax.strings && pto_ajax.strings[key]) {
                return pto_ajax.strings[key];
            }
            return fallback || key;
        },

        bindEvents: function() {
            // Tab form.
            $('#pto-tab-form').on('submit', this.saveTab);
            $('#cancel-edit').on('click', this.cancelEdit);

            // Tab buttons.
            $('.edit-tab').on('click', this.editTab);
            $('.delete-tab').on('click', this.deleteTab);

            // Page assignment.
            $('.assign-page').on('click', this.assignPage);
            $('.remove-page').on('click', this.removePage);
            $('.page-tab-select').on('change', this.onTabSelectChange);

            // Modal and "Create New Tab" button.
            $('#pto-create-new-tab').on('click', this.openCreateTabModal);
            $('#pto-quick-tab-form').on('submit', this.createTabQuick);
            $('#pto-manage-tab-form').on('submit', this.updateTab);

            // Modal controls.
            $('.pto-modal-close, #pto-modal-cancel, #pto-modal-cancel-manage').on('click', this.closeModal);
            $('.pto-modal-backdrop').on('click', this.closeModal);
            $('#pto-modal-delete').on('click', this.deleteTabFromModal);

            // Tab links on the Pages screen.
            $('.pto-tab-link').on('contextmenu', this.onTabRightClick);
            $('.pto-tab-link').on('dblclick', this.onTabDoubleClick);

            // Close modal with ESC.
            $(document).on('keydown', this.handleEscKey);
        },

        initializeSelects: function() {
            $('.page-tab-select').each(function() {
                var $select = $(this);
                var color = $select.find('option:selected').data('color');

                if (color) {
                    $select.css('border-left', '4px solid ' + color);
                } else {
                    $select.css('border-left', '');
                }
            });
        },

        saveTab: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');

            var name = $('#tab-name').val().trim();
            if (!name) {
                PTO.showMessage(PTO.strings('name_required', 'The tab name is required.'), 'error');
                return;
            }

            $button.addClass('loading');

            var data = {
                action: 'pto_save_tab',
                nonce: pto_ajax.nonce,
                tab_id: $('#tab-id').val(),
                name: name,
                description: $('#tab-description').val(),
                color: $('#tab-color').val(),
                position: $('#tab-position').val()
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('unknown_error', 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $button.removeClass('loading');
                });
        },

        editTab: function(e) {
            e.preventDefault();

            var tabData = $(this).data();

            $('#tab-id').val(tabData.tabId);
            $('#tab-name').val(tabData.name);
            $('#tab-description').val(tabData.description);
            $('#tab-color').val(tabData.color);
            $('#tab-position').val(tabData.position);

            $('#pto-tab-form button[type="submit"] .button-text').text(PTO.strings('update_tab', 'Update Tab'));
            $('#cancel-edit').show();

            $('html, body').animate({
                scrollTop: $('#pto-tab-form').offset().top - 50
            }, 500);

            $('#tab-name').focus();
        },

        cancelEdit: function(e) {
            e.preventDefault();
            PTO.resetForm();
        },

        resetForm: function() {
            $('#pto-tab-form')[0].reset();
            $('#tab-id').val('0');
            $('#tab-color').val('#0073aa');
            $('#tab-position').val('0');
            $('#pto-tab-form button[type="submit"] .button-text').text(PTO.strings('create_tab', 'Create Tab'));
            $('#cancel-edit').hide();
        },

        deleteTab: function(e) {
            e.preventDefault();

            if (!confirm(PTO.strings('confirm_delete', 'Are you sure you want to delete this tab?'))) {
                return;
            }

            var $button = $(this);
            var tabId = $button.data('tab-id');
            var $row = $button.closest('tr');

            $row.addClass('updating');

            var data = {
                action: 'pto_delete_tab',
                nonce: pto_ajax.nonce,
                tab_id: tabId
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data, 'success');

                        $row.fadeOut(300, function() {
                            $(this).remove();

                            if ($('.pto-tabs-list tbody tr').length === 0) {
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1000);
                            }
                        });
                    } else {
                        PTO.showMessage(response.data || PTO.strings('error', 'Operation error'), 'error');
                        $row.removeClass('updating');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                    $row.removeClass('updating');
                });
        },

        onTabSelectChange: function() {
            var $select = $(this);
            var selectedOption = $select.find('option:selected');
            var color = selectedOption.data('color');

            if (color) {
                $select.css('border-left', '4px solid ' + color);
            } else {
                $select.css('border-left', '');
            }

            var $currentTab = $select.siblings('.current-tab');
            if (selectedOption.val() === '0') {
                $currentTab.hide();
            } else {
                $currentTab.text(selectedOption.text())
                          .css('color', color)
                          .show();
            }
        },

        assignPage: function(e) {
            e.preventDefault();

            var $button = $(this);
            var pageId = $button.data('page-id');
            var $row = $button.closest('tr');
            var $select = $row.find('.page-tab-select');
            var tabId = $select.val();

            if (!tabId || tabId === '0') {
                PTO.showMessage(PTO.strings('select_tab', 'Please select a tab first.'), 'error');
                return;
            }

            $row.addClass('updating');

            var data = {
                action: 'pto_assign_page_to_tab',
                nonce: pto_ajax.nonce,
                page_id: pageId,
                tab_id: tabId
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('error', 'Operation error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $row.removeClass('updating');
                });
        },

        removePage: function(e) {
            e.preventDefault();

            if (!confirm(PTO.strings('confirm_remove', 'Are you sure you want to remove this page from the tab?'))) {
                return;
            }

            var $button = $(this);
            var pageId = $button.data('page-id');
            var tabId = $button.data('tab-id');
            var $row = $button.closest('tr');

            $row.addClass('updating');

            var data = {
                action: 'pto_remove_page_from_tab',
                nonce: pto_ajax.nonce,
                page_id: pageId,
                tab_id: tabId
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('error', 'Operation error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $row.removeClass('updating');
                });
        },

        showMessage: function(message, type) {
            var $messageDiv = $('#pto-messages');
            var $messageText = $messageDiv.find('p');

            $messageDiv.removeClass('notice-success notice-error notice-warning');

            if (type === 'success') {
                $messageDiv.addClass('notice-success');
            } else if (type === 'error') {
                $messageDiv.addClass('notice-error');
            } else {
                $messageDiv.addClass('notice-warning');
            }

            $messageText.text(message);
            $messageDiv.addClass('show');

            setTimeout(function() {
                $messageDiv.addClass('hide').removeClass('show');

                setTimeout(function() {
                    $messageDiv.removeClass('hide');
                }, 300);
            }, 4000);
        },

        // Modal and tab management.
        openCreateTabModal: function(e) {
            e.preventDefault();
            $('#pto-create-tab-modal').show();
            $('#quick-tab-name').focus();
        },

        openManageTabModal: function(tabId) {
            var $tabLink = $('.pto-tab-link[data-tab-id="' + tabId + '"]');
            if ($tabLink.length === 0) return;

            var tabText = $tabLink.text().trim();
            var tabName = tabText.replace(/\s*\(\d+\)\s*$/, ''); // strip the (XX) counter
            var tabColor = $tabLink.css('color');
            var tabTitle = $tabLink.attr('title') || '';

            if (tabColor && tabColor.startsWith('rgb')) {
                tabColor = PTO.rgbToHex(tabColor);
            }

            $('#manage-tab-id').val(tabId);
            $('#manage-tab-name').val(tabName);
            $('#manage-tab-description').val(tabTitle !== tabName ? tabTitle : '');
            $('#manage-tab-color').val(tabColor || '#0073aa');
            $('#manage-tab-position').val(0);

            $('#pto-manage-tab-title').text('Manage: ' + tabName);

            $('#pto-manage-tab-modal').show();
            $('#manage-tab-name').focus().select();
        },

        closeModal: function(e) {
            e.preventDefault();
            var $modal = $(e.target).closest('.pto-modal');
            if ($modal.length === 0) {
                $modal = $('.pto-modal:visible');
            }

            $modal.addClass('closing');
            setTimeout(function() {
                $modal.hide().removeClass('closing');
                $modal.find('form')[0].reset();
            }, 150);
        },

        handleEscKey: function(e) {
            if (e.keyCode === 27) { // ESC
                var $visibleModal = $('.pto-modal:visible');
                if ($visibleModal.length > 0) {
                    PTO.closeModal(e);
                }
            }
        },

        createTabQuick: function(e) {
            e.preventDefault();

            var $button = $('#pto-modal-create');

            var name = $('#quick-tab-name').val().trim();
            if (!name) {
                PTO.showMessage(PTO.strings('name_required', 'The tab name is required.'), 'error');
                return;
            }

            $button.addClass('loading');

            var data = {
                action: 'pto_create_tab_quick',
                nonce: pto_ajax.nonce,
                name: name,
                color: $('#quick-tab-color').val(),
                post_type: (window.pto_ajax && pto_ajax.current_post_type) ? pto_ajax.current_post_type : 'page'
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data.message, 'success');

                        $('#pto-create-tab-modal').addClass('closing');
                        setTimeout(function() {
                            $('#pto-create-tab-modal').hide().removeClass('closing');
                        }, 150);

                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('unknown_error', 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $button.removeClass('loading');
                });
        },

        updateTab: function(e) {
            e.preventDefault();

            var $button = $('#pto-modal-update');

            var name = $('#manage-tab-name').val().trim();
            if (!name) {
                PTO.showMessage(PTO.strings('name_required', 'The tab name is required.'), 'error');
                return;
            }

            $button.addClass('loading');

            var data = {
                action: 'pto_save_tab',
                nonce: pto_ajax.nonce,
                tab_id: $('#manage-tab-id').val(),
                name: name,
                description: $('#manage-tab-description').val(),
                color: $('#manage-tab-color').val(),
                position: $('#manage-tab-position').val()
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data.message, 'success');

                        $('#pto-manage-tab-modal').addClass('closing');
                        setTimeout(function() {
                            $('#pto-manage-tab-modal').hide().removeClass('closing');
                        }, 150);

                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('unknown_error', 'Unknown error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $button.removeClass('loading');
                });
        },

        deleteTabFromModal: function(e) {
            e.preventDefault();

            if (!confirm(PTO.strings('confirm_delete', 'Are you sure you want to delete this tab?'))) {
                return;
            }

            var tabId = $('#manage-tab-id').val();
            var $button = $(this);

            $button.addClass('loading');

            var data = {
                action: 'pto_delete_tab',
                nonce: pto_ajax.nonce,
                tab_id: tabId
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data, 'success');

                        $('#pto-manage-tab-modal').addClass('closing');
                        setTimeout(function() {
                            $('#pto-manage-tab-modal').hide().removeClass('closing');
                        }, 150);

                        setTimeout(function() {
                            window.location.href = 'edit.php?post_type=page';
                        }, 1000);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('error', 'Operation error'), 'error');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                })
                .always(function() {
                    $button.removeClass('loading');
                });
        },

        onTabRightClick: function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab-id');
            if (tabId) {
                PTO.openManageTabModal(tabId);
            }
        },

        onTabDoubleClick: function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab-id');
            if (tabId) {
                PTO.openManageTabModal(tabId);
            }
        },

        initializeColumnSelectors: function() {
            if (window.pto_ajax && pto_ajax.current_post_type) {
                this.setupPageTabSelectors();
            }
        },

        setupPageTabSelectors: function() {
            $(document).on('change', '.pto-page-tab-selector', this.handleTabSelectorChange);

            $('.pto-page-tab-selector').each(function() {
                PTO.updateSelectorAppearance($(this));
            });
        },

        handleTabSelectorChange: function() {
            var $selector = $(this);
            var pageId = $selector.data('page-id');
            var tabId = $selector.val();

            if (!pageId) return;

            $selector.addClass('updating');

            var data = {
                action: 'pto_update_page_tab',
                nonce: pto_ajax.nonce,
                page_id: pageId,
                tab_id: tabId
            };

            $.post(pto_ajax.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        PTO.showMessage(response.data.message, 'success');
                        PTO.updateSelectorAppearance($selector);
                    } else {
                        PTO.showMessage(response.data || PTO.strings('error', 'Operation error'), 'error');
                        $selector.val($selector.data('previous-value') || '0');
                    }
                })
                .fail(function() {
                    PTO.showMessage(PTO.strings('connection_error', 'Connection error'), 'error');
                    $selector.val($selector.data('previous-value') || '0');
                })
                .always(function() {
                    $selector.removeClass('updating');
                });
        },

        updateSelectorAppearance: function($selector) {
            var selectedOption = $selector.find('option:selected');
            var color = selectedOption.data('color');

            $selector.data('previous-value', $selector.val());

            if (color && $selector.val() !== '0') {
                $selector.css('border-left', '4px solid ' + color);
            } else {
                $selector.css('border-left', '1px solid #ddd');
            }
        },

        rgbToHex: function(rgb) {
            var result = rgb.match(/\d+/g);
            if (result && result.length >= 3) {
                return "#" +
                    ("0" + parseInt(result[0]).toString(16)).slice(-2) +
                    ("0" + parseInt(result[1]).toString(16)).slice(-2) +
                    ("0" + parseInt(result[2]).toString(16)).slice(-2);
            }
            return '#0073aa';
        }
    };

    $(document).ready(function() {
        PTO.init();
    });

    // Expose for debugging.
    window.PTO = PTO;

})(jQuery);
