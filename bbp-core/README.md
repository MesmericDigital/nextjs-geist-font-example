# BBP Core WordPress Plugin

A modern WordPress plugin built with Laravel-style modular architecture, integrating with Roots Acorn, WooCommerce, and Breakdance Builder.

## Features

- **Laravel-style Architecture**: Built with service providers, dependency injection, and modular design
- **WooCommerce Integration**: Custom product types, checkout modifications, and e-commerce enhancements
- **Breakdance Builder Addons**: Custom elements for product grids, cart widgets, and checkout forms
- **Modular System**: Easy to extend with custom modules
- **Admin Dashboard**: Comprehensive admin interface for managing modules and settings
- **Caching System**: Built-in caching for optimal performance
- **PHP 8.4 Compatible**: Modern PHP features and best practices

## Requirements

- PHP 8.4 or higher
- WordPress 6.0 or higher
- WooCommerce (optional, for e-commerce features)
- Breakdance Builder (optional, for custom elements)

## Installation

1. **Download and Extract**
   ```bash
   # Extract the plugin to your WordPress plugins directory
   wp-content/plugins/bbp-core/
   ```

2. **Install Dependencies**
   ```bash
   cd wp-content/plugins/bbp-core/
   composer install
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "BBP Core" and click "Activate"

## Module System

BBP Core uses a modular architecture with three main modules:

### Core Module
- Admin dashboard and interface
- Settings management
- Module management
- System tools and utilities

### WooCommerce Module
- Custom product types (BBP Configurable, BBP Bundle)
- Enhanced checkout fields
- Order management hooks
- Admin settings integration

### Breakdance Module
- Product Grid element
- Cart Widget element
- Checkout Form element
- Product Configurator element

## Configuration

### Basic Settings
Navigate to **BBP Core → Settings** in your WordPress admin to configure:

- Enable/disable logging
- Cache management
- Debug mode
- Module-specific settings

### Module Management
Go to **BBP Core → Modules** to:

- Enable/disable modules
- View module information
- Manage module dependencies

## Usage

### Helper Functions

```php
// Get BBP Core instance
$bbp = bbp_core();

// Get application container
$container = bbp_core_container();

// Get a module
$woocommerce = bbp_core_module('WooCommerce');

// Get configuration
$setting = bbp_core_config('app.debug');

// Cache data
bbp_core_cache()->put('key', 'value', 3600);

// Get plugin option
$option = bbp_core_option('enable_logging');
```

### Creating Custom Modules

1. **Create Module Directory**
   ```
   bbp-core/modules/YourModule/
   ├── module.json
   ├── src/
   │   └── Providers/
   │       └── YourModuleServiceProvider.php
   └── assets/
       ├── css/
       └── js/
   ```

2. **Module Configuration (module.json)**
   ```json
   {
       "name": "YourModule",
       "description": "Your custom module",
       "version": "1.0.0",
       "provider": "BBPCore\\Modules\\YourModule\\Providers\\YourModuleServiceProvider"
   }
   ```

3. **Service Provider**
   ```php
   <?php
   namespace BBPCore\Modules\YourModule\Providers;
   
   use Illuminate\Support\ServiceProvider;
   
   class YourModuleServiceProvider extends ServiceProvider
   {
       public function register()
       {
           // Register services
       }
       
       public function boot()
       {
           // Bootstrap module
       }
   }
   ```

### WooCommerce Integration

#### Custom Product Types
```php
// Check if BBP Core features are enabled for a product
$enabled = get_post_meta($product_id, '_bbp_core_enabled', true);

// Get product configuration ID
$config_id = get_post_meta($product_id, '_bbp_core_config_id', true);
```

#### Checkout Fields
The plugin automatically adds custom checkout fields that can be accessed:
```php
// Get custom checkout field value
$custom_value = get_post_meta($order_id, '_bbp_core_custom_field', true);
```

### Breakdance Elements

#### Product Grid
```php
// Use in Breakdance builder or programmatically
$grid = new BBPCore\Modules\Breakdance\Elements\ProductGrid();
```

#### Cart Widget
```php
// Real-time cart updates via AJAX
// Automatically integrated when Breakdance is active
```

## Hooks and Filters

### Actions
```php
// Plugin initialization
do_action('bbp_core_init');

// Module-specific initialization
do_action('bbp_core_woocommerce_init');
do_action('bbp_core_breakdance_init');

// Order events
do_action('bbp_core_order_status_changed', $order_id, $old_status, $new_status);
do_action('bbp_core_new_order', $order_id);
```

### Filters
```php
// Modify configuration
$config = apply_filters('bbp_core_config', $config);

// Template loading
$template = apply_filters('bbp_core_get_template', $template, $template_name, $args);
```

## API Endpoints

### AJAX Endpoints

#### WooCommerce Actions
- `bbp_core_wc_action` - WooCommerce-related AJAX actions
- Actions: `get_product_config`, `update_cart`

#### Breakdance Actions
- `bbp_breakdance_action` - Breakdance element actions
- Actions: `load_products`, `update_cart`, `get_element_preview`

#### Core Actions
- `bbp_core_action` - Core plugin actions
- Actions: `enable_module`, `disable_module`, `clear_cache`

## Development

### File Structure
```
bbp-core/
├── bbp-core.php              # Main plugin file
├── composer.json             # Dependencies
├── src/                      # Core classes
│   ├── BBPCore.php          # Main plugin class
│   ├── helpers.php          # Helper functions
│   ├── Contracts/           # Interfaces
│   ├── Providers/           # Service providers
│   └── Support/             # Support classes
├── modules/                  # Plugin modules
│   ├── Core/                # Core module
│   ├── WooCommerce/         # WooCommerce integration
│   └── Breakdance/          # Breakdance integration
├── config/                   # Configuration files
├── resources/               # Views and assets
└── tests/                   # Unit tests
```

### Coding Standards
- Follow WordPress coding standards
- Use PSR-4 autoloading
- Implement proper error handling
- Sanitize all user inputs
- Use WordPress nonces for security

## Troubleshooting

### Common Issues

1. **Plugin not loading**
   - Check PHP version (8.4+ required)
   - Verify Composer dependencies are installed
   - Check WordPress error logs

2. **Modules not working**
   - Ensure required dependencies are active
   - Check module configuration in admin
   - Verify file permissions

3. **Performance issues**
   - Enable caching in settings
   - Check for conflicting plugins
   - Review server resources

### Debug Mode
Enable debug mode in **BBP Core → Settings** to:
- View detailed error logs
- See module loading information
- Debug AJAX requests

### Cache Issues
Clear cache via:
- **BBP Core → Tools → Clear Cache**
- Or programmatically: `bbp_core_cache()->flush()`

## Support

For support and documentation:
- Check the plugin admin dashboard
- Review error logs in debug mode
- Ensure all dependencies are met

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Laravel-style modular architecture
- WooCommerce integration
- Breakdance Builder addons
- Admin dashboard and settings
- Caching system
- PHP 8.4 compatibility
