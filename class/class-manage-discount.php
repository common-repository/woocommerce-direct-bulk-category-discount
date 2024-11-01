<?php
 
 if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/*
 * This function will add Category listing page to Woocommerce Menu.
 */
add_action('admin_menu', 'register_my_custom_submenu_page');

function register_my_custom_submenu_page() {
    add_submenu_page( 'woocommerce', 'DBC Discount', 'DBC Discount', 'manage_options', 'woocommerce_category_bulk_discount', 'list_category_bulk_discount'); 
}

/* 
 * This function will list the categories and the options selected for them.
 */
function list_category_bulk_discount() {

	global $wpdb;

	$strCategoryListing = "SELECT t.term_id, t.name FROM " . $wpdb->prefix . "term_taxonomy tt, " . $wpdb->prefix . "terms t  
		WHERE tt.taxonomy = 'product_cat' 
		AND tt.term_id = t.term_id";
		
	$arrCatList = $wpdb->get_results($strCategoryListing);
	
	echo '<h3>DBC Discount</h3>
	<form method="POST">
		<table class="wp-list-table widefat posts" style="width:99%">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Category Name</span></th>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Type Of Discount</span></th>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Value</span></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Category Name</span></th>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Type Of Discount</span></th>
					<th scope="col" class="manage-column column-thumb" style=""><span class="wc-image tips">Value</span></th>	
				</tr>
			</tfoot>';
		
	if(isset($arrCatList) && !empty($arrCatList) && is_array($arrCatList)) {
		
		// Lets get the category details
		$strCatIds = '';			
		foreach($arrCatList AS $key => $arrTemp) {
			$strCatIds .= $arrTemp->term_id . ',';
		}
		$strCatIds = substr($strCatIds, 0, -1);
		$strCatDetail = 'SELECT * FROM ' . $wpdb->prefix . 'categorymeta WHERE post_id IN (' . $strCatIds . ')';
		$arrCatDetail = $wpdb->get_results($strCatDetail);
		foreach($arrCatDetail AS $key => $arrInner) {
			$arrCategoryDetails[$arrInner->post_id][$arrInner->meta_key] = $arrInner->meta_value;
		}
		
		// Display category details
		foreach($arrCatList AS $key => $arrVal) {
			
			$amount = $arrCategoryDetails[$arrVal->term_id]["bulk_cat_discount_amount"];
			
			echo '<tr class="' . ($key % 2 == 0? 'alternate':'') . '">
				<td>' . $arrVal->name . '</td>
				<td>
					<select name="sel' . $arrVal->term_id . '" id="sel' . $arrVal->term_id . '">
						<option value="">Please select</option>
						<option value="A"' . (isset($arrCategoryDetails[$arrVal->term_id]["bulk_cat_discount_type"]) && $arrCategoryDetails[$arrVal->term_id]["bulk_cat_discount_type"] == "A"? ' selected':'') . '>Amount</option>
						<option value="P"' . (isset($arrCategoryDetails[$arrVal->term_id]["bulk_cat_discount_type"]) && $arrCategoryDetails[$arrVal->term_id]["bulk_cat_discount_type"] == "P"? ' selected':'') . '>Percentage</option>
					</select>
				</td>
				<td>
					<input type="text" name="txtDiscountAmt' . $arrVal->term_id . '" id="txtDiscountAmt' . $arrVal->term_id . '" size="10" maxlength="6" value="' . (isset($amount) && !empty($amount)? $amount: '') . '" placeholder="Example: 10">
				</td>
			</tr>';
		}
		echo '</tbody>
		</table>
		<br/>
		<input type="submit" class="button button-primary" value="Save"/>
		</form>';
	}
	else {
		echo '<tr class="no-items"><td class="colspanchange" colspan="3">No Categories found</td></tr></tbody>';
	}
}

// If page is submitted, then save the category data
if(isset($_POST) && !empty($_POST)) {
	save_category_data();
}

function save_category_data() {
	
	global $wpdb;
	
	// Lets get the submitted data
	$arrCatData = $_POST;
	
	// Lets check if flat discount feature is on
	$isFlatDiscountOn = get_option('woocommerce_bulk_product_discount_enabled');
	
	// If flat discount is off then we will add category discount
	if($isFlatDiscountOn == 'no') {
		foreach($arrCatData AS $key => $val) {
			
			// Lets consider each row
			if(substr($key, 0, 3) == 'sel') {
				
				// Get the category id
				$catId = substr($key, 3, strlen($key));
				update_categorymeta($catId, 'bulk_cat_discount_amount', $arrCatData['txtDiscountAmt' . $catId]);
				update_categorymeta($catId, 'bulk_cat_discount_type', $arrCatData['sel' . $catId]);
				
				// Get the products from category
				$strProducts = 'SELECT tr.object_id, p.meta_value FROM ' . $wpdb->prefix . 'term_relationships tr, ' . $wpdb->prefix . 'postmeta p, ' . $wpdb->prefix . 'term_taxonomy tt WHERE tt.term_id = "' . $catId . '" AND tr.object_id = p.post_id AND p.meta_key = "_regular_price" AND tt.term_taxonomy_id = tr.term_taxonomy_id';
				$arrProducts = $wpdb->get_results($strProducts);
				
				$strBulkProductId = '';
				foreach($arrProducts AS $keyInner => $strProductId) {
					$arrSalePrice[$strProductId->object_id] = $strProductId->meta_value;
					$fltDiscountedPrice = '';
					if($arrCatData['sel' . $catId] == 'P') {
						// Calculate discounted amount
						$fltDiscountedPrice = '';
						if(!empty($strProductId->meta_value)) {
							$fltDiscountedPrice = $strProductId->meta_value - ($strProductId->meta_value * $arrCatData['txtDiscountAmt' . $catId] / 100);
						}					
					}
					else if($arrCatData['sel' . $catId] == 'A') {
						// Calculate discounted amount
						$fltDiscountedPrice = '';
						if(!empty($strProductId->meta_value)) {
							$fltDiscountedPrice = $strProductId->meta_value - $arrCatData['txtDiscountAmt' . $catId];
						}
					}
					else {
						$fltDiscountedPrice = $strProductId->meta_value;
					}
					update_post_meta($strProductId->object_id, '_sale_price', $fltDiscountedPrice);
					update_post_meta($strProductId->object_id, '_price', $fltDiscountedPrice);
					
					$objProdVar = get_post($strProductId->object_id);
					$strProVariation = 'SELECT ID post_id FROM ' . $wpdb->prefix . 'posts WHERE post_type="product_variation" AND post_parent = "' . $strProductId->object_id . '"';
					$objProductVariation = $wpdb->get_results($strProVariation);
					
					if(!empty($objProductVariation)) {
						foreach($objProductVariation AS $key => $variation) {
							$regularPrice = get_post_meta($variation->post_id, '_regular_price');
							$fltDiscountedPrice = '';
							if($arrCatData['sel' . $catId] == 'P') {
								// Calculate discounted amount
								$fltDiscountedPrice = '';
								if(!empty($regularPrice) && !empty($arrCatData['txtDiscountAmt' . $catId])) {
									$fltDiscountedPrice = $regularPrice[0] - $regularPrice[0] * $arrCatData['txtDiscountAmt' . $catId] / 100;
								}	
								else {
									$fltDiscountedPrice = $regularPrice[0];
								}							
							}
							else if($arrCatData['sel' . $catId] == 'A') {
								// Calculate discounted amount
								$fltDiscountedPrice = '';
								if(!empty($regularPrice) && !empty($arrCatData['txtDiscountAmt' . $catId])) {
									$fltDiscountedPrice = $regularPrice[0] - $arrCatData['txtDiscountAmt' . $catId];
								}
								else {
									$fltDiscountedPrice = $regularPrice[0];
								}			
							}
							else {
								$fltDiscountedPrice = $regularPrice[0];
							}
							if($fltDiscountedPrice != $regularPrice[0]) {
								update_post_meta($variation->post_id, '_sale_price', $fltDiscountedPrice);
							}
							else {
								update_post_meta($variation->post_id, '_sale_price', '');
							}
							update_post_meta($variation->post_id, '_price', $fltDiscountedPrice);
						}
					}
				}
			}
		}
	}
}

/*
 * This function will save category meta data.
 */
function update_categorymeta($catId, $meta_key, $meta_val) {
	
	global $wpdb;
	
	// Update category meta data.
	$strUpdate = 'UPDATE ' . $wpdb->prefix . 'categorymeta SET meta_value = \'' . $meta_val . "'
				WHERE post_id = '" . $catId . "' AND meta_key = '" . $meta_key . "'";
	$wpdb->get_results($strUpdate);
	$intRows = $wpdb->rows_affected;
	
	// Check if there is already a value in DB
	$strCheck = 'SELECT * FROM ' . $wpdb->prefix . "categorymeta WHERE post_id = '" . $catId . "' AND meta_key = '" . $meta_key . "'";
	$arrCheck = $wpdb->get_results($strCheck);
	
	// If not then insert meta_key & meta_value for category
	if($intRows == 0 && empty($arrCheck)) {
		$strInsert = 'INSERT INTO ' . $wpdb->prefix . 'categorymeta (post_id, meta_key, meta_value)
			VALUES ("' . $catId . '" , "' . $meta_key . '", "' . $meta_val . '")';
		$wpdb->get_results($strInsert);
	}
}
?>