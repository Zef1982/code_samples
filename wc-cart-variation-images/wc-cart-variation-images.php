<?php
/**
 * @package WC_cart_variation_images
 * @version 1.0.0
 */
/*
Plugin Name: WC cart variation images
Description: This filter replaces the default product images with the image of the selected color variation.
Version: 1.0.0
Author: ZEF
*/

add_filter('woocommerce_store_api_cart_item_images' , 'fix_cart_variation_thumb',10,3);

function fix_cart_variation_thumb($thumbnail, $cart_item, $cart_item_id){
	
	// Get the actual color from the current variation.
	$color = strtolower(str_replace(' ', '_', $cart_item['variation']['attribute_color']));
	
	// Get the gallery images from the product object.
	$product = new WC_product($cart_item['product_id']);
	$gallery_img_ids = $product->get_gallery_image_ids();
	
	// Loop throught the gallery images to match with the current variation color.
	foreach($gallery_img_ids as $img_id){
		
		$img_url = wp_get_attachment_url($img_id);
		$match = is_numeric(strpos($img_url, 't_' . $color . '.'));
		if($match){
			// Return the variation image in the proper color if match found.
			return [
				(object)[
					'id'        => 0,
					'src'       => $img_url,
					'thumbnail' => $img_url,
					'srcset'    => '',
					'sizes'     => '',
					'name'      => $product->name,
					'alt'       => $product->name,
				]
			];
		}
	}
	
}