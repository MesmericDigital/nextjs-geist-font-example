<?php
/**
 * Plugin Name: BBP Core
 * Plugin URI: https://example.com/bbp-core
 * Description: A modular WordPress plugin built with Roots Acorn, integrating WooCommerce and Breakdance Builder addons.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bbp-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BBP_CORE_VERSION', '1.0.0');
define('BBP_CORE_PLUGIN_FILE', __FILE__);
define('BBP_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BBP_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBP_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoload dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin
if (!class_exists('BBPCore')) {
    require_once BBP_CORE_PLUGIN_DIR . 'src/BBPCore.php';
    
    // Initialize plugin
    BBPCore::getInstance();
}

// Activation hook
register_activation_hook(__FILE__, ['BBPCore', 'activate']);

// Deactivation hook
register_deactivation_hook(__FILE__, ['BBPCore', 'deactivate']);

// Uninstall hook
register_uninstall_hook(__FILE__, ['BBPCore', 'uninstall']);
