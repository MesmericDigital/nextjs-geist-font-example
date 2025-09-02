<?php

if (!function_exists('bbp_core')) {
    /**
     * Get the BBP Core instance
     */
    function bbp_core()
    {
        return BBPCore::getInstance();
    }
}

if (!function_exists('bbp_core_container')) {
    /**
     * Get the application container
     */
    function bbp_core_container()
    {
        return bbp_core()->getContainer();
    }
}

if (!function_exists('bbp_core_module')) {
    /**
     * Get a module instance
     */
    function bbp_core_module($name)
    {
        return bbp_core()->getModuleManager()->getModule($name);
    }
}

if (!function_exists('bbp_core_config')) {
    /**
     * Get configuration value
     */
    function bbp_core_config($key, $default = null)
    {
        return bbp_core_container()->make('config')->get($key, $default);
    }
}

if (!function_exists('bbp_core_asset')) {
    /**
     * Get asset URL
     */
    function bbp_core_asset($path)
    {
        return BBP_CORE_PLUGIN_URL . 'assets/' . ltrim($path, '/');
    }
}

if (!function_exists('bbp_core_view')) {
    /**
     * Render a view
     */
    function bbp_core_view($view, $data = [])
    {
        return bbp_core_container()->make('view')->make($view, $data);
    }
}

if (!function_exists('bbp_core_log')) {
    /**
     * Log a message
     */
    function bbp_core_log($message, $level = 'info', $context = [])
    {
        if (bbp_core_config('app.debug', false)) {
            error_log(sprintf('[BBP Core] [%s] %s %s', strtoupper($level), $message, json_encode($context)));
        }
    }
}

if (!function_exists('bbp_core_cache')) {
    /**
     * Get cache instance
     */
    function bbp_core_cache()
    {
        return bbp_core_container()->make('cache');
    }
}

if (!function_exists('bbp_core_option')) {
    /**
     * Get or set plugin option
     */
    function bbp_core_option($key = null, $default = null)
    {
        $options = get_option('bbp_core_settings', []);
        
        if ($key === null) {
            return $options;
        }
        
        return $options[$key] ?? $default;
    }
}

if (!function_exists('bbp_core_set_option')) {
    /**
     * Set plugin option
     */
    function bbp_core_set_option($key, $value)
    {
        $options = get_option('bbp_core_settings', []);
        $options[$key] = $value;
        return update_option('bbp_core_settings', $options);
    }
}

if (!function_exists('bbp_core_is_woocommerce_active')) {
    /**
     * Check if WooCommerce is active
     */
    function bbp_core_is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }
}

if (!function_exists('bbp_core_is_breakdance_active')) {
    /**
     * Check if Breakdance is active
     */
    function bbp_core_is_breakdance_active()
    {
        return class_exists('Breakdance\PluginAPI\PluginAPI');
    }
}

if (!function_exists('bbp_core_get_template')) {
    /**
     * Get template file
     */
    function bbp_core_get_template($template_name, $args = [], $template_path = '', $default_path = '')
    {
        if (!$template_path) {
            $template_path = 'bbp-core/';
        }
        
        if (!$default_path) {
            $default_path = BBP_CORE_PLUGIN_DIR . 'templates/';
        }
        
        // Look in theme first
        $template = locate_template([
            trailingslashit($template_path) . $template_name,
            $template_name
        ]);
        
        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }
        
        // Allow 3rd party plugins to filter template file from their plugin
        $template = apply_filters('bbp_core_get_template', $template, $template_name, $args, $template_path, $default_path);
        
        if ($args && is_array($args)) {
            extract($args);
        }
        
        do_action('bbp_core_before_template_part', $template_name, $template_path, $template, $args);
        
        include $template;
        
        do_action('bbp_core_after_template_part', $template_name, $template_path, $template, $args);
    }
}

if (!function_exists('bbp_core_sanitize_key')) {
    /**
     * Sanitize a key for use in arrays, options, etc.
     */
    function bbp_core_sanitize_key($key)
    {
        return sanitize_key($key);
    }
}

if (!function_exists('bbp_core_format_price')) {
    /**
     * Format price using WooCommerce if available
     */
    function bbp_core_format_price($price)
    {
        if (bbp_core_is_woocommerce_active()) {
            return wc_price($price);
        }
        
        return '$' . number_format($price, 2);
    }
}

if (!function_exists('bbp_core_get_current_user_role')) {
    /**
     * Get current user role
     */
    function bbp_core_get_current_user_role()
    {
        if (!is_user_logged_in()) {
            return 'guest';
        }
        
        $user = wp_get_current_user();
        return $user->roles[0] ?? 'subscriber';
    }
}

if (!function_exists('bbp_core_is_admin_page')) {
    /**
     * Check if current page is BBP Core admin page
     */
    function bbp_core_is_admin_page()
    {
        global $pagenow;
        
        if (!is_admin()) {
            return false;
        }
        
        $page = $_GET['page'] ?? '';
        
        return strpos($page, 'bbp-core') === 0;
    }
}
