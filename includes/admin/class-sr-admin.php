<?php
/**
 * Class SR_Admin
 * Handles all admin functionality for the SalesRender integration
 */
class SR_Admin
{
    /**
     * @var string
     */
    private $plugin_path;

    /**
     * @var string
     */
    private $plugin_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->plugin_path = SR_WC_Integration()->get_plugin_path();
        $this->plugin_url = SR_WC_Integration()->get_plugin_url();

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Ajax handlers
        add_action('wp_ajax_sr_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_sr_sync_orders', array($this, 'sync_orders'));
        add_action('wp_ajax_sr_get_project_list', array($this, 'get_project_list'));
        
        $log_dir = WP_CONTENT_DIR . '/uploads/sr-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }

    /**
     * Add menu items
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('SalesRender', 'sr-integration'),
            __('SalesRender', 'sr-integration'),
            'manage_options',
            'sr-settings',
            array($this, 'render_settings_page'),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'sr-settings',
            __('Settings', 'sr-integration'),
            __('Settings', 'sr-integration'),
            'manage_options',
            'sr-settings'
        );

        add_submenu_page(
            'sr-settings',
            __('Order Sync', 'sr-integration'),
            __('Order Sync', 'sr-integration'),
            'manage_options',
            'sr-order-sync',
            array($this, 'render_order_sync_page')
        );

        add_submenu_page(
            'sr-settings',
            __('Products', 'sr-integration'),
            __('Products', 'sr-integration'),
            'manage_options',
            'sr-products',
            array($this, 'render_products_page')
        );

        add_submenu_page(
            'sr-settings',
            __('Logs', 'sr-integration'),
            __('Logs', 'sr-integration'),
            'manage_options',
            'sr-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Initialize plugin settings
     */
    public function init_settings()
    {
        // General Settings
        register_setting('sr_general_settings', 'sr_company_id');
        register_setting('sr_general_settings', 'sr_api_token');
        register_setting('sr_general_settings', 'sr_webhook_secret');
        register_setting('sr_general_settings', 'sr_debug_mode');

        // Field Mappings
        register_setting('sr_field_mappings', 'sr_field_mappings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_field_mappings')
        ));

        // Status Mappings
        register_setting('sr_status_mappings', 'sr_status_mappings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_status_mappings')
        ));

        // Product Settings
        register_setting('sr_product_settings', 'sr_product_mappings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_product_mappings')
        ));
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'sr-') === false) {
            return;
        }

        wp_enqueue_style(
            'sr-admin-styles',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'sr-admin-script',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('sr-admin-script', 'sr_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sr-admin-nonce'),
            'strings' => array(
                'test_connection_success' => __('API connection successful!', 'sr-integration'),
                'test_connection_error' => __('API connection failed: ', 'sr-integration'),
                'sync_success' => __('Orders synchronized successfully!', 'sr-integration'),
                'sync_error' => __('Order synchronization failed: ', 'sr-integration')
            )
        ));
    }

    /**
     * Render main settings page
     */
    public function render_settings_page()
    {
        // Load settings template
        require_once $this->plugin_path . 'templates/admin/settings.php';
    }

    /**
     * Render order sync page
     */
    public function render_order_sync_page()
    {
        // Get sync status and statistics
        $sync_stats = $this->get_sync_statistics();

        // Load order sync template
        require_once $this->plugin_path . 'templates/admin/order-sync.php';
    }

    /**
     * Render products page
     */
    public function render_products_page()
    {
        // Get product mappings
        $product_mappings = get_option('sr_product_mappings', array());

        // Load products template
        require_once $this->plugin_path . 'templates/admin/products.php';
    }

    /**
     * Render logs page
     */
    public function render_logs_page()
    {
        // Get logs
        $logs = $this->get_logs();

        // Load logs template
        require_once $this->plugin_path . 'templates/admin/logs.php';
    }

    /**
     * Test API connection
     */
    public function test_api_connection()
    {
        check_ajax_referer('sr-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'sr-integration'));
        }

        try {
            $api = new SR_API();
            $result = $api->send_request('query { ping }');

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('Connection successful!', 'sr-integration'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Sync orders manually
     */
    public function sync_orders()
    {
        check_ajax_referer('sr-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'sr-integration'));
        }

        try {
            $sync = new SR_Order_Sync();
            $result = $sync->sync_all_orders();

            wp_send_json_success(array(
                'message' => __('Orders synchronized successfully!', 'sr-integration'),
                'stats' => $result
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get project list from SalesRender
     */
    public function get_project_list()
    {
        check_ajax_referer('sr-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'sr-integration'));
        }

        try {
            $api = new SR_API();
            $result = $api->get_projects();

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get synchronization statistics
     * 
     * @return array
     */
    private function get_sync_statistics()
    {
        global $wpdb;

        $stats = array(
            'total_orders' => 0,
            'synced_orders' => 0,
            'failed_orders' => 0,
            'last_sync' => get_option('sr_last_sync_time')
        );

        // Get total orders count
        $stats['total_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND post_status != 'trash'"
        );

        // Get synced orders count
        $stats['synced_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sr_order_id'"
        );

        // Get failed orders count
        $stats['failed_orders'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sr_sync_failed' 
            AND meta_value = '1'"
        );

        return $stats;
    }

    /**
     * Get plugin logs
     * 
     * @return array
     */
    private function get_logs()
    {
        $log_file = WC_LOG_DIR . 'sr-integration.log';
        $logs = array();

        if (file_exists($log_file)) {
            $logs = array_reverse(file($log_file));
        }

        return array_slice($logs, 0, 1000); // Return last 1000 lines
    }

    /**
     * Sanitize field mappings
     * 
     * @param array $mappings
     * @return array
     */
    public function sanitize_field_mappings($mappings)
    {
        if (!is_array($mappings)) {
            return array();
        }

        $sanitized = array();
        foreach ($mappings as $wc_field => $sr_field) {
            $sanitized[sanitize_text_field($wc_field)] = sanitize_text_field($sr_field);
        }

        return $sanitized;
    }

    /**
     * Sanitize status mappings
     * 
     * @param array $mappings
     * @return array
     */
    public function sanitize_status_mappings($mappings)
    {
        if (!is_array($mappings)) {
            return array();
        }

        $sanitized = array();
        foreach ($mappings as $wc_status => $sr_status) {
            $sanitized[sanitize_text_field($wc_status)] = sanitize_text_field($sr_status);
        }

        return $sanitized;
    }

    /**
     * Sanitize product mappings
     * 
     * @param array $mappings
     * @return array
     */
    public function sanitize_product_mappings($mappings)
    {
        if (!is_array($mappings)) {
            return array();
        }

        $sanitized = array();
        foreach ($mappings as $wc_product_id => $sr_product) {
            $sanitized[(int) $wc_product_id] = array(
                'sr_item_id' => (int) $sr_product['sr_item_id'],
                'variation' => isset($sr_product['variation']) ? (int) $sr_product['variation'] : 1
            );
        }

        return $sanitized;
    }

    /**
     * Get filtered logs
     * 
     * @param array $filters Array of filters (level, date_from, date_to, search)
     * @return array
     */
    private function get_filtered_logs($filters = array())
    {
        // Получаем путь к файлу логов
        $log_file = WP_CONTENT_DIR . '/uploads/sr-logs/debug.log';
        $logs = array();

        if (!file_exists($log_file)) {
            return $logs;
        }

        // Читаем файл логов
        $handle = fopen($log_file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                // Парсим строку лога
                $log_entry = $this->parse_log_line($line);

                if ($log_entry) {
                    // Применяем фильтры
                    if ($this->should_include_log($log_entry, $filters)) {
                        $logs[] = $log_entry;
                    }
                }
            }
            fclose($handle);
        }

        return array_reverse($logs); // Новые записи первыми
    }

    /**
     * Parse single log line
     * 
     * @param string $line
     * @return array|false
     */
    private function parse_log_line($line)
    {
        // Пример формата: [2024-01-17 10:30:45] ERROR: Something went wrong {"context": "additional data"}
        if (preg_match('/^\[(.*?)\]\s+(\w+):\s+(.*?)(\s+\{.*\})?$/', $line, $matches)) {
            return array(
                'time' => $matches[1],
                'level' => strtoupper($matches[2]),
                'message' => $matches[3],
                'context' => isset($matches[4]) ? json_decode($matches[4], true) : array(),
                'source' => 'SR Integration'
            );
        }
        return false;
    }

    /**
     * Check if log entry matches filters
     * 
     * @param array $entry Log entry
     * @param array $filters Applied filters
     * @return bool
     */
    private function should_include_log($entry, $filters)
    {
        // Фильтр по уровню
        if (!empty($filters['level']) && strtoupper($filters['level']) !== $entry['level']) {
            return false;
        }

        // Фильтр по дате "с"
        if (!empty($filters['date_from'])) {
            $entry_time = strtotime($entry['time']);
            $filter_time = strtotime($filters['date_from']);
            if ($entry_time < $filter_time) {
                return false;
            }
        }

        // Фильтр по дате "по"
        if (!empty($filters['date_to'])) {
            $entry_time = strtotime($entry['time']);
            $filter_time = strtotime($filters['date_to']) + 86399; // Конец дня
            if ($entry_time > $filter_time) {
                return false;
            }
        }

        // Поиск по тексту
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $text = strtolower($entry['message']);
            if (strpos($text, $search) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get log statistics
     * 
     * @return array
     */
    private function get_log_statistics()
    {
        $logs = $this->get_filtered_logs();
        $stats = array(
            'total' => count($logs),
            'errors' => 0,
            'warnings' => 0,
            'last_entry' => null
        );

        foreach ($logs as $log) {
            if ($log['level'] === 'ERROR') {
                $stats['errors']++;
            } elseif ($log['level'] === 'WARNING') {
                $stats['warnings']++;
            }

            if (!$stats['last_entry']) {
                $stats['last_entry'] = $log['time'];
            }
        }

        return $stats;
    }

    /**
     * Clear logs
     */
    private function clear_logs()
    {
        $log_file = WP_CONTENT_DIR . '/uploads/sr-logs/debug.log';
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
    }

    /**
     * Render log pagination
     */
    private function render_log_pagination()
    {
        $page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
        $per_page = 50;
        $total_logs = count($this->get_filtered_logs());
        $total_pages = ceil($total_logs / $per_page);

        if ($total_pages <= 1) {
            return;
        }

        echo '<div class="sr-pagination">';

        // Previous
        if ($page > 1) {
            printf(
                '<a href="%s" class="button">%s</a>',
                esc_url(add_query_arg('log_page', $page - 1)),
                esc_html__('Previous', 'sr-integration')
            );
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === $page) {
                printf('<span class="button button-primary">%d</span>', $i);
            } else {
                printf(
                    '<a href="%s" class="button">%d</a>',
                    esc_url(add_query_arg('log_page', $i)),
                    $i
                );
            }
        }

        // Next
        if ($page < $total_pages) {
            printf(
                '<a href="%s" class="button">%s</a>',
                esc_url(add_query_arg('log_page', $page + 1)),
                esc_html__('Next', 'sr-integration')
            );
        }

        echo '</div>';
    }
}

// Initialize admin
return new SR_Admin();