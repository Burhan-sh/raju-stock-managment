<?php
/**
 * Order hooks for automatic stock management
 * 
 * @package Raju_Stock_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSM_Order_Hooks Class
 * 
 * Handles automatic stock changes when order status changes
 */
class RSM_Order_Hooks {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook for Process to Ship status
        add_action('woocommerce_order_status_process-to-ship', array($this, 'handle_process_to_ship'), 10, 2);
        
        // Hook for Return XL status
        add_action('woocommerce_order_status_return-xl', array($this, 'handle_return_xl'), 10, 2);
        
        // Generic status change hook (for any to process-to-ship transition)
        add_action('woocommerce_order_status_changed', array($this, 'handle_status_change'), 10, 4);
    }
    
    /**
     * Handle Process to Ship status
     * Stock will be reduced when order moves to this status
     * 
     * @param int $order_id
     * @param WC_Order $order
     */
    public function handle_process_to_ship($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            return;
        }
        
        $this->reduce_stock_for_order($order);
    }
    
    /**
     * Handle Return XL status
     * Stock will be added back when order moves to this status
     * 
     * @param int $order_id
     * @param WC_Order $order
     */
    public function handle_return_xl($order_id, $order = null) {
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            return;
        }
        
        $this->add_stock_for_return($order);
    }
    
    /**
     * Handle generic status change
     * 
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function handle_status_change($order_id, $old_status, $new_status, $order) {
        // Process to ship
        if ($new_status === 'process-to-ship') {
            $this->reduce_stock_for_order($order);
        }
        
        // Return XL
        if ($new_status === 'return-xl') {
            $this->add_stock_for_return($order);
        }
    }
    
    /**
     * Reduce stock when order is shipped
     * 
     * @param WC_Order $order
     */
    private function reduce_stock_for_order($order) {
        $order_id = $order->get_id();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            // Find matching product code
            $rsm_product = null;
            
            if ($variation_id) {
                $rsm_product = RSM_Database::get_product_by_variation_id($variation_id);
            }
            
            if (!$rsm_product && $product_id) {
                global $wpdb;
                $table = $wpdb->prefix . 'rsm_products';
                $rsm_product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d AND variation_id = 0",
                    $product_id
                ));
            }
            
            // If no mapping found, skip this item
            if (!$rsm_product) {
                continue;
            }
            
            // Check if already processed for shipping
            if (RSM_Database::is_shipped_processed($order_id, $item_id, $rsm_product->id)) {
                continue; // Already processed, skip
            }
            
            // Reduce stock
            $comment = sprintf(
                __('Order #%d shipped - %s x %d', 'raju-stock-management'),
                $order_id,
                $item->get_name(),
                $quantity
            );
            
            RSM_Database::update_stock($rsm_product->id, $quantity, 'order_minus', $comment, $order_id);
            
            // Mark as shipped
            RSM_Database::mark_as_shipped($order_id, $item_id, $rsm_product->id, $rsm_product->product_code, $quantity);
            
            // Add order note
            $order->add_order_note(sprintf(
                __('RSM: Stock reduced for %s (Code: %s) by %d', 'raju-stock-management'),
                $item->get_name(),
                $rsm_product->product_code,
                $quantity
            ));
        }
    }
    
    /**
     * Add stock back when order is returned
     * 
     * @param WC_Order $order
     */
    private function add_stock_for_return($order) {
        $order_id = $order->get_id();
        
        // Get tracking data to see what was shipped
        $tracking_data = RSM_Database::get_order_tracking($order_id);
        
        if (empty($tracking_data)) {
            // No tracking data, try to process based on order items
            $this->add_stock_from_items($order);
            return;
        }
        
        foreach ($tracking_data as $tracking) {
            // Check if already processed for return
            if ($tracking->return_processed) {
                continue; // Already returned, skip
            }
            
            // Check if was shipped first
            if (!$tracking->shipped_processed) {
                continue; // Never shipped, can't return
            }
            
            // Add stock back
            $comment = sprintf(
                __('Order #%d returned - Code: %s x %d', 'raju-stock-management'),
                $order_id,
                $tracking->product_code,
                $tracking->quantity
            );
            
            RSM_Database::update_stock($tracking->product_code_id, $tracking->quantity, 'order_return', $comment, $order_id);
            
            // Mark as returned
            RSM_Database::mark_as_returned($order_id, $tracking->order_item_id, $tracking->product_code_id);
            
            // Add order note
            $order->add_order_note(sprintf(
                __('RSM: Stock restored for Code: %s by %d', 'raju-stock-management'),
                $tracking->product_code,
                $tracking->quantity
            ));
        }
    }
    
    /**
     * Add stock from order items (fallback if no tracking data)
     * 
     * @param WC_Order $order
     */
    private function add_stock_from_items($order) {
        $order_id = $order->get_id();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            // Find matching product code
            $rsm_product = null;
            
            if ($variation_id) {
                $rsm_product = RSM_Database::get_product_by_variation_id($variation_id);
            }
            
            if (!$rsm_product && $product_id) {
                global $wpdb;
                $table = $wpdb->prefix . 'rsm_products';
                $rsm_product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE product_id = %d AND variation_id = 0",
                    $product_id
                ));
            }
            
            if (!$rsm_product) {
                continue;
            }
            
            // Check if already processed for return
            if (RSM_Database::is_return_processed($order_id, $item_id, $rsm_product->id)) {
                continue;
            }
            
            // Check if was shipped first (must be shipped before return)
            if (!RSM_Database::is_shipped_processed($order_id, $item_id, $rsm_product->id)) {
                continue; // Never shipped, can't return
            }
            
            // Add stock back
            $comment = sprintf(
                __('Order #%d returned - %s x %d', 'raju-stock-management'),
                $order_id,
                $item->get_name(),
                $quantity
            );
            
            RSM_Database::update_stock($rsm_product->id, $quantity, 'order_return', $comment, $order_id);
            
            // Mark as returned
            RSM_Database::mark_as_returned($order_id, $item_id, $rsm_product->id);
            
            $order->add_order_note(sprintf(
                __('RSM: Stock restored for %s (Code: %s) by %d', 'raju-stock-management'),
                $item->get_name(),
                $rsm_product->product_code,
                $quantity
            ));
        }
    }
}
