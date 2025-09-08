/**
 * TPAK DQ System Admin JavaScript
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * TPAK Admin Object
     */
    var TPAKAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Form submissions
            $(document).on('submit', '.tpak-admin-form', this.handleFormSubmit);
            
            // AJAX actions
            $(document).on('click', '.tpak-ajax-action', this.handleAjaxAction);
            
            // Confirmation dialogs
            $(document).on('click', '.tpak-confirm-action', this.handleConfirmAction);
            
            // Tab navigation
            $(document).on('click', '.tpak-tab-nav a', this.handleTabClick);
            
            // Auto-save functionality
            $(document).on('change', '.tpak-auto-save', this.handleAutoSave);
        },
        
        /**
         * Initialize components
         */
        initComponents: function() {
            this.initTabs();
            this.initTooltips();
            this.initDataTables();
            this.initSettings();
            this.refreshDashboard();
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
            
            // Validate form
            if (!TPAKAdmin.validateForm($form)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $form.addClass('tpak-loading');
            
            // Add loading text
            var originalText = $submitBtn.val() || $submitBtn.text();
            $submitBtn.data('original-text', originalText);
            $submitBtn.val(tpak_admin.strings.loading).text(tpak_admin.strings.loading);
        },
        
        /**
         * Handle AJAX actions
         */
        handleAjaxAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var data = $button.data('params') || {};
            
            if (!action) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).addClass('tpak-loading');
            
            // Prepare AJAX data
            var ajaxData = {
                action: 'tpak_' + action,
                nonce: tpak_admin.nonce
            };
            
            $.extend(ajaxData, data);
            
            // Send AJAX request
            $.post(tpak_admin.ajax_url, ajaxData)
                .done(function(response) {
                    TPAKAdmin.handleAjaxResponse(response, $button);
                })
                .fail(function() {
                    TPAKAdmin.showNotice(tpak_admin.strings.error, 'error');
                })
                .always(function() {
                    $button.prop('disabled', false).removeClass('tpak-loading');
                });
        },
        
        /**
         * Handle confirmation actions
         */
        handleConfirmAction: function(e) {
            var message = $(this).data('confirm') || tpak_admin.strings.confirm_delete;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },
        
        /**
         * Handle tab clicks
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(this);
            var target = $tab.attr('href');
            
            // Update active tab
            $tab.closest('.tpak-tab-nav').find('a').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tpak-tab-content').hide();
            $(target).show();
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        },
        
        /**
         * Handle auto-save
         */
        handleAutoSave: function() {
            var $field = $(this);
            var value = $field.val();
            var key = $field.data('key');
            
            if (!key) {
                return;
            }
            
            // Debounce auto-save
            clearTimeout($field.data('auto-save-timeout'));
            
            var timeout = setTimeout(function() {
                TPAKAdmin.autoSave(key, value);
            }, 1000);
            
            $field.data('auto-save-timeout', timeout);
        },
        
        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Show active tab based on URL hash
            var hash = window.location.hash;
            if (hash && $(hash).length) {
                $('.tpak-tab-nav a[href="' + hash + '"]').click();
            } else {
                $('.tpak-tab-nav a:first').click();
            }
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var $element = $(this);
                var tooltip = $element.data('tooltip');
                
                $element.attr('title', tooltip);
            });
        },
        
        /**
         * Initialize data tables
         */
        initDataTables: function() {
            $('.tpak-data-table').each(function() {
                var $table = $(this);
                
                // Add sorting functionality
                $table.find('th[data-sortable]').addClass('sortable').click(function() {
                    TPAKAdmin.sortTable($table, $(this));
                });
                
                // Add filtering
                if ($table.data('filterable')) {
                    TPAKAdmin.addTableFilter($table);
                }
            });
        },
        
        /**
         * Initialize settings page functionality
         */
        initSettings: function() {
            if ($('.tpak-settings-page').length === 0) {
                return;
            }
            
            this.initApiTesting();
            this.initManualImport();
            this.initSettingsValidation();
            this.initDependentFields();
        },
        
        /**
         * Initialize API testing functionality
         */
        initApiTesting: function() {
            $('#test-api-connection').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var originalText = $button.text();
                
                // Validate required fields
                var url = $('#limesurvey_url').val();
                var username = $('#api_username').val();
                var password = $('#api_password').val();
                var surveyId = $('#survey_id').val();
                
                if (!url || !username || !password || !surveyId) {
                    TPAKAdmin.showNotice('Please fill in all API connection fields before testing.', 'error');
                    return;
                }
                
                // Show loading state
                $button.text(tpak_admin.strings.testing || 'Testing...').prop('disabled', true);
                $('#api-test-result').empty();
                
                var data = {
                    action: 'tpak_test_api_connection',
                    nonce: tpak_admin.nonce,
                    url: url,
                    username: username,
                    password: password,
                    survey_id: surveyId
                };
                
                $.post(tpak_admin.ajax_url, data)
                    .done(function(response) {
                        var resultClass = response.success ? 'notice-success' : 'notice-error';
                        var message = response.data ? response.data.message : 'Unknown error occurred';
                        
                        $('#api-test-result').html(
                            '<div class="notice ' + resultClass + ' inline"><p>' + message + '</p></div>'
                        );
                    })
                    .fail(function() {
                        $('#api-test-result').html(
                            '<div class="notice notice-error inline"><p>Connection test failed. Please try again.</p></div>'
                        );
                    })
                    .always(function() {
                        $button.text(originalText).prop('disabled', false);
                    });
            });
        },
        
        /**
         * Initialize manual import functionality
         */
        initManualImport: function() {
            $('#trigger-manual-import').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm(tpak_admin.strings.confirm_manual_import || 'Are you sure you want to trigger a manual import? This may take some time.')) {
                    return;
                }
                
                var $button = $(this);
                var originalText = $button.text();
                
                // Show loading state
                $button.text(tpak_admin.strings.importing || 'Importing...').prop('disabled', true);
                $('#manual-import-result').empty();
                
                var data = {
                    action: 'tpak_manual_import',
                    nonce: tpak_admin.nonce
                };
                
                $.post(tpak_admin.ajax_url, data)
                    .done(function(response) {
                        var resultClass = response.success ? 'notice-success' : 'notice-error';
                        var message = response.data ? response.data.message : 'Unknown error occurred';
                        
                        $('#manual-import-result').html(
                            '<div class="notice ' + resultClass + ' inline"><p>' + message + '</p></div>'
                        );
                    })
                    .fail(function() {
                        $('#manual-import-result').html(
                            '<div class="notice notice-error inline"><p>Import failed. Please try again.</p></div>'
                        );
                    })
                    .always(function() {
                        $button.text(originalText).prop('disabled', false);
                    });
            });
        },
        
        /**
         * Initialize settings validation
         */
        initSettingsValidation: function() {
            // Real-time validation for sampling percentage
            $('#sampling_percentage').on('input', function() {
                var value = parseInt($(this).val());
                var $field = $(this);
                
                $field.removeClass('tpak-error');
                $field.next('.error-message').remove();
                
                if (value < 1 || value > 100) {
                    TPAKAdmin.showFieldError($field, 'Sampling percentage must be between 1 and 100.');
                } else {
                    // Update workflow diagram
                    TPAKAdmin.updateWorkflowDiagram(value);
                }
            });
            
            // Validate email list
            $('#notification_emails').on('blur', function() {
                var emails = $(this).val().split(',');
                var $field = $(this);
                var invalidEmails = [];
                
                $field.removeClass('tpak-error');
                $field.next('.error-message').remove();
                
                $.each(emails, function(index, email) {
                    email = email.trim();
                    if (email && !TPAKAdmin.isValidEmail(email)) {
                        invalidEmails.push(email);
                    }
                });
                
                if (invalidEmails.length > 0) {
                    TPAKAdmin.showFieldError($field, 'Invalid email addresses: ' + invalidEmails.join(', '));
                }
            });
            
            // Validate URL format
            $('#limesurvey_url').on('blur', function() {
                var url = $(this).val().trim();
                var $field = $(this);
                
                $field.removeClass('tpak-error');
                $field.next('.error-message').remove();
                
                if (url && !TPAKAdmin.isValidUrl(url)) {
                    TPAKAdmin.showFieldError($field, 'Please enter a valid URL starting with http:// or https://');
                }
            });
            
            // Validate numeric ranges
            $('input[type="number"]').on('input', function() {
                var $field = $(this);
                var value = parseInt($field.val());
                var min = parseInt($field.attr('min'));
                var max = parseInt($field.attr('max'));
                
                $field.removeClass('tpak-error');
                $field.next('.error-message').remove();
                
                if (!isNaN(min) && value < min) {
                    TPAKAdmin.showFieldError($field, 'Value must be at least ' + min + '.');
                } else if (!isNaN(max) && value > max) {
                    TPAKAdmin.showFieldError($field, 'Value must be no more than ' + max + '.');
                }
            });
        },
        
        /**
         * Initialize dependent fields
         */
        initDependentFields: function() {
            // Enable/disable cron fields based on import enabled checkbox
            $('#import_enabled').on('change', function() {
                var isEnabled = $(this).is(':checked');
                var $dependentFields = $('#import_interval, #cron_survey_id, #import_limit, #retry_attempts');
                
                $dependentFields.prop('disabled', !isEnabled);
                
                if (isEnabled) {
                    $dependentFields.closest('tr').removeClass('tpak-disabled');
                } else {
                    $dependentFields.closest('tr').addClass('tpak-disabled');
                }
            }).trigger('change');
            
            // Enable/disable notification fields based on email enabled checkbox
            $('#email_enabled').on('change', function() {
                var isEnabled = $(this).is(':checked');
                var $dependentFields = $('#notification_emails, #send_on_assignment, #send_on_status_change, #send_on_error, #email_template');
                
                $dependentFields.prop('disabled', !isEnabled);
                
                if (isEnabled) {
                    $dependentFields.closest('tr').removeClass('tpak-disabled');
                } else {
                    $dependentFields.closest('tr').addClass('tpak-disabled');
                }
            }).trigger('change');
            
            // Auto-populate cron survey ID from API settings
            $('#survey_id').on('change', function() {
                var surveyId = $(this).val();
                var $cronSurveyId = $('#cron_survey_id');
                
                if (surveyId && !$cronSurveyId.val()) {
                    $cronSurveyId.val(surveyId);
                }
            });
        },
        
        /**
         * Update workflow diagram
         */
        updateWorkflowDiagram: function(samplingPercentage) {
            var finalizedPercentage = 100 - samplingPercentage;
            var $diagram = $('.tpak-workflow-diagram ul li:last-child');
            
            if ($diagram.length) {
                $diagram.html(
                    '3. Supervisor approval → ' + samplingPercentage + '% to Examiner (C), ' + 
                    finalizedPercentage + '% automatically finalized'
                );
            }
        },
        
        /**
         * Refresh dashboard
         */
        refreshDashboard: function() {
            if ($('.tpak-dashboard').length === 0) {
                return;
            }
            
            // Refresh statistics
            this.refreshStatistics();
            
            // Check system status
            this.checkSystemStatus();
        },
        
        /**
         * Refresh statistics
         */
        refreshStatistics: function() {
            $.post(tpak_admin.ajax_url, {
                action: 'tpak_get_statistics',
                nonce: tpak_admin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    TPAKAdmin.updateStatistics(response.data);
                }
            });
        },
        
        /**
         * Check system status
         */
        checkSystemStatus: function() {
            $.post(tpak_admin.ajax_url, {
                action: 'tpak_check_system_status',
                nonce: tpak_admin.nonce
            })
            .done(function(response) {
                if (response.success && response.data) {
                    TPAKAdmin.updateSystemStatus(response.data);
                }
            });
        },
        
        /**
         * Update statistics display
         */
        updateStatistics: function(stats) {
            $.each(stats, function(key, value) {
                $('.tpak-stat-item[data-stat="' + key + '"] .tpak-stat-number').text(value);
            });
        },
        
        /**
         * Update system status display
         */
        updateSystemStatus: function(status) {
            $.each(status, function(key, value) {
                var $item = $('.tpak-status-item[data-status="' + key + '"]');
                $item.removeClass('tpak-status-ok tpak-status-warning tpak-status-error tpak-status-unknown');
                $item.addClass('tpak-status-' + value.status);
                $item.find('.tpak-status-value').text(value.text);
            });
        },
        
        /**
         * Validate form
         */
        validateForm: function($form) {
            var isValid = true;
            
            // Clear previous errors
            $form.find('.tpak-error').removeClass('tpak-error');
            $form.find('.error-message').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    TPAKAdmin.showFieldError($field, 'This field is required.');
                    isValid = false;
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !TPAKAdmin.isValidEmail(value)) {
                    TPAKAdmin.showFieldError($field, 'Please enter a valid email address.');
                    isValid = false;
                }
            });
            
            // Validate URL fields
            $form.find('input[type="url"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !TPAKAdmin.isValidUrl(value)) {
                    TPAKAdmin.showFieldError($field, 'Please enter a valid URL.');
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('tpak-error');
            $field.after('<div class="error-message tpak-error">' + message + '</div>');
        },
        
        /**
         * Handle AJAX response
         */
        handleAjaxResponse: function(response, $button) {
            if (response.success) {
                TPAKAdmin.showNotice(response.data.message || tpak_admin.strings.success, 'success');
                
                // Handle specific actions
                if (response.data.action) {
                    TPAKAdmin.handleResponseAction(response.data.action, response.data);
                }
            } else {
                TPAKAdmin.showNotice(response.data || tpak_admin.strings.error, 'error');
            }
        },
        
        /**
         * Handle response actions
         */
        handleResponseAction: function(action, data) {
            switch (action) {
                case 'reload':
                    window.location.reload();
                    break;
                    
                case 'redirect':
                    window.location.href = data.url;
                    break;
                    
                case 'refresh_stats':
                    this.refreshStatistics();
                    break;
                    
                case 'update_table':
                    this.updateTable(data.table, data.rows);
                    break;
            }
        },
        
        /**
         * Show notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.tpak-admin-content').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },
        
        /**
         * Auto-save functionality
         */
        autoSave: function(key, value) {
            $.post(tpak_admin.ajax_url, {
                action: 'tpak_auto_save',
                nonce: tpak_admin.nonce,
                key: key,
                value: value
            });
        },
        
        /**
         * Sort table
         */
        sortTable: function($table, $header) {
            var column = $header.index();
            var order = $header.hasClass('asc') ? 'desc' : 'asc';
            
            // Update header classes
            $table.find('th').removeClass('asc desc');
            $header.addClass(order);
            
            // Sort rows
            var $rows = $table.find('tbody tr').get();
            
            $rows.sort(function(a, b) {
                var aVal = $(a).find('td').eq(column).text().trim();
                var bVal = $(b).find('td').eq(column).text().trim();
                
                // Try to parse as numbers
                var aNum = parseFloat(aVal);
                var bNum = parseFloat(bVal);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return order === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return order === 'asc' ? 
                    aVal.localeCompare(bVal) : 
                    bVal.localeCompare(aVal);
            });
            
            $table.find('tbody').append($rows);
        },
        
        /**
         * Add table filter
         */
        addTableFilter: function($table) {
            var $filter = $('<input type="text" class="tpak-table-filter" placeholder="Filter table...">');
            $table.before($filter);
            
            $filter.on('keyup', function() {
                var value = $(this).val().toLowerCase();
                
                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    var text = $row.text().toLowerCase();
                    
                    if (text.indexOf(value) === -1) {
                        $row.hide();
                    } else {
                        $row.show();
                    }
                });
            });
        },
        
        /**
         * Update table
         */
        updateTable: function(tableId, rows) {
            var $table = $('#' + tableId);
            var $tbody = $table.find('tbody');
            
            $tbody.empty();
            
            $.each(rows, function(index, row) {
                var $row = $('<tr>');
                
                $.each(row, function(key, value) {
                    $row.append('<td>' + value + '</td>');
                });
                
                $tbody.append($row);
            });
        },
        
        /**
         * Validate email
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * Validate URL
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        TPAKAdmin.init();
    });
    
    // Make TPAKAdmin globally available
    window.TPAKAdmin = TPAKAdmin;
    
})(jQuery);