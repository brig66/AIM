// AIM AI Visibility Plugin - Admin JavaScript

jQuery(document).ready(function($) {
    'use strict';

    // Tab Navigation
    $('.tab-button').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.tab-button').removeClass('active');
        $(this).addClass('active');

        $('.tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });

    // Add Service Category
    $('#add-service').on('click', function(e) {
        e.preventDefault();

        var serviceCount = $('#services-list .service-item').length;
        var html = `
            <div class="service-item" data-index="${serviceCount}">
                <input type="hidden" name="settings[aim_ai_service_categories][${serviceCount}][index]" value="${serviceCount}">
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="settings[aim_ai_service_categories][${serviceCount}][name]" placeholder="e.g., Gondola Shelving Systems">
                </div>
                <div class="form-group">
                    <label>Service Description</label>
                    <textarea name="settings[aim_ai_service_categories][${serviceCount}][description]" placeholder="Description of this service category..."></textarea>
                </div>
                <button type="button" class="button button-secondary remove-service">Remove Service</button>
            </div>
        `;

        $('#services-list').append(html);
    });

    // Remove Service Category
    $(document).on('click', '.remove-service', function(e) {
        e.preventDefault();
        $(this).closest('.service-item').fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Save Settings Form
    $('#aim-ai-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();

        // Collect form data
        var settings = {};
        $form.serializeArray().forEach(function(item) {
            if (item.name.startsWith('settings[')) {
                var key = item.name.replace('settings[', '').replace(']', '');
                settings[item.name] = item.value;
            }
        });

        $submitBtn.addClass('loading').text('Saving...');

        $.ajax({
            url: aimAiAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aim_ai_save_settings',
                nonce: aimAiAdmin.nonce,
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Settings saved successfully!', 'success');
                    updateStatus(response.data.status);
                } else {
                    showNotice('Error saving settings: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotice('AJAX error: Unable to save settings', 'error');
            },
            complete: function() {
                $submitBtn.removeClass('loading').text(originalText);
            }
        });
    });

    // Undo Changes
    $('#undo-button').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to undo the last changes? This cannot be undone.')) {
            return;
        }

        var $btn = $(this);
        var originalText = $btn.text();

        $btn.addClass('loading').text('Undoing...');

        $.ajax({
            url: aimAiAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aim_ai_undo_changes',
                nonce: aimAiAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Changes undone successfully! Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('Error: ' + (response.data || 'Unable to undo changes'), 'error');
                }
            },
            error: function() {
                showNotice('AJAX error: Unable to undo changes', 'error');
            },
            complete: function() {
                $btn.removeClass('loading').text(originalText);
            }
        });
    });

    // Auto-update Status
    setInterval(function() {
        updateStatusAjax();
    }, 30000); // Every 30 seconds

    // Helper Functions
    function showNotice(message, type) {
        var noticeHtml = `
            <div class="notice notice-${type}" style="display:none;">
                <p>${escapeHtml(message)}</p>
            </div>
        `;

        var $notice = $(noticeHtml).prependTo('.aim-ai-form');
        $notice.fadeIn().delay(5000).fadeOut(function() {
            $(this).remove();
        });
    }

    function updateStatus(status) {
        $('.status-card').each(function() {
            var html = `
                <h3>Plugin Status</h3>
                <p>Total Changes: <strong>${status.total_changes}</strong></p>
                <p>Last Updated: <strong>${status.last_change ? formatDate(status.last_change) : 'Never'}</strong></p>
                <p>Status: <strong>${status.settings_active ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'}</strong></p>
            `;
            $(this).html(html);
        });
    }

    function updateStatusAjax() {
        $.ajax({
            url: aimAiAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aim_ai_get_status',
                nonce: aimAiAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatus(response.data);
                }
            }
        });
    }

    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Input Validation
    $('input[type="url"]').on('change', function() {
        var url = $(this).val();
        if (url && !isValidUrl(url)) {
            showNotice('Invalid URL format: ' + escapeHtml(url), 'warning');
            $(this).css('border-color', '#ffc107');
        } else {
            $(this).css('border-color', '');
        }
    });

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    // Phone Number Formatting
    $('input[type="tel"]').on('change', function() {
        var phone = $(this).val().replace(/\D/g, '');
        if (phone.length === 10) {
            $(this).val('+1-' + phone.substring(0, 3) + '-' + phone.substring(3, 6) + '-' + phone.substring(6));
        }
    });

    // Character Counter for Textarea
    $('textarea[name*="description"], textarea[name*="org_description"]').on('keyup', function() {
        var count = $(this).val().length;
        var max = $(this).attr('maxlength');
        if (max) {
            var counterHtml = ` (${count}/${max})`;
            var $counter = $(this).next('.char-count');
            if ($counter.length) {
                $counter.text(counterHtml);
            } else {
                $(this).after('<small class="char-count">' + counterHtml + '</small>');
            }
        }
    });

    // Meta Description Preview
    $('#default_meta_description').on('keyup', function() {
        var text = $(this).val();
        var length = text.length;
        var $info = $(this).next('small');

        if (length > 160) {
            $info.html('160 characters recommended. <span class="text-danger">Currently ' + length + ' characters</span>');
        } else if (length < 120) {
            $info.html('160 characters recommended. <span class="text-warning">Could be longer (' + length + ')</span>');
        } else {
            $info.html('160 characters recommended. <span class="text-success">Good length (' + length + ')</span>');
        }
    });

    // Initialize
    $('textarea[name*="description"], textarea[name*="org_description"]').trigger('keyup');
    $('#default_meta_description').trigger('keyup');
});
