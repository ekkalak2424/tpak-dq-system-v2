/**
 * TPAK Security JavaScript
 * 
 * Handles client-side security features including session management,
 * nonce handling, and secure AJAX requests.
 */

(function($) {
    'use strict';
    
    var TPAKSecurity = {
        
        // Configuration
        config: {
            sessionCheckInterval: 300000, // 5 minutes
            sessionWarningTime: 300, // 5 minutes before expiry
            ajaxTimeout: 30000, // 30 seconds
            maxRetries: 3
        },
        
        // State
        sessionTimer: null,
        warningShown: false,
        retryCount: 0,
        
        /**
         * Initialize security features
         */
        init: function() {
            this.setupSessionMonitoring();
            this.setupAjaxSecurity();
            this.setupFormSecurity();
            this.setupCSRFProtection();
        },
        
        /**
         * Setup session monitoring
         */
        setupSessionMonitoring: function() {
            var self = this;
            
            // Check session status periodically
            this.sessionTimer = setInterval(function() {
                self.checkSession();
            }, this.config.sessionCheckInterval);
            
            // Check session on page focus
            $(window).on('focus', function() {
                self.checkSession();
            });
            
            // Warn before session expires
            $(document).on('tpak:session_warning', function(e, timeRemaining) {
                self.showSessionWarning(timeRemaining);
            });
            
            // Handle session expiry
            $(document).on('tpak:session_expired', function() {
                self.handleSessionExpiry();
            });
        },
        
        /**
         * Check session status
         */
        checkSession: function() {
            var self = this;
            
            $.ajax({
                url: tpakAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tpak_check_session',
                    nonce: tpakAjax.nonce
                },
                timeout: this.config.ajaxTimeout,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        if (!data.valid) {
                            $(document).trigger('tpak:session_expired');
                        } else if (data.remaining < self.config.sessionWarningTime && !self.warningShown) {
                            $(document).trigger('tpak:session_warning', [data.remaining]);
                        }
                    } else {
                        $(document).trigger('tpak:session_expired');
                    }
                },
                error: function() {
                    // Retry on error
                    if (self.retryCount < self.config.maxRetries) {
                        self.retryCount++;
                        setTimeout(function() {
                            self.checkSession();
                        }, 5000);
                    }
                }
            });
        },
        
        /**
         * Show session warning
         */
        showSessionWarning: function(timeRemaining) {
            this.warningShown = true;
            
            var minutes = Math.floor(timeRemaining / 60);
            var message = tpakSecurity.messages.sessionWarning.replace('%d', minutes);
            
            var $warning = $('<div class="tpak-session-warning notice notice-warning">')
                .html('<p>' + message + '</p>')
                .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
            
            $('.wrap').prepend($warning);
            
            // Auto-hide after 10 seconds
            setTimeout(function() {
                $warning.fadeOut();
            }, 10000);
            
            // Handle dismiss
            $warning.on('click', '.notice-dismiss', function() {
                $warning.fadeOut();
            });
        },
        
        /**
         * Handle session expiry
         */
        handleSessionExpiry: function() {
            clearInterval(this.sessionTimer);
            
            // Show expiry message
            var $modal = $('<div class="tpak-session-expired-modal">')
                .html('<div class="tpak-modal-content">' +
                      '<h3>' + tpakSecurity.messages.sessionExpired + '</h3>' +
                      '<p>' + tpakSecurity.messages.sessionExpiredDesc + '</p>' +
                      '<button type="button" class="button button-primary" onclick="window.location.reload()">' +
                      tpakSecurity.messages.reloadPage + '</button>' +
                      '</div>');
            
            $('body').append($modal);
            $modal.show();
            
            // Disable all forms and AJAX
            $('form').on('submit', function(e) {
                e.preventDefault();
                alert(tpakSecurity.messages.sessionExpired);
            });
        },
        
        /**
         * Setup AJAX security
         */
        setupAjaxSecurity: function() {
            var self = this;
            
            // Setup global AJAX defaults
            $.ajaxSetup({
                timeout: this.config.ajaxTimeout,
                beforeSend: function(xhr, settings) {
                    // Add nonce to all TPAK AJAX requests
                    if (settings.url.indexOf('tpak_') !== -1 || 
                        (settings.data && settings.data.indexOf('tpak_') !== -1)) {
                        
                        if (typeof settings.data === 'string') {
                            settings.data += '&nonce=' + encodeURIComponent(tpakAjax.nonce);
                        } else if (typeof settings.data === 'object') {
                            settings.data.nonce = tpakAjax.nonce;
                        }
                    }
                },
                error: function(xhr, status, error) {
                    self.handleAjaxError(xhr, status, error);
                }
            });
        },
        
        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, status, error) {
            if (xhr.status === 401 || xhr.status === 403) {
                // Authentication/authorization error
                $(document).trigger('tpak:session_expired');
            } else if (xhr.status === 429) {
                // Rate limit exceeded
                this.showMessage(tpakSecurity.messages.rateLimitExceeded, 'error');
            } else if (status === 'timeout') {
                // Timeout error
                this.showMessage(tpakSecurity.messages.requestTimeout, 'error');
            }
        },
        
        /**
         * Setup form security
         */
        setupFormSecurity: function() {
            var self = this;
            
            // Add nonces to forms that don't have them
            $('form[action*="tpak"]').each(function() {
                var $form = $(this);
                
                if (!$form.find('input[name="_wpnonce"]').length) {
                    $form.append('<input type="hidden" name="_wpnonce" value="' + tpakAjax.nonce + '">');
                }
            });
            
            // Validate forms before submission
            $('form').on('submit', function(e) {
                if (!self.validateForm($(this))) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        /**
         * Validate form
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check for required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    errors.push($field.attr('name') + ' is required');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validate email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !self.isValidEmail(value)) {
                    isValid = false;
                    errors.push('Invalid email format');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validate URL fields
            $form.find('input[type="url"]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();
                
                if (value && !self.isValidUrl(value)) {
                    isValid = false;
                    errors.push('Invalid URL format');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Show errors
            if (!isValid) {
                this.showMessage(errors.join('<br>'), 'error');
            }
            
            return isValid;
        },
        
        /**
         * Setup CSRF protection
         */
        setupCSRFProtection: function() {
            // Refresh nonce periodically
            setInterval(function() {
                $.ajax({
                    url: tpakAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tpak_refresh_nonce'
                    },
                    success: function(response) {
                        if (response.success && response.data.nonce) {
                            tpakAjax.nonce = response.data.nonce;
                            
                            // Update nonce in forms
                            $('input[name="_wpnonce"]').val(response.data.nonce);
                        }
                    }
                });
            }, 900000); // 15 minutes
        },
        
        /**
         * Secure AJAX request wrapper
         */
        secureAjax: function(options) {
            var self = this;
            var defaults = {
                type: 'POST',
                dataType: 'json',
                timeout: this.config.ajaxTimeout,
                beforeSend: function() {
                    // Show loading indicator
                    self.showLoading();
                },
                complete: function() {
                    // Hide loading indicator
                    self.hideLoading();
                },
                error: function(xhr, status, error) {
                    self.handleAjaxError(xhr, status, error);
                }
            };
            
            options = $.extend(defaults, options);
            
            // Ensure nonce is included
            if (!options.data) {
                options.data = {};
            }
            
            if (typeof options.data === 'object') {
                options.data.nonce = tpakAjax.nonce;
            }
            
            return $.ajax(options);
        },
        
        /**
         * Show loading indicator
         */
        showLoading: function() {
            if (!$('.tpak-loading').length) {
                $('body').append('<div class="tpak-loading"><div class="tpak-spinner"></div></div>');
            }
        },
        
        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('.tpak-loading').remove();
        },
        
        /**
         * Show message
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="tpak-message notice notice-' + type + '">')
                .html('<p>' + message + '</p>')
                .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
            
            $('.wrap').prepend($message);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
            
            // Handle dismiss
            $message.on('click', '.notice-dismiss', function() {
                $message.fadeOut();
            });
        },
        
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * Validate URL format
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },
        
        /**
         * Sanitize HTML
         */
        sanitizeHtml: function(html) {
            var div = document.createElement('div');
            div.textContent = html;
            return div.innerHTML;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof tpakAjax !== 'undefined' && typeof tpakSecurity !== 'undefined') {
            TPAKSecurity.init();
        }
    });
    
    // Expose to global scope
    window.TPAKSecurity = TPAKSecurity;
    
})(jQuery);