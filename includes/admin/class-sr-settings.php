<?php
/**
 * Class SR_Settings
 * Handles all plugin settings management
 */
class SR_Settings {
    /**
     * Default settings values
     *
     * @var array
     */
    private $defaults = array(
        'api_credentials' => array(
            'company_id' => '',
            'api_token' => '',
            'webhook_secret' => '',
            'debug_mode' => 'no'
        ),
        'status_mappings' => array(
            'wc_to_sr' => array(
                'pending' => 'new',
                'processing' => 'in_progress',
                'completed' => 'completed',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded'
            ),
            'sr_to_wc' => array(
                'new' => 'pending',
                'in_progress' => 'processing',
                'completed' => 'completed',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded'
            )
        ),
        'field_mappings' => array(
            'name_field' => 'name',
            'phone_field' => 'phone',
            'email_field' => 'email',
            'address_field' => 'address'
        ),
        'package_settings' => array(
            'discounts' => array(
                '2x' => 5,  // 5% discount for 2x package
                '3x' => 10, // 10% discount for 3x package
                '4x' => 15  // 15% discount for 4x package
            )
        )
    );

    /**
     * Class constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
    }

    /**
     * Initialize plugin settings
     */
    public function init_settings() {
        // Register settings
        register_setting(
            'sr_general_settings',
            'sr_api_credentials',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_api_credentials')
            )
        );

        register_setting(
            'sr_status_mappings',
            'sr_status_mappings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_status_mappings')
            )
        );

        register_setting(
            'sr_field_mappings',
            'sr_field_mappings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_field_mappings')
            )
        );

        register_setting(
            'sr_package_settings',
            'sr_package_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_package_settings')
            )
        );
    }

    /**
     * Get API credentials
     *
     * @return array
     */
    public function get_api_credentials() {
        $credentials = get_option('sr_api_credentials', $this->defaults['api_credentials']);
        return wp_parse_args($credentials, $this->defaults['api_credentials']);
    }

    /**
     * Get status mappings
     *
     * @param string $direction Either 'wc_to_sr' or 'sr_to_wc'
     * @return array
     */
    public function get_status_mappings($direction = 'wc_to_sr') {
        $mappings = get_option('sr_status_mappings', $this->defaults['status_mappings']);
        $mappings = wp_parse_args($mappings, $this->defaults['status_mappings']);
        return isset($mappings[$direction]) ? $mappings[$direction] : array();
    }

    /**
     * Get field mappings
     *
     * @return array
     */
    public function get_field_mappings() {
        $mappings = get_option('sr_field_mappings', $this->defaults['field_mappings']);
        return wp_parse_args($mappings, $this->defaults['field_mappings']);
    }

    /**
     * Get package settings
     *
     * @return array
     */
    public function get_package_settings() {
        $settings = get_option('sr_package_settings', $this->defaults['package_settings']);
        return wp_parse_args($settings, $this->defaults['package_settings']);
    }

    /**
     * Sanitize API credentials
     *
     * @param array $credentials
     * @return array
     */
    public function sanitize_api_credentials($credentials) {
        if (!is_array($credentials)) {
            return $this->defaults['api_credentials'];
        }

        return array(
            'company_id' => sanitize_text_field($credentials['company_id'] ?? ''),
            'api_token' => sanitize_text_field($credentials['api_token'] ?? ''),
            'webhook_secret' => sanitize_text_field($credentials['webhook_secret'] ?? ''),
            'debug_mode' => isset($credentials['debug_mode']) ? 'yes' : 'no'
        );
    }

    /**
     * Sanitize status mappings
     *
     * @param array $mappings
     * @return array
     */
    public function sanitize_status_mappings($mappings) {
        if (!is_array($mappings)) {
            return $this->defaults['status_mappings'];
        }

        $sanitized = array(
            'wc_to_sr' => array(),
            'sr_to_wc' => array()
        );

        foreach (['wc_to_sr', 'sr_to_wc'] as $direction) {
            if (isset($mappings[$direction]) && is_array($mappings[$direction])) {
                foreach ($mappings[$direction] as $from => $to) {
                    $sanitized[$direction][sanitize_key($from)] = sanitize_key($to);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize field mappings
     *
     * @param array $mappings
     * @return array
     */
    public function sanitize_field_mappings($mappings) {
        if (!is_array($mappings)) {
            return $this->defaults['field_mappings'];
        }

        $sanitized = array();
        foreach ($mappings as $field => $mapping) {
            $sanitized[sanitize_key($field)] = sanitize_text_field($mapping);
        }

        return $sanitized;
    }

    /**
     * Sanitize package settings
     *
     * @param array $settings
     * @return array
     */
    public function sanitize_package_settings($settings) {
        if (!is_array($settings)) {
            return $this->defaults['package_settings'];
        }

        $sanitized = array(
            'discounts' => array()
        );

        if (isset($settings['discounts']) && is_array($settings['discounts'])) {
            foreach ($settings['discounts'] as $package => $discount) {
                $sanitized['discounts'][sanitize_key($package)] = absint($discount);
            }
        }

        return $sanitized;
    }

    /**
     * Get debug mode status
     *
     * @return bool
     */
    public function is_debug_mode() {
        $credentials = $this->get_api_credentials();
        return $credentials['debug_mode'] === 'yes';
    }

    /**
     * Calculate package price with discount
     *
     * @param float $base_price
     * @param string $package_type
     * @return float
     */
    public function calculate_package_price($base_price, $package_type) {
        $settings = $this->get_package_settings();
        $discount = isset($settings['discounts'][$package_type]) ? $settings['discounts'][$package_type] : 0;
        $multiplier = $package_type[0];  // Get first character (2,3,4)
        
        // Calculate total price with discount
        $total_price = $base_price * $multiplier;
        $discount_amount = ($total_price * $discount) / 100;
        
        return round($total_price - $discount_amount, 2);
    }

    /**
     * Get webhook URL
     *
     * @return string
     */
    public function get_webhook_url() {
        return add_query_arg('sr_webhook', '1', site_url('/'));
    }

    /**
     * Validate API credentials
     *
     * @return bool|WP_Error
     */
    public function validate_api_credentials() {
        $credentials = $this->get_api_credentials();
        
        if (empty($credentials['company_id']) || empty($credentials['api_token'])) {
            return new WP_Error('invalid_credentials', __('Company ID and API Token are required.', 'sr-integration'));
        }

        // Test API connection
        $api = new SR_API();
        $result = $api->test_connection();

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Reset settings to defaults
     *
     * @param string $setting_type Optional. Specific setting to reset
     */
    public function reset_settings($setting_type = '') {
        if ($setting_type && isset($this->defaults[$setting_type])) {
            update_option('sr_' . $setting_type, $this->defaults[$setting_type]);
        } else {
            foreach ($this->defaults as $key => $value) {
                update_option('sr_' . $key, $value);
            }
        }
    }

    /**
     * Export settings
     *
     * @return array
     */
    public function export_settings() {
        $settings = array();
        foreach (array_keys($this->defaults) as $key) {
            $settings[$key] = get_option('sr_' . $key, $this->defaults[$key]);
        }
        return $settings;
    }

    /**
     * Import settings
     *
     * @param array $settings
     * @return bool|WP_Error
     */
    public function import_settings($settings) {
        if (!is_array($settings)) {
            return new WP_Error('invalid_format', __('Invalid settings format.', 'sr-integration'));
        }

        foreach ($settings as $key => $value) {
            if (isset($this->defaults[$key])) {
                $sanitize_method = 'sanitize_' . $key;
                if (method_exists($this, $sanitize_method)) {
                    $value = $this->$sanitize_method($value);
                }
                update_option('sr_' . $key, $value);
            }
        }

        return true;
    }
}