<?php
/**
 * Admin Logs Page
 *
 * Displays system logs with filtering and management options.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$logger = TPAK_Logger::get_instance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('tpak_logs_action', 'tpak_logs_nonce');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_logs':
                $clear_args = [];
                
                if (!empty($_POST['clear_level'])) {
                    $clear_args['level'] = sanitize_text_field($_POST['clear_level']);
                }
                
                if (!empty($_POST['clear_category'])) {
                    $clear_args['category'] = sanitize_text_field($_POST['clear_category']);
                }
                
                if (!empty($_POST['clear_older_than'])) {
                    $clear_args['older_than_days'] = intval($_POST['clear_older_than']);
                }
                
                if ($logger->clear_logs($clear_args)) {
                    echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully.', 'tpak-dq-system') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to clear logs.', 'tpak-dq-system') . '</p></div>';
                }
                break;
                
            case 'set_log_level':
                $new_level = sanitize_text_field($_POST['log_level']);
                $logger->set_log_level($new_level);
                echo '<div class="notice notice-success"><p>' . __('Log level updated successfully.', 'tpak-dq-system') . '</p></div>';
                break;
        }
    }
}

// Get filter parameters
$current_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$current_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;

// Get logs
$log_args = [
    'level' => $current_level,
    'category' => $current_category,
    'limit' => $per_page,
    'offset' => ($current_page - 1) * $per_page,
    'order' => 'DESC'
];

$logs = $logger->get_logs($log_args);
$log_stats = $logger->get_log_stats();

// Get available options
$log_levels = $logger->get_log_levels();
$log_categories = $logger->get_log_categories();
?>

<div class="wrap">
    <h1><?php _e('System Logs', 'tpak-dq-system'); ?></h1>
    
    <!-- Log Statistics -->
    <div class="tpak-log-stats">
        <div class="tpak-stats-grid">
            <div class="tpak-stat-card">
                <h3><?php _e('Total Logs', 'tpak-dq-system'); ?></h3>
                <span class="tpak-stat-number"><?php echo esc_html($log_stats['total_logs']); ?></span>
            </div>
            <div class="tpak-stat-card error">
                <h3><?php _e('Recent Errors (24h)', 'tpak-dq-system'); ?></h3>
                <span class="tpak-stat-number"><?php echo esc_html($log_stats['recent_errors']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Log Level Settings -->
    <div class="tpak-log-settings">
        <h2><?php _e('Log Settings', 'tpak-dq-system'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('tpak_logs_action', 'tpak_logs_nonce'); ?>
            <input type="hidden" name="action" value="set_log_level">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Current Log Level', 'tpak-dq-system'); ?></th>
                    <td>
                        <select name="log_level">
                            <?php foreach ($log_levels as $level): ?>
                                <option value="<?php echo esc_attr($level); ?>" <?php selected($logger->get_log_level(), $level); ?>>
                                    <?php echo esc_html(ucfirst($level)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Only log messages at or above this level will be recorded.', 'tpak-dq-system'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Update Log Level', 'tpak-dq-system')); ?>
        </form>
    </div>
    
    <!-- Log Filters -->
    <div class="tpak-log-filters">
        <h2><?php _e('Filter Logs', 'tpak-dq-system'); ?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <div class="tpak-filter-row">
                <label for="level"><?php _e('Level:', 'tpak-dq-system'); ?></label>
                <select name="level" id="level">
                    <option value=""><?php _e('All Levels', 'tpak-dq-system'); ?></option>
                    <?php foreach ($log_levels as $level): ?>
                        <option value="<?php echo esc_attr($level); ?>" <?php selected($current_level, $level); ?>>
                            <?php echo esc_html(ucfirst($level)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="category"><?php _e('Category:', 'tpak-dq-system'); ?></label>
                <select name="category" id="category">
                    <option value=""><?php _e('All Categories', 'tpak-dq-system'); ?></option>
                    <?php foreach ($log_categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($current_category, $category); ?>>
                            <?php echo esc_html(ucfirst($category)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php submit_button(__('Filter', 'tpak-dq-system'), 'secondary', 'submit', false); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $_GET['page'])); ?>" class="button">
                    <?php _e('Clear Filters', 'tpak-dq-system'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Log Entries -->
    <div class="tpak-log-entries">
        <h2><?php _e('Log Entries', 'tpak-dq-system'); ?></h2>
        
        <?php if (empty($logs)): ?>
            <p><?php _e('No log entries found.', 'tpak-dq-system'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-timestamp"><?php _e('Timestamp', 'tpak-dq-system'); ?></th>
                        <th scope="col" class="column-level"><?php _e('Level', 'tpak-dq-system'); ?></th>
                        <th scope="col" class="column-category"><?php _e('Category', 'tpak-dq-system'); ?></th>
                        <th scope="col" class="column-message"><?php _e('Message', 'tpak-dq-system'); ?></th>
                        <th scope="col" class="column-user"><?php _e('User', 'tpak-dq-system'); ?></th>
                        <th scope="col" class="column-actions"><?php _e('Actions', 'tpak-dq-system'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr class="tpak-log-entry tpak-log-<?php echo esc_attr($log['level']); ?>">
                            <td class="column-timestamp">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['timestamp']))); ?>
                            </td>
                            <td class="column-level">
                                <span class="tpak-log-level tpak-level-<?php echo esc_attr($log['level']); ?>">
                                    <?php echo esc_html(ucfirst($log['level'])); ?>
                                </span>
                            </td>
                            <td class="column-category">
                                <?php echo esc_html(ucfirst($log['category'])); ?>
                            </td>
                            <td class="column-message">
                                <div class="tpak-log-message">
                                    <?php echo esc_html($log['message']); ?>
                                </div>
                                <?php if (!empty($log['context'])): ?>
                                    <button type="button" class="button-link tpak-toggle-context" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                        <?php _e('Show Details', 'tpak-dq-system'); ?>
                                    </button>
                                    <div class="tpak-log-context" id="context-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                        <pre><?php echo esc_html($log['context']); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="column-user">
                                <?php if ($log['user_id']): ?>
                                    <?php
                                    $user = get_user_by('id', $log['user_id']);
                                    echo $user ? esc_html($user->display_name) : __('Unknown User', 'tpak-dq-system');
                                    ?>
                                <?php else: ?>
                                    <?php _e('System', 'tpak-dq-system'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if (!empty($log['context'])): ?>
                                    <button type="button" class="button-link tpak-copy-context" data-context="<?php echo esc_attr($log['context']); ?>">
                                        <?php _e('Copy', 'tpak-dq-system'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php
            $total_logs = $log_stats['total_logs'];
            $total_pages = ceil($total_logs / $per_page);
            
            if ($total_pages > 1):
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ]);
                
                if ($page_links):
            ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo $page_links; ?>
                    </div>
                </div>
            <?php
                endif;
            endif;
            ?>
        <?php endif; ?>
    </div>
    
    <!-- Log Management -->
    <div class="tpak-log-management">
        <h2><?php _e('Log Management', 'tpak-dq-system'); ?></h2>
        <form method="post" action="" onsubmit="return confirm('<?php _e('Are you sure you want to clear logs? This action cannot be undone.', 'tpak-dq-system'); ?>');">
            <?php wp_nonce_field('tpak_logs_action', 'tpak_logs_nonce'); ?>
            <input type="hidden" name="action" value="clear_logs">
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Clear Logs', 'tpak-dq-system'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Clear logs options', 'tpak-dq-system'); ?></legend>
                            
                            <p>
                                <label for="clear_level"><?php _e('Clear logs by level:', 'tpak-dq-system'); ?></label>
                                <select name="clear_level" id="clear_level">
                                    <option value=""><?php _e('All Levels', 'tpak-dq-system'); ?></option>
                                    <?php foreach ($log_levels as $level): ?>
                                        <option value="<?php echo esc_attr($level); ?>">
                                            <?php echo esc_html(ucfirst($level)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            
                            <p>
                                <label for="clear_category"><?php _e('Clear logs by category:', 'tpak-dq-system'); ?></label>
                                <select name="clear_category" id="clear_category">
                                    <option value=""><?php _e('All Categories', 'tpak-dq-system'); ?></option>
                                    <?php foreach ($log_categories as $category): ?>
                                        <option value="<?php echo esc_attr($category); ?>">
                                            <?php echo esc_html(ucfirst($category)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            
                            <p>
                                <label for="clear_older_than"><?php _e('Clear logs older than (days):', 'tpak-dq-system'); ?></label>
                                <input type="number" name="clear_older_than" id="clear_older_than" min="1" max="365" class="small-text">
                                <span class="description"><?php _e('Leave empty to clear all matching logs', 'tpak-dq-system'); ?></span>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Clear Logs', 'tpak-dq-system'), 'delete'); ?>
        </form>
    </div>
</div>

<style>
.tpak-log-stats {
    margin: 20px 0;
}

.tpak-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.tpak-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.tpak-stat-card.error {
    border-left: 4px solid #dc3232;
}

.tpak-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.tpak-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #23282d;
}

.tpak-stat-card.error .tpak-stat-number {
    color: #dc3232;
}

.tpak-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.tpak-filter-row label {
    font-weight: 600;
}

.tpak-log-level {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.tpak-level-debug { background: #f0f0f1; color: #646970; }
.tpak-level-info { background: #d1ecf1; color: #0c5460; }
.tpak-level-warning { background: #fff3cd; color: #856404; }
.tpak-level-error { background: #f8d7da; color: #721c24; }
.tpak-level-critical { background: #dc3232; color: #fff; }

.tpak-log-message {
    max-width: 400px;
    word-wrap: break-word;
}

.tpak-log-context {
    margin-top: 10px;
    padding: 10px;
    background: #f6f7f7;
    border-radius: 3px;
}

.tpak-log-context pre {
    margin: 0;
    white-space: pre-wrap;
    font-size: 12px;
}

.column-timestamp { width: 150px; }
.column-level { width: 80px; }
.column-category { width: 100px; }
.column-user { width: 120px; }
.column-actions { width: 80px; }
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle context display
    $('.tpak-toggle-context').on('click', function() {
        var logId = $(this).data('log-id');
        var context = $('#context-' + logId);
        var button = $(this);
        
        if (context.is(':visible')) {
            context.hide();
            button.text('<?php _e('Show Details', 'tpak-dq-system'); ?>');
        } else {
            context.show();
            button.text('<?php _e('Hide Details', 'tpak-dq-system'); ?>');
        }
    });
    
    // Copy context to clipboard
    $('.tpak-copy-context').on('click', function() {
        var context = $(this).data('context');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(context).then(function() {
                alert('<?php _e('Context copied to clipboard', 'tpak-dq-system'); ?>');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = context;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('<?php _e('Context copied to clipboard', 'tpak-dq-system'); ?>');
        }
    });
});
</script>