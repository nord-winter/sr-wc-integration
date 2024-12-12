<?php
/**
 * Class SR_Order_Sync
 * Handles order synchronization between WooCommerce and SalesRender
 */
class SR_Order_Sync {
    /**
     * @var SR_API
     */
    private $api;

    /**
     * Status mapping between WooCommerce and SalesRender
     */
    private $status_mapping = [
        'wc_to_sr' => [
            'pending' => 'new',
            'processing' => 'in_progress',
            'completed' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded'
        ],
        'sr_to_wc' => [
            'new' => 'pending',
            'in_progress' => 'processing',
            'completed' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded'
        ]
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new SR_API();
        
        // WooCommerce hooks
        add_action('woocommerce_new_order', array($this, 'sync_new_order'), 10, 1);
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_status'), 10, 4);
        
        // SalesRender webhook handler
        add_action('wp_ajax_nopriv_sr_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_sr_webhook', array($this, 'handle_webhook'));

        // Init webhook endpoint
        add_action('init', array($this, 'register_webhook_endpoint'));
    }

    /**
     * Register webhook endpoint for SalesRender
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule(
            '^sr-webhook/?$',
            'index.php?sr_webhook=1',
            'top'
        );
        add_filter('query_vars', function($vars) {
            $vars[] = 'sr_webhook';
            return $vars;
        });
        add_action('template_redirect', array($this, 'handle_webhook_request'));
    }

    /**
     * Handle webhook requests from SalesRender
     */
    public function handle_webhook_request() {
        if (get_query_var('sr_webhook')) {
            // Verify webhook signature
            if (!$this->verify_webhook_signature()) {
                status_header(401);
                exit('Invalid webhook signature');
            }

            // Get webhook data
            $payload = file_get_contents('php://input');
            $data = json_decode($payload, true);

            if (!empty($data['orderId']) && !empty($data['status'])) {
                $this->update_wc_order_status($data['orderId'], $data['status']);
            }

            status_header(200);
            exit('Webhook processed');
        }
    }

    /**
     * Verify webhook signature from SalesRender
     * 
     * @return boolean
     */
    private function verify_webhook_signature() {
        if (empty($_SERVER['HTTP_X_SR_SIGNATURE'])) {
            return false;
        }

        $signature = $_SERVER['HTTP_X_SR_SIGNATURE'];
        $payload = file_get_contents('php://input');
        $secret = get_option('sr_webhook_secret');

        return hash_equals(
            $signature,
            hash_hmac('sha256', $payload, $secret)
        );
    }

    /**
     * Sync new order to SalesRender
     * 
     * @param int $order_id WooCommerce order ID
     */
    public function sync_new_order($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            $result = $this->api->create_order($order);
            
            if (!is_wp_error($result)) {
                // Store SalesRender order ID
                $order->update_meta_data('_sr_order_id', $result['data']['orderMutation']['addOrder']['id']);
                $order->save();
                
                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('Order synchronized with SalesRender. SR Order ID: %s', 'sr-integration'),
                        $result['data']['orderMutation']['addOrder']['id']
                    )
                );
            } else {
                $order->add_order_note(
                    sprintf(
                        __('Failed to sync order with SalesRender: %s', 'sr-integration'),
                        $result->get_error_message()
                    )
                );
            }
        } catch (Exception $e) {
            $order->add_order_note(
                sprintf(
                    __('Error syncing order with SalesRender: %s', 'sr-integration'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Sync order status changes to SalesRender
     * 
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function sync_order_status($order_id, $old_status, $new_status, $order) {
        // Skip if status hasn't changed
        if ($old_status === $new_status) {
            return;
        }

        // Get SalesRender order ID
        $sr_order_id = $order->get_meta('_sr_order_id');
        if (!$sr_order_id) {
            return;
        }

        // Map WooCommerce status to SalesRender status
        $sr_status = $this->map_status_to_sr($new_status);
        if (!$sr_status) {
            return;
        }

        try {
            $result = $this->api->update_order_status($sr_order_id, $sr_status);
            
            if (!is_wp_error($result)) {
                $order->add_order_note(
                    sprintf(
                        __('Order status synchronized with SalesRender: %s', 'sr-integration'),
                        $sr_status
                    )
                );
            } else {
                $order->add_order_note(
                    sprintf(
                        __('Failed to sync status with SalesRender: %s', 'sr-integration'),
                        $result->get_error_message()
                    )
                );
            }
        } catch (Exception $e) {
            $order->add_order_note(
                sprintf(
                    __('Error syncing status with SalesRender: %s', 'sr-integration'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Update WooCommerce order status from SalesRender webhook
     * 
     * @param string $sr_order_id SalesRender order ID
     * @param string $sr_status SalesRender status
     */
    private function update_wc_order_status($sr_order_id, $sr_status) {
        global $wpdb;

        // Find WooCommerce order by SalesRender ID
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sr_order_id' 
            AND meta_value = %s",
            $sr_order_id
        ));

        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Map SalesRender status to WooCommerce status
        $wc_status = $this->map_status_to_wc($sr_status);
        if (!$wc_status) {
            return;
        }

        // Update order status
        $order->update_status(
            $wc_status,
            sprintf(
                __('Status updated from SalesRender: %s', 'sr-integration'),
                $sr_status
            )
        );
    }

    /**
     * Map WooCommerce status to SalesRender status
     * 
     * @param string $wc_status
     * @return string|null
     */
    private function map_status_to_sr($wc_status) {
        $mapping = get_option('sr_status_mapping_to_sr', $this->status_mapping['wc_to_sr']);
        return isset($mapping[$wc_status]) ? $mapping[$wc_status] : null;
    }

    /**
     * Map SalesRender status to WooCommerce status
     * 
     * @param string $sr_status
     * @return string|null
     */
    private function map_status_to_wc($sr_status) {
        $mapping = get_option('sr_status_mapping_to_wc', $this->status_mapping['sr_to_wc']);
        return isset($mapping[$sr_status]) ? $mapping[$sr_status] : null;
    }
}