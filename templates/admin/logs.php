<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle log actions
if (isset($_POST['sr_clear_logs'])) {
    check_admin_referer('sr_logs_actions');
    $this->clear_logs();
    echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared successfully.', 'sr-integration') . '</p></div>';
}

// Get log entries with filters
$log_level = isset($_GET['log_level']) ? sanitize_text_field($_GET['log_level']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search = isset($_GET['log_search']) ? sanitize_text_field($_GET['log_search']) : '';

$logs = $this->get_filtered_logs([
    'level' => $log_level,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'search' => $search
]);

// Get log statistics
$stats = $this->get_log_statistics();
?>

<div class="wrap">
    <h1>
        <?php esc_html_e('Integration Logs', 'sr-integration'); ?>
        <a href="<?php echo esc_url(add_query_arg('sr_export_logs', '1')); ?>" 
           class="page-title-action">
            <?php esc_html_e('Export Logs', 'sr-integration'); ?>
        </a>
    </h1>

    <!-- Log Statistics -->
    <div class="sr-log-stats">
        <div class="sr-stats-grid">
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Total Entries', 'sr-integration'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Errors', 'sr-integration'); ?></h3>
                <div class="stat-number error"><?php echo esc_html($stats['errors']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Warnings', 'sr-integration'); ?></h3>
                <div class="stat-number warning"><?php echo esc_html($stats['warnings']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Last Entry', 'sr-integration'); ?></h3>
                <div class="stat-text">
                    <?php 
                    if (!empty($stats['last_entry'])) {
                        echo esc_html(date_i18n(
                            get_option('date_format') . ' ' . get_option('time_format'), 
                            strtotime($stats['last_entry'])
                        ));
                    } else {
                        esc_html_e('No entries', 'sr-integration');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sr-log-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="sr-logs">
            
            <div class="sr-filters-grid">
                <div class="filter-item">
                    <select name="log_level">
                        <option value=""><?php esc_html_e('All Levels', 'sr-integration'); ?></option>
                        <option value="error" <?php selected($log_level, 'error'); ?>>
                            <?php esc_html_e('Errors', 'sr-integration'); ?>
                        </option>
                        <option value="warning" <?php selected($log_level, 'warning'); ?>>
                            <?php esc_html_e('Warnings', 'sr-integration'); ?>
                        </option>
                        <option value="info" <?php selected($log_level, 'info'); ?>>
                            <?php esc_html_e('Info', 'sr-integration'); ?>
                        </option>
                    </select>
                </div>

                <div class="filter-item">
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo esc_attr($date_from); ?>" 
                           placeholder="<?php esc_attr_e('From Date', 'sr-integration'); ?>">
                </div>

                <div class="filter-item">
                    <input type="date" 
                           name="date_to" 
                           value="<?php echo esc_attr($date_to); ?>" 
                           placeholder="<?php esc_attr_e('To Date', 'sr-integration'); ?>">
                </div>

                <div class="filter-item">
                    <input type="text" 
                           name="log_search" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="<?php esc_attr_e('Search logs...', 'sr-integration'); ?>">
                </div>

                <div class="filter-item">
                    <button type="submit" class="button">
                        <?php esc_html_e('Apply Filters', 'sr-integration'); ?>
                    </button>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sr-logs')); ?>" 
                       class="button">
                        <?php esc_html_e('Reset', 'sr-integration'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Log Actions -->
    <div class="sr-log-actions">
        <form method="post" action="" class="alignright">
            <?php wp_nonce_field('sr_logs_actions'); ?>
            <input type="submit" 
                   name="sr_clear_logs" 
                   class="button" 
                   value="<?php esc_attr_e('Clear Logs', 'sr-integration'); ?>"
                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'sr-integration'); ?>');">
        </form>
    </div>

    <!-- Log Table -->
    <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-time"><?php esc_html_e('Time', 'sr-integration'); ?></th>
                    <th class="column-level"><?php esc_html_e('Level', 'sr-integration'); ?></th>
                    <th class="column-source"><?php esc_html_e('Source', 'sr-integration'); ?></th>
                    <th class="column-message"><?php esc_html_e('Message', 'sr-integration'); ?></th>
                    <th class="column-context"><?php esc_html_e('Context', 'sr-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr class="log-entry log-level-<?php echo esc_attr(strtolower($log['level'])); ?>">
                        <td>
                            <?php echo esc_html(date_i18n(
                                get_option('date_format') . ' ' . get_option('time_format'),
                                strtotime($log['time'])
                            )); ?>
                        </td>
                        <td>
                            <span class="log-level">
                                <?php echo esc_html($log['level']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['source']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td>
                            <?php if (!empty($log['context'])): ?>
                                <button type="button" 
                                        class="button button-small toggle-context" 
                                        data-context="<?php echo esc_attr(json_encode($log['context'])); ?>">
                                    <?php esc_html_e('Show Details', 'sr-integration'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php $this->render_log_pagination(); ?>

    <?php else: ?>
        <div class="sr-no-logs">
            <p><?php esc_html_e('No log entries found.', 'sr-integration'); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Context Modal -->
<div id="sr-context-modal" class="sr-modal" style="display: none;">
    <div class="sr-modal-content">
        <span class="sr-modal-close">&times;</span>
        <h2><?php esc_html_e('Log Details', 'sr-integration'); ?></h2>
        <pre class="sr-context-details"></pre>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle context details
    $('.toggle-context').on('click', function() {
        var context = $(this).data('context');
        $('.sr-context-details').html(JSON.stringify(context, null, 2));
        $('#sr-context-modal').show();
    });

    // Close modal
    $('.sr-modal-close').on('click', function() {
        $('#sr-context-modal').hide();
    });

    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('sr-modal')) {
            $('#sr-context-modal').hide();
        }
    });
});
</script>

<style>
.sr-log-stats {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.sr-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.sr-stat-box {
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    text-align: center;
}

.sr-stat-box h3 {
    margin: 0 0 10px;
    font-size: 14px;
}

.stat-number {
    font-size: 24px;
    font-weight: 600;
}

.stat-number.error {
    color: #dc3232;
}

.stat-number.warning {
    color: #ffb900;
}

.sr-log-filters {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.sr-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-item input,
.filter-item select {
    width: 100%;
}

.sr-log-actions {
    margin: 20px 0;
    overflow: hidden;
}

.log-entry td {
    vertical-align: middle;
}

.log-level {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.log-level-error .log-level {
    background: #ffd8d8;
    color: #dc3232;
}

.log-level-warning .log-level {
    background: #fff5e6;
    color: #ffb900;
}

.log-level-info .log-level {
    background: #e8f4f9;
    color: #2271b1;
}

.column-time {
    width: 15%;
}

.column-level {
    width: 10%;
}

.column-source {
    width: 15%;
}

.column-context {
    width: 10%;
}

.sr-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    z-index: 100000;
}

.sr-modal-content {
    position: relative;
    background: #fff;
    margin: 5% auto;
    padding: 20px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    border-radius: 4px;
}

.sr-modal-close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 24px;
    cursor: pointer;
}

.sr-context-details {
    background: #f8f9fa;
    padding: 15px;
    margin: 10px 0;
    overflow-x: auto;
    white-space: pre-wrap;
}

.sr-no-logs {
    padding: 40px;
    text-align: center;
    background: #fff;
    border: 1px solid #ccd0d4;
}

/* Mobile responsiveness */
@media screen and (max-width: 782px) {
    .sr-filters-grid {
        grid-template-columns: 1fr;
    }
    
    .column-time,
    .column-level,
    .column-source {
        width: auto;
    }
}
</style>