jQuery(document).ready(function($) {

    // Auto-resize a textarea to fit its content.
    function autoResizeTextarea(textarea) {
        // Reset height to measure the required height.
        textarea.style.height = 'auto';

        const newHeight = Math.max(60, textarea.scrollHeight); // min 60px
        const maxHeight = 200; // max 200px

        if (newHeight <= maxHeight) {
            textarea.style.height = newHeight + 'px';
            textarea.style.overflowY = 'hidden';
        } else {
            textarea.style.height = maxHeight + 'px';
            textarea.style.overflowY = 'auto';
        }
    }

    // Initialise auto-resize for all existing textareas.
    $('.page-notes-textarea').each(function() {
        autoResizeTextarea(this);

        $(this).on('input paste keyup', function() {
            autoResizeTextarea(this);
        });
    });

    // Debounce timer for auto-save.
    let saveTimer = {};

    // Show a save status message.
    function showSaveStatus(element, message, type = 'success') {
        const statusDiv = element.closest('.page-notes-container').find('.page-notes-status');
        statusDiv.text(message)
                 .removeClass('show')
                 .addClass('show');

        if (type === 'success') {
            statusDiv.css('color', '#46b450');
        } else if (type === 'error') {
            statusDiv.css('color', '#dc3232');
        } else {
            statusDiv.css('color', '#666');
        }

        // Hide after 2s for success/error.
        if (type !== 'saving') {
            setTimeout(() => {
                statusDiv.removeClass('show');
            }, 2000);
        }
    }

    // Update the status class on the row.
    function updateRowStatusClass($select) {
        const $row = $select.closest('tr');
        if ($row && $row.length) {
            $row.removeClass('status-draft status-revision status-process status-done');
            const newStatus = $select.val();
            if (newStatus) {
                $row.addClass('status-' + newStatus);
            }
        }
    }

    // Handle status dropdown changes.
    $(document).on('change', '.page-status-selector', function() {
        const $select = $(this);
        const postId = $select.data('post');
        const nonce = devToolsPageState.nonce;
        const newStatus = $select.val();
        const $loader = $select.siblings('.page-status-loading');

        // Update the row style immediately.
        updateRowStatusClass($select);

        // Show spinner.
        $loader.show();
        $select.prop('disabled', true);

        $.ajax({
            url: devToolsPageState.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_page_status',
                post_id: postId,
                status: newStatus,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Status saved successfully.
                } else {
                    alert('Error: ' + response.data.message);
                    // Revert selection on error.
                    $select.val($select.data('original-value'));
                }
            },
            error: function() {
                alert(devToolsPageState.messages.error);
                // Revert selection on error.
                $select.val($select.data('original-value'));
            },
            complete: function() {
                // Hide spinner and re-enable the select.
                $loader.hide();
                $select.prop('disabled', false);
                // Update the original value.
                $select.data('original-value', $select.val());
            }
        });
    });

    // Store the original value on load.
    $('.page-status-selector').each(function() {
        $(this).data('original-value', $(this).val());
        // Set the initial row class.
        updateRowStatusClass($(this));
    });

    // Handle note changes with debounce.
    $(document).on('input paste keyup', '.page-notes-textarea', function() {
        const $textarea = $(this);
        const postId = $textarea.data('post');
        const nonce = devToolsPageState.nonce;
        const notes = $textarea.val();

        // Auto-resize.
        autoResizeTextarea(this);

        // Clear the previous timer.
        if (saveTimer[postId]) {
            clearTimeout(saveTimer[postId]);
        }

        // Show the "saving..." state.
        showSaveStatus($textarea, devToolsPageState.messages.saving, 'saving');

        // Set a new timer (1.5s delay).
        saveTimer[postId] = setTimeout(function() {
            $.ajax({
                url: devToolsPageState.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_page_notes',
                    post_id: postId,
                    notes: notes,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        showSaveStatus($textarea, devToolsPageState.messages.saved, 'success');
                    } else {
                        showSaveStatus($textarea, devToolsPageState.messages.error, 'error');
                    }
                },
                error: function() {
                    showSaveStatus($textarea, devToolsPageState.messages.error, 'error');
                }
            });
        }, 1500);
    });

    // Initialise rows added dynamically (e.g. when WordPress re-renders via AJAX).
    function initializeNewElements() {
        $('.page-notes-textarea').each(function() {
            if (!$(this).data('initialized')) {
                autoResizeTextarea(this);
                $(this).data('initialized', true);
            }
        });

        $('.page-status-selector').each(function() {
            if (!$(this).data('original-value')) {
                $(this).data('original-value', $(this).val());
            }
        });
    }

    // Observe the DOM for new rows.
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    initializeNewElements();
                }
            });
        });

        // Watch the pages table.
        const targetNode = document.querySelector('#the-list');
        if (targetNode) {
            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });
        }
    }

    // Visual focus polish.
    $(document).on('focus', '.page-notes-textarea', function() {
        $(this).parent().addClass('focused');
    });

    $(document).on('blur', '.page-notes-textarea', function() {
        $(this).parent().removeClass('focused');
    });

    // (Row coloring is handled in the change handler and on init.)

    // Show a save status message for the responsive checkboxes.
    function showResponsiveStatus(element, message, type = 'success') {
        const statusDiv = element.closest('.page-responsive-container').find('.responsive-status');
        statusDiv.text(message)
                 .removeClass('show')
                 .addClass('show');

        if (type === 'success') {
            statusDiv.css('color', '#46b450');
        } else if (type === 'error') {
            statusDiv.css('color', '#dc3232');
        }

        // Hide after 2s.
        setTimeout(() => {
            statusDiv.removeClass('show');
        }, 2000);
    }

    // Handle responsive checkbox changes.
    $(document).on('change', '.responsive-checkbox', function() {
        const $checkbox = $(this);
        const $container = $checkbox.closest('.responsive-checkboxes');
        const postId = $container.data('post');
        const nonce = devToolsPageState.nonce;
        const device = $checkbox.data('device');
        const checked = $checkbox.is(':checked');

        // Disable the checkbox temporarily.
        $checkbox.prop('disabled', true);

        $.ajax({
            url: devToolsPageState.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_page_responsive',
                post_id: postId,
                device: device,
                checked: checked,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showResponsiveStatus($checkbox, devToolsPageState.messages.saved, 'success');
                } else {
                    showResponsiveStatus($checkbox, devToolsPageState.messages.error, 'error');
                    // Revert checkbox on error.
                    $checkbox.prop('checked', !checked);
                }
            },
            error: function() {
                showResponsiveStatus($checkbox, devToolsPageState.messages.error, 'error');
                // Revert checkbox on error.
                $checkbox.prop('checked', !checked);
            },
            complete: function() {
                // Re-enable the checkbox.
                $checkbox.prop('disabled', false);
            }
        });
    });

});
