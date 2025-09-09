/**
 * Meta Boxes JavaScript
 * 
 * Handles interactions for survey data meta boxes
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    var MetaBoxes = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Workflow action buttons
            $(document).on('click', '.tpak-workflow-action-btn', this.handleWorkflowAction);
            
            // JSON validation for survey data
            $(document).on('blur', '#tpak_survey_data', this.validateJSON);
            
            // Auto-resize textarea
            $(document).on('input', '#tpak_survey_data', this.autoResizeTextarea);
        },
        
        /**
         * Initialize components
         */
        initializeComponents: function() {
            // Format JSON in survey data textarea
            this.formatJSON();
            
            // Initialize tooltips if available
            if ($.fn.tooltip) {
                $('.tpak-workflow-action-btn').tooltip();
            }
        },
        
        /**
         * Handle workflow action
         */
        handleWorkflowAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var postId = $button.data('post-id');
            var requiresNote = $button.data('requires-note') === '1';
            
            // Show notes field if required
            if (requiresNote) {
                MetaBoxes.showNotesField($button, function(notes) {
                    MetaBoxes.performWorkflowAction(action, postId, notes, $button);
                });
            } else {
                // Confirm action
                if (confirm(tpakMetaBoxes.strings.confirmAction)) {
                    MetaBoxes.performWorkflowAction(action, postId, '', $button);
                }
            }
        },
        
        /**
         * Show notes field
         */
        showNotesField: function($button, callback) {
            var $notesContainer = $('#tpak-action-notes');
            var $notesField = $('#tpak-workflow-notes');
            
            // Show notes container
            $notesContainer.show();
            $notesField.focus();
            
            // Create confirm/cancel buttons if they don't exist
            if (!$notesContainer.find('.tpak-notes-actions').length) {
                var $actions = $('<div class="tpak-notes-actions" style="margin-top: 10px;"></div>');
                var $confirmBtn = $('<button type="button" class="button button-primary tpak-confirm-action">' + 
                                  tpakMetaBoxes.strings.confirmAction + '</button>');
                var $cancelBtn = $('<button type="button" class="button tpak-cancel-action" style="margin-left: 5px;">Cancel</button>');
                
                $actions.append($confirmBtn).append($cancelBtn);
                $notesContainer.append($actions);
            }
            
            // Handle confirm
            $notesContainer.off('click', '.tpak-confirm-action').on('click', '.tpak-confirm-action', function() {
                var notes = $notesField.val().trim();
                
                if (!notes) {
                    alert(tpakMetaBoxes.strings.notesRequired);
                    $notesField.focus();
                    return;
                }
                
                $notesContainer.hide();
                $notesField.val('');
                callback(notes);
            });
            
            // Handle cancel
            $notesContainer.off('click', '.tpak-cancel-action').on('click', '.tpak-cancel-action', function() {
                $notesContainer.hide();
                $notesField.val('');
            });
        },
        
        /**
         * Perform workflow action
         */
        performWorkflowAction: function(action, postId, notes, $button) {
            // Disable button and show loading
            $button.prop('disabled', true);
            var originalText = $button.text();
            $button.text(tpakMetaBoxes.strings.processing);
            
            // Prepare data
            var data = {
                action: 'tpak_workflow_action',
                workflow_action: action,
                post_id: postId,
                notes: notes,
                nonce: tpakMetaBoxes.nonce
            };
            
            // Send AJAX request
            $.post(tpakMetaBoxes.ajaxUrl, data)
                .done(function(response) {
                    if (response.success) {
                        MetaBoxes.handleWorkflowSuccess(response.data, $button);
                    } else {
                        MetaBoxes.handleWorkflowError(response.data.message || tpakMetaBoxes.strings.error, $button);
                    }
                })
                .fail(function() {
                    MetaBoxes.handleWorkflowError(tpakMetaBoxes.strings.error, $button);
                })
                .always(function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                });
        },
        
        /**
         * Handle workflow success
         */
        handleWorkflowSuccess: function(data, $button) {
            // Show success message
            MetaBoxes.showMessage(data.message || tpakMetaBoxes.strings.success, 'success');
            
            // Update status badge
            if (data.status_label && data.status_color) {
                var $statusBadge = $('.tpak-status-badge');
                $statusBadge.text(data.status_label);
                $statusBadge.css('background-color', data.status_color);
            }
            
            // Reload the page to update available actions
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        },
        
        /**
         * Handle workflow error
         */
        handleWorkflowError: function(message, $button) {
            MetaBoxes.showMessage(message, 'error');
        },
        
        /**
         * Show message
         */
        showMessage: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
        },
        
        /**
         * Validate JSON in textarea
         */
        validateJSON: function() {
            var $textarea = $(this);
            var jsonString = $textarea.val();
            
            if (!jsonString.trim()) {
                return;
            }
            
            try {
                var parsed = JSON.parse(jsonString);
                
                // Format the JSON
                var formatted = JSON.stringify(parsed, null, 2);
                if (formatted !== jsonString) {
                    $textarea.val(formatted);
                }
                
                // Remove error styling
                $textarea.removeClass('tpak-json-error');
                
                // Remove error message
                $textarea.siblings('.tpak-json-error-message').remove();
                
            } catch (e) {
                // Add error styling
                $textarea.addClass('tpak-json-error');
                
                // Show error message
                if (!$textarea.siblings('.tpak-json-error-message').length) {
                    var $errorMsg = $('<div class="tpak-json-error-message" style="color: #dc3232; font-size: 12px; margin-top: 5px;">Invalid JSON: ' + e.message + '</div>');
                    $textarea.after($errorMsg);
                }
            }
        },
        
        /**
         * Auto-resize textarea
         */
        autoResizeTextarea: function() {
            var $textarea = $(this);
            
            // Reset height to auto to get the correct scrollHeight
            $textarea.css('height', 'auto');
            
            // Set new height based on content
            var newHeight = Math.max($textarea[0].scrollHeight, 200);
            $textarea.css('height', newHeight + 'px');
        },
        
        /**
         * Format JSON in survey data textarea
         */
        formatJSON: function() {
            var $textarea = $('#tpak_survey_data');
            
            if ($textarea.length && $textarea.val()) {
                try {
                    var parsed = JSON.parse($textarea.val());
                    var formatted = JSON.stringify(parsed, null, 2);
                    $textarea.val(formatted);
                    
                    // Auto-resize
                    MetaBoxes.autoResizeTextarea.call($textarea[0]);
                } catch (e) {
                    // Invalid JSON, leave as is
                }
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        MetaBoxes.init();
    });
    
})(jQuery);