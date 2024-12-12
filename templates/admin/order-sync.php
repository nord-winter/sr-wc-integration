<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get sync statistics
$stats = $this->get_sync_statistics();

// Get orders that need sync
$orders_to_sync = wc_get_orders(array(
    'status' => array_keys(wc_get_order_statuses()),
    'limit' => -1,
    'meta_query' => array(
        'relation' => 'OR',
        array(
            'key' => '_sr_order_id',
            'compare' => 'NOT EXISTS'
        ),
        array(
            'key' => '_sr_sync_failed',
            'value' => '1'
        )
    )
));
?>

<div class="wrap">
    <h1><?php esc_html_e('Order Synchronization', 'sr-integration'); ?></h1>

    <!-- Sync Statistics Dashboard -->
    <div class="sr-sync-dashboard">
        <div class="sr-stats-grid">
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Total Orders', 'sr-integration'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['total_orders']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Synced Orders', 'sr-integration'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['synced_orders']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Failed Syncs', 'sr-integration'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['failed_orders']); ?></div>
            </div>
            
            <div class="sr-stat-box">
                <h3><?php esc_html_e('Last Sync', 'sr-integration'); ?></h3>
                <div class="stat-text">
                    <?php 
                    if (!empty($stats['last_sync'])) {
                        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $stats['last_sync']));
                    } else {
                        esc_html_e('Never', 'sr-integration');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Actions -->
    <div class="sr-sync-actions">
        <h2><?php esc_html_e('Synchronization Actions', 'sr-integration'); ?></h2>
        
        <div class="sr-action-buttons">
            <button type="button" id="sr-sync-all" class="button button-primary">
                <?php esc_html_e('Sync All Orders', 'sr-integration'); ?>
            </button>
            
            <button type="button" id="sr-sync-failed" class="button button-secondary">
                <?php esc_html_e('Retry Failed Orders', 'sr-integration'); ?>
            </button>
            
            <div class="sr-sync-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">0%</div>
            </div>
        </div>
    </div>

    <!-- Orders Pending Sync -->
    <div class="sr-pending-orders">
        <h2>
            <?php esc_html_e('Orders Pending Sync', 'sr-integration'); ?>
            <span class="count">(<?php echo count($orders_to_sync); ?>)</span>
        </h2>

        <?php if (!empty($orders_to_sync)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order', 'sr-integration'); ?></th>
                        <th><?php esc_html_e('Date', 'sr-integration'); ?></th>
                        <th><?php esc_html_e('Status', 'sr-integration'); ?></th>
                        <th><?php esc_html_e('Total', 'sr-integration'); ?></th>
                        <th><?php esc_html_e('Actions', 'sr-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders_to_sync as $order): ?>
                        <tr data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">
                                    #<?php echo esc_html($order->get_order_number()); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo esc_html($order->get_date_created()->date_i18n(get_option('date_format'))); ?>
                            </td>
                            <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                            <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                            <td>
                                <button type="button" 
                                        class="button button-small sr-sync-single" 
                                        data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                                    <?php esc_html_e('Sync Now', 'sr-integration'); ?>
                                </button>
                                <span class="spinner"></span>
                                <span class="sync-status"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="sr-no-orders">
                <p><?php esc_html_e('No orders pending synchronization.', 'sr-integration'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Single order sync
    $('.sr-sync-single').on('click', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var $spinner = $row.find('.spinner');
        var $status = $row.find('.sync-status');
        var orderId = $button.data('order-id');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sr_sync_single_order',
                nonce: '<?php echo wp_create_nonce('sr-admin-nonce'); ?>',
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="success">' + response.data.message + '</span>');
                    $row.fadeOut(500, function() {
                        $(this).remove();
                        updatePendingCount();
                    });
                } else {
                    $status.html('<span class="error">' + response.data + '</span>');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                $status.html('<span class="error"><?php esc_html_e('Sync failed', 'sr-integration'); ?></span>');
                $button.prop('disabled', false);
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    });

    // Bulk sync
    $('#sr-sync-all, #sr-sync-failed').on('click', function() {
        var $button = $(this);
        var isFailed = $button.attr('id') === 'sr-sync-failed';
        var $progress = $('.sr-sync-progress');
        var $progressBar = $progress.find('.progress-bar-fill');
        var $progressText = $progress.find('.progress-text');

        $button.prop('disabled', true);
        $('.sr-action-buttons button').not($button).prop('disabled', true);
        $progress.show();

        var processOrders = function(page = 1) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sr_sync_orders_batch',
                    nonce: '<?php echo wp_create_nonce('sr-admin-nonce'); ?>',
                    page: page,
                    failed_only: isFailed ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        var progress = (response.data.processed / response.data.total) * 100;
                        $progressBar.css('width', progress + '%');
                        $progressText.text(Math.round(progress) + '%');

                        if (response.data.processed < response.data.total) {
                            processOrders(page + 1);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(response.data);
                        enableButtons();
                    }
                },
                error: function() {
                    alert('<?php esc_html_e('Sync process failed', 'sr-integration'); ?>');
                    enableButtons();
                }
            });
        };

        processOrders();
    });

    function enableButtons() {
        $('.sr-action-buttons button').prop('disabled', false);
        $('.sr-sync-progress').hide();
    }

    function updatePendingCount() {
        var count = $('.sr-pending-orders tbody tr').length;
        $('.sr-pending-orders .count').text('(' + count + ')');
        if (count === 0) {
            $('.sr-pending-orders tbody').html(
                '<tr><td colspan="5"><?php esc_html_e('No orders pending synchronization.', 'sr-integration'); ?></td></tr>'
            );
        }
    }
});
</script>

<style>
.sr-sync-dashboard {
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
    color: #23282d;
    font-size: 14px;
}

.stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
}

.stat-text {
    font-size: 16px;
    color: #50575e;
}

.sr-sync-actions {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
}

.sr-action-buttons {
    margin-top: 15px;
}

.sr-action-buttons button {
    margin-right: 10px;
}

.sr-sync-progress {
    margin-top: 15px;
}

.progress-bar {
    height: 20px;
    background: #f1f1f1;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
}

.progress-text {
    margin-top: 5px;
    text-align: center;
}

.sr-pending-orders {
    margin-top: 20px;
}

.sr-pending-orders h2 {
    margin-bottom: 15px;
}

.sr-pending-orders .count {
    color: #666;
    font-size: 14px;
    font-weight: normal;
}

.spinner {
    float: none;
    margin: 0 10px;
}

.sync-status {
    display: inline-block;
    margin-left: 10px;
}

.sync-status .success {
    color: #46b450;
}

.sync-status .error {
    color: #dc3232;
}

.sr-no-orders {
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    text-align: center;
}
</style>