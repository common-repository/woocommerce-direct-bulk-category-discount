<?php

class WC_Settings_Category_Bulk_Discount {
 
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
    }
 
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['woocommerce_bulk_category_discount'] = __( 'DBC Global Discount', 'woocommerce_bulk_category_discount' );
				
        return $settings_tabs;
    }
}

?>