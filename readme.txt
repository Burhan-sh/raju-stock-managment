=== Raju Stock Management ===
Contributors: rajuplastics
Tags: woocommerce, stock, inventory, product codes, stock management
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom stock management system with product code mapping to WooCommerce variations.

== Description ==

Raju Stock Management is a custom inventory management plugin for WooCommerce. It allows you to:

* Create custom product codes (e.g., S3001, S3002)
* Map product codes to WooCommerce products or variations
* Manually add or remove stock with comments
* Automatic stock reduction when order status changes to "Process to Ship"
* Automatic stock restoration when order status changes to "Return XL"
* Complete stock history with date, quantity, order ID, and comments
* Prevents duplicate stock operations for the same order

== Features ==

**Product Code Management:**
* Add unique product codes
* Map codes to WooCommerce products or variations
* View current stock levels

**Stock Operations:**
* Manually add stock with comment
* Manually remove stock with comment
* View stock change history

**Automatic Stock Management:**
* Stock reduces when order moves to "Process to Ship" status
* Stock restores when order moves to "Return XL" status
* Prevents double-processing of orders

**Stock History:**
* Complete log of all stock changes
* Filter by product code, date range, change type
* View order links for order-related changes
* User tracking for manual changes

**Screen Options (v2.1.0+):**
* Column visibility toggle (Product Code, Product Name, Mapping, Stock, Actions)
* View modes: List, Compact, Card view
* Pagination settings

**Sorting (v2.1.0+):**
* Sort by Product Code, Product Name, or Stock
* Ascending/Descending order

**Print Stock Report (v2.1.0+):**
* Print preview with attractive layout
* Shows all product codes with quantities
* Total stock quantity summary

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to "Stock Management" in the admin menu
4. Add your product codes and map them to WooCommerce products

== Changelog ==

= 2.1.0 =
* Added column visibility options in Screen Options
* Added View Mode options (List, Compact, Card views)
* Added sorting functionality to all columns
* Added Print Stock feature with print preview
* UI improvements and optimizations

= 2.0.1 =
* Bug fixes and improvements

= 1.0.0 =
* Initial release

== Hindi / हिंदी ==

यह plugin WooCommerce के लिए custom stock management system है।

**कैसे काम करता है:**

1. Plugin Menu में Product Code add करें (जैसे S3001)
2. WooCommerce Product या Variation से map करें
3. Stock quantity add करें

**Automatic Stock Management:**
* जब Order का status "Process to Ship" हो → Stock minus होगा
* जब Order का status "Return XL" हो → Stock वापस add होगा
* एक order duplicate process नहीं होगा

**Stock History:**
* सभी stock changes का record with date और comment
* Filter by product code, date, type
