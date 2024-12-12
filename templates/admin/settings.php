<?php
if (!defined('ABSPATH')) {
    exit;
}

// Save settings if posted
if (isset($_POST['sr_save_settings'])) {
    check_admin_referer('sr_settings');
    
    // Update settings
    update_option('sr_company_id', sanitize_text_field($_POST['sr_company_id']));
    update_option('sr_api_token', sanitize_text_field($_POST['sr_api_token']));
    update_option('sr_webhook_secret', sanitize_text_field($_POST['sr_webhook_secret']));
    update_option('sr_debug_mode', isset($_POST['sr_debug_mode']) ? 'yes' : 'no');
    
    // Show success message
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'sr-integration') . '</p></div>';
}

$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php echo esc_html__('SalesRender Integration Settings', 'sr-integration'); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=sr-settings&tab=general" 
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('General Settings', 'sr-integration'); ?>
        </a>
        <a href="?page=sr-settings&tab=status" 
           class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Status Mapping', 'sr-integration'); ?>
        </a>
        <a href="?page=sr-settings&tab=fields" 
           class="nav-tab <?php echo $current_tab === 'fields' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Field Mapping', 'sr-integration'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'general'): ?>
            <form method="post" action="">
                <?php wp_nonce_field('sr_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sr_company_id">
                                <?php esc_html_e('Company ID', 'sr-integration'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sr_company_id" 
                                   name="sr_company_id" 
                                   value="<?php echo esc_attr(get_option('sr_company_id')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Your SalesRender Company ID', 'sr-integration'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_api_token">
                                <?php esc_html_e('API Token', 'sr-integration'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="sr_api_token" 
                                   name="sr_api_token" 
                                   value="<?php echo esc_attr(get_option('sr_api_token')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Your SalesRender API Token', 'sr-integration'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_webhook_secret">
                                <?php esc_html_e('Webhook Secret', 'sr-integration'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="sr_webhook_secret" 
                                   name="sr_webhook_secret" 
                                   value="<?php echo esc_attr(get_option('sr_webhook_secret')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Secret key for webhook verification', 'sr-integration'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_debug_mode">
                                <?php esc_html_e('Debug Mode', 'sr-integration'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sr_debug_mode" 
                                       name="sr_debug_mode" 
                                       value="1" 
                                       <?php checked(get_option('sr_debug_mode'), 'yes'); ?>>
                                <?php esc_html_e('Enable debug logging', 'sr-integration'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Log debug information for troubleshooting', 'sr-integration'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div class="sr-api-test">
                    <button type="button" 
                            id="sr-test-connection" 
                            class="button button-secondary">
                        <?php esc_html_e('Test API Connection', 'sr-integration'); ?>
                    </button>
                    <span class="spinner"></span>
                    <span class="sr-test-result"></span>
                </div>

                <p class="submit">
                    <input type="submit" 
                           name="sr_save_settings" 
                           class="button button-primary" 
                           value="<?php esc_attr_e('Save Settings', 'sr-integration'); ?>">
                </p>
            </form>

        <?php elseif ($current_tab === 'status'): ?>
            <form method="post" action="">
                <?php wp_nonce_field('sr_status_mappings'); ?>
                
                <h2><?php esc_html_e('Order Status Mapping', 'sr-integration'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Map WooCommerce order statuses to SalesRender statuses', 'sr-integration'); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('WooCommerce Status', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('SalesRender Status', 'sr-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $wc_statuses = wc_get_order_statuses();
                        $sr_statuses = array(
                            'new' => __('New', 'sr-integration'),
                            'processing' => __('Processing', 'sr-integration'),
                            'completed' => __('Completed', 'sr-integration'),
                            'cancelled' => __('Cancelled', 'sr-integration')
                        );
                        $mappings = get_option('sr_status_mappings', array());

                        foreach ($wc_statuses as $wc_status => $wc_label):
                            $current_mapping = isset($mappings[$wc_status]) ? $mappings[$wc_status] : '';
                        ?>
                            <tr>
                                <td><?php echo esc_html($wc_label); ?></td>
                                <td>
                                    <select name="sr_status_mappings[<?php echo esc_attr($wc_status); ?>]">
                                        <option value=""><?php esc_html_e('-- Select Status --', 'sr-integration'); ?></option>
                                        <?php foreach ($sr_statuses as $sr_status => $sr_label): ?>
                                            <option value="<?php echo esc_attr($sr_status); ?>" 
                                                    <?php selected($current_mapping, $sr_status); ?>>
                                                <?php echo esc_html($sr_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" 
                           name="sr_save_status_mappings" 
                           class="button button-primary" 
                           value="<?php esc_attr_e('Save Status Mappings', 'sr-integration'); ?>">
                </p>
            </form>

        <?php elseif ($current_tab === 'fields'): ?>
            <form method="post" action="">
                <?php wp_nonce_field('sr_field_mappings'); ?>
                
                <h2><?php esc_html_e('Field Mapping', 'sr-integration'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Map WooCommerce order fields to SalesRender fields', 'sr-integration'); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('WooCommerce Field', 'sr-integration'); ?></th>
                            <th><?php esc_html_e('SalesRender Field', 'sr-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $wc_fields = array(
                            'billing_first_name' => __('Billing First Name', 'sr-integration'),
                            'billing_last_name' => __('Billing Last Name', 'sr-integration'),
                            'billing_email' => __('Billing Email', 'sr-integration'),
                            'billing_phone' => __('Billing Phone', 'sr-integration'),
                            'billing_address_1' => __('Billing Address 1', 'sr-integration'),
                            'billing_city' => __('Billing City', 'sr-integration'),
                            'billing_postcode' => __('Billing Postcode', 'sr-integration')
                        );
                        $field_mappings = get_option('sr_field_mappings', array());

                        foreach ($wc_fields as $wc_field => $wc_label):
                            $current_mapping = isset($field_mappings[$wc_field]) ? $field_mappings[$wc_field] : '';
                        ?>
                            <tr>
                                <td><?php echo esc_html($wc_label); ?></td>
                                <td>
                                    <input type="text" 
                                           name="sr_field_mappings[<?php echo esc_attr($wc_field); ?>]" 
                                           value="<?php echo esc_attr($current_mapping); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" 
                           name="sr_save_field_mappings" 
                           class="button button-primary" 
                           value="<?php esc_attr_e('Save Field Mappings', 'sr-integration'); ?>">
                </p>
            </form>

        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test API connection
    $('#sr-test-connection').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('.sr-test-result');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sr_test_api_connection',
                nonce: '<?php echo wp_create_nonce('sr-admin-nonce'); ?>',
                company_id: $('#sr_company_id').val(),
                api_token: $('#sr_api_token').val()
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span class="success">' + response.data + '</span>');
                } else {
                    $result.html('<span class="error">' + response.data + '</span>');
                }
            },
            error: function() {
                $result.html('<span class="error"><?php esc_html_e('Connection test failed', 'sr-integration'); ?></span>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>

<style>
.sr-api-test {
    margin: 20px 0;
}
.sr-api-test .spinner {
    float: none;
    margin-left: 10px;
}
.sr-test-result {
    margin-left: 10px;
}
.sr-test-result .success {
    color: green;
}
.sr-test-result .error {
    color: red;
}
.nav-tab-wrapper {
    margin-bottom: 20px;
}
.form-table td {
    vertical-align: top;
}
.wp-list-table {
    margin-top: 20px;
}
</style>