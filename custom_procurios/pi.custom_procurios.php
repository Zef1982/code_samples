<?php

class Custom_procurios
{
	
	/**
	 * Procurios plugin for ExpressionEngine.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/**
    * Constructor 
    *
    * @return void
    */
	public function __construct(){


		// Load procurios library.
		ee()->load->library('procurios');

		// Get access token from Procurios.
		$access_token = ee()->procurios->get_access_token();

		// Set headers using access token.
		ee()->procurios->set_headers($access_token);

	}

	/**
    * Get options and parse into EE template. 
    *
    * @return void
    */
	public function get_options(){

		// Set empty return data variables.
		$variables = [];
		// get form.
		$form = ee()->TMPL->fetch_param('form');
		// Get Procurios form ID.
		$procurios_form_id = ee()->procurios->form_types[$form];
		// Get schema for form with form ID.
		$form_schema = ee()->procurios->get_schema($procurios_form_id);
		if(isset($_GET['debug'])){
			echo "<pre>";
			print_r($form_schema);
			echo "</pre>";
			exit;
		}
		// Get membership startdate options.
		$field_name;
		switch($procurios_form_id){
			case 19:
				$field_name = "class_3753_fieldMagazineEdition";
				break;

			case 3:
				$field_name = "class_3800_fieldMagazineEdition";
				break;

			case 20:
				$field_name = "class_3935_fieldMagazineEdition";
				break;
		}
		foreach(array_reverse($form_schema["properties"][$field_name]["enumNames"]) as $value => $label){
			$variables["membership_starts"][] = ["value" => $value, "label" => $label];
		}
		// return variables to tagdata.
		return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, [$variables]);
	}

	/**
    * Register acqaintance from EE Freeform to Procurios. 
    *
    * @return void
    */
	public function register_acquaintance(){
		$webhook_result = ee()->TMPL->fetch_param('webhook_result');
		if(preg_match("#^acquaintance\|(\d+)\|(\d+)$#i", $webhook_result, $matches)){
			$form_id = $matches[1];
			$entry_id = $matches[2];
			if(10 == $form_id && is_numeric($entry_id)){
				// Load library to send Freeform input data to Procurios
				ee()->load->library('freeformProcurios', NULL, 'freeformProcurios');
				// Get freeform entry data.
				$freeform_entry_data = ee()->freeformProcurios->get_freeform_entry_data($entry_id, $form_id);
				// Send freeforme ntry data to Procurios.
				ee()->freeformProcurios->send_to_procurios($freeform_entry_data, $form_id, TRUE);
			}
		}
	}

	/**
    * Send to Procurios from EE Freeform to Procurios. 
    *
    * @return void
    */
	public function send_to_procurios(){
		$entry_id = (isset($_GET['entry_id'])? $_GET['entry_id'] : FALSE);
		$form_id = (isset($_GET['form_id'])? $_GET['form_id'] : FALSE);
		if(in_array(ee()->session->userdata('group_id'), [1,5]) && isset($form_id) && is_numeric($form_id) && isset($entry_id) && is_numeric($entry_id)){
			// Load library to send Freeform input data to Procurios.
			ee()->load->library('freeformProcurios', NULL, 'freeformProcurios');
			// Get freeform entry data.
			$freeform_entry_data = ee()->freeformProcurios->get_freeform_entry_data($entry_id, $form_id);
			// Send freeform entry data to Procurios.
			ee()->freeformProcurios->send_to_procurios($freeform_entry_data, $form_id, ($form_id == 10? TRUE : FALSE));
		}
	}

}
