<?php

namespace BBPCore\Support;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use BBPCore\Contracts\ModuleInterface;

/**
 * Module Manager
 * 
 * Handles loading, registration, and management of Laravel-style modules
 */
class ModuleManager
{
    /**
     * Application container
     */
    protected $app;

    /**
     * Loaded modules
     */
    protected $modules;

    /**
     * Module paths
     */
    protected $modulePaths = [];

    /**
     * Enabled modules
     */
    protected $enabledModules = [];

    /**
     * Constructor
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->modules = new Collection();
        $this->loadEnabledModules();
    }

    /**
     * Load all modules
     */
    public function loadModules()
    {
        $modulesPath = $this->app['path.modules'];
        
        if (!is_dir($modulesPath)) {
            wp_mkdir_p($modulesPath);
            return;
        }

        $directories = glob($modulesPath . '*', GLOB_ONLYDIR);
        
        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            
            if ($this->isModuleEnabled($moduleName)) {
                $this->loadModule($moduleName, $directory);
            }
        }
    }

    /**
     * Load a specific module
     */
    public function loadModule($name, $path)
    {
        $moduleFile = $path . '/module.json';
        
        if (!file_exists($moduleFile)) {
            bbp_core_log("Module configuration not found: {$moduleFile}", 'warning');
            return false;
        }

        $config = json_decode(file_get_contents($moduleFile), true);
        
        if (!$config) {
            bbp_core_log("Invalid module configuration: {$moduleFile}", 'error');
            return false;
        }

        // Load module service provider
        $providerClass = $config['provider'] ?? null;
        
        if ($providerClass && class_exists($providerClass)) {
            $this->app->register($providerClass);
        }

        // Store module information
        $this->modules->put($name, [
            'name' => $name,
            'path' => $path,
            'config' => $config,
            'loaded' => true
        ]);

        $this->modulePaths[$name] = $path;

        bbp_core_log("Module loaded: {$name}");
        
        return true;
    }

    /**
     * Initialize all loaded modules
     */
    public function initializeModules()
    {
        foreach ($this->modules as $name => $module) {
            $this->initializeModule($name);
        }
    }

    /**
     * Initialize a specific module
     */
    public function initializeModule($name)
    {
        $module = $this->modules->get($name);
        
        if (!$module || !$module['loaded']) {
            return false;
        }

        $config = $module['config'];
        
        // Load module routes
        $this->loadModuleRoutes($name, $module['path']);
        
        // Load module hooks
        $this->loadModuleHooks($name, $module['path']);
        
        // Load module assets
        $this->loadModuleAssets($name, $module['path'], $config);
        
        // Fire module initialized action
        do_action("bbp_core_module_{$name}_initialized", $module);
        
        bbp_core_log("Module initialized: {$name}");
        
        return true;
    }

    /**
     * Load module routes
     */
    protected function loadModuleRoutes($name, $path)
    {
        $routesFile = $path . '/routes/web.php';
        
        if (file_exists($routesFile)) {
            require_once $routesFile;
        }

        $apiRoutesFile = $path . '/routes/api.php';
        
        if (file_exists($apiRoutesFile)) {
            require_once $apiRoutesFile;
        }
    }

    /**
     * Load module hooks
     */
    protected function loadModuleHooks($name, $path)
    {
        $hooksFile = $path . '/hooks.php';
        
        if (file_exists($hooksFile)) {
            require_once $hooksFile;
        }
    }

    /**
     * Load module assets
     */
    protected function loadModuleAssets($name, $path, $config)
    {
        $assets = $config['assets'] ?? [];
        
        if (empty($assets)) {
            return;
        }

        add_action('wp_enqueue_scripts', function() use ($name, $path, $assets) {
            $this->enqueueModuleAssets($name, $path, $assets, 'frontend');
        });

        add_action('admin_enqueue_scripts', function() use ($name, $path, $assets) {
            $this->enqueueModuleAssets($name, $path, $assets, 'admin');
        });
    }

    /**
     * Enqueue module assets
     */
    protected function enqueueModuleAssets($name, $path, $assets, $context)
    {
        $baseUrl = str_replace(ABSPATH, home_url('/'), $path);
        
        // Enqueue CSS files
        if (isset($assets['css'][$context])) {
            foreach ($assets['css'][$context] as $handle => $file) {
                wp_enqueue_style(
                    "bbp-core-{$name}-{$handle}",
                    $baseUrl . '/assets/css/' . $file,
                    [],
                    BBPCore::VERSION
                );
            }
        }

        // Enqueue JS files
        if (isset($assets['js'][$context])) {
            foreach ($assets['js'][$context] as $handle => $file) {
                wp_enqueue_script(
                    "bbp-core-{$name}-{$handle}",
                    $baseUrl . '/assets/js/' . $file,
                    ['jquery'],
                    BBPCore::VERSION,
                    true
                );
            }
        }
    }

    /**
     * Get a module
     */
    public function getModule($name)
    {
        return $this->modules->get($name);
    }

    /**
     * Get all modules
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Check if module exists
     */
    public function hasModule($name)
    {
        return $this->modules->has($name);
    }

    /**
     * Check if module is loaded
     */
    public function isModuleLoaded($name)
    {
        $module = $this->modules->get($name);
        return $module && $module['loaded'];
    }

    /**
     * Enable a module
     */
    public function enableModule($name)
    {
        $enabled = $this->enabledModules;
        
        if (!in_array($name, $enabled)) {
            $enabled[] = $name;
            $this->saveEnabledModules($enabled);
            $this->enabledModules = $enabled;
            return true;
        }
        
        return false;
    }

    /**
     * Disable a module
     */
    public function disableModule($name)
    {
        $enabled = $this->enabledModules;
        $key = array_search($name, $enabled);
        
        if ($key !== false) {
            unset($enabled[$key]);
            $this->saveEnabledModules(array_values($enabled));
            $this->enabledModules = array_values($enabled);
            return true;
        }
        
        return false;
    }

    /**
     * Check if module is enabled
     */
    public function isModuleEnabled($name)
    {
        return in_array($name, $this->enabledModules);
    }

    /**
     * Get enabled modules
     */
    public function getEnabledModules()
    {
        return $this->enabledModules;
    }

    /**
     * Load enabled modules from database
     */
    protected function loadEnabledModules()
    {
        $this->enabledModules = get_option('bbp_core_enabled_modules', [
            'WooCommerce',
            'Breakdance',
            'Core'
        ]);
    }

    /**
     * Save enabled modules to database
     */
    protected function saveEnabledModules($modules)
    {
        update_option('bbp_core_enabled_modules', $modules);
    }

    /**
     * Get module path
     */
    public function getModulePath($name)
    {
        return $this->modulePaths[$name] ?? null;
    }

    /**
     * Create a new module
     */
    public function createModule($name, $config = [])
    {
        $modulePath = $this->app['path.modules'] . $name;
        
        if (is_dir($modulePath)) {
            return false; // Module already exists
        }

        // Create module directory structure
        wp_mkdir_p($modulePath);
        wp_mkdir_p($modulePath . '/src');
        wp_mkdir_p($modulePath . '/src/Controllers');
        wp_mkdir_p($modulePath . '/src/Models');
        wp_mkdir_p($modulePath . '/src/Services');
        wp_mkdir_p($modulePath . '/resources/views');
        wp_mkdir_p($modulePath . '/assets/css');
        wp_mkdir_p($modulePath . '/assets/js');
        wp_mkdir_p($modulePath . '/routes');

        // Create module configuration
        $defaultConfig = [
            'name' => $name,
            'description' => "BBP Core {$name} Module",
            'version' => '1.0.0',
            'provider' => "BBPCore\\Modules\\{$name}\\Providers\\{$name}ServiceProvider",
            'assets' => [
                'css' => [
                    'frontend' => [],
                    'admin' => []
                ],
                'js' => [
                    'frontend' => [],
                    'admin' => []
                ]
            ]
        ];

        $moduleConfig = array_merge($defaultConfig, $config);
        
        file_put_contents(
            $modulePath . '/module.json',
            json_encode($moduleConfig, JSON_PRETTY_PRINT)
        );

        // Create basic service provider
        $this->createModuleServiceProvider($name, $modulePath);

        return true;
    }

    /**
     * Create module service provider
     */
    protected function createModuleServiceProvider($name, $path)
    {
        $providerContent = "<?php

namespace BBPCore\\Modules\\{$name}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class {$name}ServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register module services
    }

    public function boot()
    {
        // Bootstrap module
    }
}";

        wp_mkdir_p($path . '/src/Providers');
        file_put_contents($path . "/src/Providers/{$name}ServiceProvider.php", $providerContent);
    }
}
