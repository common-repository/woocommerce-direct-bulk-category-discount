<?php

/*
 * Plugin Name: Woocommerce DBC Discount
 * Plugin URI: http://wordpress.org/plugins/woocommerce-direct-bulk-category-discount/
 * Author: VerticalLogix 
 * Author URI: http://www.verticallogix.com
 * Description: Apply discounts on categories and directly set values to sale price of products.
 * Version: 1.2
 */
 
 if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

require_once('class/class-category-bulk-discount.php');
require_once('class/class-manage-discount.php');
/*
 * This function will add custom settings under Woocommerce Settings > Bulk Category Settings.
 */
 
function add_setting() {

	// Add our custom settings to woocommerce settings tab.
	$updated_settings = array(
		array(
			'title'	 => 'DBC Global Discount',
			'type' 	=> 'title',
			'desc' 	=> 'Settings for a complete shop sale.',
			'id' 	=> 'woocommerce_bulk_category_discount'
		),
                array(
			'name'	=> __( 'Enable Discount For All Products', 'woocommerce' ),
			'desc'  => __( 'This will enable discount on all products.', 'woocommerce' ),
			'id'    => 'woocommerce_bulk_product_discount_enabled',
			'std'   => '', // WooCommerce < 2.0
			'default'  => '', // WooCommerce >= 2.0
			'type'  => 'checkbox',
		),
		array(
			'name'	=> __( 'Type Of Discount', 'woocommerce' ),
			'desc'  => __( 'Select how you want to apply discount on categories.', 'woocommerce' ),
			'id'    => 'woocommerce_bulk_category_discount_type',
			'css'   => 'width:85px;',
			'std'   => '$', // WooCommerce < 2.0
			'default'  => '$', // WooCommerce >= 2.0
			'type'  => 'select',
			'options' => array(
				'$'   => __( 'Amount', 'woocommerce' ),
				'%'  => __( 'Percentage', 'woocommerce' )
			),
			'desc_tip' =>  true,
		),
		array(
			'name'	=> __( 'Value', 'woocommerce' ),
			'desc'  => __( 'Please enter how much discount do you want to apply on categories.', 'woocommerce' ),
			'id'    => 'woocommerce_bulk_category_discount_amount',
			'css'   => 'width:150px;',
			'std'   => '', // WooCommerce < 2.0
			'default'  => '', // WooCommerce >= 2.0
			'type'  => 'text',
			'desc_tip' =>  true,
		),
		
		/* array(
			'name'	=> __( 'Enable coupons', 'woocommerce' ),
			'desc'  => __( 'Enable use of coupons on disocunted products.', 'woocommerce' ),
			'id'    => 'woocommerce_bulk_coupons_enabled',
			'std'   => '', // WooCommerce < 2.0
			'default'  => '', // WooCommerce >= 2.0
			'type'  => 'checkbox',
		), */
		array( 'type' => 'sectionend', 'id' => 'woocommerce_bulk_category_discount' )
	);	
	return $updated_settings;
}

/*
 * This function will add custom settings under Woocommerce Settings > Bulk Category Settings.
 */
add_action( 'woocommerce_settings_tabs_woocommerce_bulk_category_discount', 'settings_tab' );
 
function settings_tab() {
    woocommerce_admin_fields(add_setting());
}


/*
 * This function will save custom settings under Woocommerce Settings > Bulk Category Settings.
 */
add_action( 'woocommerce_update_options_woocommerce_bulk_category_discount', 'update_settings' );
 
function update_settings() {
    
	global $wpdb;
	
	// Save the settings
	woocommerce_update_options(add_setting());
	
	// Based on the saved settings, lets update discounted price of all products
	if(isset($_POST['woocommerce_bulk_product_discount_enabled']) && !empty($_POST['woocommerce_bulk_product_discount_enabled'])) {
		
		if($_POST['woocommerce_bulk_product_discount_enabled'] == '1') {
			
			// Get price of all the products
			$strAllPro = 'SELECT post_id, meta_value FROM ' . $wpdb->prefix . 'postmeta WHERE meta_key = "_regular_price"';
			$arrAllPro = $wpdb->get_results($strAllPro);
			
			// Calculate discount & update price of all the products
			foreach($arrAllPro AS $key => $arrValue) {
				
				if(empty($arrValue->meta_value)) {
					continue;
				}
				if($_POST['woocommerce_bulk_category_discount_type'] == '%') {
					// Calculate discount
					$fltDiscPrice = $arrValue->meta_value - ($arrValue->meta_value * $_POST['woocommerce_bulk_category_discount_amount'] /100);
				}
				else {
					// Calculate discount
					$fltDiscPrice = $arrValue->meta_value - $_POST['woocommerce_bulk_category_discount_amount'];
				}
				// Update the prices
				update_post_meta($arrValue->post_id, '_sale_price', $fltDiscPrice);
				update_post_meta($arrValue->post_id, '_price', $fltDiscPrice);
			}
		}
	}	
}

/*
 * This function will start installation process of the plugin, when plugin is installed & activated.
 */
register_activation_hook( __FILE__, 'woocommerce_category_discount_activate' );

function woocommerce_category_discount_activate() {
	
	global $wpdb;
	
	// Check if required tables already exist in database
	$table_name = $wpdb->prefix . "categorymeta";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		
		// If table does not exist, then lets create one
		$strCreateTable = "CREATE TABLE $table_name (
						 meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						 post_id bigint(20) unsigned NOT NULL DEFAULT '0',
						 meta_key varchar(255) DEFAULT NULL,
						 meta_value longtext,
						 PRIMARY KEY (meta_id),
						 KEY post_id (post_id),
						 KEY meta_key (meta_key)
						)ENGINE=InnoDB DEFAULT CHARSET=utf8;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta($strCreateTable);
	}
}

/*
 * This function will start installation process of the plugin, when plugin is installed & activated.
 */
register_deactivation_hook( __FILE__, 'woocommerce_category_discount_deactivate' );

function woocommerce_category_discount_deactivate() {
	
	global $wpdb;
	
	// Check if required tables already exist in database
	$table_name = $wpdb->prefix . "categorymeta";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
		
		// If table does not exist, then lets create one
		$strDropTable = "DROP TABLE `" . $wpdb->prefix . "categorymeta`";
		$wpdb->get_results($strDropTable);
	}
}

/*
 * This function will display discount on the cart on each product.
 */
 
add_filter('woocommerce_cart_item_price', 'show_discounted_price', 10, 2);
 
function show_discounted_price($a, $product) {

	global $woocommerce;

	// Get the prices details
	$productId = (isset($product['variation_id']) && !empty($product['variation_id']))?$product['variation_id']:$product['product_id'];
	$sale_price = get_post_meta($productId, '_sale_price');
	$regular_price = get_post_meta($productId, '_regular_price');

	// If the product is on sale, then show details of discount
	if(!empty($sale_price[0]) && $regular_price[0] - $sale_price[0] > 0) {
		return woocommerce_price($sale_price[0]) . '<span style="color:#00a0d6;font-size:11px;"><br/>( Incl. discount of ' . woocommerce_price($regular_price[0] - $sale_price[0]) . ')</span>';
	}
	// If its normal product then show normal price
	else {
		return woocommerce_price($regular_price[0]);
	}

}

/*
 * This function will check if coupon can be used or not based on selection.
 */
 add_filter('woocommerce_coupons_enabled', 'check_coupon_active', 10);
 
 function check_coupon_active() {
 
	global $wpdb, $woocommerce;
 
	// Get the option saved in admin
	$isCouponEnabled = get_option('woocommerce_bulk_coupons_enabled');
 
	if($isCouponEnabled == 'yes') {
		return true;
	}
	else {
		
		// Get the categories which have active discounts
		$strCategory = 'SELECT post_id FROM ' . $wpdb->prefix . 'categorymeta WHERE meta_key = "bulk_cat_discount_amount" AND (meta_value <> "" AND meta_value <> 0)';
		$arrGetCategory = $wpdb->get_results($strCategory);
		$arrCategoryId = array();
		
		// Create array of categories
		foreach($arrGetCategory AS $key => $post) {
			$arrCategoryId[] = $post->post_id;
		}
		$strCategoryIds = implode(',', $arrCategoryId);
		
		// Get the products from category
		$strProducts = 'SELECT tr.object_id FROM ' . $wpdb->prefix . 'term_relationships tr, ' . $wpdb->prefix . 'term_taxonomy tt WHERE tt.term_id IN (' . $strCategoryIds . ') AND tt.term_taxonomy_id = tr.term_taxonomy_id';
		$arrProducts = $wpdb->get_results($strProducts);
		
		// Create array of discounted products
		$arrDiscountedPro = array();
		foreach($arrProducts AS $key => $post) {
			$arrDiscountedPro[] = $post->object_id;
		}
		
		foreach($woocommerce->cart->cart_contents AS $key => $arrVal) {
			
			// If there is any product which has discount
			if(in_array($arrVal['product_id'], $arrDiscountedPro)) {
				return false;
			}
		}
		return true;
	}
 }

WC_Settings_Category_Bulk_Discount::init();

?>