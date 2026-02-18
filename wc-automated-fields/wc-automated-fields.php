<?php
/**
 * @package WC_automated_fields
 * @version 1.0.0
 */
/*
Plugin Name: WC automated fields
Description: This plugin automatically insert product fields for specific product, based on XML files.
Version: 1.0.0
Author: ZEF
*/

use SW_WAPF\Includes\Models\Field;
use SW_WAPF\Includes\Models\FieldGroup;
use SW_WAPF\Includes\Classes\Helper;
use SW_WAPF\Includes\Models\ConditionRule;
use SW_WAPF\Includes\Models\ConditionRuleGroup;

add_action( 'woocommerce_new_product', 'import_product_fields', 10, 2);
add_action( 'woocommerce_update_product', 'import_product_fields', 10, 2);

/**
 * Function to add product fields for specific products.
 * 
 * @param $product_id
 * @param $product
 * 
 * @return void
 */
function import_product_fields( $product_id, $product ){

    // Check if there is any fields present in the meta data for this product.
    $meta_key = '_wapf_fieldgroup';
    $post_meta = get_post_meta( $product_id, $meta_key, true );
    
    // None is present, so lets add it!
    if(empty($post_meta)){
 
        $category_ids = $product->get_category_ids();

        // Look category ID's  up in WP terms to see if category slug matches $product type!
        foreach ( $category_ids as $category_id) {

            // Get the term slug for this category.
            $term_slug = get_term( $category_id )->slug;

            // Match the slug with an existing XML file.
            $xml_file = dirname(__FILE__) . '/product-fields/' . $term_slug . '.xml';
            if (file_exists($xml_file)) {

                // Get data into array.
                $xml = simplexml_load_string(file_get_contents($xml_file), "SimpleXMLElement", LIBXML_NOCDATA);
                $xml_json = json_encode($xml);
                $xml_array = json_decode($xml_json, TRUE);
                
                // Create fieldgroup.
                $fg = create_fieldgroup($product_id, $xml_array['field']);

                // Update meta post.
                update_post_meta( $product_id, $meta_key, Helper::wp_slash($fg->to_array()));
   
            }
        }

    }
}

/**
 * Function to create fieldgroup
 * 
 * @param int $product_id
 * @param array $xml_fields
 * 
 * @return object fg
 */
function create_fieldgroup($product_id, $xml_fields) {
    $fg = new FieldGroup();
    $fg->id = 'p_' . $product_id;
    $fg->type = 'wapf_product';
    $fg->layout['labels_position'] = 'above';
    $fg->layout['instructions_position'] = 'field';
    $fg->layout['mark_required'] = 1;

    // Get fields from teh XML file into a usable array.
    $fg->fields = get_fields($xml_fields);

    // Add rules.
    $rule = new ConditionRule();
    $rule->condition = 'product';
    $rule->value[] = [
        'id' => $product_id,
        'text' => ''
    ];
    $rule->subject = 'product';

    $condition = new ConditionRuleGroup();
    $condition->rules[] = $rule;
    $fg->rules_groups[] = $condition;
    return $fg;
}

/**
 * Function to get fields for fieldgroup from XML data
 * 
 * @param array $xml_fields
 * 
 * @return array fields
 */
function get_fields($xml_fields) {
    $fields = [];
    foreach ( $xml_fields as $xml_field ) {

        $field = new Field();
        $field->id = uniqid();
        $field->label = $xml_field['label'];
        $field->type = $xml_field['type'];
        $field->required = $xml_field['required'];
        $field->options['choices'] = get_choices($xml_field['options']['option']);
        $field->conditionals = [];
        $field->pricing = [
            'type' => 'fixed',
            'amount' => 0,
            'enabled' => FALSE
        ];
        $fields[] = $field;
    }
    return $fields;
}

/**
 * Function to get choices
 * 
 * @param array $options
 * 
 * @return array choices
 */
function get_choices($options){
    $choices = [];
    foreach( $options as $option){
        $choice = [];
        $choice['id'] = uniqid();
        $choice['label'] = $option['label'];
        $choice['selected'] = (isset($option['selected'])? TRUE : '');
        $choice['pricing_type'] = (isset($option['pricing_type'])? $option['pricing_type'] : 'none') ;
        $choice['pricing_amount'] = (isset($option['pricing_amount'])? $option['pricing_amount'] : 0);
        $choices[] = $choice;
    }
    return $choices;
}