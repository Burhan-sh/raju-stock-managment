<?php
/**
 * AJAX handlers for Raju Stock Management
 * 
 * @package Raju_Stock_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSM_Ajax Class
 */
class RSM_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Product operations
        add_action('wp_ajax_rsm_add_product', array($this, 'add_product'));
        add_action('wp_ajax_rsm_update_product', array($this, 'update_product'));
        add_action('wp_ajax_rsm_delete_product', array($this, 'delete_product'));
        
        // Mapping operations
        add_action('wp_ajax_rsm_add_mapping', array($this, 'add_mapping'));
        add_action('wp_ajax_rsm_remove_mapping', array($this, 'remove_mapping'));
        
        // Stock operations
        add_action('wp_ajax_rsm_update_stock', array($this, 'update_stock'));
        
        // WooCommerce product search
        add_action('wp_ajax_rsm_search_products', array($this, 'search_products'));
        add_action('wp_ajax_rsm_get_variations', array($this, 'get_variations'));
    }
    
    /**
     * Verify nonce
     */
    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rsm_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'raju-stock-management')));
            exit;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'raju-stock-management')));
            exit;
        }
    }
    
    /**
     * Add new product
     */
    public function add_product() {
        $this->verify_nonce();
        
        $product_code = isset($_POST['product_code']) ? sanitize_text_field($_POST['product_code']) : '';
        
        if (empty($product_code)) {
            wp_send_json_error(array('message' => __('Product code is required.', 'raju-stock-management')));
        }
        
        // Check if code already exists
        $existing = RSM_Database::get_product_by_code($product_code);
        if ($existing) {
            wp_send_json_error(array('message' => __('This product code already exists.', 'raju-stock-management')));
        }
        
        $data = array(
            'product_code' => $product_code,
            'product_name' => isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '',
            'current_stock' => isset($_POST['current_stock']) ? absint($_POST['current_stock']) : 0
        );
        
        $result = RSM_Database::add_product($data);
        
        if ($result === false) {
            global $wpdb;
            wp_send_json_error(array('message' => __('Database error: ', 'raju-stock-management') . $wpdb->last_error));
        }
        
        if ($result) {
            // Log initial stock if any
            if ($data['current_stock'] > 0) {
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'rsm_stock_history', array(
                    'product_code_id' => $result,
                    'product_code' => $product_code,
                    'change_type' => 'add',
                    'quantity' => $data['current_stock'],
                    'stock_before' => 0,
                    'stock_after' => $data['current_stock'],
                    'comment' => __('Initial stock', 'raju-stock-management'),
                    'created_by' => get_current_user_id()
                ), array('%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d'));
            }
            
            wp_send_json_success(array(
                'message' => __('Product code added successfully.', 'raju-stock-management'),
                'redirect' => admin_url('admin.php?page=raju-stock-management&action=edit&id=' . $result . '&rsm_message=product_added'),
                'product_id' => $result
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to add product code.', 'raju-stock-management')));
        }
    }
    
    /**
     * Update product
     */
    public function update_product() {
        $this->verify_nonce();
        
        $id = isset($_POST['product_id_edit']) ? absint($_POST['product_id_edit']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'raju-stock-management')));
        }
        
        $data = array(
            'product_name' => isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : ''
        );
        
        $result = RSM_Database::update_product($id, $data);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Product code updated successfully.', 'raju-stock-management'),
                'redirect' => admin_url('admin.php?page=raju-stock-management&action=edit&id=' . $id . '&rsm_message=product_updated')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update product code.', 'raju-stock-management')));
        }
    }
    
    /**
     * Delete product
     */
    public function delete_product() {
        $this->verify_nonce();
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'raju-stock-management')));
        }
        
        $result = RSM_Database::delete_product($id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Product code deleted successfully.', 'raju-stock-management')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete product code.', 'raju-stock-management')));
        }
    }
    
    /**
     * Add mapping
     */
    public function add_mapping() {
        $this->verify_nonce();
        
        $product_code_id = isset($_POST['product_code_id']) ? absint($_POST['product_code_id']) : 0;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        if (!$product_code_id || !$product_id) {
            wp_send_json_error(array('message' => __('Please select a product.', 'raju-stock-management')));
        }
        
        $result = RSM_Database::add_mapping($product_code_id, $product_id, $variation_id);
        
        if ($result) {
            // Get product name for response
            $mapping_name = '';
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $parent = wc_get_product($variation->get_parent_id());
                    $mapping_name = $parent ? $parent->get_name() . ' → ' . $variation->get_name() : $variation->get_name();
                }
            } else {
                $wc_product = wc_get_product($product_id);
                if ($wc_product) {
                    $mapping_name = $wc_product->get_name();
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Mapping added successfully.', 'raju-stock-management'),
                'mapping_id' => $result,
                'mapping_name' => $mapping_name
            ));
        } else {
            wp_send_json_error(array('message' => __('Mapping already exists or failed to add.', 'raju-stock-management')));
        }
    }
    
    /**
     * Remove mapping
     */
    public function remove_mapping() {
        $this->verify_nonce();
        
        $mapping_id = isset($_POST['mapping_id']) ? absint($_POST['mapping_id']) : 0;
        
        if (!$mapping_id) {
            wp_send_json_error(array('message' => __('Invalid mapping ID.', 'raju-stock-management')));
        }
        
        $result = RSM_Database::remove_mapping($mapping_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Mapping removed successfully.', 'raju-stock-management')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove mapping.', 'raju-stock-management')));
        }
    }
    
    /**
     * Update stock
     */
    public function update_stock() {
        $this->verify_nonce();
        
        $product_code_id = isset($_POST['product_code_id']) ? absint($_POST['product_code_id']) : 0;
        $action = isset($_POST['stock_action']) ? sanitize_text_field($_POST['stock_action']) : '';
        $quantity = isset($_POST['stock_quantity']) ? absint($_POST['stock_quantity']) : 0;
        $comment = isset($_POST['stock_comment']) ? sanitize_textarea_field($_POST['stock_comment']) : '';
        
        if (!$product_code_id || !$action || !$quantity) {
            wp_send_json_error(array('message' => __('Invalid data provided.', 'raju-stock-management')));
        }
        
        if (!in_array($action, array('add', 'remove'))) {
            wp_send_json_error(array('message' => __('Invalid action.', 'raju-stock-management')));
        }
        
        $new_stock = RSM_Database::update_stock($product_code_id, $quantity, $action, $comment);
        
        if ($new_stock !== false) {
            wp_send_json_success(array(
                'message' => __('Stock updated successfully.', 'raju-stock-management'),
                'new_stock' => $new_stock
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update stock.', 'raju-stock-management')));
        }
    }
    
    /**
     * Search WooCommerce products
     */
    public function search_products() {
        $this->verify_nonce();
        
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        
        if (empty($term) || strlen($term) < 2) {
            wp_send_json_success(array());
        }
        
        $args = array(
            'post_type' => array('product'),
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => 20
        );
        
        $products = get_posts($args);
        $results = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) continue;
            
            $results[] = array(
                'id' => $product->get_id(),
                'label' => $product->get_name() . ' (#' . $product->get_id() . ')',
                'value' => $product->get_name(),
                'type' => $product->get_type()
            );
        }
        
        // Also search by SKU
        $sku_products = wc_get_products(array(
            'sku' => $term,
            'limit' => 10,
            'status' => 'publish'
        ));
        
        foreach ($sku_products as $product) {
            $exists = false;
            foreach ($results as $r) {
                if ($r['id'] == $product->get_id()) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $results[] = array(
                    'id' => $product->get_id(),
                    'label' => $product->get_name() . ' (SKU: ' . $product->get_sku() . ')',
                    'value' => $product->get_name(),
                    'type' => $product->get_type()
                );
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Get product variations
     */
    public function get_variations() {
        $this->verify_nonce();
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'raju-stock-management')));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_success(array('variations' => array()));
        }
        
        $variations = $product->get_available_variations();
        $results = array();
        
        foreach ($variations as $variation) {
            $variation_product = wc_get_product($variation['variation_id']);
            if (!$variation_product) continue;
            
            $attributes = array();
            foreach ($variation['attributes'] as $key => $value) {
                $attr_name = str_replace('attribute_', '', $key);
                $attr_label = wc_attribute_label($attr_name);
                $attributes[] = $attr_label . ': ' . $value;
            }
            
            $results[] = array(
                'id' => $variation['variation_id'],
                'name' => implode(', ', $attributes),
                'sku' => $variation_product->get_sku()
            );
        }
        
        wp_send_json_success(array('variations' => $results));
    }
}
