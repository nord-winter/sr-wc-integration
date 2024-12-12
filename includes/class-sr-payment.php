<?php
/**
 * Class SR_Payment
 * Handles payment processing and OPN Payment Gateway integration
 */
class SR_Payment {
    /**
     * @var SR_Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new SR_Settings();

        // Initialize hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_payment_scripts'));
        add_action('wp_ajax_sr_process_payment', array($this, 'process_payment_ajax'));
        add_action('wp_ajax_nopriv_sr_process_payment', array($this, 'process_payment_ajax'));
        
        // Order processing hooks
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);
        add_action('woocommerce_order_refunded', array($this, 'handle_order_refund'), 10, 2);

        // Add OPN webhook handler
        add_action('woocommerce_api_sr_opn_webhook', array($this, 'handle_opn_webhook'));
    }

    /**
     * Enqueue payment related scripts
     */
    public function enqueue_payment_scripts() {
        if (!is_checkout()) {
            return;
        }

        // Enqueue OPN SDK
        wp_enqueue_script(
            'opn-js',
            'https://cdn.omise.co/omise.js',
            array(),
            null,
            true
        );

        // Enqueue our payment script
        wp_enqueue_script(
            'sr-payment',
            SR_WC_Integration()->get_plugin_url() . 'assets/js/payment.js',
            array('jquery', 'opn-js'),
            SR_WC_Integration()->get_version(),
            true
        );

        // Pass payment configuration to JS
        wp_localize_script('sr-payment', 'sr_payment_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sr_payment'),
            'public_key' => $this->get_public_key(),
            'is_test_mode' => $this->is_test_mode(),
            'currency' => get_woocommerce_currency(),
            '3ds_enabled' => $this->is_3ds_enabled(),
            'submit_text' => __('Pay Now', 'sr-integration'),
            'processing_text' => __('Processing...', 'sr-integration'),
            'error_messages' => array(
                'card_error' => __('Card validation failed. Please check your card details.', 'sr-integration'),
                'network_error' => __('Network error occurred. Please try again.', 'sr-integration'),
                'generic_error' => __('An error occurred. Please try again.', 'sr-integration')
            )
        ));
    }

    /**
     * Process payment via Ajax
     */
    public function process_payment_ajax() {
        check_ajax_referer('sr_payment', 'nonce');

        try {
            $order_id = absint($_POST['order_id']);
            $order = wc_get_order($order_id);

            if (!$order) {
                throw new Exception(__('Order not found.', 'sr-integration'));
            }

            $token = sanitize_text_field($_POST['token']);
            if (empty($token)) {
                throw new Exception(__('Payment token not received.', 'sr-integration'));
            }

            $result = $this->process_payment($order, $token);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success(array(
                'redirect' => $result['redirect'],
                'result' => $result['result']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Process payment
     * 
     * @param WC_Order $order
     * @param string $token
     * @return array|WP_Error
     */
    public function process_payment($order, $token) {
        try {
            $opn = $this->get_opn_client();

            // Create charge
            $charge_data = array(
                'amount' => $this->get_amount_in_cents($order->get_total()),
                'currency' => strtolower($order->get_currency()),
                'card' => $token,
                'capture' => true,
                'description' => sprintf(
                    __('Order %s from %s', 'sr-integration'),
                    $order->get_order_number(),
                    get_bloginfo('name')
                ),
                'metadata' => array(
                    'order_id' => $order->get_id(),
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email()
                )
            );

            // Add 3DS if enabled
            if ($this->is_3ds_enabled()) {
                $charge_data['return_uri'] = $this->get_3ds_return_url($order);
            }

            $charge = $opn->charges()->create($charge_data);

            // Handle 3DS redirect
            if ($charge->status === 'pending' && !empty($charge->authorize_uri)) {
                return array(
                    'result' => 'success',
                    'redirect' => $charge->authorize_uri
                );
            }

            // Handle immediate charge result
            if ($charge->status === 'successful') {
                $this->handle_successful_payment($order, $charge);

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }

            // Handle failed charge
            throw new Exception($charge->failure_message ?? __('Payment failed.', 'sr-integration'));

        } catch (Exception $e) {
            return new WP_Error('payment_error', $e->getMessage());
        }
    }

    /**
     * Handle OPN webhook
     */
    public function handle_opn_webhook() {
        try {
            $payload = file_get_contents('php://input');
            
            if (!$this->verify_webhook_signature($payload)) {
                throw new Exception('Invalid webhook signature');
            }

            $event = json_decode($payload);

            switch ($event->type) {
                case 'charge.complete':
                    $this->handle_successful_charge($event->data);
                    break;

                case 'charge.failed':
                    $this->handle_failed_charge($event->data);
                    break;

                case 'refund.created':
                    $this->handle_refund_created($event->data);
                    break;
            }

            status_header(200);
            exit('Webhook processed');

        } catch (Exception $e) {
            status_header(400);
            exit($e->getMessage());
        }
    }

    /**
     * Handle successful payment
     * 
     * @param WC_Order $order
     * @param object $charge
     */
    private function handle_successful_payment($order, $charge) {
        // Update order status
        $order->payment_complete($charge->id);

        // Add order note
        $order->add_order_note(
            sprintf(__('Payment completed via OPN (Transaction ID: %s)', 'sr-integration'), 
            $charge->id)
        );

        // Save charge metadata
        $order->update_meta_data('_opn_charge_id', $charge->id);
        $order->update_meta_data('_opn_payment_method', $charge->card->brand);
        $order->update_meta_data('_opn_card_last4', $charge->card->last_digits);
        $order->save();

        // Empty cart
        WC()->cart->empty_cart();
    }

    /**
     * Handle successful charge from webhook
     * 
     * @param object $charge_data
     */
    private function handle_successful_charge($charge_data) {
        $order_id = $charge_data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Don't process already completed orders
        if ($order->is_paid()) {
            return;
        }

        $this->handle_successful_payment($order, $charge_data);
    }

    /**
     * Handle failed charge from webhook
     * 
     * @param object $charge_data
     */
    private function handle_failed_charge($charge_data) {
        $order_id = $charge_data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Don't process already failed orders
        if ($order->has_status('failed')) {
            return;
        }

        $order->update_status(
            'failed',
            sprintf(
                __('Payment failed: %s', 'sr-integration'),
                $charge_data->failure_message ?? __('Unknown error', 'sr-integration')
            )
        );
    }

    /**
     * Handle refund created from webhook
     * 
     * @param object $refund_data
     */
    private function handle_refund_created($refund_data) {
        $order_id = $refund_data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $amount = $this->get_amount_in_currency($refund_data->amount);

        wc_create_refund(array(
            'amount' => $amount,
            'reason' => $refund_data->description ?? '',
            'order_id' => $order_id,
            'refund_payment' => false
        ));

        $order->add_order_note(
            sprintf(
                __('Refund processed via OPN for %s (Transaction ID: %s)', 'sr-integration'),
                wc_price($amount),
                $refund_data->id
            )
        );
    }

    /**
     * Get OPN client instance
     * 
     * @return OmiseClient
     */
    private function get_opn_client() {
        return new OmiseClient(
            $this->settings->get_api_credentials()['secret_key']
        );
    }

    /**
     * Get public key for JS SDK
     * 
     * @return string
     */
    private function get_public_key() {
        $credentials = $this->settings->get_api_credentials();
        return $this->is_test_mode() ? 
            $credentials['test_public_key'] : 
            $credentials['public_key'];
    }

    /**
     * Check if test mode is enabled
     * 
     * @return bool
     */
    private function is_test_mode() {
        return $this->settings->get_api_credentials()['test_mode'] === 'yes';
    }

    /**
     * Check if 3DS is enabled
     * 
     * @return bool
     */
    private function is_3ds_enabled() {
        return $this->settings->get_api_credentials()['3ds_enabled'] === 'yes';
    }

    /**
     * Convert amount to cents
     * 
     * @param float $amount
     * @return int
     */
    private function get_amount_in_cents($amount) {
        return (int) round($amount * 100);
    }

    /**
     * Convert amount from cents to currency
     * 
     * @param int $amount
     * @return float
     */
    private function get_amount_in_currency($amount) {
        return (float) ($amount / 100);
    }

    /**
     * Get 3DS return URL
     * 
     * @param WC_Order $order
     * @return string
     */
    private function get_3ds_return_url($order) {
        return add_query_arg(
            array(
                'order_id' => $order->get_id(),
                'key' => $order->get_order_key()
            ),
            WC()->api_request_url('sr_opn_return')
        );
    }

    /**
     * Verify webhook signature
     * 
     * @param string $payload
     * @return bool
     */
    private function verify_webhook_signature($payload) {
        if (empty($_SERVER['HTTP_OPN_SIGNATURE'])) {
            return false;
        }

        $signature = $_SERVER['HTTP_OPN_SIGNATURE'];
        $secret = $this->settings->get_api_credentials()['webhook_secret'];

        return hash_equals(
            $signature,
            hash_hmac('sha256', $payload, $secret)
        );
    }
}