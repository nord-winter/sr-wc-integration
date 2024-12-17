<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SR_Autoloader
 * Handles automatic class loading for the plugin
 */
class SR_Autoloader {
    /**
     * Path to the includes directory
     *
     * @var string
     */
    private $include_path = '';

    /**
     * The Constructor
     */
    public function __construct() {
        if (function_exists('__DIR__')) {
            $this->include_path = dirname(__DIR__) . '/includes/';
        } else {
            $this->include_path = dirname(dirname(__FILE__)) . '/includes/';
        }
    }

    /**
     * Register the autoloader
     */
    public static function register() {
        $loader = new self();
        spl_autoload_register(array($loader, 'autoload'));
    }

    /**
     * Autoload classes
     *
     * @param string $class
     */
    public function autoload($class) {
        $class = strtolower($class);
        
        if (strpos($class, 'sr_') !== 0) {
            return;
        }

        $file = $this->get_file_name_from_class($class);
        $path = '';
        $file_path = '';

        if (strpos($class, 'sr_api') === 0) {
            $path = $this->include_path . 'api/';
        } elseif (strpos($class, 'sr_admin') === 0) {
            $path = $this->include_path . 'admin/';
        } elseif (strpos($class, 'sr_payment') === 0) {
            $path = $this->include_path . 'payment/';
        }

        if (empty($path)) {
            $path = $this->include_path;
        }

        $file_path = $path . $file;

        if ($file_path && is_readable($file_path)) {
            require_once $file_path;
            return;
        }
    }

    /**
     * Convert class name to file name
     *
     * @param string $class Class name
     * @return string File name
     */
    private function get_file_name_from_class($class) {
        return 'class-' . str_replace('_', '-', $class) . '.php';
    }
}