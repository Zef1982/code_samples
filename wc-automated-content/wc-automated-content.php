<?php
/**
 * @package WC_automated_content
 * @version 1.0.0
 */
/*
Plugin Name: WC automated content
Description: This plugin automatically insert product content for specific product.
Version: 1.0.0
Author: ZEF
*/

add_action( 'woocommerce_new_product', 'attach_product_content', 10, 2);
add_action( 'woocommerce_update_product', 'attach_product_content', 10, 2);

/**
 * Function to attach product content for specific products.
 * 
 * @param $product_id
 * @param $product
 * 
 * @return void
 */
function attach_product_content( $product_id, $product ){

    // Track changes.
    $changes = FALSE;

    // Look category ID's  up in WP terms to see if category slug matches $product type!
    $category_ids = $product->get_category_ids();
    foreach ( $category_ids as $category_id) {

        // Get the term slug for this category.
        $term_slug = get_term( $category_id )->slug;

        // Match the slug with an existing XML file.
        $xml_file = dirname(__FILE__) . '/product-content/' . $term_slug . '.xml';
        
        if (file_exists($xml_file)) {

            // Get data into array.
            $xml = simplexml_load_string(file_get_contents($xml_file), "SimpleXMLElement", LIBXML_NOCDATA);
            $xml_json = json_encode($xml);
            $xml_array = json_decode($xml_json, TRUE);

            // Automatically add product description.
            if(isset($xml_array['description'])) {
                // Get pattern block ID by pattern block post slug from XML.
                $description_post_slug = $xml_array['description'];
                $pattern_block = get_page_by_path( $description_post_slug, OBJECT, 'wp_block' );
                $pattern_block_id = $pattern_block->ID;

                // Check if the ID is present in the product description.
                $product_description = $product->get_description();
                $new_product_description = '<!-- wp:block {"ref":' . $pattern_block_id . '} /-->';
                if( !preg_match('#' . $new_product_description . '#', $product_description)){
                    
                    // Add pattern block to the post.
                    $product->set_description($new_product_description);
                    $product->save();
                    return;
                }

            }
            
        }
    }

}