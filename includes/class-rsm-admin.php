<?php
/**
 * Admin functionality for Raju Stock Management
 * 
 * @package Raju_Stock_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSM_Admin Class
 */
class RSM_Admin {
    
    /**
     * Screen hook suffixes
     */
    private $products_page_hook;
    private $history_page_hook;
    
    /**
     * Available columns for products table
     */
    private $available_columns = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        
        // Define available columns
        $this->available_columns = array(
            'product_code' => __('Product Code', 'raju-stock-management'),
            'product_name' => __('Product Name', 'raju-stock-management'),
            'wc_mapping' => __('WooCommerce Mapping', 'raju-stock-management'),
            'current_stock' => __('Current Stock', 'raju-stock-management'),
            'actions' => __('Actions', 'raju-stock-management')
        );
        
        // AJAX handlers for screen options
        add_action('wp_ajax_rsm_save_screen_options', array($this, 'ajax_save_screen_options'));
    }
    
    /**
     * Save screen options
     */
    public function set_screen_option($status, $option, $value) {
        if (in_array($option, array('rsm_products_per_page', 'rsm_history_per_page'))) {
            return absint($value);
        }
        return $status;
    }
    
    /**
     * AJAX handler for saving screen options
     */
    public function ajax_save_screen_options() {
        check_ajax_referer('rsm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'raju-stock-management')));
        }
        
        $user_id = get_current_user_id();
        
        // Save hidden columns - always save, even if empty array
        $hidden_columns = array();
        if (isset($_POST['hidden_columns']) && is_array($_POST['hidden_columns'])) {
            $hidden_columns = array_map('sanitize_text_field', $_POST['hidden_columns']);
        }
        update_user_meta($user_id, 'rsm_hidden_columns', $hidden_columns);
        
        // Save view mode
        if (isset($_POST['view_mode'])) {
            $view_mode = sanitize_text_field($_POST['view_mode']);
            if (in_array($view_mode, array('list', 'compact', 'card'))) {
                update_user_meta($user_id, 'rsm_view_mode', $view_mode);
            }
        }
        
        // Save per page option
        if (isset($_POST['per_page'])) {
            $per_page = absint($_POST['per_page']);
            if ($per_page > 0) {
                update_user_meta($user_id, 'rsm_products_per_page', $per_page);
            }
        }
        
        wp_send_json_success(array('message' => __('Settings saved.', 'raju-stock-management')));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $this->products_page_hook = add_menu_page(
            __('Raju Stock Management', 'raju-stock-management'),
            __('Stock Management', 'raju-stock-management'),
            'manage_woocommerce',
            'raju-stock-management',
            array($this, 'render_products_page'),
            'dashicons-database',
            56
        );
        
        $submenu_hook = add_submenu_page(
            'raju-stock-management',
            __('Product Codes', 'raju-stock-management'),
            __('Product Codes', 'raju-stock-management'),
            'manage_woocommerce',
            'raju-stock-management',
            array($this, 'render_products_page')
        );
        
        $this->history_page_hook = add_submenu_page(
            'raju-stock-management',
            __('Stock History', 'raju-stock-management'),
            __('Stock History', 'raju-stock-management'),
            'manage_woocommerce',
            'rsm-stock-history',
            array($this, 'render_history_page')
        );
        
        // Add screen options
        add_action('load-' . $this->products_page_hook, array($this, 'add_products_screen_options'));
        add_action('load-' . $submenu_hook, array($this, 'add_products_screen_options'));
        add_action('load-' . $this->history_page_hook, array($this, 'add_history_screen_options'));
    }
    
    /**
     * Add screen options for products page
     */
    public function add_products_screen_options() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Only show screen options on list view
        if ($action !== 'list') {
            return;
        }
        
        // Per page option
        $option = 'per_page';
        $args = array(
            'label'   => __('Product Codes per page', 'raju-stock-management'),
            'default' => 20,
            'option'  => 'rsm_products_per_page'
        );
        add_screen_option($option, $args);
        
        // Add custom screen options panel
        add_filter('screen_settings', array($this, 'render_screen_options'), 10, 2);
    }
    
    /**
     * Render custom screen options
     */
    public function render_screen_options($settings, $screen) {
        if (strpos($screen->id, 'raju-stock-management') === false) {
            return $settings;
        }
        
        $user_id = get_current_user_id();
        $hidden_columns = get_user_meta($user_id, 'rsm_hidden_columns', true);
        if (!is_array($hidden_columns)) {
            $hidden_columns = array();
        }
        
        $view_mode = get_user_meta($user_id, 'rsm_view_mode', true);
        if (!$view_mode) {
            $view_mode = 'list';
        }
        
        ob_start();
        ?>
        <fieldset class="rsm-screen-options-columns">
            <legend><?php esc_html_e('Columns', 'raju-stock-management'); ?></legend>
            <div class="rsm-columns-prefs">
                <?php foreach ($this->available_columns as $column_key => $column_label) : ?>
                    <label>
                        <input type="checkbox" class="rsm-toggle-column" value="<?php echo esc_attr($column_key); ?>" <?php checked(!in_array($column_key, $hidden_columns)); ?>>
                        <?php echo esc_html($column_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        
        <fieldset class="rsm-screen-options-view">
            <legend><?php esc_html_e('View Mode', 'raju-stock-management'); ?></legend>
            <div class="rsm-view-mode-prefs">
                <label>
                    <input type="radio" name="rsm_view_mode" value="list" <?php checked($view_mode, 'list'); ?>>
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('List View', 'raju-stock-management'); ?>
                </label>
                <label>
                    <input type="radio" name="rsm_view_mode" value="compact" <?php checked($view_mode, 'compact'); ?>>
                    <span class="dashicons dashicons-editor-justify"></span> <?php esc_html_e('Compact View', 'raju-stock-management'); ?>
                </label>
                <label>
                    <input type="radio" name="rsm_view_mode" value="card" <?php checked($view_mode, 'card'); ?>>
                    <span class="dashicons dashicons-grid-view"></span> <?php esc_html_e('Card View', 'raju-stock-management'); ?>
                </label>
            </div>
        </fieldset>
        <?php
        $custom_settings = ob_get_clean();
        
        // Return without the extra Apply button - we'll use the default one via JS
        return $settings . $custom_settings;
    }
    
    /**
     * Add screen options for history page
     */
    public function add_history_screen_options() {
        $option = 'per_page';
        $args = array(
            'label'   => __('History items per page', 'raju-stock-management'),
            'default' => 50,
            'option'  => 'rsm_history_per_page'
        );
        add_screen_option($option, $args);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'raju-stock-management') === false && strpos($hook, 'rsm-stock-history') === false) {
            return;
        }
        
        wp_enqueue_style('rsm-admin-css', RSM_PLUGIN_URL . 'assets/css/admin.css', array(), RSM_VERSION);
        wp_enqueue_script('rsm-admin-js', RSM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-autocomplete'), RSM_VERSION, true);
        
        wp_localize_script('rsm-admin-js', 'rsm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rsm_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this product code?', 'raju-stock-management'),
                'loading' => __('Loading...', 'raju-stock-management'),
                'error' => __('An error occurred. Please try again.', 'raju-stock-management'),
                'success' => __('Operation completed successfully.', 'raju-stock-management'),
                'select_variation' => __('Select a variation', 'raju-stock-management'),
                'print_preview' => __('Print Preview', 'raju-stock-management'),
                'print' => __('Print', 'raju-stock-management'),
                'cancel' => __('Cancel', 'raju-stock-management')
            )
        ));
    }
    
    /**
     * Render products page
     */
    public function render_products_page() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'add':
                $this->render_add_product_form();
                break;
            case 'edit':
                $this->render_edit_product_form();
                break;
            case 'stock':
                $this->render_stock_management();
                break;
            default:
                $this->render_products_list();
        }
    }
    
    /**
     * Render products list
     */
    private function render_products_list() {
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'product_code';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        
        // Validate order
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'ASC';
        }
        
        // Validate orderby
        $valid_orderby = array('product_code', 'product_name', 'current_stock');
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'product_code';
        }
        
        // Get per_page from screen options
        $user = get_current_user_id();
        $screen = get_current_screen();
        $screen_option = $screen->get_option('per_page', 'option');
        $per_page = get_user_meta($user, $screen_option, true);
        if (empty($per_page) || $per_page < 1) {
            $per_page = 20;
        }
        
        // Get hidden columns
        $hidden_columns = get_user_meta($user, 'rsm_hidden_columns', true);
        if (!is_array($hidden_columns)) {
            $hidden_columns = array();
        }
        
        // Get view mode
        $view_mode = get_user_meta($user, 'rsm_view_mode', true);
        if (!$view_mode) {
            $view_mode = 'list';
        }
        
        $offset = ($page - 1) * $per_page;
        
        $products = RSM_Database::get_all_products(array(
            'search' => $search,
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => $orderby,
            'order' => $order
        ));
        
        $total = RSM_Database::get_products_count($search);
        $total_pages = ceil($total / $per_page);
        
        // Calculate total stock for print
        $total_stock = 0;
        $all_products_for_print = RSM_Database::get_all_products(array(
            'search' => $search,
            'limit' => 10000,
            'offset' => 0,
            'orderby' => $orderby,
            'order' => $order
        ));
        foreach ($all_products_for_print as $p) {
            $total_stock += intval($p->current_stock);
        }
        
        // Helper function for sort link
        $get_sort_link = function($column) use ($orderby, $order) {
            $new_order = ($orderby === $column && $order === 'ASC') ? 'desc' : 'asc';
            return add_query_arg(array('orderby' => $column, 'order' => $new_order));
        };

        $get_sort_class = function($column) use ($orderby, $order) {
            if ($orderby === $column) {
                return 'sorted ' . strtolower($order);
            }
            return 'sortable asc';
        };

        // Helper for rendering sorting indicators like WP core
        $render_sorting_indicators = function($column) use ($orderby, $order) {
            $is_sorted = ($orderby === $column);
            $asc_active = $is_sorted && $order === 'ASC';
            $desc_active = $is_sorted && $order === 'DESC';
            return '<span class="sorting-indicators">'
                . '<span class="sorting-indicator asc' . ($asc_active ? ' active' : '') . '"></span>'
                . '<span class="sorting-indicator desc' . ($desc_active ? ' active' : '') . '"></span>'
                . '</span>';
        };
        
        ?>
        <div class="wrap rsm-wrap rsm-view-<?php echo esc_attr($view_mode); ?>">
            <h1 class="wp-heading-inline"><?php esc_html_e('Product Codes', 'raju-stock-management'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New Product Code', 'raju-stock-management'); ?>
            </a>
            <button type="button" class="page-title-action" id="rsm-print-stock">
                <span class="dashicons dashicons-printer" style="vertical-align: middle; margin-right: 3px;"></span>
                <?php esc_html_e('Print Stock', 'raju-stock-management'); ?>
            </button>
            
            <hr class="wp-header-end">
            
            <?php $this->display_notices(); ?>
            
            <form method="get" class="rsm-search-form">
                <input type="hidden" name="page" value="raju-stock-management">
                <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                <input type="hidden" name="order" value="<?php echo esc_attr(strtolower($order)); ?>">
                <p class="search-box">
                    <label class="screen-reader-text" for="rsm-search"><?php esc_html_e('Search', 'raju-stock-management'); ?></label>
                    <input type="search" id="rsm-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by code or name...', 'raju-stock-management'); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search', 'raju-stock-management'); ?>">
                </p>
            </form>
            
            <?php if ($view_mode === 'card') : ?>
                <!-- Card View -->
                <div class="rsm-card-grid">
                    <?php if (empty($products)) : ?>
                        <div class="rsm-no-products"><?php esc_html_e('No product codes found.', 'raju-stock-management'); ?></div>
                    <?php else : ?>
                        <?php foreach ($products as $product) : ?>
                            <?php
                            $mappings = RSM_Database::get_mappings($product->id);
                            $mapping_texts = array();
                            
                            if (!empty($mappings)) {
                                foreach ($mappings as $mapping) {
                                    if ($mapping->variation_id) {
                                        $variation = wc_get_product($mapping->variation_id);
                                        if ($variation) {
                                            $parent = wc_get_product($variation->get_parent_id());
                                            $mapping_texts[] = $parent ? $parent->get_name() . ' - ' . $variation->get_name() : $variation->get_name();
                                        }
                                    } elseif ($mapping->product_id) {
                                        $wc_product = wc_get_product($mapping->product_id);
                                        if ($wc_product) {
                                            $mapping_texts[] = $wc_product->get_name();
                                        }
                                    }
                                }
                            }
                            ?>
                            <div class="rsm-product-card">
                                <div class="rsm-card-header">
                                    <span class="rsm-card-code"><?php echo esc_html($product->product_code); ?></span>
                                    <span class="rsm-stock-badge <?php echo $product->current_stock > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                        <?php echo esc_html($product->current_stock); ?>
                                    </span>
                                </div>
                                <div class="rsm-card-body">
                                    <div class="rsm-card-name"><?php echo esc_html($product->product_name ?: '-'); ?></div>
                                    <?php if (!empty($mapping_texts)) : ?>
                                        <div class="rsm-card-mappings">
                                            <small><?php echo esc_html(count($mapping_texts)); ?> <?php esc_html_e('mappings', 'raju-stock-management'); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="rsm-card-actions">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=stock&id=' . $product->id)); ?>" class="button button-small button-primary">
                                        <?php esc_html_e('Stock', 'raju-stock-management'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=edit&id=' . $product->id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'raju-stock-management'); ?>
                                    </a>
                                    <button type="button" class="button button-small rsm-delete-product" data-id="<?php echo esc_attr($product->id); ?>">
                                        <?php esc_html_e('Delete', 'raju-stock-management'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <!-- Table View (List / Compact) -->
                <table class="wp-list-table widefat fixed striped rsm-products-table <?php echo $view_mode === 'compact' ? 'rsm-compact-view' : ''; ?>">
                    <thead>
                        <tr>
                            <?php if (!in_array('product_code', $hidden_columns)) : ?>
                                <th scope="col" class="column-code manage-column <?php echo esc_attr($get_sort_class('product_code')); ?>">
                                    <a href="<?php echo esc_url($get_sort_link('product_code')); ?>">
                                        <span><?php esc_html_e('Product Code', 'raju-stock-management'); ?></span>
                                        <?php echo $render_sorting_indicators('product_code'); ?>
                                    </a>
                                </th>
                            <?php endif; ?>
                            <?php if (!in_array('product_name', $hidden_columns)) : ?>
                                <th scope="col" class="column-name manage-column <?php echo esc_attr($get_sort_class('product_name')); ?>">
                                    <a href="<?php echo esc_url($get_sort_link('product_name')); ?>">
                                        <span><?php esc_html_e('Product Name', 'raju-stock-management'); ?></span>
                                        <?php echo $render_sorting_indicators('product_name'); ?>
                                    </a>
                                </th>
                            <?php endif; ?>
                            <?php if (!in_array('wc_mapping', $hidden_columns)) : ?>
                                <th scope="col" class="column-mapping"><?php esc_html_e('WooCommerce Mapping', 'raju-stock-management'); ?></th>
                            <?php endif; ?>
                            <?php if (!in_array('current_stock', $hidden_columns)) : ?>
                                <th scope="col" class="column-stock manage-column <?php echo esc_attr($get_sort_class('current_stock')); ?>">
                                    <a href="<?php echo esc_url($get_sort_link('current_stock')); ?>">
                                        <span><?php esc_html_e('Current Stock', 'raju-stock-management'); ?></span>
                                        <?php echo $render_sorting_indicators('current_stock'); ?>
                                    </a>
                                </th>
                            <?php endif; ?>
                            <?php if (!in_array('actions', $hidden_columns)) : ?>
                                <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'raju-stock-management'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)) : ?>
                            <tr>
                                <td colspan="5"><?php esc_html_e('No product codes found.', 'raju-stock-management'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($products as $product) : ?>
                                <?php
                                $mappings = RSM_Database::get_mappings($product->id);
                                $mapping_texts = array();
                                
                                if (!empty($mappings)) {
                                    foreach ($mappings as $mapping) {
                                        if ($mapping->variation_id) {
                                            $variation = wc_get_product($mapping->variation_id);
                                            if ($variation) {
                                                $parent = wc_get_product($variation->get_parent_id());
                                                $mapping_texts[] = $parent ? $parent->get_name() . ' - ' . $variation->get_name() : $variation->get_name();
                                            }
                                        } elseif ($mapping->product_id) {
                                            $wc_product = wc_get_product($mapping->product_id);
                                            if ($wc_product) {
                                                $mapping_texts[] = $wc_product->get_name();
                                            }
                                        }
                                    }
                                }
                                $mapping_text = !empty($mapping_texts) ? implode('<br>', $mapping_texts) : '-';
                                $mapping_count = count($mappings);
                                ?>
                                <tr data-product-code="<?php echo esc_attr($product->product_code); ?>" data-stock="<?php echo esc_attr($product->current_stock); ?>">
                                    <?php if (!in_array('product_code', $hidden_columns)) : ?>
                                        <td class="column-code">
                                            <strong><?php echo esc_html($product->product_code); ?></strong>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (!in_array('product_name', $hidden_columns)) : ?>
                                        <td class="column-name"><?php echo esc_html($product->product_name); ?></td>
                                    <?php endif; ?>
                                    <?php if (!in_array('wc_mapping', $hidden_columns)) : ?>
                                        <td class="column-mapping">
                                            <?php if ($view_mode === 'compact') : ?>
                                                <?php if ($mapping_count > 0) : ?>
                                                    <span class="rsm-mapping-count"><?php echo esc_html($mapping_count); ?> <?php esc_html_e('mappings', 'raju-stock-management'); ?></span>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <?php echo wp_kses_post($mapping_text); ?>
                                                <?php if ($mapping_count > 0) : ?>
                                                    <br><small class="rsm-mapping-count">(<?php echo esc_html($mapping_count); ?> <?php esc_html_e('mappings', 'raju-stock-management'); ?>)</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (!in_array('current_stock', $hidden_columns)) : ?>
                                        <td class="column-stock">
                                            <span class="rsm-stock-badge <?php echo $product->current_stock > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                                <?php echo esc_html($product->current_stock); ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (!in_array('actions', $hidden_columns)) : ?>
                                        <td class="column-actions">
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=stock&id=' . $product->id)); ?>" class="button button-small">
                                                <?php esc_html_e('Manage Stock', 'raju-stock-management'); ?>
                                            </a>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=edit&id=' . $product->id)); ?>" class="button button-small">
                                                <?php esc_html_e('Edit', 'raju-stock-management'); ?>
                                            </a>
                                            <button type="button" class="button button-small rsm-delete-product" data-id="<?php echo esc_attr($product->id); ?>">
                                                <?php esc_html_e('Delete', 'raju-stock-management'); ?>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(esc_html(_n('%s item', '%s items', $total, 'raju-stock-management')), number_format_i18n($total)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Hidden data for print -->
            <script type="text/javascript">
                var rsmPrintData = {
                    products: <?php echo json_encode(array_map(function($p) {
                        return array(
                            'code' => $p->product_code,
                            'name' => $p->product_name,
                            'stock' => intval($p->current_stock)
                        );
                    }, $all_products_for_print)); ?>,
                    totalStock: <?php echo intval($total_stock); ?>,
                    dateTime: '<?php echo esc_js(current_time('d M Y, H:i')); ?>',
                    siteName: '<?php echo esc_js(get_bloginfo('name')); ?>'
                };
            </script>
        </div>
        <?php
    }
    
    /**
     * Render add product form
     */
    private function render_add_product_form() {
        ?>
        <div class="wrap rsm-wrap">
            <h1><?php esc_html_e('Add New Product Code', 'raju-stock-management'); ?></h1>
            
            <form method="post" id="rsm-add-product-form" class="rsm-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="product_code"><?php esc_html_e('Product Code', 'raju-stock-management'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="product_code" id="product_code" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Unique product code (e.g., S3001)', 'raju-stock-management'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="product_name"><?php esc_html_e('Product Name', 'raju-stock-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="product_name" id="product_name" class="regular-text">
                            <p class="description"><?php esc_html_e('Optional descriptive name for this product code', 'raju-stock-management'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="current_stock"><?php esc_html_e('Initial Stock', 'raju-stock-management'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="current_stock" id="current_stock" class="small-text" value="0" min="0">
                            <p class="description"><?php esc_html_e('Initial stock quantity for this product code', 'raju-stock-management'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="rsm-save-product">
                        <?php esc_html_e('Add Product Code', 'raju-stock-management'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'raju-stock-management'); ?>
                    </a>
                </p>
                <p class="description"><?php esc_html_e('Note: You can add WooCommerce product mappings after creating the product code.', 'raju-stock-management'); ?></p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render edit product form
     */
    private function render_edit_product_form() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if (!$id) {
            wp_redirect(admin_url('admin.php?page=raju-stock-management'));
            exit;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$product) {
            wp_redirect(admin_url('admin.php?page=raju-stock-management'));
            exit;
        }
        
        // Get existing mappings
        $mappings = RSM_Database::get_mappings($id);
        
        ?>
        <div class="wrap rsm-wrap">
            <h1><?php esc_html_e('Edit Product Code', 'raju-stock-management'); ?>: <?php echo esc_html($product->product_code); ?></h1>
            
            <?php $this->display_notices(); ?>
            
            <div class="rsm-edit-container">
                <!-- Basic Info Section -->
                <div class="rsm-section">
                    <h2><?php esc_html_e('Basic Information', 'raju-stock-management'); ?></h2>
                    <form method="post" id="rsm-edit-product-form" class="rsm-form">
                        <input type="hidden" name="product_id_edit" value="<?php echo esc_attr($id); ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="product_code"><?php esc_html_e('Product Code', 'raju-stock-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="product_code" id="product_code" class="regular-text" value="<?php echo esc_attr($product->product_code); ?>" readonly>
                                    <p class="description"><?php esc_html_e('Product code cannot be changed', 'raju-stock-management'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="product_name"><?php esc_html_e('Product Name', 'raju-stock-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="product_name" id="product_name" class="regular-text" value="<?php echo esc_attr($product->product_name); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Current Stock', 'raju-stock-management'); ?></label>
                                </th>
                                <td>
                                    <span class="rsm-stock-badge <?php echo $product->current_stock > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                        <?php echo esc_html($product->current_stock); ?>
                                    </span>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=stock&id=' . $id)); ?>" class="button button-small">
                                        <?php esc_html_e('Manage Stock', 'raju-stock-management'); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary" id="rsm-update-product">
                                <?php esc_html_e('Update Product Code', 'raju-stock-management'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management')); ?>" class="button">
                                <?php esc_html_e('Back to List', 'raju-stock-management'); ?>
                            </a>
                        </p>
                    </form>
                </div>
                
                <!-- Mappings Section -->
                <div class="rsm-section rsm-mappings-section">
                    <h2><?php esc_html_e('WooCommerce Product Mappings', 'raju-stock-management'); ?></h2>
                    <p class="description"><?php esc_html_e('Map this product code to one or more WooCommerce products/variations. When any mapped variation is ordered and shipped, stock will be reduced.', 'raju-stock-management'); ?></p>
                    
                    <!-- Existing Mappings -->
                    <div class="rsm-existing-mappings">
                        <h3><?php esc_html_e('Current Mappings', 'raju-stock-management'); ?></h3>
                        <?php if (empty($mappings)) : ?>
                            <p class="rsm-no-mappings"><?php esc_html_e('No mappings yet. Add a mapping below.', 'raju-stock-management'); ?></p>
                        <?php else : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Product/Variation', 'raju-stock-management'); ?></th>
                                        <th width="100"><?php esc_html_e('Action', 'raju-stock-management'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="rsm-mappings-list">
                                    <?php foreach ($mappings as $mapping) : 
                                        $mapping_name = '';
                                        if ($mapping->variation_id) {
                                            $variation = wc_get_product($mapping->variation_id);
                                            if ($variation) {
                                                $parent = wc_get_product($variation->get_parent_id());
                                                $mapping_name = $parent ? $parent->get_name() . ' → ' . $variation->get_name() : $variation->get_name();
                                            }
                                        } elseif ($mapping->product_id) {
                                            $wc_product = wc_get_product($mapping->product_id);
                                            if ($wc_product) {
                                                $mapping_name = $wc_product->get_name();
                                            }
                                        }
                                    ?>
                                        <tr data-mapping-id="<?php echo esc_attr($mapping->id); ?>">
                                            <td><?php echo esc_html($mapping_name ?: 'Unknown Product #' . $mapping->product_id); ?></td>
                                            <td>
                                                <button type="button" class="button button-small rsm-remove-mapping" data-mapping-id="<?php echo esc_attr($mapping->id); ?>">
                                                    <?php esc_html_e('Remove', 'raju-stock-management'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add New Mapping -->
                    <div class="rsm-add-mapping-form">
                        <h3><?php esc_html_e('Add New Mapping', 'raju-stock-management'); ?></h3>
                        <div class="rsm-mapping-row">
                            <input type="text" id="wc_product_search" class="regular-text" placeholder="<?php esc_attr_e('Search for a product...', 'raju-stock-management'); ?>">
                            <input type="hidden" id="mapping_product_id" value="0">
                            <input type="hidden" id="mapping_product_code_id" value="<?php echo esc_attr($id); ?>">
                        </div>
                        <div id="rsm-variations-container" style="display:none; margin-top:10px;">
                            <label for="mapping_variation_id"><?php esc_html_e('Select Variation:', 'raju-stock-management'); ?></label>
                            <select id="mapping_variation_id" class="regular-text">
                                <option value="0"><?php esc_html_e('All variations', 'raju-stock-management'); ?></option>
                            </select>
                        </div>
                        <p style="margin-top:10px;">
                            <button type="button" class="button button-primary" id="rsm-add-mapping">
                                <?php esc_html_e('Add Mapping', 'raju-stock-management'); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render stock management page
     */
    private function render_stock_management() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        
        if (!$id) {
            wp_redirect(admin_url('admin.php?page=raju-stock-management'));
            exit;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$product) {
            wp_redirect(admin_url('admin.php?page=raju-stock-management'));
            exit;
        }
        
        // Get recent history for this product
        $history = RSM_Database::get_stock_history(array(
            'product_code_id' => $id,
            'limit' => 20
        ));
        
        ?>
        <div class="wrap rsm-wrap">
            <h1>
                <?php esc_html_e('Manage Stock:', 'raju-stock-management'); ?>
                <span class="rsm-product-code"><?php echo esc_html($product->product_code); ?></span>
            </h1>
            
            <?php $this->display_notices(); ?>
            
            <div class="rsm-stock-management-container">
                <div class="rsm-stock-current">
                    <h2><?php esc_html_e('Current Stock', 'raju-stock-management'); ?></h2>
                    <div class="rsm-stock-number" id="rsm-current-stock"><?php echo esc_html($product->current_stock); ?></div>
                </div>
                
                <div class="rsm-stock-actions">
                    <h2><?php esc_html_e('Update Stock', 'raju-stock-management'); ?></h2>
                    
                    <form method="post" id="rsm-stock-form">
                        <input type="hidden" name="product_code_id" value="<?php echo esc_attr($id); ?>">
                        
                        <div class="rsm-stock-form-row">
                            <label for="stock_action"><?php esc_html_e('Action:', 'raju-stock-management'); ?></label>
                            <select name="stock_action" id="stock_action" class="regular-text">
                                <option value="add"><?php esc_html_e('Add Stock', 'raju-stock-management'); ?></option>
                                <option value="remove"><?php esc_html_e('Remove Stock', 'raju-stock-management'); ?></option>
                            </select>
                        </div>
                        
                        <div class="rsm-stock-form-row">
                            <label for="stock_quantity"><?php esc_html_e('Quantity:', 'raju-stock-management'); ?></label>
                            <input type="number" name="stock_quantity" id="stock_quantity" class="small-text" value="1" min="1" required>
                        </div>
                        
                        <div class="rsm-stock-form-row">
                            <label for="stock_comment"><?php esc_html_e('Comment:', 'raju-stock-management'); ?></label>
                            <textarea name="stock_comment" id="stock_comment" class="large-text" rows="3" placeholder="<?php esc_attr_e('Reason for stock change...', 'raju-stock-management'); ?>"></textarea>
                        </div>
                        
                        <div class="rsm-stock-form-row">
                            <button type="submit" class="button button-primary button-large" id="rsm-update-stock">
                                <?php esc_html_e('Update Stock', 'raju-stock-management'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="rsm-stock-history-section">
                <h2><?php esc_html_e('Recent Stock Changes', 'raju-stock-management'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('Type', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('Quantity', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('Before', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('After', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('Order', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('Comment', 'raju-stock-management'); ?></th>
                            <th><?php esc_html_e('User', 'raju-stock-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)) : ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e('No stock changes recorded yet.', 'raju-stock-management'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($history as $entry) : ?>
                                <?php
                                $type_labels = array(
                                    'add' => __('Added', 'raju-stock-management'),
                                    'remove' => __('Removed', 'raju-stock-management'),
                                    'order_minus' => __('Order Shipped', 'raju-stock-management'),
                                    'order_return' => __('Order Returned', 'raju-stock-management')
                                );
                                $type_classes = array(
                                    'add' => 'rsm-type-add',
                                    'remove' => 'rsm-type-remove',
                                    'order_minus' => 'rsm-type-order',
                                    'order_return' => 'rsm-type-return'
                                );
                                $user = $entry->created_by ? get_user_by('id', $entry->created_by) : null;
                                ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n('d M Y H:i', strtotime($entry->created_at))); ?></td>
                                    <td>
                                        <span class="rsm-type-badge <?php echo esc_attr($type_classes[$entry->change_type] ?? ''); ?>">
                                            <?php echo esc_html($type_labels[$entry->change_type] ?? $entry->change_type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $prefix = in_array($entry->change_type, array('add', 'order_return')) ? '+' : '-';
                                        echo esc_html($prefix . $entry->quantity);
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($entry->stock_before); ?></td>
                                    <td><?php echo esc_html($entry->stock_after); ?></td>
                                    <td>
                                        <?php if ($entry->order_id) : ?>
                                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $entry->order_id . '&action=edit')); ?>">
                                                #<?php echo esc_html($entry->order_id); ?>
                                            </a>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($entry->comment ?: '-'); ?></td>
                                    <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=rsm-stock-history&product_code=' . $product->product_code)); ?>" class="button">
                        <?php esc_html_e('View Full History', 'raju-stock-management'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management')); ?>" class="button">
                        <?php esc_html_e('Back to Products', 'raju-stock-management'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render stock history page
     */
    public function render_history_page() {
        $product_code = isset($_GET['product_code']) ? sanitize_text_field($_GET['product_code']) : '';
        $change_type = isset($_GET['change_type']) ? sanitize_text_field($_GET['change_type']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get per_page from screen options
        $user = get_current_user_id();
        $screen = get_current_screen();
        $screen_option = $screen->get_option('per_page', 'option');
        $per_page = get_user_meta($user, $screen_option, true);
        if (empty($per_page) || $per_page < 1) {
            $per_page = 50;
        }
        
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'product_code' => $product_code,
            'change_type' => $change_type,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'limit' => $per_page,
            'offset' => $offset
        );
        
        $history = RSM_Database::get_stock_history($args);
        $total = RSM_Database::get_stock_history_count($args);
        $total_pages = ceil($total / $per_page);
        
        // Get all product codes for filter
        $all_products = RSM_Database::get_all_products(array('limit' => 1000));
        
        ?>
        <div class="wrap rsm-wrap">
            <h1><?php esc_html_e('Stock History', 'raju-stock-management'); ?></h1>
            
            <form method="get" class="rsm-filter-form">
                <input type="hidden" name="page" value="rsm-stock-history">
                
                <div class="rsm-filter-row">
                    <label for="product_code"><?php esc_html_e('Product Code:', 'raju-stock-management'); ?></label>
                    <select name="product_code" id="product_code">
                        <option value=""><?php esc_html_e('All Products', 'raju-stock-management'); ?></option>
                        <?php foreach ($all_products as $prod) : ?>
                            <option value="<?php echo esc_attr($prod->product_code); ?>" <?php selected($product_code, $prod->product_code); ?>>
                                <?php echo esc_html($prod->product_code . ' - ' . $prod->product_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="change_type"><?php esc_html_e('Type:', 'raju-stock-management'); ?></label>
                    <select name="change_type" id="change_type">
                        <option value=""><?php esc_html_e('All Types', 'raju-stock-management'); ?></option>
                        <option value="add" <?php selected($change_type, 'add'); ?>><?php esc_html_e('Added', 'raju-stock-management'); ?></option>
                        <option value="remove" <?php selected($change_type, 'remove'); ?>><?php esc_html_e('Removed', 'raju-stock-management'); ?></option>
                        <option value="order_minus" <?php selected($change_type, 'order_minus'); ?>><?php esc_html_e('Order Shipped', 'raju-stock-management'); ?></option>
                        <option value="order_return" <?php selected($change_type, 'order_return'); ?>><?php esc_html_e('Order Returned', 'raju-stock-management'); ?></option>
                    </select>
                    
                    <label for="date_from"><?php esc_html_e('From:', 'raju-stock-management'); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                    
                    <label for="date_to"><?php esc_html_e('To:', 'raju-stock-management'); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                    
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'raju-stock-management'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=rsm-stock-history')); ?>" class="button"><?php esc_html_e('Reset', 'raju-stock-management'); ?></a>
                </div>
            </form>
            
            <table class="wp-list-table widefat fixed striped rsm-history-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Date', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Product Code', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Type', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Qty', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Before', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('After', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Order', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('Comment', 'raju-stock-management'); ?></th>
                        <th scope="col"><?php esc_html_e('User', 'raju-stock-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)) : ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e('No stock history found.', 'raju-stock-management'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($history as $entry) : ?>
                            <?php
                            $type_labels = array(
                                'add' => __('Added', 'raju-stock-management'),
                                'remove' => __('Removed', 'raju-stock-management'),
                                'order_minus' => __('Order Shipped', 'raju-stock-management'),
                                'order_return' => __('Order Returned', 'raju-stock-management')
                            );
                            $type_classes = array(
                                'add' => 'rsm-type-add',
                                'remove' => 'rsm-type-remove',
                                'order_minus' => 'rsm-type-order',
                                'order_return' => 'rsm-type-return'
                            );
                            $user = $entry->created_by ? get_user_by('id', $entry->created_by) : null;
                            ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('d M Y H:i', strtotime($entry->created_at))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=raju-stock-management&action=stock&id=' . $entry->product_code_id)); ?>">
                                        <?php echo esc_html($entry->product_code); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="rsm-type-badge <?php echo esc_attr($type_classes[$entry->change_type] ?? ''); ?>">
                                        <?php echo esc_html($type_labels[$entry->change_type] ?? $entry->change_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $prefix = in_array($entry->change_type, array('add', 'order_return')) ? '+' : '-';
                                    echo esc_html($prefix . $entry->quantity);
                                    ?>
                                </td>
                                <td><?php echo esc_html($entry->stock_before); ?></td>
                                <td><?php echo esc_html($entry->stock_after); ?></td>
                                <td>
                                    <?php if ($entry->order_id) : ?>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $entry->order_id . '&action=edit')); ?>">
                                            #<?php echo esc_html($entry->order_id); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td title="<?php echo esc_attr($entry->comment); ?>">
                                    <?php echo esc_html(wp_trim_words($entry->comment, 10, '...')); ?>
                                </td>
                                <td><?php echo $user ? esc_html($user->display_name) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(esc_html(_n('%s item', '%s items', $total, 'raju-stock-management')), number_format_i18n($total)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display admin notices
     */
    private function display_notices() {
        if (isset($_GET['rsm_message'])) {
            $messages = array(
                'product_added' => array('success', __('Product code added successfully.', 'raju-stock-management')),
                'product_updated' => array('success', __('Product code updated successfully.', 'raju-stock-management')),
                'product_deleted' => array('success', __('Product code deleted successfully.', 'raju-stock-management')),
                'stock_updated' => array('success', __('Stock updated successfully.', 'raju-stock-management')),
                'error' => array('error', __('An error occurred. Please try again.', 'raju-stock-management')),
                'duplicate_code' => array('error', __('This product code already exists.', 'raju-stock-management'))
            );
            
            $message_key = sanitize_text_field($_GET['rsm_message']);
            
            if (isset($messages[$message_key])) {
                $type = $messages[$message_key][0];
                $message = $messages[$message_key][1];
                ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
            }
        }
    }
}
