<?php
/**
 * Plugin Name: SalesRender WooCommerce Integration
 * Plugin URI: https://github.com/nord-winter/sr-wc-integration
 * Description: Integration between WooCommerce, SalesRender CRM and OPN Payment Gateway
 * Version: 1.0.0
 * Author: Vladislav Simutin 
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-sr-autoloader.php';
SR_Autoloader::register();

final class SR_WC_Integration {
    private static $instance = null;
    private $plugin_path;
    private $plugin_url;
    private $version = '1.0.0'; 
    
    // Константы для статусов заказов
    const STATUS_NEW = 'sr-new';
    const STATUS_PROCESSING = 'sr-processing';
    const STATUS_COMPLETED = 'sr-completed';
    const STATUS_FAILED = 'sr-failed';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Initialize plugin after WooCommerce loads
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }
    private function init_other_components() {
        // Инициализация других компонентов
        $this->includes();
        
        // Инициализация чекаута
        if (class_exists('SR_Checkout')) {
            new SR_Checkout();
        }
        
        // Инициализация синхронизации заказов
        if (class_exists('SR_Order_Sync')) {
            new SR_Order_Sync();
        }
        
        // Инициализация платежного функционала
        if (class_exists('SR_Payment')) {
            new SR_Payment();
        }
    }

    private function includes() {
        // API и интеграции
        require_once $this->plugin_path . 'includes/api/class-sr-api.php';
        require_once $this->plugin_path . 'includes/api/class-opn-api.php';
        
        // Основные классы
        require_once $this->plugin_path . 'includes/class-sr-checkout.php';
        require_once $this->plugin_path . 'includes/class-sr-order-sync.php';
        require_once $this->plugin_path . 'includes/class-sr-payment.php';
        require_once $this->plugin_path . 'includes/admin/class-sr-settings.php';
        
        // Административные классы
        if (is_admin()) {
            require_once $this->plugin_path . 'includes/admin/class-sr-admin.php';
        }
    }

    private function init_hooks() {
        // Инициализация плагина
        add_action('plugins_loaded', array($this, 'init_plugin'));
        
        // Подключение стилей и скриптов
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Обработка заказов
        add_action('woocommerce_checkout_order_processed', array($this, 'sync_order_to_salesrender'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Интеграция с OPN
        add_filter('woocommerce_payment_gateways', array($this, 'add_opn_gateway'));
    }

    public function init_plugin() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     esc_html__('SalesRender Integration requires WooCommerce to be installed and active.', 'sr-integration') . 
                     '</p></div>';
            });
            return;
        }

        // Initialize components
        $this->init_payment_gateway();
        $this->init_other_components();
    }

    public function enqueue_frontend_assets() {
        if (is_checkout()) {
            // Стили для десктопной и мобильной версии
            wp_enqueue_style('sr-checkout', $this->plugin_url . 'assets/css/checkout.css', array(), '1.0.0');
            wp_enqueue_style('sr-checkout-mobile', $this->plugin_url . 'assets/css/checkout-mobile.css', array(), '1.0.0');
            
            // Скрипты для checkout
            wp_enqueue_script('sr-checkout', $this->plugin_url . 'assets/js/checkout.js', array('jquery'), '1.0.0', true);
            
            // Локализация для JS
            wp_localize_script('sr-checkout', 'sr_checkout_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sr-checkout-nonce'),
                'is_mobile' => wp_is_mobile()
            ));
        }
    }

    private function init_payment_gateway() {
        if (class_exists('SR_OPN_Gateway')) {
            SR_OPN_Gateway::init();
        }
    }

    /**
     * Admin notice for missing WooCommerce
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('SalesRender Integration requires WooCommerce to be installed and active.', 'sr-integration'); ?></p>
        </div>
        <?php
    }

    private function register_order_statuses() {
        register_post_status(self::STATUS_NEW, array(
            'label' => _x('New (SalesRender)', 'Order status', 'sr-integration'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('New (SalesRender) <span class="count">(%s)</span>',
                                   'New (SalesRender) <span class="count">(%s)</span>')
        ));
        // Регистрация остальных статусов...
    }

    public function sync_order_to_salesrender($order_id, $posted_data, $order) {
        $sr_api = new SR_API();
        $sr_api->create_order($order);
    }

    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        $sr_api = new SR_API();
        $sr_api->update_order_status($order_id, $new_status);
    }

    // Вспомогательные методы
    public function get_plugin_path() {
        return $this->plugin_path;
    }

    public function get_plugin_url() {
        return $this->plugin_url;
    }
        /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}

// Инициализация плагина
function SR_WC_Integration() {
    return SR_WC_Integration::instance();
}

$GLOBALS['sr_wc_integration'] = SR_WC_Integration();