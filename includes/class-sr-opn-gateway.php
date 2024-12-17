<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SR_OPN_Gateway
 * Handles OPN Payment Gateway integration
 */
class SR_OPN_Gateway
{
    /**
     * Initialize the gateway
     */
    public static function init()
    {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Ensure WordPress functions are available
        if (!function_exists('add_action') || !function_exists('add_filter')) {
            return; // Прерываем выполнение, если функции не доступны
        }
        // Load gateway class when WooCommerce is initialized
        add_action('woocommerce_loaded', array(__CLASS__, 'load_gateway'));


        // Add gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));
    }

    /**
     * Load the gateway class
     */
    public static function load_gateway()
    {
        require_once dirname(__FILE__) . '/class-sr-opn-payment-gateway.php';
    }

    /**
     * Add gateway to WooCommerce
     */
    public static function add_gateway($gateways)
    {
        $gateways[] = 'SR_OPN_Payment_Gateway';
        return $gateways;
    }
}

