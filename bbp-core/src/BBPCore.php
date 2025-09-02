<?php

use BBPCore\Providers\BBPCoreServiceProvider;
use BBPCore\Support\ModuleManager;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/**
 * Main BBP Core Plugin Class
 */
class BBPCore
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Application container
     */
    private $app;

    /**
     * Module manager
     */
    private $moduleManager;

    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Minimum PHP version
     */
    const MIN_PHP_VERSION = '8.4';

    /**
     * Minimum WordPress version
     */
    const MIN_WP_VERSION = '6.0';

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->checkRequirements();
        $this->initializeContainer();
        $this->loadModules();
        $this->registerHooks();
    }

    /**
     * Get plugin instance (Singleton pattern)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check system requirements
     */
    private function checkRequirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(
                    __('BBP Core requires PHP %s or higher. You are running PHP %s.', 'bbp-core'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return;
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function() use ($wp_version) {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(
                    __('BBP Core requires WordPress %s or higher. You are running WordPress %s.', 'bbp-core'),
                    self::MIN_WP_VERSION,
                    $wp_version
                );
                echo '</p></div>';
            });
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo __('BBP Core works best with WooCommerce installed and activated.', 'bbp-core');
                echo '</p></div>';
            });
        }
    }

    /**
     * Initialize the application container
     */
    private function initializeContainer()
    {
        $this->app = new Container();
        
        // Bind plugin paths
        $this->app->instance('path.plugin', BBP_CORE_PLUGIN_DIR);
        $this->app->instance('path.modules', BBP_CORE_PLUGIN_DIR . 'modules/');
        $this->app->instance('path.config', BBP_CORE_PLUGIN_DIR . 'config/');
        $this->app->instance('path.resources', BBP_CORE_PLUGIN_DIR . 'resources/');
        
        // Set application as facade root
        Facade::setFacadeApplication($this->app);
        
        // Register core service provider
        $this->app->register(BBPCoreServiceProvider::class);
    }

    /**
     * Load and initialize modules
     */
    private function loadModules()
    {
        $this->moduleManager = new ModuleManager($this->app);
        $this->moduleManager->loadModules();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks()
    {
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'pluginsLoaded']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('bbp-core', false, dirname(BBP_CORE_PLUGIN_BASENAME) . '/languages');
        
        // Initialize modules
        $this->moduleManager->initializeModules();
        
        do_action('bbp_core_init');
    }

    /**
     * Plugins loaded hook
     */
    public function pluginsLoaded()
    {
        do_action('bbp_core_plugins_loaded');
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts()
    {
        wp_enqueue_style(
            'bbp-core-frontend',
            BBP_CORE_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'bbp-core-frontend',
            BBP_CORE_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            self::VERSION,
            true
        );

        // Localize script
        wp_localize_script('bbp-core-frontend', 'bbpCore', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bbp_core_nonce'),
            'version' => self::VERSION
        ]);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts()
    {
        wp_enqueue_style(
            'bbp-core-admin',
            BBP_CORE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'bbp-core-admin',
            BBP_CORE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            self::VERSION,
            true
        );
    }

    /**
     * Get application container
     */
    public function getContainer()
    {
        return $this->app;
    }

    /**
     * Get module manager
     */
    public function getModuleManager()
    {
        return $this->moduleManager;
    }

    /**
     * Plugin activation
     */
    public static function activate()
    {
        // Create database tables if needed
        self::createTables();
        
        // Set default options
        self::setDefaultOptions();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('bbp_core_activated');
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('bbp_core_deactivated');
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall()
    {
        // Remove options
        delete_option('bbp_core_version');
        delete_option('bbp_core_settings');
        
        // Drop tables if needed
        self::dropTables();
        
        do_action('bbp_core_uninstalled');
    }

    /**
     * Create database tables
     */
    private static function createTables()
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Example table creation
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bbp_core_data (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop database tables
     */
    private static function dropTables()
    {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}bbp_core_data");
    }

    /**
     * Set default options
     */
    private static function setDefaultOptions()
    {
        add_option('bbp_core_version', self::VERSION);
        add_option('bbp_core_settings', [
            'enable_woocommerce_integration' => true,
            'enable_breakdance_addons' => true,
            'debug_mode' => false
        ]);
    }
}
