<?php

class Custom_mollie_ext
{

	/**
	 * Mollie payment extension for ExpressionEngine.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/** @var array extension settings. */
    private $settings       = array();

	/** @var string extension name. */
    private $name           = 'Custom Mollie Payments';

	/** @var string extension version. */
    private $version        = '0.1.0';

	/** @var string extension description. */
    private $description    = 'Freeform Mollie payments';

	/** @var string extension settings exist. */
    private $settings_exist = 'y';

	/** @var string extension docs url. */
    private $docs_url       = 'http://www.estdigital.nl/';

	/**
    * Constructor
    *
    * @param array settings   
    *
    * @return void
    */
    function __construct($settings = '')
    {
		$this->settings = $settings;
		$this->site_url = ee()->functions->fetch_site_index(TRUE);

    }

	/**
    * Activate extension in EE.   
    *
    * @return void
    */
    function activate_extension()
    {
    	// Setup custom settings in this array.
		$this->settings = array();

        // Delete old hooks
        ee()->db->query("DELETE FROM exp_extensions WHERE class = '". __CLASS__ ."'");

    	$hooks = array(
			'freeform_next_submission_after_save',
		);

		foreach($hooks as $hook)
		{
			ee()->db->insert('extensions', array(
				'class'    => get_class($this),
				'method'   => $hook,
				'hook'     => $hook,
				'settings' => serialize($this->settings),
				'priority' => 10,
				'version'  => $this->version,
				'enabled'  => 'y'
			));
		}

	}

	/**
    * Freeform hook.
    *
    * @param object model
    * @param boolean is_new   
    *
    * @return void
    */
	function freeform_next_submission_after_save($model, $is_new)
	{
		$form_types = Array(
			"kennismakingsactie" 	=> 10,
			"zaad_bestellen"		=> 21,
			"agenda_order"			=> 2,
			"specials"				=> 15,
			"jubileumtulp"			=> 22
		);

		$form_id = $model->formId;

		if ( $is_new AND in_array($form_id, $form_types)){

			require_once dirname($_SERVER['DOCUMENT_ROOT']) . "/webmanager/user/addons/custom_mollie/vendor/autoload.php";

			$mollie = new \Mollie\Api\MollieApiClient();
			$mollie->setApiKey(ee()->config->item((ENV == "prod" ? "prod_api_key" : "dev_api_key"), "mollie_settings" ));

			$entry_id = $model->id;
			
			switch($form_id){
				case $form_types["kennismakingsactie"]:
					$amount = "7.50";
					$description = "Kennismakingsactie";
					$form_type = "kennismakingsactie";
					break;

				case $form_types["zaad_bestellen"]:
					$amount = $this->get_seed_order_price();
					$description = "Zaad bestelling";
					$form_type = "zaad_bestellen";
					break;

				case $form_types["agenda_order"]:
					$amount = "10.95";
					$description = "Agenda Groei & Bloei";
					$form_type = "agenda_order";
					break;

				case $form_types["specials"]:
					$amount = $this->get_specials_order_price($_POST['specials_index'], $form_id, $entry_id);
					$description = "Specials Groei & Bloei";
					$form_type = "specials";
					break;

				case $form_types["jubileumtulp"]:
					$amount = $this->get_aniv_tulip_price($inputs['aantal']);
					$description = "jubtulp";
					$form_type = "jubileumtulp";
					break;
			}

			$order_id = $form_id."-".$entry_id;

			$payment = $mollie->payments->create([
				"amount" => [
						"currency" => "EUR",
						"value" => $amount
				],
				"description" => $description . " groei.nl order #".$order_id,
				"redirectUrl" => $this->site_url."payment/redir?order_id=".$order_id, //
				"webhookUrl" => $this->site_url."payment/webhook",
				"metadata" => [
						"order_id" => $order_id,
						"form_type" => $form_type,
						"email" => $_POST['email'],
				],
				"locale" => "nl_NL",

			]);
			ee()->db->update(
				'freeform_next_submissions',
				array(
					'field_67'  => 'payment_in_process',
					'field_106' => $amount
				),
				array(
					'formId' 	=> $form_id,
					'id'	 	=> $entry_id
				)
			);

			header("Location: " . $payment->getCheckoutUrl(), true, 303);
			exit;
		}

	}

	/**
    * Get seed order price from shopping basket. 
    *
    * @return string
    */
	private function get_seed_order_price(){
		
		if (isset($_COOKIE["shoppingBasket"]) && !empty($_COOKIE["shoppingBasket"])) {
			$basket = json_decode($_COOKIE["shoppingBasket"]);
			$default_price = 3.75;
			$contains_bulb = FALSE;
			$total_price = 0;
			$zaad_prijs_field_id = $this->get_field_id("zaad_prijs");
			$zaad_is_bol_field_id = $this->get_field_id("zaad_is_bol");
			foreach ($basket as $seed) {
				$entry_data = ee()->db->select("channel_titles.title, zaad_prijs_field.field_id_" . $zaad_prijs_field_id . " AS zaad_prijs, zaad_is_bol_field.field_id_" . $zaad_is_bol_field_id . " AS zaad_is_bol")
					->from("channel_data_field_" . $zaad_prijs_field_id . " AS zaad_prijs_field")
					->join ("channel_data_field_" . $zaad_is_bol_field_id . " AS zaad_is_bol_field", "zaad_prijs_field.entry_id = zaad_is_bol_field.entry_id", "left")
					->join ("channel_titles", "channel_titles.entry_id = zaad_is_bol_field.entry_id")
					->where("zaad_prijs_field.entry_id", $seed->seed_id)
					->get();
				$seed_entry = $entry_data->result_array()[0];
				// ofcourse they'll fill in comma's instead of dots!
				$custom_price = (float)str_replace(",", ".", $seed_entry["zaad_prijs"]);
				$price = (!empty($custom_price) ? $custom_price : $default_price);
				$total_price += $price * $seed->number;
				// Detect if bulb is present, so we can add shipping costs.
				if ($seed_entry["zaad_is_bol"]) {
					$contains_bulb = TRUE;
				}
			}
			// Add bulb shipping costs if bulbs are present.
			if($contains_bulb){
				$total_price += 7.5;
			}
			return number_format($total_price, 2);
		}
	}

	/**
    * Get tulip price.
    *
    * @param int number   
    *
    * @return string
    */
	private function get_aniv_tulip_price($number){
		$tulip_price = 11.25;
		$shipping_price = 7.5;
		$price = ($number * $tulip_price) + $shipping_price;
		return number_format($price, 2);
	}

	/**
    * Get specials price.
    *
    * @param int specials_index
    * @param int form_id
    * @param int entry_id
    *
    * @return string
    */
	private function get_specials_order_price($specials_index, $form_id, $entry_id){

		// Get the selected special.
		$specials = ee()->db->select("col_id_33 AS title, col_id_35 AS price")
			->from("channel_grid_field_408")
			->where("row_order", ($specials_index - 1))
			->get()
			->result_array();

		// Add summary to Freeform.
		ee()->db->update(
			"freeform_next_submissions",
			["field_90" => $specials[0]["title"]],
			[ 
				"formId"	=> $form_id,
				"id" 		=> $entry_id
			]
		);

		// Return price.
		$price = (float)str_replace(",", ".", $specials[0]["price"]);
		$shipping_costs = 2.5;
		return number_format(($price + $shipping_costs), 2);
	}

	/**
    * Get field ID from EE.
    *
    * @param strinf field_name
    *
    * @return int
    */
	private function get_field_id($field_name){
		$field = ee('Model')->get('ChannelField')
			->filter('field_name', $field_name)
			->first();		
		return $field->field_id;		
	}
	

	function update_extension($current = '')
	{
		// Nothing to change...
		return FALSE;
	}

	/**
	 * Disable Extension
	 */
	function disable_extension()
	{
		// -------------------------------------------
		//  Delete the extension hooks
		// -------------------------------------------

		ee()->db->where('class', get_class($this))
		             ->delete('exp_extensions');
	}


}
