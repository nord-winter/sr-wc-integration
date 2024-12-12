<?php
/**
 * Class SR_API
 * Handles all API interactions with SalesRender
 */
class SR_API {
    const API_BASE_URL = 'https://de.backend.salesrender.com/companies/';
    const API_CRM_SCOPE = '/CRM';

    private $company_id;
    private $token;
    private $api_url;

    /**
     * SR_API constructor.
     */
    public function __construct() {
        $this->company_id = get_option('sr_company_id');
        $this->token = get_option('sr_api_token');
        $this->api_url = self::API_BASE_URL . $this->company_id . self::API_CRM_SCOPE;
    }

    /**
     * Send GraphQL query to SalesRender API
     * 
     * @param string $query GraphQL query
     * @param array $variables Query variables
     * @return array|WP_Error Response data or WP_Error on failure
     */
    public function send_request($query, $variables = []) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token
        );

        $body = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);

        $response = wp_remote_post($this->get_api_url(), [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['errors'])) {
            return new WP_Error(
                'sr_api_error',
                $data['errors'][0]['message'] ?? 'Unknown API error',
                $data['errors']
            );
        }

        return $data;
    }

    /**
     * Create order in SalesRender
     * 
     * @param WC_Order $order WooCommerce order object
     * @return array|WP_Error
     */
    public function create_order($order) {
        $query = $this->get_create_order_query();
        $variables = $this->prepare_order_data($order);

        return $this->send_request($query, $variables);
    }

    /**
     * Update order status in SalesRender
     * 
     * @param string $order_id Order ID in SalesRender
     * @param string $status New status
     * @return array|WP_Error
     */
    public function update_order_status($order_id, $status) {
        $query = $this->get_update_status_query();
        $variables = [
            'input' => [
                'id' => $order_id,
                'status' => $status
            ]
        ];

        return $this->send_request($query, $variables);
    }

    /**
     * Get GraphQL query for order creation
     * 
     * @return string
     */
    private function get_create_order_query() {
        return <<<'GRAPHQL'
        mutation CreateOrder($input: AddOrderInput!) {
            orderMutation {
                addOrder(input: $input) {
                    id
                    status
                    created
                }
            }
        }
        GRAPHQL;
    }

    /**
     * Get GraphQL query for status update
     * 
     * @return string
     */
    private function get_update_status_query() {
        return <<<'GRAPHQL'
        mutation UpdateOrderStatus($input: UpdateOrderStatusInput!) {
            orderMutation {
                updateStatus(input: $input) {
                    id
                    status
                }
            }
        }
        GRAPHQL;
    }

    /**
     * Prepare order data for SalesRender API
     * 
     * @param WC_Order $order WooCommerce order
     * @return array
     */
    private function prepare_order_data($order) {
        $settings = get_option('sr_field_mappings', []);
        
        return [
            'input' => [
                'statusId' => $settings['default_status_id'] ?? 1,
                'projectId' => $settings['project_id'] ?? 1,
                'orderData' => [
                    'humanNameFields' => [
                        [
                            'field' => $settings['name_field'] ?? 'name',
                            'value' => [
                                'firstName' => $order->get_billing_first_name(),
                                'lastName' => $order->get_billing_last_name()
                            ]
                        ]
                    ],
                    'phoneFields' => [
                        [
                            'field' => $settings['phone_field'] ?? 'phone',
                            'value' => $order->get_billing_phone()
                        ]
                    ],
                    'emailFields' => [
                        [
                            'field' => $settings['email_field'] ?? 'email',
                            'value' => $order->get_billing_email()
                        ]
                    ]
                ],
                'cart' => [
                    'items' => $this->prepare_cart_items($order),
                ],
                'source' => [
                    'refererUri' => wp_get_referer(),
                    'ip' => $order->get_customer_ip_address()
                ]
            ]
        ];
    }

    /**
     * Prepare cart items for SalesRender API
     * 
     * @param WC_Order $order WooCommerce order
     * @return array
     */
    private function prepare_cart_items($order) {
        $items = [];
        $settings = get_option('sr_product_mappings', []);

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (isset($settings[$product_id])) {
                $items[] = [
                    'itemId' => (int) $settings[$product_id]['sr_item_id'],
                    'quantity' => (int) $item->get_quantity(),
                    'variation' => 1,
                    'price' => (int) round($item->get_total() * 100)
                ];
            }
        }

        return $items;
    }

    /**
     * Get complete API URL with token
     * 
     * @return string
     */
    private function get_api_url() {
        return $this->api_url . '?token=' . $this->token;
    }
}