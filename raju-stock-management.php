<?php
/**
 * Plugin Name: Raju Stock Management
 * Description: Custom stock management system with product code mapping to WooCommerce variations
 * Version: 2.1.1
 * Author: Raju Plastics
 * Text Domain: raju-stock-management
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * Requires PHP: 7.4
 * 
 * @package Raju_Stock_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RSM_VERSION', '2.1.1');
define('RSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RSM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function rsm_check_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'rsm_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice when WooCommerce is not active
 */
function rsm_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><strong>Raju Stock Management:</strong> <?php esc_html_e('WooCommerce plugin must be active for this plugin to work.', 'raju-stock-management'); ?></p>
    </div>
    <?php
}

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Main Plugin Class
 */
class Raju_Stock_Management {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Database table names
     */
    public $table_products;
    public $table_stock_history;
    public $table_order_tracking;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        $this->table_products = $wpdb->prefix . 'rsm_products';
        $this->table_stock_history = $wpdb->prefix . 'rsm_stock_history';
        $this->table_order_tracking = $wpdb->prefix . 'rsm_order_tracking';
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check WooCommerce
        if (!rsm_check_woocommerce_active()) {
            return;
        }
        
        // Include required files
        $this->includes();
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once RSM_PLUGIN_DIR . 'includes/class-rsm-database.php';
        require_once RSM_PLUGIN_DIR . 'includes/class-rsm-admin.php';
        require_once RSM_PLUGIN_DIR . 'includes/class-rsm-ajax.php';
        require_once RSM_PLUGIN_DIR . 'includes/class-rsm-order-hooks.php';
    }
    
    /**
     * Register hooks
     */
    private function register_hooks() {
        // Initialize admin
        if (is_admin()) {
            new RSM_Admin();
            new RSM_Ajax();
        }
        
        // Order hooks for stock management
        new RSM_Order_Hooks();
    }
}

/**
 * Register Return XL order status
 */
function rsm_register_return_xl_status() {
    register_post_status('wc-return-xl', array(
        'label'                     => _x('Return XL', 'Order status', 'raju-stock-management'),
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop('Return XL <span class="count">(%s)</span>', 'Return XL <span class="count">(%s)</span>', 'raju-stock-management')
    ));
}
add_action('init', 'rsm_register_return_xl_status');

/**
 * Add Return XL to order statuses
 */
function rsm_add_return_xl_to_order_statuses($order_statuses) {
    $new_statuses = array();
    
    foreach ($order_statuses as $key => $status) {
        $new_statuses[$key] = $status;
        if ('wc-completed' === $key) {
            $new_statuses['wc-return-xl'] = _x('Return XL', 'Order status', 'raju-stock-management');
        }
    }
    
    return $new_statuses;
}
add_filter('wc_order_statuses', 'rsm_add_return_xl_to_order_statuses');

/**
 * Initialize the plugin
 */
function rsm_init() {
    return Raju_Stock_Management::get_instance();
}

// Start the plugin
rsm_init();

/**
 * Plugin activation - Create database tables
 */
function rsm_activate_plugin() {
    // Include database class
    require_once plugin_dir_path(__FILE__) . 'includes/class-rsm-database.php';
    RSM_Database::create_tables();
}
register_activation_hook(__FILE__, 'rsm_activate_plugin');

/**
 * Check and create tables if missing (fallback)
 */
function rsm_check_tables() {
    global $wpdb;
    
    // Check if main table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}rsm_products'");
    
    if (!$table_exists) {
        // Include database class if not already included
        if (!class_exists('RSM_Database')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-rsm-database.php';
        }
        RSM_Database::create_tables();
    }
}
add_action('admin_init', 'rsm_check_tables');

function rsm_get_variation_product_code($variation_id) {
    if (empty($variation_id) || $variation_id == 0) {
        return '';
    }
    
    global $wpdb;
    
    $products_table = $wpdb->prefix . 'rsm_products';
    $mappings_table = $wpdb->prefix . 'rsm_product_mappings';
    
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$mappings_table'");
    if (!$table_exists) {
        return '';
    }
    
    $product_code = $wpdb->get_var($wpdb->prepare(
        "SELECT p.product_code FROM $products_table p
        INNER JOIN $mappings_table m ON p.id = m.product_code_id
        WHERE m.variation_id = %d
        LIMIT 1",
        $variation_id
    ));
    
    return !empty($product_code) ? $product_code : '';
}

function rj_vai_get_additional_info($variation_id) {
    return rsm_get_variation_product_code($variation_id);
}
