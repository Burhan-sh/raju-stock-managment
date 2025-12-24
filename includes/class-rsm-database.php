<?php
/**
 * Database handler for Raju Stock Management
 * 
 * @package Raju_Stock_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSM_Database Class
 */
class RSM_Database {
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Products table - stores product codes (without variation mapping - moved to separate table)
        $table_products = $wpdb->prefix . 'rsm_products';
        $sql_products = "CREATE TABLE $table_products (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_code varchar(100) NOT NULL,
            product_name varchar(255) DEFAULT '',
            current_stock int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY product_code (product_code)
        ) $charset_collate;";
        
        // Product mappings table - maps product codes to multiple variations
        $table_mappings = $wpdb->prefix . 'rsm_product_mappings';
        $sql_mappings = "CREATE TABLE $table_mappings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_code_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (product_code_id, product_id, variation_id),
            KEY product_code_id (product_code_id),
            KEY product_id (product_id),
            KEY variation_id (variation_id)
        ) $charset_collate;";
        
        // Stock history table - tracks all stock changes
        $table_stock_history = $wpdb->prefix . 'rsm_stock_history';
        $sql_stock_history = "CREATE TABLE $table_stock_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_code_id bigint(20) unsigned NOT NULL,
            product_code varchar(100) NOT NULL,
            change_type enum('add','remove','order_minus','order_return') NOT NULL,
            quantity int(11) NOT NULL,
            stock_before int(11) NOT NULL,
            stock_after int(11) NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            comment text DEFAULT '',
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_code_id (product_code_id),
            KEY order_id (order_id),
            KEY change_type (change_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Order tracking table - prevents duplicate stock operations
        $table_order_tracking = $wpdb->prefix . 'rsm_order_tracking';
        $sql_order_tracking = "CREATE TABLE $table_order_tracking (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            product_code_id bigint(20) unsigned NOT NULL,
            product_code varchar(100) NOT NULL,
            quantity int(11) NOT NULL,
            shipped_processed tinyint(1) DEFAULT 0,
            return_processed tinyint(1) DEFAULT 0,
            shipped_at datetime DEFAULT NULL,
            returned_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_item_unique (order_id, order_item_id, product_code_id),
            KEY order_id (order_id),
            KEY product_code_id (product_code_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_products);
        dbDelta($sql_mappings);
        dbDelta($sql_stock_history);
        dbDelta($sql_order_tracking);
        
        // Store database version
        update_option('rsm_db_version', RSM_VERSION);
    }
    
    /**
     * Get product by code
     */
    public static function get_product_by_code($product_code) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE product_code = %s",
            $product_code
        ));
    }
    
    /**
     * Get product by variation ID
     */
    public static function get_product_by_variation_id($variation_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'rsm_products';
        $mappings_table = $wpdb->prefix . 'rsm_product_mappings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.* FROM $products_table p
            INNER JOIN $mappings_table m ON p.id = m.product_code_id
            WHERE m.variation_id = %d",
            $variation_id
        ));
    }
    
    /**
     * Get product by product ID (for simple products)
     */
    public static function get_product_by_product_id($product_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'rsm_products';
        $mappings_table = $wpdb->prefix . 'rsm_product_mappings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.* FROM $products_table p
            INNER JOIN $mappings_table m ON p.id = m.product_code_id
            WHERE m.product_id = %d AND m.variation_id = 0",
            $product_id
        ));
    }
    
    /**
     * Get all products
     */
    public static function get_all_products($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        $defaults = array(
            'orderby' => 'product_code',
            'order' => 'ASC',
            'limit' => 50,
            'offset' => 0,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $table";
        
        if (!empty($args['search'])) {
            $sql .= $wpdb->prepare(" WHERE product_code LIKE %s OR product_name LIKE %s", 
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%'
            );
        }
        
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get total products count
     */
    public static function get_products_count($search = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        $sql = "SELECT COUNT(*) FROM $table";
        
        if (!empty($search)) {
            $sql .= $wpdb->prepare(" WHERE product_code LIKE %s OR product_name LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Add new product
     */
    public static function add_product($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        $result = $wpdb->insert($table, array(
            'product_code' => sanitize_text_field($data['product_code']),
            'product_name' => sanitize_text_field($data['product_name'] ?? ''),
            'current_stock' => absint($data['current_stock'] ?? 0)
        ), array('%s', '%s', '%d'));
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Add product mapping (link product code to variation/product)
     */
    public static function add_mapping($product_code_id, $product_id, $variation_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_product_mappings';
        
        // Check if mapping already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE product_code_id = %d AND product_id = %d AND variation_id = %d",
            $product_code_id, $product_id, $variation_id
        ));
        
        if ($exists) {
            return $exists; // Already exists
        }
        
        $result = $wpdb->insert($table, array(
            'product_code_id' => $product_code_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id
        ), array('%d', '%d', '%d'));
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Remove product mapping
     */
    public static function remove_mapping($mapping_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_product_mappings';
        
        return $wpdb->delete($table, array('id' => $mapping_id), array('%d'));
    }
    
    /**
     * Get all mappings for a product code
     */
    public static function get_mappings($product_code_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_product_mappings';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_code_id = %d ORDER BY id ASC",
            $product_code_id
        ));
    }
    
    /**
     * Delete all mappings for a product code
     */
    public static function delete_mappings($product_code_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_product_mappings';
        
        return $wpdb->delete($table, array('product_code_id' => $product_code_id), array('%d'));
    }
    }
    
    /**
     * Update product
     */
    public static function update_product($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        $update_data = array();
        $format = array();
        
        if (isset($data['product_name'])) {
            $update_data['product_name'] = sanitize_text_field($data['product_name']);
            $format[] = '%s';
        }
        
        if (isset($data['current_stock'])) {
            $update_data['current_stock'] = (int) $data['current_stock'];
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return true; // Nothing to update
        }
        
        return $wpdb->update($table, $update_data, array('id' => $id), $format, array('%d'));
    }
    
    /**
     * Delete product
     */
    public static function delete_product($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_products';
        
        // Also delete mappings
        self::delete_mappings($id);
        
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }
    
    /**
     * Update stock and log history
     */
    public static function update_stock($product_code_id, $quantity, $type, $comment = '', $order_id = null) {
        global $wpdb;
        
        $products_table = $wpdb->prefix . 'rsm_products';
        $history_table = $wpdb->prefix . 'rsm_stock_history';
        
        // Get current product
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d",
            $product_code_id
        ));
        
        if (!$product) {
            return false;
        }
        
        $stock_before = (int) $product->current_stock;
        
        // Calculate new stock based on type
        if (in_array($type, array('add', 'order_return'))) {
            $stock_after = $stock_before + abs($quantity);
        } else {
            $stock_after = $stock_before - abs($quantity);
        }
        
        // Update product stock
        $wpdb->update(
            $products_table,
            array('current_stock' => $stock_after),
            array('id' => $product_code_id),
            array('%d'),
            array('%d')
        );
        
        // Log history
        $wpdb->insert($history_table, array(
            'product_code_id' => $product_code_id,
            'product_code' => $product->product_code,
            'change_type' => $type,
            'quantity' => abs($quantity),
            'stock_before' => $stock_before,
            'stock_after' => $stock_after,
            'order_id' => $order_id,
            'comment' => sanitize_textarea_field($comment),
            'created_by' => get_current_user_id()
        ), array('%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d'));
        
        return $stock_after;
    }
    
    /**
     * Get stock history
     */
    public static function get_stock_history($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_stock_history';
        
        $defaults = array(
            'product_code_id' => 0,
            'product_code' => '',
            'change_type' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
            'date_from' => '',
            'date_to' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        if ($args['product_code_id']) {
            $where[] = 'product_code_id = %d';
            $values[] = $args['product_code_id'];
        }
        
        if ($args['product_code']) {
            $where[] = 'product_code = %s';
            $values[] = $args['product_code'];
        }
        
        if ($args['change_type']) {
            $where[] = 'change_type = %s';
            $values[] = $args['change_type'];
        }
        
        if ($args['date_from']) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if ($args['date_to']) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        $sql .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get stock history count
     */
    public static function get_stock_history_count($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_stock_history';
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['product_code_id'])) {
            $where[] = 'product_code_id = %d';
            $values[] = $args['product_code_id'];
        }
        
        if (!empty($args['product_code'])) {
            $where[] = 'product_code = %s';
            $values[] = $args['product_code'];
        }
        
        if (!empty($args['change_type'])) {
            $where[] = 'change_type = %s';
            $values[] = $args['change_type'];
        }
        
        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Check if order item is already processed for shipping
     */
    public static function is_shipped_processed($order_id, $order_item_id, $product_code_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_order_tracking';
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT shipped_processed FROM $table 
            WHERE order_id = %d AND order_item_id = %d AND product_code_id = %d",
            $order_id, $order_item_id, $product_code_id
        ));
    }
    
    /**
     * Check if order item is already processed for return
     */
    public static function is_return_processed($order_id, $order_item_id, $product_code_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_order_tracking';
        
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT return_processed FROM $table 
            WHERE order_id = %d AND order_item_id = %d AND product_code_id = %d",
            $order_id, $order_item_id, $product_code_id
        ));
    }
    
    /**
     * Mark order item as shipped
     */
    public static function mark_as_shipped($order_id, $order_item_id, $product_code_id, $product_code, $quantity) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_order_tracking';
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_id = %d AND order_item_id = %d AND product_code_id = %d",
            $order_id, $order_item_id, $product_code_id
        ));
        
        if ($exists) {
            return $wpdb->update(
                $table,
                array('shipped_processed' => 1, 'shipped_at' => current_time('mysql')),
                array('id' => $exists),
                array('%d', '%s'),
                array('%d')
            );
        } else {
            return $wpdb->insert($table, array(
                'order_id' => $order_id,
                'order_item_id' => $order_item_id,
                'product_code_id' => $product_code_id,
                'product_code' => $product_code,
                'quantity' => $quantity,
                'shipped_processed' => 1,
                'shipped_at' => current_time('mysql')
            ), array('%d', '%d', '%d', '%s', '%d', '%d', '%s'));
        }
    }
    
    /**
     * Mark order item as returned
     */
    public static function mark_as_returned($order_id, $order_item_id, $product_code_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_order_tracking';
        
        return $wpdb->update(
            $table,
            array('return_processed' => 1, 'returned_at' => current_time('mysql')),
            array('order_id' => $order_id, 'order_item_id' => $order_item_id, 'product_code_id' => $product_code_id),
            array('%d', '%s'),
            array('%d', '%d', '%d')
        );
    }
    
    /**
     * Get order tracking data
     */
    public static function get_order_tracking($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rsm_order_tracking';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d",
            $order_id
        ));
    }
}
