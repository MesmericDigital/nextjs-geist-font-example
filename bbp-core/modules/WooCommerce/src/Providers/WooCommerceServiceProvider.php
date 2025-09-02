<?php

namespace BBPCore\Modules\WooCommerce\Providers;

use Illuminate\Support\ServiceProvider;
use BBPCore\Modules\WooCommerce\Services\ProductService;
use BBPCore\Modules\WooCommerce\Services\CheckoutService;
use BBPCore\Modules\WooCommerce\Services\OrderService;
use BBPCore\Modules\WooCommerce\Controllers\ProductController;
use BBPCore\Modules\WooCommerce\Controllers\CheckoutController;

/**
 * WooCommerce Module Service Provider
 */
class WooCommerceServiceProvider extends ServiceProvider
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
        if (!$this->isWooCommerceActive()) {
            return;
        }

        $this->registerHooks();
        $this->loadRoutes();
        $this->registerCustomPostTypes();
        $this->registerCustomFields();
    }

    /**
     * Register module services
     */
    protected function registerServices()
    {
        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService();
        });

        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService();
        });

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService();
        });
    }

    /**
     * Register controllers
     */
    protected function registerControllers()
    {
        $this->app->bind(ProductController::class, function ($app) {
            return new ProductController(
                $app->make(ProductService::class)
            );
        });

        $this->app->bind(CheckoutController::class, function ($app) {
            return new CheckoutController(
                $app->make(CheckoutService::class)
            );
        });
    }

    /**
     * Register WordPress hooks
     */
    protected function registerHooks()
    {
        // Product hooks
        add_action('woocommerce_init', [$this, 'initWooCommerce']);
        add_filter('woocommerce_product_types', [$this, 'registerProductTypes']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'addProductFields']);
        add_action('woocommerce_process_product_meta', [$this, 'saveProductFields']);

        // Checkout hooks
        add_filter('woocommerce_checkout_fields', [$this, 'modifyCheckoutFields']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckoutFields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCheckoutFields']);

        // Order hooks
        add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChange'], 10, 3);
        add_action('woocommerce_new_order', [$this, 'handleNewOrder']);

        // Admin hooks
        add_action('admin_menu', [$this, 'addAdminMenus']);
        add_action('admin_init', [$this, 'registerSettings']);

        // AJAX hooks
        add_action('wp_ajax_bbp_core_wc_action', [$this, 'handleAjaxAction']);
        add_action('wp_ajax_nopriv_bbp_core_wc_action', [$this, 'handleAjaxAction']);
    }

    /**
     * Load module routes
     */
    protected function loadRoutes()
    {
        $routesPath = dirname(__DIR__) . '/routes/';
        
        if (file_exists($routesPath . 'web.php')) {
            require_once $routesPath . 'web.php';
        }

        if (file_exists($routesPath . 'api.php')) {
            require_once $routesPath . 'api.php';
        }
    }

    /**
     * Register custom post types
     */
    protected function registerCustomPostTypes()
    {
        // Register custom product configurator post type
        register_post_type('bbp_product_config', [
            'labels' => [
                'name' => __('Product Configurations', 'bbp-core'),
                'singular_name' => __('Product Configuration', 'bbp-core'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=product',
            'supports' => ['title', 'editor', 'custom-fields'],
            'capability_type' => 'product',
        ]);
    }

    /**
     * Register custom fields
     */
    protected function registerCustomFields()
    {
        // Add custom meta boxes for products
        add_action('add_meta_boxes', function() {
            add_meta_box(
                'bbp_core_product_settings',
                __('BBP Core Settings', 'bbp-core'),
                [$this, 'renderProductMetaBox'],
                'product',
                'normal',
                'high'
            );
        });
    }

    /**
     * Initialize WooCommerce integration
     */
    public function initWooCommerce()
    {
        bbp_core_log('WooCommerce module initialized');
        do_action('bbp_core_woocommerce_init');
    }

    /**
     * Register custom product types
     */
    public function registerProductTypes($types)
    {
        $types['bbp_configurable'] = __('BBP Configurable Product', 'bbp-core');
        $types['bbp_bundle'] = __('BBP Bundle Product', 'bbp-core');
        
        return $types;
    }

    /**
     * Add custom product fields
     */
    public function addProductFields()
    {
        global $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox([
            'id' => '_bbp_core_enabled',
            'label' => __('Enable BBP Core Features', 'bbp-core'),
            'description' => __('Enable advanced BBP Core features for this product.', 'bbp-core'),
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_bbp_core_config_id',
            'label' => __('Configuration ID', 'bbp-core'),
            'description' => __('Enter the configuration ID for this product.', 'bbp-core'),
            'type' => 'text',
        ]);
        
        echo '</div>';
    }

    /**
     * Save custom product fields
     */
    public function saveProductFields($post_id)
    {
        $enabled = isset($_POST['_bbp_core_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_bbp_core_enabled', $enabled);
        
        if (isset($_POST['_bbp_core_config_id'])) {
            update_post_meta($post_id, '_bbp_core_config_id', sanitize_text_field($_POST['_bbp_core_config_id']));
        }
    }

    /**
     * Modify checkout fields
     */
    public function modifyCheckoutFields($fields)
    {
        // Add custom checkout field
        $fields['billing']['bbp_core_custom_field'] = [
            'label' => __('Custom Field', 'bbp-core'),
            'placeholder' => __('Enter custom information', 'bbp-core'),
            'required' => false,
            'class' => ['form-row-wide'],
            'clear' => true,
        ];
        
        return $fields;
    }

    /**
     * Validate checkout fields
     */
    public function validateCheckoutFields()
    {
        // Add custom validation logic here
        if (isset($_POST['bbp_core_custom_field']) && empty($_POST['bbp_core_custom_field'])) {
            // wc_add_notice(__('Custom field is required.', 'bbp-core'), 'error');
        }
    }

    /**
     * Save checkout fields
     */
    public function saveCheckoutFields($order_id)
    {
        if (isset($_POST['bbp_core_custom_field'])) {
            update_post_meta($order_id, '_bbp_core_custom_field', sanitize_text_field($_POST['bbp_core_custom_field']));
        }
    }

    /**
     * Handle order status changes
     */
    public function handleOrderStatusChange($order_id, $old_status, $new_status)
    {
        bbp_core_log("Order {$order_id} status changed from {$old_status} to {$new_status}");
        
        do_action('bbp_core_order_status_changed', $order_id, $old_status, $new_status);
    }

    /**
     * Handle new orders
     */
    public function handleNewOrder($order_id)
    {
        bbp_core_log("New order created: {$order_id}");
        
        do_action('bbp_core_new_order', $order_id);
    }

    /**
     * Add admin menus
     */
    public function addAdminMenus()
    {
        add_submenu_page(
            'woocommerce',
            __('BBP Core Settings', 'bbp-core'),
            __('BBP Core', 'bbp-core'),
            'manage_woocommerce',
            'bbp-core-woocommerce',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings()
    {
        register_setting('bbp_core_woocommerce', 'bbp_core_woocommerce_settings');
        
        add_settings_section(
            'bbp_core_woocommerce_general',
            __('General Settings', 'bbp-core'),
            null,
            'bbp_core_woocommerce'
        );
        
        add_settings_field(
            'enable_custom_product_types',
            __('Enable Custom Product Types', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_woocommerce',
            'bbp_core_woocommerce_general',
            ['field' => 'enable_custom_product_types']
        );
    }

    /**
     * Render admin page
     */
    public function renderAdminPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bbp_core_woocommerce');
                do_settings_sections('bbp_core_woocommerce');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function renderCheckboxField($args)
    {
        $options = get_option('bbp_core_woocommerce_settings', []);
        $value = $options[$args['field']] ?? false;
        
        echo '<input type="checkbox" name="bbp_core_woocommerce_settings[' . esc_attr($args['field']) . ']" value="1" ' . checked(1, $value, false) . ' />';
    }

    /**
     * Render product meta box
     */
    public function renderProductMetaBox($post)
    {
        wp_nonce_field('bbp_core_product_meta', 'bbp_core_product_meta_nonce');
        
        $enabled = get_post_meta($post->ID, '_bbp_core_enabled', true);
        $config_id = get_post_meta($post->ID, '_bbp_core_config_id', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="bbp_core_enabled"><?php _e('Enable BBP Core', 'bbp-core'); ?></label></th>
                <td>
                    <input type="checkbox" id="bbp_core_enabled" name="_bbp_core_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                    <p class="description"><?php _e('Enable BBP Core features for this product.', 'bbp-core'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="bbp_core_config_id"><?php _e('Configuration ID', 'bbp-core'); ?></label></th>
                <td>
                    <input type="text" id="bbp_core_config_id" name="_bbp_core_config_id" value="<?php echo esc_attr($config_id); ?>" class="regular-text" />
                    <p class="description"><?php _e('Enter the configuration ID for this product.', 'bbp-core'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle AJAX actions
     */
    public function handleAjaxAction()
    {
        check_ajax_referer('bbp_core_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['bbp_action'] ?? '');
        
        switch ($action) {
            case 'get_product_config':
                $this->handleGetProductConfig();
                break;
            case 'update_cart':
                $this->handleUpdateCart();
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * Handle get product config AJAX
     */
    protected function handleGetProductConfig()
    {
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        $config = get_post_meta($product_id, '_bbp_core_config_id', true);
        
        wp_send_json_success(['config' => $config]);
    }

    /**
     * Handle update cart AJAX
     */
    protected function handleUpdateCart()
    {
        // Custom cart update logic
        wp_send_json_success(['message' => 'Cart updated']);
    }

    /**
     * Check if WooCommerce is active
     */
    protected function isWooCommerceActive()
    {
        return class_exists('WooCommerce');
    }
}
