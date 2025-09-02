<?php

namespace BBPCore\Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use BBPCore\Modules\Core\Services\AdminService;
use BBPCore\Modules\Core\Services\SettingsService;
use BBPCore\Modules\Core\Controllers\AdminController;
use BBPCore\Modules\Core\Controllers\SettingsController;

/**
 * Core Module Service Provider
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        $this->registerServices();
        $this->registerControllers();
    }

    /**
     * Bootstrap services
     */
    public function boot()
    {
        $this->registerHooks();
        $this->registerAdminPages();
        $this->registerShortcodes();
        $this->registerWidgets();
    }

    /**
     * Register module services
     */
    protected function registerServices()
    {
        $this->app->singleton(AdminService::class, function ($app) {
            return new AdminService();
        });

        $this->app->singleton(SettingsService::class, function ($app) {
            return new SettingsService();
        });
    }

    /**
     * Register controllers
     */
    protected function registerControllers()
    {
        $this->app->bind(AdminController::class, function ($app) {
            return new AdminController(
                $app->make(AdminService::class),
                $app->make(SettingsService::class)
            );
        });

        $this->app->bind(SettingsController::class, function ($app) {
            return new SettingsController(
                $app->make(SettingsService::class)
            );
        });
    }

    /**
     * Register WordPress hooks
     */
    protected function registerHooks()
    {
        // Admin hooks
        add_action('admin_menu', [$this, 'addAdminMenus']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 100);

        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('init', [$this, 'initCore']);

        // AJAX hooks
        add_action('wp_ajax_bbp_core_action', [$this, 'handleAjaxAction']);
        add_action('wp_ajax_nopriv_bbp_core_action', [$this, 'handlePublicAjaxAction']);

        // Dashboard hooks
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidgets']);

        // Plugin hooks
        add_filter('plugin_action_links_' . BBP_CORE_PLUGIN_BASENAME, [$this, 'addPluginActionLinks']);
        add_filter('plugin_row_meta', [$this, 'addPluginRowMeta'], 10, 2);
    }

    /**
     * Register admin pages
     */
    protected function registerAdminPages()
    {
        // Main admin pages will be registered in addAdminMenus
    }

    /**
     * Register shortcodes
     */
    protected function registerShortcodes()
    {
        add_shortcode('bbp_core_info', [$this, 'renderInfoShortcode']);
        add_shortcode('bbp_core_status', [$this, 'renderStatusShortcode']);
    }

    /**
     * Register widgets
     */
    protected function registerWidgets()
    {
        add_action('widgets_init', function() {
            // Register custom widgets here if needed
        });
    }

    /**
     * Initialize core functionality
     */
    public function initCore()
    {
        bbp_core_log('Core module initialized');
        do_action('bbp_core_core_init');
    }

    /**
     * Add admin menus
     */
    public function addAdminMenus()
    {
        // Main BBP Core menu
        add_menu_page(
            __('BBP Core', 'bbp-core'),
            __('BBP Core', 'bbp-core'),
            'manage_options',
            'bbp-core',
            [$this, 'renderMainAdminPage'],
            'dashicons-admin-plugins',
            30
        );

        // Dashboard submenu
        add_submenu_page(
            'bbp-core',
            __('Dashboard', 'bbp-core'),
            __('Dashboard', 'bbp-core'),
            'manage_options',
            'bbp-core',
            [$this, 'renderMainAdminPage']
        );

        // Modules submenu
        add_submenu_page(
            'bbp-core',
            __('Modules', 'bbp-core'),
            __('Modules', 'bbp-core'),
            'manage_options',
            'bbp-core-modules',
            [$this, 'renderModulesPage']
        );

        // Settings submenu
        add_submenu_page(
            'bbp-core',
            __('Settings', 'bbp-core'),
            __('Settings', 'bbp-core'),
            'manage_options',
            'bbp-core-settings',
            [$this, 'renderSettingsPage']
        );

        // Tools submenu
        add_submenu_page(
            'bbp-core',
            __('Tools', 'bbp-core'),
            __('Tools', 'bbp-core'),
            'manage_options',
            'bbp-core-tools',
            [$this, 'renderToolsPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings()
    {
        register_setting('bbp_core_settings', 'bbp_core_settings', [
            'sanitize_callback' => [$this, 'sanitizeSettings']
        ]);

        // General settings section
        add_settings_section(
            'bbp_core_general',
            __('General Settings', 'bbp-core'),
            [$this, 'renderGeneralSectionDescription'],
            'bbp_core_settings'
        );

        add_settings_field(
            'enable_logging',
            __('Enable Logging', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_settings',
            'bbp_core_general',
            ['field' => 'enable_logging', 'description' => __('Enable debug logging for troubleshooting.', 'bbp-core')]
        );

        add_settings_field(
            'enable_caching',
            __('Enable Caching', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_settings',
            'bbp_core_general',
            ['field' => 'enable_caching', 'description' => __('Enable caching for better performance.', 'bbp-core')]
        );

        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_settings',
            'bbp_core_general',
            ['field' => 'debug_mode', 'description' => __('Enable debug mode (not recommended for production).', 'bbp-core')]
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook)
    {
        if (strpos($hook, 'bbp-core') === false) {
            return;
        }

        wp_enqueue_style(
            'bbp-core-admin',
            $this->getAssetUrl('css/admin.css'),
            [],
            BBPCore::VERSION
        );

        wp_enqueue_script(
            'bbp-core-admin',
            $this->getAssetUrl('js/admin.js'),
            ['jquery', 'wp-util'],
            BBPCore::VERSION,
            true
        );

        wp_localize_script('bbp-core-admin', 'bbpCoreAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bbp_core_admin_nonce'),
            'strings' => [
                'confirm' => __('Are you sure?', 'bbp-core'),
                'success' => __('Operation completed successfully.', 'bbp-core'),
                'error' => __('An error occurred. Please try again.', 'bbp-core'),
            ]
        ]);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets()
    {
        wp_enqueue_style(
            'bbp-core-frontend',
            $this->getAssetUrl('css/core.css'),
            [],
            BBPCore::VERSION
        );

        wp_enqueue_script(
            'bbp-core-frontend',
            $this->getAssetUrl('js/core.js'),
            ['jquery'],
            BBPCore::VERSION,
            true
        );
    }

    /**
     * Add admin bar menu
     */
    public function addAdminBarMenu($wp_admin_bar)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_menu([
            'id' => 'bbp-core',
            'title' => __('BBP Core', 'bbp-core'),
            'href' => admin_url('admin.php?page=bbp-core'),
        ]);

        $wp_admin_bar->add_menu([
            'parent' => 'bbp-core',
            'id' => 'bbp-core-dashboard',
            'title' => __('Dashboard', 'bbp-core'),
            'href' => admin_url('admin.php?page=bbp-core'),
        ]);

        $wp_admin_bar->add_menu([
            'parent' => 'bbp-core',
            'id' => 'bbp-core-modules',
            'title' => __('Modules', 'bbp-core'),
            'href' => admin_url('admin.php?page=bbp-core-modules'),
        ]);

        $wp_admin_bar->add_menu([
            'parent' => 'bbp-core',
            'id' => 'bbp-core-settings',
            'title' => __('Settings', 'bbp-core'),
            'href' => admin_url('admin.php?page=bbp-core-settings'),
        ]);
    }

    /**
     * Add dashboard widgets
     */
    public function addDashboardWidgets()
    {
        wp_add_dashboard_widget(
            'bbp_core_status',
            __('BBP Core Status', 'bbp-core'),
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Render main admin page
     */
    public function renderMainAdminPage()
    {
        $moduleManager = bbp_core()->getModuleManager();
        $modules = $moduleManager->getModules();
        $stats = $this->getSystemStats();
        
        ?>
        <div class="wrap bbp-core-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="bbp-admin-grid">
                <div class="bbp-admin-main">
                    <div class="bbp-dashboard-cards">
                        <div class="bbp-card">
                            <h3><?php _e('System Status', 'bbp-core'); ?></h3>
                            <ul>
                                <li><?php printf(__('Plugin Version: %s', 'bbp-core'), BBPCore::VERSION); ?></li>
                                <li><?php printf(__('PHP Version: %s', 'bbp-core'), PHP_VERSION); ?></li>
                                <li><?php printf(__('WordPress Version: %s', 'bbp-core'), get_bloginfo('version')); ?></li>
                                <li><?php printf(__('Active Modules: %d', 'bbp-core'), $modules->count()); ?></li>
                            </ul>
                        </div>
                        
                        <div class="bbp-card">
                            <h3><?php _e('Quick Actions', 'bbp-core'); ?></h3>
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=bbp-core-modules'); ?>" class="button button-primary">
                                    <?php _e('Manage Modules', 'bbp-core'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=bbp-core-settings'); ?>" class="button">
                                    <?php _e('Settings', 'bbp-core'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bbp-admin-sidebar">
                    <div class="bbp-admin-widget">
                        <h3><?php _e('Active Modules', 'bbp-core'); ?></h3>
                        <ul>
                            <?php foreach ($modules as $name => $module): ?>
                                <li><?php echo esc_html($module['config']['description'] ?? $name); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render modules page
     */
    public function renderModulesPage()
    {
        $moduleManager = bbp_core()->getModuleManager();
        $modules = $moduleManager->getModules();
        $enabledModules = $moduleManager->getEnabledModules();
        
        ?>
        <div class="wrap bbp-core-admin">
            <h1><?php _e('BBP Core Modules', 'bbp-core'); ?></h1>
            
            <div class="bbp-modules-grid">
                <?php foreach ($modules as $name => $module): ?>
                    <div class="bbp-module-card <?php echo $moduleManager->isModuleEnabled($name) ? 'enabled' : 'disabled'; ?>">
                        <h3><?php echo esc_html($module['config']['name'] ?? $name); ?></h3>
                        <p><?php echo esc_html($module['config']['description'] ?? ''); ?></p>
                        <p><strong><?php _e('Version:', 'bbp-core'); ?></strong> <?php echo esc_html($module['config']['version'] ?? '1.0.0'); ?></p>
                        
                        <div class="bbp-module-actions">
                            <?php if ($moduleManager->isModuleEnabled($name)): ?>
                                <button class="button bbp-disable-module" data-module="<?php echo esc_attr($name); ?>">
                                    <?php _e('Disable', 'bbp-core'); ?>
                                </button>
                            <?php else: ?>
                                <button class="button button-primary bbp-enable-module" data-module="<?php echo esc_attr($name); ?>">
                                    <?php _e('Enable', 'bbp-core'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .bbp-modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .bbp-module-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 1rem;
            border-radius: 4px;
        }
        
        .bbp-module-card.enabled {
            border-left: 4px solid #00a32a;
        }
        
        .bbp-module-card.disabled {
            border-left: 4px solid #ddd;
        }
        
        .bbp-module-actions {
            margin-top: 1rem;
        }
        </style>
        <?php
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage()
    {
        ?>
        <div class="wrap bbp-core-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('bbp_core_settings');
                do_settings_sections('bbp_core_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render tools page
     */
    public function renderToolsPage()
    {
        ?>
        <div class="wrap bbp-core-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="bbp-tools-grid">
                <div class="bbp-tool-card">
                    <h3><?php _e('Cache Management', 'bbp-core'); ?></h3>
                    <p><?php _e('Clear all BBP Core cache data.', 'bbp-core'); ?></p>
                    <button class="button bbp-clear-cache"><?php _e('Clear Cache', 'bbp-core'); ?></button>
                </div>
                
                <div class="bbp-tool-card">
                    <h3><?php _e('System Information', 'bbp-core'); ?></h3>
                    <p><?php _e('View detailed system information for debugging.', 'bbp-core'); ?></p>
                    <button class="button bbp-show-sysinfo"><?php _e('Show System Info', 'bbp-core'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget()
    {
        $moduleManager = bbp_core()->getModuleManager();
        $modules = $moduleManager->getModules();
        
        ?>
        <div class="bbp-dashboard-widget">
            <p><strong><?php printf(__('BBP Core v%s', 'bbp-core'), BBPCore::VERSION); ?></strong></p>
            <p><?php printf(__('Active Modules: %d', 'bbp-core'), $modules->count()); ?></p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=bbp-core'); ?>" class="button button-small">
                    <?php _e('View Dashboard', 'bbp-core'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle AJAX actions
     */
    public function handleAjaxAction()
    {
        check_ajax_referer('bbp_core_admin_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['bbp_action'] ?? '');
        
        switch ($action) {
            case 'enable_module':
                $this->handleEnableModule();
                break;
            case 'disable_module':
                $this->handleDisableModule();
                break;
            case 'clear_cache':
                $this->handleClearCache();
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * Handle public AJAX actions
     */
    public function handlePublicAjaxAction()
    {
        // Handle public AJAX actions here
        wp_send_json_error(['message' => 'No public actions available']);
    }

    /**
     * Handle enable module AJAX
     */
    protected function handleEnableModule()
    {
        $module = sanitize_text_field($_POST['module'] ?? '');
        
        if (!$module) {
            wp_send_json_error(['message' => 'Invalid module name']);
        }
        
        $moduleManager = bbp_core()->getModuleManager();
        
        if ($moduleManager->enableModule($module)) {
            wp_send_json_success(['message' => 'Module enabled successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to enable module']);
        }
    }

    /**
     * Handle disable module AJAX
     */
    protected function handleDisableModule()
    {
        $module = sanitize_text_field($_POST['module'] ?? '');
        
        if (!$module) {
            wp_send_json_error(['message' => 'Invalid module name']);
        }
        
        $moduleManager = bbp_core()->getModuleManager();
        
        if ($moduleManager->disableModule($module)) {
            wp_send_json_success(['message' => 'Module disabled successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to disable module']);
        }
    }

    /**
     * Handle clear cache AJAX
     */
    protected function handleClearCache()
    {
        $cache = bbp_core_cache();
        
        if ($cache->flush()) {
            wp_send_json_success(['message' => 'Cache cleared successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to clear cache']);
        }
    }

    /**
     * Render info shortcode
     */
    public function renderInfoShortcode($atts)
    {
        $atts = shortcode_atts([
            'type' => 'version'
        ], $atts);
        
        switch ($atts['type']) {
            case 'version':
                return BBPCore::VERSION;
            case 'modules':
                return bbp_core()->getModuleManager()->getModules()->count();
            default:
                return '';
        }
    }

    /**
     * Render status shortcode
     */
    public function renderStatusShortcode($atts)
    {
        return '<span class="bbp-core-status">Active</span>';
    }

    /**
     * Add plugin action links
     */
    public function addPluginActionLinks($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=bbp-core-settings') . '">' . __('Settings', 'bbp-core') . '</a>';
        array_unshift($links, $settings_link);
        
        return $links;
    }

    /**
     * Add plugin row meta
     */
    public function addPluginRowMeta($links, $file)
    {
        if ($file === BBP_CORE_PLUGIN_BASENAME) {
            $links[] = '<a href="' . admin_url('admin.php?page=bbp-core') . '">' . __('Dashboard', 'bbp-core') . '</a>';
            $links[] = '<a href="' . admin_url('admin.php?page=bbp-core-modules') . '">' . __('Modules', 'bbp-core') . '</a>';
        }
        
        return $links;
    }

    /**
     * Sanitize settings
     */
    public function sanitizeSettings($input)
    {
        $sanitized = [];
        
        $sanitized['enable_logging'] = !empty($input['enable_logging']);
        $sanitized['enable_caching'] = !empty($input['enable_caching']);
        $sanitized['debug_mode'] = !empty($input['debug_mode']);
        
        return $sanitized;
    }

    /**
     * Render general section description
     */
    public function renderGeneralSectionDescription()
    {
        echo '<p>' . __('Configure general BBP Core settings.', 'bbp-core') . '</p>';
    }

    /**
     * Render checkbox field
     */
    public function renderCheckboxField($args)
    {
        $options = get_option('bbp_core_settings', []);
        $value = $options[$args['field']] ?? false;
        
        echo '<input type="checkbox" name="bbp_core_settings[' . esc_attr($args['field']) . ']" value="1" ' . checked(1, $value, false) . ' />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    /**
     * Get system stats
     */
    protected function getSystemStats()
    {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => BBPCore::VERSION,
            'active_modules' => bbp_core()->getModuleManager()->getModules()->count(),
        ];
    }

    /**
     * Get asset URL
     */
    protected function getAssetUrl($path)
    {
        $modulePath = dirname(__DIR__, 2);
        $moduleUrl = str_replace(ABSPATH, home_url('/'), $modulePath);
        
        return $moduleUrl . '/assets/' . ltrim($path, '/');
    }
}
