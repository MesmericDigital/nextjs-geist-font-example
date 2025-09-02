<?php

namespace BBPCore\Providers;

use Illuminate\Support\ServiceProvider;
use BBPCore\Support\ModuleManager;
use BBPCore\Support\ConfigRepository;
use BBPCore\Support\ViewFactory;
use BBPCore\Support\CacheManager;

/**
 * BBP Core Service Provider
 */
class BBPCoreServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        $this->registerConfig();
        $this->registerView();
        $this->registerCache();
        $this->registerModuleManager();
        $this->registerAliases();
    }

    /**
     * Bootstrap services
     */
    public function boot()
    {
        $this->loadConfiguration();
        $this->registerCommands();
        $this->publishAssets();
    }

    /**
     * Register configuration repository
     */
    protected function registerConfig()
    {
        $this->app->singleton('config', function ($app) {
            return new ConfigRepository($app['path.config']);
        });
    }

    /**
     * Register view factory
     */
    protected function registerView()
    {
        $this->app->singleton('view', function ($app) {
            return new ViewFactory($app['path.resources'] . 'views/');
        });
    }

    /**
     * Register cache manager
     */
    protected function registerCache()
    {
        $this->app->singleton('cache', function ($app) {
            return new CacheManager();
        });
    }

    /**
     * Register module manager
     */
    protected function registerModuleManager()
    {
        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager($app);
        });
    }

    /**
     * Register service aliases
     */
    protected function registerAliases()
    {
        $aliases = [
            'config' => ConfigRepository::class,
            'view' => ViewFactory::class,
            'cache' => CacheManager::class,
        ];

        foreach ($aliases as $alias => $class) {
            $this->app->alias($class, $alias);
        }
    }

    /**
     * Load configuration files
     */
    protected function loadConfiguration()
    {
        $configPath = $this->app['path.config'];
        
        if (!is_dir($configPath)) {
            return;
        }

        $config = $this->app['config'];
        
        foreach (glob($configPath . '*.php') as $file) {
            $key = basename($file, '.php');
            $config->set($key, require $file);
        }
    }

    /**
     * Register console commands
     */
    protected function registerCommands()
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        // Register WP-CLI commands here
        // \WP_CLI::add_command('bbp-core', BBPCoreCommand::class);
    }

    /**
     * Publish assets
     */
    protected function publishAssets()
    {
        // This method can be used to copy assets from modules to public directories
        // or perform other asset-related tasks during boot
    }

    /**
     * Get the services provided by the provider
     */
    public function provides()
    {
        return [
            'config',
            'view',
            'cache',
            ModuleManager::class,
        ];
    }
}
