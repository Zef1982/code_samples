<?php
/**
 * @package WC_product_group_structured_data
 * @version 1.0.0
 */
/*
Plugin Name: WC product group structured data
Description: This plugin returns the product group structured data for a product and all of it's variations.
Version: 1.0.0
Author: ZEF
*/

add_action( 'wp_head', function() {
    if ( function_exists('is_product') && is_product() ) {
		
        global $product;
		
        if ( ! $product ) return;

        echo '<script type="application/ld+json">' . wp_json_encode( get_product_group_schema( $product ) ) . '</script>';
    }
});

// Remove the default structured data form WooCommerce!
add_filter( 'woocommerce_structured_data_product', 'structured_data_product_nulled', 10, 2 );
function structured_data_product_nulled( $markup, $product ){
    if( is_product() ) {
        $markup = [];
    }
    return $markup;
}

/**
 * Get the product group schema.
 * 
 * @param object $product
 * 
 * @return array productGroup
 * 
 */
function get_product_group_schema ( $product ) {
	
	$product_variations = get_product_variations_schema( $product );

	$group_schema = [
		'@context' => 'https://schema.org',
		'@type'    => 'productGroup',
		'name'     => $product->get_name(),
		'image'    => wp_get_attachment_url( $product->get_image_id() ),
		'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
		'url'           => get_permalink( $product->get_id() ),
		'keywords' => get_comma_seperated_product_keywords ( $product ),
		'numberOfItems' => count($product_variations),
		'hasVariant' => $product_variations
	];
	
	return $group_schema;
}

/**
 * Get comma seperated keywords.
 * 
 * @param object $product
 * 
 * @return string 
 * 
 */
function get_comma_seperated_product_keywords ( $product ) {
	$keywords = [];
	$terms = get_the_terms($product->get_id(), 'product_tag' );
	foreach($terms as $term){
		$keywords[] = $term->name;
	}
	return implode(',', $keywords);
}

/**
 * Get structured data for all product variations.
 * 
 * @param object $product
 * 
 * @return array of structured data products 
 * 
 */
function get_product_variations_schema ( $product ) {
	
	$variations = $product->get_available_variations();
	$image_urls = get_gallery_image_urls( $product );
	
	$product_variations = [];
	foreach ($variations as $variation){

		// Simplify attribute vars.
		$color = $variation['attributes']['attribute_color'];
		$size = $variation['attributes']['attribute_size'];

		// Get image url of color variation from the gallery.
		$image = match_image_with_color( $color, $image_urls );

		$product_variations[] = [
			'@type'    => 'Product',
			'name'	=> $product->get_name() . ' ' . $color . ' ' . $size,
			'image'    => $image,
			'color'		=> $color,
			'size'		=> $size,
			'offers'   => [
				'@type'         => 'Offer',
				'priceCurrency' => get_woocommerce_currency(),
				'price'         => $variation['display_price'],
				'sku'		=> $variation['sku'],
				'availability'  => $variation['is_in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'seller' => [
					'@type' => 'Organization',
					'name' => get_bloginfo( 'name' ),
					'url' => get_site_url(),
					'logo' => get_site_url() . '/wp-content/uploads/2026/03/funkyBirdLogo.webp'
				]
			],
		];
	}
	return $product_variations;
}

/**
 * Get url which matches with the given color.
 * 
 * @param string $color
 * @param array $image_urls
 * 
 * @return string matching image url
 * 
 */
function match_image_with_color ( $color, $image_urls ){
	$underscored_lower_case_color = str_replace(" ", "_", strtolower($color));
	foreach($image_urls as $image_url) {
		$color_matches_image_url = is_numeric(strpos($image_url, $underscored_lower_case_color));
		if($color_matches_image_url) {
			return $image_url;
		}
	}
}

/**
 * Get all gallery images of the product.
 * 
 * @param object $product
 * 
 * @return array of image urls 
 * 
 */
function get_gallery_image_urls( $product ) {
	// Add defaultproduct image.
	$image_urls = [ wp_get_attachment_url( $product->get_image_id() ) ];
	// Add all product gallery images.
	$image_ids = $product->get_gallery_image_ids();
	foreach($image_ids as $image_id) {
		$image_urls[] = wp_get_attachment_url( $image_id );
	}
	return $image_urls;
}