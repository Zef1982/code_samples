<?php
/**
 * @package Auto_image_Alt_title
 * @version 1.0.0
 */
/*
Plugin Name: Auto image alt title
Description: Adds alt & title for uploaded images, if the product category name is present in the filename.
Version: 1.0.0
Author: ZEF
*/

function change_empty_alt_to_title( $response ) {
	
	$categories = get_product_categories();
	
	foreach($categories as $category){
		
		// Replace spaces with underscores and remove trailing slash from category slug.
		$underscored_category = preg_replace(['#-#', '#s$#'], ['_', ''], $category->slug);
		
		// Remove extension from the filename and remove caps.
		$filename = strtolower(preg_replace('#\.[a-z]{3}$#', '', $response['filename']));
		
		// Check if category is present in filename.
		$match = is_numeric(strpos($filename, '_'.$underscored_category));
		if($match){
			
			// Seperate the filename into title, category and color.
			$filename_parts = explode($underscored_category, $filename);
			
			// Set the new alt & title with spaces and caps.
			$color = ucfirst_multiple_sentence($filename_parts[1]);
			$title =  ucfirst_multiple_sentence($filename_parts[0]);
			$alt = $title . preg_replace('#s$#', '', $category->name) . ' '. $color;

			$response['alt'] = $alt;
			$response['title'] = $alt;
			
			break;
		}
	}

	return $response;
}

function get_product_categories(){
	$taxonomy     = 'product_cat';
	$orderby      = 'name';  
	$show_count   = 0;      // 1 for yes, 0 for no
	$pad_counts   = 0;      // 1 for yes, 0 for no
	$hierarchical = 1;      // 1 for yes, 0 for no  
	$title        = '';  
	$empty        = 0;

	$args = array(
		'taxonomy'     => $taxonomy,
		'orderby'      => $orderby,
		'show_count'   => $show_count,
		'pad_counts'   => $pad_counts,
		'hierarchical' => $hierarchical,
		'title_li'     => $title,
		'hide_empty'   => $empty
	);
	return get_categories($args);
}

function ucfirst_multiple_sentence($old_sentence){
	$parts = explode('_', $old_sentence);
	$new_sentence = '';
	foreach($parts as $part){
		if(!empty($new_sentence)){
			$new_sentence.= ' ';
		}
		$new_sentence.= ucfirst($part);
	}
	return $new_sentence;
}
add_filter( 'wp_prepare_attachment_for_js', 'change_empty_alt_to_title' );