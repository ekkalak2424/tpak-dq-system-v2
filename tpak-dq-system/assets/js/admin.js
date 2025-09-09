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
/**
 
* TPAK Data Management Class
 */
function TPAKDataManager() {
    var self = this;
    var $ = jQuery;
    
    // Current state
    this.currentPage = 1;
    this.currentFilters = {
        status: '',
        search: '',
        orderby: 'date',
        order: 'DESC'
    };
    this.selectedItems = [];
    
    /**
     * Initialize data management
     */
    this.init = function() {
        this.bindEvents();
        this.loadData();
    };
    
    /**
     * Bind event handlers
     */
    this.bindEvents = function() {
        // Filter and search
        $('#filter-button').on('click', this.applyFilters.bind(this));
        $('#reset-filters').on('click', this.resetFilters.bind(this));
        $('#search-input').on('keypress', function(e) {
            if (e.which === 13) {
                self.applyFilters();
            }
        });
        
        // Sorting
        $('.sortable .sort-link').on('click', this.handleSort.bind(this));
        
        // Selection
        $('#select-all').on('change', this.toggleSelectAll.bind(this));
        $(document).on('change', '.item-checkbox', this.updateSelection.bind(this));
        
        // Bulk actions
        $('#bulk-action-button').on('click', this.performBulkAction.bind(this));
        $('#bulk-action-selector').on('change', this.toggleBulkActionButton.bind(this));
        
        // Row actions
        $(document).on('click', '.view-data', this.viewData.bind(this));
        $(document).on('click', '.edit-data', this.editData.bind(this));
        $(document).on('click', '.workflow-action', this.performWorkflowAction.bind(this));
        
        // Modal events
        $('.tpak-modal-close').on('click', this.closeModal.bind(this));
        $('.tpak-modal').on('click', function(e) {
            if (e.target === this) {
                self.closeModal();
            }
        });
        
        // Tab switching in modal
        $('.tpak-tab-link').on('click', this.switchModalTab.bind(this));
        
        // Data editing
        $('#edit-data-button').on('click', this.enableDataEditing.bind(this));
        $('#save-data-button').on('click', this.saveDataChanges.bind(this));
        $('#cancel-edit-button').on('click', this.cancelDataEditing.bind(this));
        
        // Workflow action confirmation
        $('#confirm-workflow-action').on('click', this.confirmWorkflowAction.bind(this));
        
        // Statistics card clicks
        $('.tpak-stat-card').on('click', this.filterByStatus.bind(this));
    };
    
    /**
     * Load data table
     */
    this.loadData = function() {
        $('#loading-indicator').show();
        $('#data-table-body').empty();
        
        var requestData = {
            action: 'tpak_load_data_table',
            nonce: tpak_admin.nonce,
            page: this.currentPage,
            status: this.currentFilters.status,
            search: this.currentFilters.search,
            orderby: this.currentFilters.orderby,
            order: this.currentFilters.order
        };
        
        $.post(tpak_admin.ajax_url, requestData)
            .done(function(response) {
                if (response.success) {
                    self.renderDataTable(response.data);
                } else {
                    self.showError(response.data || tpak_admin.strings.error);
                }
            })
            .fail(function() {
                self.showError(tpak_admin.strings.error);
            })
            .always(function() {
                $('#loading-indicator').hide();
            });
    };
    
    /**
     * Render data table
     */
    this.renderDataTable = function(data) {
        var $tbody = $('#data-table-body');
        $tbody.empty();
        
        if (data.data.length === 0) {
            $tbody.append('<tr><td colspan="8" class="no-items">' + 
                         'No data found matching your criteria.' + '</td></tr>');
            $('#items-count').text('0 items');
            return;
        }
        
        $.each(data.data, function(index, item) {
            var row = self.renderDataRow(item);
            $tbody.append(row);
        });
        
        // Update pagination and counts
        this.renderPagination(data);
        $('#items-count').text(data.total + ' items');
        
        // Reset selection
        this.selectedItems = [];
        $('#select-all').prop('checked', false);
        this.toggleBulkActionButton();
    };
    
    /**
     * Render data row
     */
    this.renderDataRow = function(item) {
        var statusClass = 'status-' + item.status.replace(/_/g, '-');
        var actionsHtml = '';
        
        $.each(item.actions, function(action, config) {
            actionsHtml += '<button type="button" class="button ' + config.class + ' workflow-action" ' +
                          'data-action="' + action + '" data-id="' + item.id + '">' +
                          config.label + '</button> ';
        });
        
        return '<tr data-id="' + item.id + '">' +
               '<th scope="row" class="check-column">' +
               '<input type="checkbox" class="item-checkbox" value="' + item.id + '">' +
               '</th>' +
               '<td class="column-survey-id">' + this.escapeHtml(item.survey_id) + '</td>' +
               '<td class="column-response-id">' + this.escapeHtml(item.response_id) + '</td>' +
               '<td class="column-status">' +
               '<span class="status-badge ' + statusClass + '">' + this.escapeHtml(item.status_label) + '</span>' +
               '</td>' +
               '<td class="column-assigned-user">' + this.escapeHtml(item.assigned_user) + '</td>' +
               '<td class="column-date">' + this.formatDate(item.created_date) + '</td>' +
               '<td class="column-modified">' + this.formatDate(item.last_modified) + '</td>' +
               '<td class="column-actions">' + actionsHtml + '</td>' +
               '</tr>';
    };
    
    /**
     * Render pagination
     */
    this.renderPagination = function(data) {
        var $container = $('#pagination-container');
        $container.empty();
        
        if (data.total_pages <= 1) {
            return;
        }
        
        var paginationHtml = '<span class="pagination-links">';
        
        // First page
        if (this.currentPage > 1) {
            paginationHtml += '<a class="first-page button" data-page="1">&laquo;</a>';
            paginationHtml += '<a class="prev-page button" data-page="' + (this.currentPage - 1) + '">&lsaquo;</a>';
        }
        
        // Page numbers
        var startPage = Math.max(1, this.currentPage - 2);
        var endPage = Math.min(data.total_pages, this.currentPage + 2);
        
        for (var i = startPage; i <= endPage; i++) {
            if (i === this.currentPage) {
                paginationHtml += '<span class="paging-input">' + i + '</span>';
            } else {
                paginationHtml += '<a class="page-numbers button" data-page="' + i + '">' + i + '</a>';
            }
        }
        
        // Last page
        if (this.currentPage < data.total_pages) {
            paginationHtml += '<a class="next-page button" data-page="' + (this.currentPage + 1) + '">&rsaquo;</a>';
            paginationHtml += '<a class="last-page button" data-page="' + data.total_pages + '">&raquo;</a>';
        }
        
        paginationHtml += '</span>';
        
        $container.html(paginationHtml);
        
        // Bind pagination events
        $container.find('a').on('click', function(e) {
            e.preventDefault();
            self.currentPage = parseInt($(this).data('page'));
            self.loadData();
        });
    };
    
    /**
     * Apply filters
     */
    this.applyFilters = function() {
        this.currentFilters.status = $('#status-filter').val();
        this.currentFilters.search = $('#search-input').val();
        this.currentPage = 1;
        this.loadData();
    };
    
    /**
     * Reset filters
     */
    this.resetFilters = function() {
        $('#status-filter').val('');
        $('#search-input').val('');
        this.currentFilters = {
            status: '',
            search: '',
            orderby: 'date',
            order: 'DESC'
        };
        this.currentPage = 1;
        this.loadData();
    };
    
    /**
     * Handle sorting
     */
    this.handleSort = function(e) {
        e.preventDefault();
        
        var $link = $(e.currentTarget);
        var $th = $link.closest('th');
        var orderby = $th.data('orderby');
        
        // Toggle order if same column
        if (this.currentFilters.orderby === orderby) {
            this.currentFilters.order = this.currentFilters.order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            this.currentFilters.orderby = orderby;
            this.currentFilters.order = 'ASC';
        }
        
        // Update UI
        $('.sortable').removeClass('asc desc');
        $th.addClass(this.currentFilters.order.toLowerCase());
        
        this.currentPage = 1;
        this.loadData();
    };
    
    /**
     * Toggle select all
     */
    this.toggleSelectAll = function() {
        var checked = $('#select-all').prop('checked');
        $('.item-checkbox').prop('checked', checked);
        this.updateSelection();
    };
    
    /**
     * Update selection
     */
    this.updateSelection = function() {
        this.selectedItems = [];
        $('.item-checkbox:checked').each(function() {
            self.selectedItems.push(parseInt($(this).val()));
        });
        
        $('#select-all').prop('checked', 
            $('.item-checkbox').length > 0 && 
            $('.item-checkbox:checked').length === $('.item-checkbox').length
        );
        
        this.toggleBulkActionButton();
    };
    
    /**
     * Toggle bulk action button
     */
    this.toggleBulkActionButton = function() {
        var hasSelection = this.selectedItems.length > 0;
        var hasAction = $('#bulk-action-selector').val() !== '';
        $('#bulk-action-button').prop('disabled', !(hasSelection && hasAction));
    };
    
    /**
     * Filter by status (from statistics cards)
     */
    this.filterByStatus = function(e) {
        var status = $(e.currentTarget).data('status');
        $('#status-filter').val(status);
        this.applyFilters();
    };
    
    /**
     * View data details
     */
    this.viewData = function(e) {
        var dataId = $(e.currentTarget).data('id');
        this.openDataModal(dataId, 'view');
    };
    
    /**
     * Edit data
     */
    this.editData = function(e) {
        var dataId = $(e.currentTarget).data('id');
        this.openDataModal(dataId, 'edit');
    };
    
    /**
     * Open data modal
     */
    this.openDataModal = function(dataId, mode) {
        $('#data-detail-modal').show();
        $('.tpak-modal-loading').show();
        $('#modal-content').hide();
        
        var requestData = {
            action: 'tpak_get_data_details',
            nonce: tpak_admin.nonce,
            data_id: dataId
        };
        
        $.post(tpak_admin.ajax_url, requestData)
            .done(function(response) {
                if (response.success) {
                    self.populateDataModal(response.data, mode);
                } else {
                    self.showError(response.data || tpak_admin.strings.error);
                    self.closeModal();
                }
            })
            .fail(function() {
                self.showError(tpak_admin.strings.error);
                self.closeModal();
            })
            .always(function() {
                $('.tpak-modal-loading').hide();
                $('#modal-content').show();
            });
    };
    
    /**
     * Populate data modal
     */
    this.populateDataModal = function(data, mode) {
        // Populate overview tab
        $('#detail-survey-id').text(data.survey_id);
        $('#detail-response-id').text(data.response_id);
        $('#detail-status').html('<span class="status-badge status-' + 
                                data.status.replace(/_/g, '-') + '">' + 
                                data.status + '</span>');
        $('#detail-assigned-user').text(data.assigned_user || 'Unassigned');
        
        // Populate workflow actions
        this.populateWorkflowActions(data.actions, data.id);
        
        // Populate survey data tab
        this.populateDataTab(data.data, data.can_edit && mode === 'edit');
        
        // Populate audit trail tab
        this.populateAuditTrail(data.audit_trail);
        
        // Store current data ID
        $('#data-detail-modal').data('current-id', data.id);
    };
    
    /**
     * Populate workflow actions
     */
    this.populateWorkflowActions = function(actions, dataId) {
        var $container = $('#workflow-actions-container');
        $container.empty();
        
        if (Object.keys(actions).length === 0) {
            $container.html('<p>No actions available for this data.</p>');
            return;
        }
        
        $.each(actions, function(action, config) {
            var $button = $('<button type="button" class="button ' + config.class + ' workflow-action" ' +
                           'data-action="' + action + '" data-id="' + dataId + '">' +
                           config.label + '</button>');
            $container.append($button);
        });
    };
    
    /**
     * Populate data tab
     */
    this.populateDataTab = function(data, canEdit) {
        var formattedData = JSON.stringify(data, null, 2);
        
        $('#data-display').html('<pre>' + this.escapeHtml(formattedData) + '</pre>');
        $('#data-editor-textarea').val(formattedData);
        
        if (canEdit) {
            $('#edit-data-button').show();
        } else {
            $('#edit-data-button').hide();
        }
        
        // Reset editing state
        $('#data-display').show();
        $('#data-editor').hide();
        $('#edit-data-button').show();
        $('#save-data-button, #cancel-edit-button').hide();
    };
    
    /**
     * Populate audit trail
     */
    this.populateAuditTrail = function(auditTrail) {
        var $container = $('#audit-trail-container');
        $container.empty();
        
        if (auditTrail.length === 0) {
            $container.html('<p>No audit trail entries found.</p>');
            return;
        }
        
        var html = '<div class="audit-trail">';
        $.each(auditTrail, function(index, entry) {
            html += '<div class="audit-entry">';
            html += '<div class="audit-header">';
            html += '<strong>' + self.escapeHtml(entry.action) + '</strong>';
            html += ' by ' + self.escapeHtml(entry.user_name);
            html += ' on ' + self.formatDate(entry.timestamp);
            html += '</div>';
            
            if (entry.notes) {
                html += '<div class="audit-notes">' + self.escapeHtml(entry.notes) + '</div>';
            }
            
            if (entry.old_value !== null || entry.new_value !== null) {
                html += '<div class="audit-changes">';
                if (entry.old_value !== null) {
                    html += '<div class="old-value">From: ' + self.escapeHtml(JSON.stringify(entry.old_value)) + '</div>';
                }
                if (entry.new_value !== null) {
                    html += '<div class="new-value">To: ' + self.escapeHtml(JSON.stringify(entry.new_value)) + '</div>';
                }
                html += '</div>';
            }
            
            html += '</div>';
        });
        html += '</div>';
        
        $container.html(html);
    };
    
    /**
     * Switch modal tab
     */
    this.switchModalTab = function(e) {
        e.preventDefault();
        
        var $link = $(e.currentTarget);
        var targetTab = $link.attr('href');
        
        // Update tab links
        $('.tpak-tab-link').removeClass('active');
        $link.addClass('active');
        
        // Update tab content
        $('.tpak-tab-content').removeClass('active');
        $(targetTab).addClass('active');
    };
    
    /**
     * Enable data editing
     */
    this.enableDataEditing = function() {
        $('#data-display').hide();
        $('#data-editor').show();
        $('#edit-data-button').hide();
        $('#save-data-button, #cancel-edit-button').show();
    };
    
    /**
     * Cancel data editing
     */
    this.cancelDataEditing = function() {
        $('#data-display').show();
        $('#data-editor').hide();
        $('#edit-data-button').show();
        $('#save-data-button, #cancel-edit-button').hide();
    };
    
    /**
     * Save data changes
     */
    this.saveDataChanges = function() {
        var dataId = $('#data-detail-modal').data('current-id');
        var newData = $('#data-editor-textarea').val();
        
        // Validate JSON
        try {
            JSON.parse(newData);
        } catch (e) {
            this.showError('Invalid JSON format. Please check your data.');
            return;
        }
        
        var requestData = {
            action: 'tpak_update_data',
            nonce: tpak_admin.nonce,
            data_id: dataId,
            survey_data: newData
        };
        
        $('#save-data-button').prop('disabled', true).text('Saving...');
        
        $.post(tpak_admin.ajax_url, requestData)
            .done(function(response) {
                if (response.success) {
                    self.showSuccess(response.data.message);
                    self.cancelDataEditing();
                    // Refresh the data display
                    $('#data-display').html('<pre>' + self.escapeHtml(newData) + '</pre>');
                } else {
                    self.showError(response.data || tpak_admin.strings.error);
                }
            })
            .fail(function() {
                self.showError(tpak_admin.strings.error);
            })
            .always(function() {
                $('#save-data-button').prop('disabled', false).text('Save Changes');
            });
    };
    
    /**
     * Perform workflow action
     */
    this.performWorkflowAction = function(e) {
        var $button = $(e.currentTarget);
        var action = $button.data('action');
        var dataId = $button.data('id');
        
        // Show confirmation modal
        this.showWorkflowConfirmation(action, dataId);
    };
    
    /**
     * Show workflow confirmation modal
     */
    this.showWorkflowConfirmation = function(action, dataId) {
        var actionLabels = {
            'approve_to_b': 'approve this data and send it to the Supervisor',
            'approve_to_c': 'send this data to the Examiner',
            'finalize_sampling': 'apply the sampling gate to this data',
            'finalize': 'finalize this data',
            'reject_to_a': 'reject this data back to the Interviewer',
            'reject_to_b': 'reject this data back to the Supervisor',
            'resubmit_to_b': 'resubmit this data to the Supervisor',
            'resubmit_to_c': 'resubmit this data to the Examiner'
        };
        
        var message = 'Are you sure you want to ' + (actionLabels[action] || 'perform this action') + '?';
        
        $('#workflow-confirmation-message').text(message);
        $('#workflow-notes-input').val('');
        $('#workflow-action-modal').data('action', action).data('data-id', dataId).show();
    };
    
    /**
     * Confirm workflow action
     */
    this.confirmWorkflowAction = function() {
        var action = $('#workflow-action-modal').data('action');
        var dataId = $('#workflow-action-modal').data('data-id');
        var notes = $('#workflow-notes-input').val();
        
        var requestData = {
            action: 'tpak_perform_workflow_action',
            nonce: tpak_admin.nonce,
            data_id: dataId,
            action: action,
            notes: notes
        };
        
        $('#confirm-workflow-action').prop('disabled', true).text('Processing...');
        
        $.post(tpak_admin.ajax_url, requestData)
            .done(function(response) {
                if (response.success) {
                    self.showSuccess(response.data.message);
                    self.closeModal();
                    self.loadData(); // Refresh the table
                } else {
                    self.showError(response.data || tpak_admin.strings.error);
                }
            })
            .fail(function() {
                self.showError(tpak_admin.strings.error);
            })
            .always(function() {
                $('#confirm-workflow-action').prop('disabled', false).text('Confirm');
            });
    };
    
    /**
     * Perform bulk action
     */
    this.performBulkAction = function() {
        var action = $('#bulk-action-selector').val();
        
        if (!action || this.selectedItems.length === 0) {
            return;
        }
        
        if (!confirm('Are you sure you want to perform this action on ' + this.selectedItems.length + ' items?')) {
            return;
        }
        
        var requestData = {
            action: 'tpak_bulk_action',
            nonce: tpak_admin.nonce,
            action: action,
            data_ids: this.selectedItems
        };
        
        $('#bulk-action-button').prop('disabled', true).text('Processing...');
        
        $.post(tpak_admin.ajax_url, requestData)
            .done(function(response) {
                if (response.success) {
                    var message = 'Bulk action completed. Success: ' + response.data.success + 
                                 ', Failed: ' + response.data.failed;
                    self.showSuccess(message);
                    
                    if (response.data.messages.length > 0) {
                        console.log('Bulk action messages:', response.data.messages);
                    }
                    
                    self.loadData(); // Refresh the table
                } else {
                    self.showError(response.data || tpak_admin.strings.error);
                }
            })
            .fail(function() {
                self.showError(tpak_admin.strings.error);
            })
            .always(function() {
                $('#bulk-action-button').prop('disabled', false).text('Apply');
                $('#bulk-action-selector').val('');
                self.toggleBulkActionButton();
            });
    };
    
    /**
     * Close modal
     */
    this.closeModal = function() {
        $('.tpak-modal').hide();
    };
    
    /**
     * Show error message
     */
    this.showError = function(message) {
        this.showNotice(message, 'error');
    };
    
    /**
     * Show success message
     */
    this.showSuccess = function(message) {
        this.showNotice(message, 'success');
    };
    
    /**
     * Show notice
     */
    this.showNotice = function(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                       '<p>' + message + '</p>' +
                       '<button type="button" class="notice-dismiss">' +
                       '<span class="screen-reader-text">Dismiss this notice.</span>' +
                       '</button>' +
                       '</div>');
        
        $('.tpak-data-management').prepend($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    };
    
    /**
     * Escape HTML
     */
    this.escapeHtml = function(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    };
    
    /**
     * Format date
     */
    this.formatDate = function(dateString) {
        if (!dateString) return '';
        
        var date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    };
    
    // Initialize on creation
    this.init();
}