/**
 * Dashboard JavaScript
 * 
 * Handles dashboard interactions, charts, and real-time updates
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    var Dashboard = {
        
        /**
         * Initialize dashboard
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.startAutoRefresh();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh statistics button
            $('.tpak-refresh-stats').on('click', this.refreshStats.bind(this));
            
            // Auto-refresh toggle (if implemented)
            $('#tpak-auto-refresh').on('change', this.toggleAutoRefresh.bind(this));
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, charts will not be displayed');
                return;
            }
            
            this.initWorkflowChart();
            this.initActivityChart();
        },
        
        /**
         * Initialize workflow status chart
         */
        initWorkflowChart: function() {
            var canvas = document.getElementById('tpak-workflow-chart');
            if (!canvas || !window.tpakDashboardData || !window.tpakDashboardData.workflowChart) {
                return;
            }
            
            var data = window.tpakDashboardData.workflowChart;
            
            if (!data.labels || data.labels.length === 0) {
                $(canvas).parent().html('<p class="tpak-no-data">No data available</p>');
                return;
            }
            
            var ctx = canvas.getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: data.colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // We use custom legend
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.parsed;
                                    var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var percentage = Math.round((value / total) * 100);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize activity chart
         */
        initActivityChart: function() {
            var canvas = document.getElementById('tpak-activity-chart');
            if (!canvas || !window.tpakDashboardData || !window.tpakDashboardData.activityData) {
                return;
            }
            
            var data = window.tpakDashboardData.activityData;
            
            if (Object.keys(data).length === 0) {
                $(canvas).parent().html('<p class="tpak-no-data">No activity data available</p>');
                return;
            }
            
            var ctx = canvas.getContext('2d');
            var labels = Object.keys(data);
            var values = Object.values(data);
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.map(function(date) {
                        return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Records Imported',
                        data: values,
                        borderColor: '#2196f3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        x: {
                            ticks: {
                                maxTicksLimit: 10
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        },
        
        /**
         * Refresh statistics via AJAX
         */
        refreshStats: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $statsGrid = $('#tpak-stats-grid');
            var $loading = $('.tpak-stats-loading');
            
            // Show loading state
            $button.prop('disabled', true);
            $statsGrid.hide();
            $loading.show();
            
            $.ajax({
                url: tpak_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_refresh_stats',
                    nonce: tpak_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.updateStats(response.data.stats);
                        Dashboard.updateCharts(response.data);
                        Dashboard.showNotice('Statistics refreshed successfully', 'success');
                    } else {
                        Dashboard.showNotice('Failed to refresh statistics', 'error');
                    }
                },
                error: function() {
                    Dashboard.showNotice('Error refreshing statistics', 'error');
                },
                complete: function() {
                    // Hide loading state
                    $button.prop('disabled', false);
                    $loading.hide();
                    $statsGrid.show();
                }
            });
        },
        
        /**
         * Update statistics display
         */
        updateStats: function(stats) {
            var $statsGrid = $('#tpak-stats-grid');
            $statsGrid.empty();
            
            $.each(stats, function(key, value) {
                var $statItem = $('<div class="tpak-stat-item">' +
                    '<div class="tpak-stat-number">' + value + '</div>' +
                    '<div class="tpak-stat-label">' + key + '</div>' +
                    '</div>');
                
                $statsGrid.append($statItem);
            });
            
            // Animate the update
            $statsGrid.find('.tpak-stat-item').hide().fadeIn(300);
        },
        
        /**
         * Update charts with new data
         */
        updateCharts: function(data) {
            // Update global data
            if (data.chart_data) {
                window.tpakDashboardData.workflowChart = data.chart_data;
            }
            if (data.activity_data) {
                window.tpakDashboardData.activityData = data.activity_data;
            }
            
            // Reinitialize charts
            this.destroyCharts();
            this.initCharts();
        },
        
        /**
         * Destroy existing charts
         */
        destroyCharts: function() {
            // Destroy Chart.js instances if they exist
            if (window.Chart) {
                Chart.helpers.each(Chart.instances, function(instance) {
                    instance.destroy();
                });
            }
        },
        
        /**
         * Start auto-refresh timer
         */
        startAutoRefresh: function() {
            // Auto-refresh every 5 minutes
            this.autoRefreshInterval = setInterval(function() {
                if ($('#tpak-auto-refresh').is(':checked')) {
                    $('.tpak-refresh-stats').trigger('click');
                }
            }, 300000); // 5 minutes
        },
        
        /**
         * Toggle auto-refresh
         */
        toggleAutoRefresh: function(e) {
            var enabled = $(e.target).is(':checked');
            
            if (enabled) {
                this.showNotice('Auto-refresh enabled (every 5 minutes)', 'info');
            } else {
                this.showNotice('Auto-refresh disabled', 'info');
            }
        },
        
        /**
         * Show notification
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss">' +
                '<span class="screen-reader-text">Dismiss this notice.</span>' +
                '</button>' +
                '</div>');
            
            $('.tpak-admin-content').prepend($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
            
            // Handle manual dismiss
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Format numbers for display
         */
        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        },
        
        /**
         * Get relative time string
         */
        getRelativeTime: function(date) {
            var now = new Date();
            var diff = now - new Date(date);
            var seconds = Math.floor(diff / 1000);
            var minutes = Math.floor(seconds / 60);
            var hours = Math.floor(minutes / 60);
            var days = Math.floor(hours / 24);
            
            if (days > 0) {
                return days + ' day' + (days > 1 ? 's' : '') + ' ago';
            } else if (hours > 0) {
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            } else if (minutes > 0) {
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            } else {
                return 'Just now';
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        Dashboard.init();
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (Dashboard.autoRefreshInterval) {
            clearInterval(Dashboard.autoRefreshInterval);
        }
        Dashboard.destroyCharts();
    });
    
})(jQuery);