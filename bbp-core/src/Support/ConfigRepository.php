<?php

namespace BBPCore\Support;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Configuration Repository
 * 
 * Manages configuration values for the BBP Core plugin
 */
class ConfigRepository implements ArrayAccess
{
    /**
     * Configuration items
     */
    protected $items = [];

    /**
     * Configuration path
     */
    protected $configPath;

    /**
     * Constructor
     */
    public function __construct($configPath = null)
    {
        $this->configPath = $configPath;
        $this->loadDefaultConfig();
    }

    /**
     * Load default configuration
     */
    protected function loadDefaultConfig()
    {
        $this->items = [
            'app' => [
                'name' => 'BBP Core',
                'version' => BBPCore::VERSION,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'timezone' => get_option('timezone_string', 'UTC'),
                'locale' => get_locale(),
            ],
            'cache' => [
                'default' => 'wordpress',
                'prefix' => 'bbp_core_',
                'ttl' => 3600, // 1 hour
            ],
            'modules' => [
                'auto_discover' => true,
                'enabled' => [
                    'Core',
                    'WooCommerce',
                    'Breakdance'
                ]
            ],
            'woocommerce' => [
                'enabled' => true,
                'integration' => [
                    'product_types' => true,
                    'checkout_fields' => true,
                    'custom_settings' => true,
                ]
            ],
            'breakdance' => [
                'enabled' => true,
                'elements' => [
                    'product_grid' => true,
                    'cart_widget' => true,
                    'checkout_form' => true,
                ]
            ],
            'assets' => [
                'version' => BBPCore::VERSION,
                'minify' => !defined('WP_DEBUG') || !WP_DEBUG,
                'combine' => false,
            ],
            'security' => [
                'nonce_lifetime' => DAY_IN_SECONDS,
                'rate_limiting' => true,
                'sanitize_input' => true,
            ]
        ];
    }

    /**
     * Get a configuration value
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * Set a configuration value
     */
    public function set($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $k => $v) {
            Arr::set($this->items, $k, $v);
        }
    }

    /**
     * Check if configuration key exists
     */
    public function has($key)
    {
        return Arr::has($this->items, $key);
    }

    /**
     * Get all configuration items
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Prepend a value to an array configuration value
     */
    public function prepend($key, $value)
    {
        $array = $this->get($key, []);
        array_unshift($array, $value);
        $this->set($key, $array);
    }

    /**
     * Push a value to an array configuration value
     */
    public function push($key, $value)
    {
        $array = $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }

    /**
     * Load configuration from file
     */
    public function loadFromFile($file)
    {
        if (!file_exists($file)) {
            return false;
        }

        $config = require $file;
        
        if (is_array($config)) {
            $this->items = array_merge($this->items, $config);
            return true;
        }

        return false;
    }

    /**
     * Load all configuration files from directory
     */
    public function loadFromDirectory($directory)
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = glob($directory . '*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            if (is_array($config)) {
                $this->set($key, $config);
            }
        }

        return true;
    }

    /**
     * Save configuration to WordPress options
     */
    public function save()
    {
        return update_option('bbp_core_config', $this->items);
    }

    /**
     * Load configuration from WordPress options
     */
    public function load()
    {
        $saved = get_option('bbp_core_config', []);
        
        if (is_array($saved)) {
            $this->items = array_merge($this->items, $saved);
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function reset()
    {
        $this->items = [];
        $this->loadDefaultConfig();
        delete_option('bbp_core_config');
    }

    /**
     * Get configuration for a specific module
     */
    public function getModuleConfig($module)
    {
        return $this->get("modules.{$module}", []);
    }

    /**
     * Set configuration for a specific module
     */
    public function setModuleConfig($module, $config)
    {
        $this->set("modules.{$module}", $config);
    }

    /**
     * Check if a module is enabled
     */
    public function isModuleEnabled($module)
    {
        $enabled = $this->get('modules.enabled', []);
        return in_array($module, $enabled);
    }

    /**
     * Enable a module
     */
    public function enableModule($module)
    {
        $enabled = $this->get('modules.enabled', []);
        
        if (!in_array($module, $enabled)) {
            $enabled[] = $module;
            $this->set('modules.enabled', $enabled);
        }
    }

    /**
     * Disable a module
     */
    public function disableModule($module)
    {
        $enabled = $this->get('modules.enabled', []);
        $key = array_search($module, $enabled);
        
        if ($key !== false) {
            unset($enabled[$key]);
            $this->set('modules.enabled', array_values($enabled));
        }
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    public function offsetGet($key): mixed
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    public function offsetUnset($key): void
    {
        $this->set($key, null);
    }
}
