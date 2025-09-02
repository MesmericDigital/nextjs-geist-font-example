<?php

namespace BBPCore\Modules\Breakdance\Providers;

use Illuminate\Support\ServiceProvider;
use BBPCore\Modules\Breakdance\Elements\ProductGrid;
use BBPCore\Modules\Breakdance\Elements\CartWidget;
use BBPCore\Modules\Breakdance\Elements\CheckoutForm;
use BBPCore\Modules\Breakdance\Elements\ProductConfigurator;
use BBPCore\Modules\Breakdance\Services\ElementService;
use BBPCore\Modules\Breakdance\Services\AnimationService;

/**
 * Breakdance Module Service Provider
 */
class BreakdanceServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register()
    {
        $this->registerServices();
        $this->registerElements();
    }

    /**
     * Bootstrap services
     */
    public function boot()
    {
        if (!$this->isBreakdanceActive()) {
            return;
        }

        $this->registerHooks();
        $this->registerBreakdanceElements();
        $this->loadElementAssets();
    }

    /**
     * Register module services
     */
    protected function registerServices()
    {
        $this->app->singleton(ElementService::class, function ($app) {
            return new ElementService();
        });

        $this->app->singleton(AnimationService::class, function ($app) {
            return new AnimationService();
        });
    }

    /**
     * Register element classes
     */
    protected function registerElements()
    {
        $this->app->bind(ProductGrid::class, function ($app) {
            return new ProductGrid();
        });

        $this->app->bind(CartWidget::class, function ($app) {
            return new CartWidget();
        });

        $this->app->bind(CheckoutForm::class, function ($app) {
            return new CheckoutForm();
        });

        $this->app->bind(ProductConfigurator::class, function ($app) {
            return new ProductConfigurator();
        });
    }

    /**
     * Register WordPress hooks
     */
    protected function registerHooks()
    {
        // Breakdance initialization
        add_action('breakdance_loaded', [$this, 'initBreakdance']);
        
        // Element registration
        add_action('breakdance_register_elements', [$this, 'registerBreakdanceElements']);
        
        // Asset hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('breakdance_element_assets', [$this, 'enqueueElementAssets']);
        
        // AJAX hooks for dynamic content
        add_action('wp_ajax_bbp_breakdance_action', [$this, 'handleAjaxAction']);
        add_action('wp_ajax_nopriv_bbp_breakdance_action', [$this, 'handleAjaxAction']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'addAdminMenus']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Initialize Breakdance integration
     */
    public function initBreakdance()
    {
        bbp_core_log('Breakdance module initialized');
        do_action('bbp_core_breakdance_init');
    }

    /**
     * Register Breakdance elements
     */
    public function registerBreakdanceElements()
    {
        if (!function_exists('breakdance_register_element')) {
            return;
        }

        // Register Product Grid Element
        breakdance_register_element([
            'name' => 'BBPProductGrid',
            'label' => __('BBP Product Grid', 'bbp-core'),
            'category' => 'ecommerce',
            'icon' => 'GridIcon',
            'class' => ProductGrid::class,
            'supports' => ['responsive', 'animations', 'spacing'],
        ]);

        // Register Cart Widget Element
        breakdance_register_element([
            'name' => 'BBPCartWidget',
            'label' => __('BBP Cart Widget', 'bbp-core'),
            'category' => 'ecommerce',
            'icon' => 'ShoppingCartIcon',
            'class' => CartWidget::class,
            'supports' => ['responsive', 'real-time'],
        ]);

        // Register Checkout Form Element
        breakdance_register_element([
            'name' => 'BBPCheckoutForm',
            'label' => __('BBP Checkout Form', 'bbp-core'),
            'category' => 'ecommerce',
            'icon' => 'CreditCardIcon',
            'class' => CheckoutForm::class,
            'supports' => ['responsive', 'validation'],
        ]);

        // Register Product Configurator Element
        breakdance_register_element([
            'name' => 'BBPProductConfigurator',
            'label' => __('BBP Product Configurator', 'bbp-core'),
            'category' => 'ecommerce',
            'icon' => 'SettingsIcon',
            'class' => ProductConfigurator::class,
            'supports' => ['responsive', 'dynamic'],
        ]);
    }


    /**
     * Enqueue general assets
     */
    public function enqueueAssets()
    {
        if (!$this->isBreakdanceBuilderActive()) {
            return;
        }

        // Enqueue frontend styles
        wp_enqueue_style(
            'bbp-core-breakdance-elements',
            $this->getAssetUrl('css/elements.css'),
            [],
            BBPCore::VERSION
        );

        wp_enqueue_style(
            'bbp-core-breakdance-animations',
            $this->getAssetUrl('css/animations.css'),
            [],
            BBPCore::VERSION
        );

        // Enqueue frontend scripts
        wp_enqueue_script(
            'bbp-core-breakdance-elements',
            $this->getAssetUrl('js/elements.js'),
            ['jquery'],
            BBPCore::VERSION,
            true
        );

        wp_enqueue_script(
            'bbp-core-breakdance-interactions',
            $this->getAssetUrl('js/interactions.js'),
            ['jquery'],
            BBPCore::VERSION,
            true
        );

        // Localize script
        wp_localize_script('bbp-core-breakdance-elements', 'bbpBreakdance', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bbp_breakdance_nonce'),
            'isBuilder' => $this->isBreakdanceBuilderActive(),
            'woocommerceActive' => bbp_core_is_woocommerce_active(),
        ]);
    }

    /**
     * Enqueue element-specific assets
     */
    public function enqueueElementAssets($element)
    {
        // Element-specific asset loading logic
        switch ($element) {
            case 'BBPProductGrid':
                wp_enqueue_style('bbp-product-grid', $this->getAssetUrl('css/product-grid.css'));
                wp_enqueue_script('bbp-product-grid', $this->getAssetUrl('js/product-grid.js'));
                break;
            
            case 'BBPCartWidget':
                wp_enqueue_style('bbp-cart-widget', $this->getAssetUrl('css/cart-widget.css'));
                wp_enqueue_script('bbp-cart-widget', $this->getAssetUrl('js/cart-widget.js'));
                break;
        }
    }

    /**
     * Load element assets
     */
    protected function loadElementAssets()
    {
        add_action('wp_head', function() {
            if ($this->isBreakdanceBuilderActive()) {
                echo '<style id="bbp-core-breakdance-inline">';
                echo $this->getInlineCSS();
                echo '</style>';
            }
        });
    }

    /**
     * Get inline CSS for elements
     */
    protected function getInlineCSS()
    {
        return '
            .bbp-element {
                position: relative;
            }
            
            .bbp-product-grid {
                display: grid;
                gap: 1rem;
            }
            
            .bbp-cart-widget {
                position: relative;
            }
            
            .bbp-checkout-form {
                max-width: 600px;
            }
            
            .bbp-product-configurator {
                background: #f9f9f9;
                padding: 1rem;
                border-radius: 8px;
            }
        ';
    }

    /**
     * Add admin menus
     */
    public function addAdminMenus()
    {
        add_submenu_page(
            'breakdance',
            __('BBP Core Elements', 'bbp-core'),
            __('BBP Elements', 'bbp-core'),
            'manage_options',
            'bbp-core-breakdance',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings()
    {
        register_setting('bbp_core_breakdance', 'bbp_core_breakdance_settings');
        
        add_settings_section(
            'bbp_core_breakdance_elements',
            __('Element Settings', 'bbp-core'),
            null,
            'bbp_core_breakdance'
        );
        
        add_settings_field(
            'enable_ecommerce_elements',
            __('Enable E-commerce Elements', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_breakdance',
            'bbp_core_breakdance_elements',
            ['field' => 'enable_ecommerce_elements']
        );
        
        add_settings_field(
            'enable_animations',
            __('Enable Animations', 'bbp-core'),
            [$this, 'renderCheckboxField'],
            'bbp_core_breakdance',
            'bbp_core_breakdance_elements',
            ['field' => 'enable_animations']
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
            
            <div class="bbp-admin-grid">
                <div class="bbp-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('bbp_core_breakdance');
                        do_settings_sections('bbp_core_breakdance');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="bbp-admin-sidebar">
                    <div class="bbp-admin-widget">
                        <h3><?php _e('Available Elements', 'bbp-core'); ?></h3>
                        <ul>
                            <li><?php _e('BBP Product Grid', 'bbp-core'); ?></li>
                            <li><?php _e('BBP Cart Widget', 'bbp-core'); ?></li>
                            <li><?php _e('BBP Checkout Form', 'bbp-core'); ?></li>
                            <li><?php _e('BBP Product Configurator', 'bbp-core'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .bbp-admin-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .bbp-admin-widget {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 1rem;
            border-radius: 4px;
        }
        
        .bbp-admin-widget h3 {
            margin-top: 0;
        }
        
        .bbp-admin-widget ul {
            margin: 0;
            padding-left: 1rem;
        }
        </style>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function renderCheckboxField($args)
    {
        $options = get_option('bbp_core_breakdance_settings', []);
        $value = $options[$args['field']] ?? true;
        
        echo '<input type="checkbox" name="bbp_core_breakdance_settings[' . esc_attr($args['field']) . ']" value="1" ' . checked(1, $value, false) . ' />';
    }

    /**
     * Handle AJAX actions
     */
    public function handleAjaxAction()
    {
        check_ajax_referer('bbp_breakdance_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['bbp_action'] ?? '');
        
        switch ($action) {
            case 'load_products':
                $this->handleLoadProducts();
                break;
            case 'update_cart':
                $this->handleUpdateCart();
                break;
            case 'get_element_preview':
                $this->handleGetElementPreview();
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * Handle load products AJAX
     */
    protected function handleLoadProducts()
    {
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 12);
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (!bbp_core_is_woocommerce_active()) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }
        
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
        ];
        
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category,
                ]
            ];
        }
        
        $products = get_posts($args);
        $product_data = [];
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            
            $product_data[] = [
                'id' => $product->ID,
                'title' => $product->post_title,
                'price' => $wc_product->get_price_html(),
                'image' => get_the_post_thumbnail_url($product->ID, 'medium'),
                'url' => get_permalink($product->ID),
            ];
        }
        
        wp_send_json_success(['products' => $product_data]);
    }

    /**
     * Handle update cart AJAX
     */
    protected function handleUpdateCart()
    {
        if (!bbp_core_is_woocommerce_active()) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        $result = WC()->cart->add_to_cart($product_id, $quantity);
        
        if ($result) {
            wp_send_json_success([
                'message' => 'Product added to cart',
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add product to cart']);
        }
    }

    /**
     * Handle get element preview AJAX
     */
    protected function handleGetElementPreview()
    {
        $element_type = sanitize_text_field($_POST['element_type'] ?? '');
        $settings = $_POST['settings'] ?? [];
        
        // Generate preview HTML based on element type and settings
        $preview_html = $this->generateElementPreview($element_type, $settings);
        
        wp_send_json_success(['html' => $preview_html]);
    }

    /**
     * Generate element preview HTML
     */
    protected function generateElementPreview($element_type, $settings)
    {
        switch ($element_type) {
            case 'BBPProductGrid':
                return '<div class="bbp-product-grid-preview">Product Grid Preview</div>';
            case 'BBPCartWidget':
                return '<div class="bbp-cart-widget-preview">Cart Widget Preview</div>';
            case 'BBPCheckoutForm':
                return '<div class="bbp-checkout-form-preview">Checkout Form Preview</div>';
            case 'BBPProductConfigurator':
                return '<div class="bbp-configurator-preview">Product Configurator Preview</div>';
            default:
                return '<div class="bbp-element-preview">Element Preview</div>';
        }
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

    /**
     * Check if Breakdance is active
     */
    protected function isBreakdanceActive()
    {
        return class_exists('Breakdance\PluginAPI\PluginAPI') || function_exists('breakdance_register_element');
    }

    /**
     * Check if Breakdance builder is active
     */
    protected function isBreakdanceBuilderActive()
    {
        return $this->isBreakdanceActive() && (
            isset($_GET['breakdance']) && $_GET['breakdance'] === 'builder' ||
            defined('BREAKDANCE_BUILDER_ACTIVE') && BREAKDANCE_BUILDER_ACTIVE
        );
    }
}
