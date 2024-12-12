<?php
/**
 * Class SR_OPN_Payment_Gateway
 * Handle OPN Payment Gateway integration
 */
class SR_OPN_Payment_Gateway extends WC_Payment_Gateway {
    /**
     * Gateway ID
     */
    const GATEWAY_ID = 'sr_opn';

    /**
     * Test mode
     *
     * @var bool
     */
    private $test_mode = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = self::GATEWAY_ID;
        $this->method_title = __('OPN Payment Gateway', 'sr-integration');
        $this->method_description = __('Accept payments through OPN payment gateway with 3D Secure support.', 'sr-integration');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
            '3DS'
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define properties
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->test_mode = 'yes' === $this->get_option('test_mode');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'handle_webhook'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'sr-integration'),
                'type' => 'checkbox',
                'label' => __('Enable OPN Payment', 'sr-integration'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'sr-integration'),
                'type' => 'text',
                'description' => __('Payment method title that customers see.', 'sr-integration'),
                'default' => __('Credit Card (OPN)', 'sr-integration'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'sr-integration'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers see.', 'sr-integration'),
                'default' => __('Pay securely using your credit card.', 'sr-integration'),
                'desc_tip' => true,
            ),
            'test_mode' => array(
                'title' => __('Test mode', 'sr-integration'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'sr-integration'),
                'default' => 'yes',
                'description' => __('Place the payment gateway in test mode.', 'sr-integration'),
            ),
            'public_key' => array(
                'title' => __('Public Key', 'sr-integration'),
                'type' => 'text',
                'description' => __('Get your API keys from your OPN account.', 'sr-integration'),
                'default' => '',
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'sr-integration'),
                'type' => 'password',
                'description' => __('Get your API keys from your OPN account.', 'sr-integration'),
                'default' => '',
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret', 'sr-integration'),
                'type' => 'password',
                'description' => __('Get your webhook secret from your OPN account.', 'sr-integration'),
                'default' => '',
                'desc_tip' => true,
            ),
            '3ds_mode' => array(
                'title' => __('3D Secure Mode', 'sr-integration'),
                'type' => 'select',
                'options' => array(
                    'auto' => __('Automatic (Recommended)', 'sr-integration'),
                    'force' => __('Force 3DS for all transactions', 'sr-integration'),
                    'off' => __('Disable 3DS', 'sr-integration')
                ),
                'description' => __('Choose how to handle 3D Secure authentication.', 'sr-integration'),
                'default' => 'auto',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Enqueue payment scripts
     */
    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        // Enqueue OPN JS SDK
        wp_enqueue_script(
            'opn-js',
            'https://cdn.omise.co/omise.js',
            array('jquery'),
            null,
            true
        );

        // Enqueue our custom script
        wp_enqueue_script(
            'sr-opn-payment',
            plugins_url('assets/js/opn-payment.js', dirname(__FILE__)),
            array('jquery', 'opn-js'),
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('sr-opn-payment', 'sr_opn_params', array(
            'public_key' => $this->test_mode ? $this->get_option('test_public_key') : $this->get_option('public_key'),
            'is_test_mode' => $this->test_mode,
            '3ds_mode' => $this->get_option('3ds_mode'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sr-opn-nonce')
        ));
    }

    /**
     * Process payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        try {
            $order = wc_get_order($order_id);
            
            // Get token from posted data
            $token = sanitize_text_field($_POST['opn_token']);
            if (empty($token)) {
                throw new Exception(__('Payment token was not generated correctly.', 'sr-integration'));
            }

            // Initialize OPN API with credentials
            $opn = new OmiseClient(
                $this->test_mode ? $this->get_option('test_secret_key') : $this->get_option('secret_key')
            );

            // Create charge
            $charge = $opn->charges()->create([
                'amount' => (int) ($order->get_total() * 100), // Convert to smallest currency unit
                'currency' => strtolower($order->get_currency()),
                'card' => $token,
                'capture' => true,
                'description' => sprintf(
                    __('Order %s from %s', 'sr-integration'),
                    $order->get_order_number(),
                    get_bloginfo('name')
                ),
                'metadata' => [
                    'order_id' => $order->get_id(),
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email()
                ]
            ]);

            // Handle 3DS authentication if required
            if ($charge->status === 'pending' && !empty($charge->authorize_uri)) {
                return array(
                    'result' => 'success',
                    'redirect' => $charge->authorize_uri
                );
            }

            // Check if charge was successful
            if ($charge->status === 'successful' || $charge->status === 'pending') {
                // Mark order as processing
                $order->payment_complete($charge->id);
                
                // Add transaction ID
                $order->add_order_note(
                    sprintf(__('OPN payment completed (Transaction ID: %s)', 'sr-integration'), $charge->id)
                );
                
                // Empty cart
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } else {
                throw new Exception($charge->failure_message ?? __('Payment failed.', 'sr-integration'));
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    /**
     * Handle OPN webhook
     */
    public function handle_webhook() {
        try {
            $payload = file_get_contents('php://input');
            $event = json_decode($payload);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid webhook payload');
            }

            // Verify webhook signature
            if (!$this->verify_webhook_signature($payload)) {
                throw new Exception('Invalid webhook signature');
            }

            // Process event
            switch ($event->type) {
                case 'charge.complete':
                    $this->handle_successful_payment($event->data);
                    break;
                case 'charge.fail':
                    $this->handle_failed_payment($event->data);
                    break;
                case 'refund.create':
                    $this->handle_refund($event->data);
                    break;
            }

            exit('Webhook processed successfully');
        } catch (Exception $e) {
            header('HTTP/1.1 400 Bad Request');
            exit($e->getMessage());
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @return bool
     */
    private function verify_webhook_signature($payload) {
        $signature = $_SERVER['HTTP_OMISE_SIGNATURE'] ?? '';
        $secret = $this->get_option('webhook_secret');
        
        return hash_equals(
            $signature,
            hash_hmac('sha256', $payload, $secret)
        );
    }

    /**
     * Handle successful payment webhook
     *
     * @param object $data
     */
    private function handle_successful_payment($data) {
        $order_id = $data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->has_status('processing') || $order->has_status('completed')) {
            return;
        }

        $order->payment_complete($data->id);
        $order->add_order_note(
            sprintf(__('Payment confirmed via webhook (Transaction ID: %s)', 'sr-integration'), $data->id)
        );
    }

    /**
     * Handle failed payment webhook
     *
     * @param object $data
     */
    private function handle_failed_payment($data) {
        $order_id = $data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->has_status('failed')) {
            return;
        }

        $order->update_status(
            'failed',
            sprintf(__('Payment failed via webhook (Transaction ID: %s): %s', 'sr-integration'),
                $data->id,
                $data->failure_message ?? 'Unknown error'
            )
        );
    }

    /**
     * Handle refund webhook
     *
     * @param object $data
     */
    private function handle_refund($data) {
        $order_id = $data->metadata->order_id ?? null;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $refund_amount = $data->amount / 100; // Convert from smallest currency unit

        if (!$order->has_status('refunded')) {
            $order->add_order_note(
                sprintf(__('Refund processed via webhook for amount %s (Transaction ID: %s)', 'sr-integration'),
                    wc_price($refund_amount),
                    $data->id
                )
            );

            wc_create_refund(array(
                'amount' => $refund_amount,
                'reason' => $data->description ?? '',
                'order_id' => $order_id,
                'refund_payment' => false // Payment already refunded through OPN
            ));
        }
    }

    /**
     * Process refund through gateway
     *
     * @param int $order_id
     * @param float $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found.', 'sr-integration'));
            }

            $transaction_id = $order->get_transaction_id();
            if (empty($transaction_id)) {
                throw new Exception(__('Transaction ID not found.', 'sr-integration'));
            }

            // Initialize OPN API
            $opn = new OmiseClient(
                $this->test_mode ? $this->get_option('test_secret_key') : $this->get_option('secret_key')
            );

            // Create refund
            $refund = $opn->refunds()->create([
                'charge' => $transaction_id,
                'amount' => (int) ($amount * 100), // Convert to smallest currency unit
                'metadata' => [
                    'order_id' => $order_id,
                    'reason' => $reason
                ]
            ]);

            if ($refund->status === 'succeeded') {
                $order->add_order_note(
                    sprintf(__('Refund processed manually for amount %s (Transaction ID: %s)', 'sr-integration'),
                        wc_price($amount),
                        $refund->id
                    )
                );
                return true;
            } else {
                throw new Exception($refund->failure_message ?? __('Refund failed.', 'sr-integration'));
            }
        } catch (Exception $e) {
            return new WP_Error('sr_opn_refund_error', $e->getMessage());
        }
    }
}