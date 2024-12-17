<?php
/**
 * Class SR_Checkout
 * Handles custom checkout functionality
 */
class SR_Checkout
{
    /**
     * Settings instance
     * 
     * @var SR_Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settings = new SR_Settings();

        // Remove default WooCommerce checkout
        remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
        remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);

        // Add custom checkout actions
        add_action('init', array($this, 'register_checkout_endpoint'));
        add_action('template_redirect', array($this, 'handle_checkout_endpoint'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        add_filter('wc_get_template', array($this, 'override_checkout_template'), 10, 5);

        // Ajax handlers
        add_action('wp_ajax_sr_update_package', array($this, 'update_package_selection'));
        add_action('wp_ajax_nopriv_sr_update_package', array($this, 'update_package_selection'));
        add_action('wp_ajax_sr_validate_form', array($this, 'validate_checkout_form'));
        add_action('wp_ajax_nopriv_sr_validate_form', array($this, 'validate_checkout_form'));

        // Form processing
        add_action('template_redirect', array($this, 'process_checkout_form'));
    }

    /**
     * Render product options (пакеты 1x, 2x, 3x, 4x)
     */
    private function render_product_options()
    {
        $packages = array(
            '4x' => array(
                'quantity' => 40,
                'discount' => 15 // 15% скидка
            ),
            '3x' => array(
                'quantity' => 30,
                'discount' => 10 // 10% скидка
            ),
            '2x' => array(
                'quantity' => 20,
                'discount' => 5 // 5% скидка
            ),
            '1x' => array(
                'quantity' => 10,
                'discount' => 0 // без скидки
            )
        );

        foreach ($packages as $type => $data) {
            ?>
            <div class="sr-package-option" data-package="<?php echo esc_attr($type); ?>">
                <h3><?php printf(esc_html__('Package %s', 'sr-integration'), $type); ?></h3>
                <div class="sr-package-quantity">
                    <?php printf(esc_html__('%d units', 'sr-integration'), $data['quantity']); ?>
                </div>
                <?php if ($data['discount'] > 0): ?>
                    <div class="sr-package-discount">
                        <?php printf(esc_html__('Save %d%%', 'sr-integration'), $data['discount']); ?>
                    </div>
                <?php endif; ?>
                <div class="sr-package-price" data-base-price="0">
                    <!-- Цена будет установлена через JavaScript -->
                </div>
            </div>
            <?php
        }
    }

    /**
     * Register custom checkout endpoint
     */
    public function register_checkout_endpoint()
    {
        add_rewrite_endpoint('sr-checkout', EP_PAGES);

        if (get_option('sr_flush_rules', false)) {
            flush_rewrite_rules();
            delete_option('sr_flush_rules');
        }
    }

    /**
     * Handle custom checkout endpoint
     */
    public function handle_checkout_endpoint()
    {
        if (!is_page('checkout')) {
            return;
        }

        if (get_query_var('sr-checkout') === false) {
            return;
        }

        // Load custom checkout template
        include $this->get_template_path('checkout.php');
        exit;
    }

    /**
     * Get public key for JS SDK
     * 
     * @return string
     */
    private function get_public_key()
    {
        $credentials = $this->settings->get_api_credentials();
        return $this->is_test_mode() ?
            $credentials['test_public_key'] ?? '' :
            $credentials['public_key'] ?? '';
    }

    /**
     * Check if test mode is enabled
     * 
     * @return bool
     */
    private function is_test_mode()
    {
        $credentials = $this->settings->get_api_credentials();
        return ($credentials['test_mode'] ?? 'no') === 'yes';
    }

    /**
     * Check if 3DS is enabled
     * 
     * @return bool
     */
    private function is_3ds_enabled()
    {
        $credentials = $this->settings->get_api_credentials();
        return ($credentials['3ds_enabled'] ?? 'yes') === 'yes';
    }

    /**
     * Render country options for select dropdown
     */
    private function render_country_options()
    {
        // Получаем список стран из WooCommerce
        $countries = WC()->countries->get_allowed_countries();

        $allowed_countries = array(
            'TH' => 'Thailand',
            // 'SG' => 'Singapore',
            // 'MY' => 'Malaysia'
        );
        $countries = $allowed_countries; //TODO: add support for other countries

        // Добавляем опции
        foreach ($countries as $code => $name) {
            printf(
                '<option value="%s">%s</option>',
                esc_attr($code),
                esc_html($name)
            );
        }
    }

    /**
     * Enqueue checkout scripts and styles
     */
    public function enqueue_checkout_scripts()
    {
        if (!is_page('checkout')) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'sr-checkout-style',
            SR_WC_Integration()->get_plugin_url() . 'assets/css/checkout.css',
            array(),
            SR_WC_Integration()->get_version()
        );

        // Mobile styles
        if (wp_is_mobile()) {
            wp_enqueue_style(
                'sr-checkout-mobile',
                SR_WC_Integration()->get_plugin_url() . 'assets/css/checkout-mobile.css',
                array('sr-checkout-style'),
                SR_WC_Integration()->get_version()
            );
        }
        // jQuery Validate
        wp_enqueue_script(
            'jquery-validate',
            'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js',
            array('jquery'),
            '1.19.5',
            true
        );

        // Scripts
        wp_enqueue_script(
            'sr-checkout',
            SR_WC_Integration()->get_plugin_url() . 'assets/js/checkout.js',
            array('jquery', 'jquery-validate'),
            SR_WC_Integration()->get_version(),
            true
        );

        // Localize script
        wp_localize_script('sr-checkout', 'sr_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'is_mobile' => wp_is_mobile(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'package_data' => $this->get_package_data(),
            'nonce' => wp_create_nonce('sr-checkout'),
            'i18n' => array(
                'error_required' => __('This field is required.', 'sr-integration'),
                'error_email' => __('Please enter a valid email address.', 'sr-integration'),
                'error_phone' => __('Please enter a valid phone number.', 'sr-integration'),
                'processing' => __('Processing...', 'sr-integration')
            )
        ));
    }

    /**
     * Override default checkout template
     * 
     * @param string $template
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     * @return string
     */
    public function override_checkout_template($template, $template_name, $args, $template_path, $default_path)
    {
        if ($template_name !== 'checkout/form-checkout.php') {
            return $template;
        }

        $custom_template = $this->get_template_path('checkout.php');
        if (file_exists($custom_template)) {
            return $custom_template;
        }

        return $template;
    }

    /**
     * Update package selection via Ajax
     */
    public function update_package_selection()
    {
        check_ajax_referer('sr-checkout', 'nonce');

        $package_type = isset($_POST['package_type']) ? sanitize_text_field($_POST['package_type']) : '';
        if (!in_array($package_type, array('1x', '2x', '3x', '4x'))) {
            wp_send_json_error(__('Invalid package type.', 'sr-integration'));
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Invalid product.', 'sr-integration'));
        }

        $base_price = $product->get_price();
        $package_price = $this->settings->calculate_package_price($base_price, $package_type);

        wp_send_json_success(array(
            'price' => $package_price,
            'formatted_price' => wc_price($package_price),
            'quantity' => $this->get_package_quantity($package_type)
        ));
    }

    /**
     * Validate checkout form fields via Ajax
     */
    public function validate_checkout_form()
    {
        check_ajax_referer('sr-checkout', 'nonce');

        $fields = isset($_POST['fields']) ? (array) $_POST['fields'] : array();
        $errors = array();

        // Validate required fields
        $required_fields = array(
            'first_name' => __('First name', 'sr-integration'),
            'last_name' => __('Last name', 'sr-integration'),
            'email' => __('Email address', 'sr-integration'),
            'phone' => __('Phone number', 'sr-integration'),
            'city' => __('City', 'sr-integration'),
            'address' => __('Address', 'sr-integration'),
            'postcode' => __('Postal code', 'sr-integration')
        );

        foreach ($required_fields as $field => $label) {
            if (empty($fields[$field])) {
                $errors[$field] = sprintf(
                    __('%s is required.', 'sr-integration'),
                    $label
                );
            }
        }

        // Validate email
        if (!empty($fields['email']) && !is_email($fields['email'])) {
            $errors['email'] = __('Please enter a valid email address.', 'sr-integration');
        }

        // Validate phone (basic validation, can be extended)
        if (!empty($fields['phone'])) {
            $phone = trim($fields['phone']);
            if (!preg_match('/^\+?[0-9]{10,}$/', $phone)) {
                $errors['phone'] = __('Please enter a valid phone number.', 'sr-integration');
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors));
        }

        wp_send_json_success();
    }

    /**
     * Process checkout form submission
     */
    public function process_checkout_form()
    {
        if (!isset($_POST['sr_checkout_submit']) || !wp_verify_nonce($_POST['sr_checkout_nonce'], 'sr_checkout_process')) {
            return;
        }

        // Validate form
        $errors = array();
        try {
            // Create order
            $order_data = array(
                'status' => 'pending',
                'customer_id' => get_current_user_id(),
                'created_via' => 'sr_checkout',
                'customer_ip_address' => WC_Geolocation::get_ip_address(),
                'customer_user_agent' => wc_get_user_agent(),
                'billing' => array(
                    'first_name' => sanitize_text_field($_POST['first_name']),
                    'last_name' => sanitize_text_field($_POST['last_name']),
                    'email' => sanitize_email($_POST['email']),
                    'phone' => sanitize_text_field($_POST['phone']),
                    'city' => sanitize_text_field($_POST['city']),
                    'address_1' => sanitize_textarea_field($_POST['address']),
                    'postcode' => sanitize_text_field($_POST['postcode'])
                )
            );

            $order = wc_create_order($order_data);

            if (is_wp_error($order)) {
                throw new Exception($order->get_error_message());
            }

            // Add product to order
            $product_id = absint($_POST['product_id']);
            $package_type = sanitize_text_field($_POST['package_type']);
            $quantity = $this->get_package_quantity($package_type);

            $product = wc_get_product($product_id);
            $price = $this->settings->calculate_package_price($product->get_price(), $package_type);

            $order->add_product($product, $quantity, array(
                'subtotal' => $price * $quantity,
                'total' => $price * $quantity
            ));

            $order->calculate_totals();

            // Redirect to payment
            $payment_url = $order->get_checkout_payment_url();
            wp_redirect($payment_url);
            exit;

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    /**
     * Get package data for JS initialization
     * 
     * @return array
     */
    private function get_package_data()
    {
        $product_id = get_query_var('product-id', 0);
        $product = wc_get_product($product_id);

        if (!$product) {
            return array();
        }

        $base_price = $product->get_price();
        $data = array();

        foreach (array('1x', '2x', '3x', '4x') as $package_type) {
            $price = $this->settings->calculate_package_price($base_price, $package_type);
            $data[$package_type] = array(
                'price' => $price,
                'formatted_price' => wc_price($price),
                'quantity' => $this->get_package_quantity($package_type)
            );
        }

        return $data;
    }

    /**
     * Get package quantity based on type
     * 
     * @param string $package_type
     * @return int
     */
    private function get_package_quantity($package_type)
    {
        $quantities = array(
            '1x' => 10,
            '2x' => 20,
            '3x' => 30,
            '4x' => 40
        );

        return isset($quantities[$package_type]) ? $quantities[$package_type] : 10;
    }

    /**
     * Get template path
     * 
     * @param string $template
     * @return string
     */
    private function get_template_path($template)
    {
        return SR_WC_Integration()->get_plugin_path() . 'templates/' . $template;
    }
}