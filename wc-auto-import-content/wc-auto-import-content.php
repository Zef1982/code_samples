<?php
/**
 * @package WC_auto_import_content
 * @version 1.0.0
 */
/*
Plugin Name: WC auto import content
Description: This plugin automatically inserts product description, adds attributes, variations, SKU's and tags, and orders the gallery images by filename alphabetically for specific product on save, using category-based XML files.
Version: 1.0.0
Author: ZEF
*/

add_filter( 'woocommerce_before_product_object_save', 'import_product_content', 10, 2);

/**
 * Function to attach product content for specific products.
 * 
 * @param object $product
 * @param array $data
 * 
 * @return void
 */
function import_product_content( $product, $data ){
    error_log('import_product_content');
    // 300 seconds = 5 minutes.
    ini_set( 'max_execution_time', '300' ); 
    
    // Look category ID's  up in WP terms to see if category slug matches $product type!
    $category_ids = $product->get_category_ids();

    foreach ( $category_ids as $category_id) {

        // Get the term slug for this category.
        $term_slug = get_term( $category_id )->slug;

        // Match the slug with content XML file.
        $product_content_xml_file = dirname(__FILE__) . '/product-content/' . $term_slug . '.xml';
        $product_content_array = get_array_from_xml($product_content_xml_file);
        
        // Automatically add product description.
        if(is_array($product_content_array) && isset($product_content_array['description'])) {
            add_product_description_block_by_slug($product, $product_content_array['description']);
        }

        add_product_attributes_by_category($product->get_id(), $term_slug);

        $variations = $product->get_available_variations();

        // We don't need new variations every time!
        if(empty($variations)){
            add_product_variations($product, $product_content_array);
        }

        add_product_variation_skus($product, $variations, $term_slug);

        // Add tags to the product.
        wp_set_object_terms($product->get_id(), explode('|', $product_content_array['keywords']) , 'product_tag', true);

        // We do all of this only for 1 category!
        break;
            
    }

    sort_product_gallery($product);

    return $poduct;
}

/**
 * Save the product gallery in alphabetical order of filename.
 * 
 * @param object $product passed by reference, because we override the gallery!
 * 
 * @return void
 */
function sort_product_gallery( &$product ) {
    
    // Get product images from the edit form!
    if(isset($_POST['product_image_gallery']) && !empty($_POST['product_image_gallery'])){
        $attachment_ids = explode(',', $_POST['product_image_gallery']);
    }else{
        return;
    }

    // Don't do anything if no images are attached yet!
    if(empty($attachment_ids)){
        return;
    }

    // Get product image links.
    $image_links = [];
    foreach( $attachment_ids as $attachment_id ) {
        $img_link = wp_get_attachment_url( $attachment_id );
        // Avoid double images!
        if(!in_array($img_link, $image_links)){
            $image_links[$attachment_id] = $img_link;
        }
    }
    
    // Sort product image links naturally.
    asort($image_links, SORT_NATURAL);
    // Save the new order of the attachment IDs.
    $image_ids = [];
    foreach($image_links as $attachment_id => $img_link){
        $image_ids[] = $attachment_id;
    }
    // Save the new order to the product POST  variable.
    $product->set_gallery_image_ids($image_ids);
}

/**
 * Add SKU's to all product variations, based on SKU base which is unique per product,
 * combined with product SKU start and COlor & Size SKU segments from XML file.
 * 
 * @param object $product
 * @param array $variations
 * @param string $term_slug
 * 
 * @return void
 */
function add_product_variation_skus($product, $variations, $term_slug){
    // Match the slug with content XML file.
    $product_sku_xml_file = dirname(__FILE__) . '/product-sku/' . $term_slug . '.xml';
    $product_sku_array = get_array_from_xml($product_sku_xml_file);
    $product_sku = $product->get_sku();
    // Loop through all variations.
    foreach ( $variations as $variation ) {

    // If SKU base is present, add it for the variations.
        if(strlen($product_sku) == 9 ) {

            // Check if current variation matches Color & Size from the XML.
            foreach($product_sku_array['colors']['color'] as $color) {
                foreach($product_sku_array['sizes']['size'] as $size) {

                    $attribute_color = $variation['attributes']['attribute_color'];
                    $attribute_size = $variation['attributes']['attribute_size'];
                    if($attribute_color == $color['name'] && $attribute_size == $size['name']){

                        // We have a match! Add SKU start and Color & Size SKU tot the product SKU base.
                        $sku = $product_sku . '-' . $color['sku'] . '-' . $size['sku'];
                        check_add_post_meta('_sku', $variation['variation_id'], $sku);
                    }
                }
            }
        }
    }
}

/**
 * Check if prost meta is present before adding it!
 * 
 * @param string $meta_key
 * @param int $product_id
 * @param mixed $value
 * 
 * @return void
 */
function check_add_post_meta($meta_key, $product_id, $value) {
    $post_meta = get_post_meta($product_id, $meta_key, $value, true);
    if(empty($post_meta)){
        add_post_meta($product_id, $meta_key, $value, true);
    }
}

/**
 * Transform XML structure into associative array.
 * 
 * @param string $xml_file path to XML file
 * 
 * @return array
 */
function get_array_from_xml($xml_file){

    if (file_exists($xml_file)) {

        $xml = simplexml_load_string(file_get_contents($xml_file), "SimpleXMLElement", LIBXML_NOCDATA);
        $xml_json = json_encode($xml);
        $xml_array = json_decode($xml_json, TRUE);

        return $xml_array;
    }
}

/**
 * Dynamically add pattern block to product description, 
 * based on slug in XML which is matched by product category.
 * 
 * @param object $product passed by reference, because we override the product description!
 * @param string $description_post_slug
 * 
 * @return void
 */
function add_product_description_block_by_slug(&$product, $description_post_slug) {
           
    // Get pattern block ID by pattern block post slug from XML.
    $pattern_block = get_page_by_path( $description_post_slug, OBJECT, 'wp_block' );
    if(!empty($pattern_block)){
        $pattern_block_id = $pattern_block->ID;

        // Check if the ID is present in the product description.
        $product_description = $product->get_description();
        $new_product_description = '<!-- wp:block {"ref":' . $pattern_block_id . '} /-->';

        // Check if pattern block is present in current description.
        if( !preg_match('#' . $new_product_description . '#', $product_description)){
            
            // Add pattern block to the post.
            $product->set_description($product_description . $new_product_description);
        }
    }
}

/**
 * Dynamically add product attributes, based on XML content which is matched by product category.
 * 
 * @param int $product_id
 * @param string $term_slug category slug to match XML file with
 * 
 * @return void
 */
function add_product_attributes_by_category($product_id, $term_slug) {
    // Match the slug with product variations file.
    $product_attributes_xml_file = dirname(__FILE__) . '/product-attributes/' . $term_slug . '.xml';
    $product_attributes_array = get_array_from_xml($product_attributes_xml_file);
    
    // Add attributes to product.
    if(is_array($product_attributes_array)){
        check_add_post_meta('_product_attributes', $product_id, $product_attributes_array);
    }

}

/**
 * Dynamically add product variations with price, 
 * based on XML content and earlier added product attributes.
 * 
 * @param object $product
 * @param array product_content_array
 * 
 * @return void
 */
function add_product_variations($product, $product_content_array) {

    // Create post for every combination of the attributes from the XML-file.
    $data_store = $product->get_data_store();

    // Set variation properties.
    if(isset($product_content_array['price']) && isset($product_content_array['regular_price'])) {
        $default_values = Array(
            "price" => $product_content_array['price'],
            "regular_price" => $product_content_array['regular_price']
        );
    }
    
    $data_store->create_all_product_variations( $product, -1, $default_values);
}