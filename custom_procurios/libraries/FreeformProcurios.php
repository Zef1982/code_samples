<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class FreeformProcurios{

	/**
	 * Procurios Freeform library for ExpressionEngine.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/** @var array postfields_registration_discount */
	private $postfields_registration_discount = [
		"class_3753_fieldMagazineEdition" 		=> "kortingscode",
		"class_3753_paymentMethod" 				=> "automatische_incasso",
		"class_3753_acceptDirectDebitAgreement" => "automatische_incasso"
	];

	/** @var array postfields_registration_gift */
	private $postfields_registration_gift = [
		"class_3800_fieldMagazineEdition" 					=> "kortingscode",		
		"class_3800_welcomeGift_paymentMethod" 				=> "automatische_incasso",
		"class_3800_welcomeGift_acceptDirectDebitAgreement" => "automatische_incasso",
		"class_1_ff_1734"									=> "tekst_cadeau"
	];

	/** @var array postfields_registration_as_present */
	private $postfields_registration_as_present = [
		"class_3935_fieldMagazineEdition"							=> "kortingscode",
		"class_3935_gender"											=> "geslacht",
		"class_3935_birthDate"										=> "geboortedatum",
		"class_3935_initials"										=> "voorletters",
		"class_3935_firstName"										=> "voornaam",
		"class_3935_insertion"										=> "voorvoegsels",
		"class_3935_name"											=> "achternaam",
		"class_3935_phoneNumber"									=> "telefoon",
		"class_3935_emailAddress"									=> "email",
		"class_3935_address"										=> [],
		"class_3935_livesAbroad"									=> "woonachtig_buitenland",
		"class_3935_giftedSubscription_paymentMethod"				=> "a_automatische_incasso",
		"class_3935_giftedSubscription_acceptDirectDebitAgreement"	=> "a_automatische_incasso"
	];

	/** @var array postfields_shared */
    private $postfields_shared = [
        "class_1_ff_446" 				=> "geslacht",
		"class_1_ff_447" 				=> "geboortedatum", 					//Not in acquaintance form!
		"class_1_relation_initials" 	=> "voorletters",
		"class_1_relation_firstname" 	=> "voornaam",
		"class_1_relation_insertion" 	=> "voorvoegsels",
		"class_1_relation_name" 		=> "achternaam",
		"class_1_phone_number_2" 		=> "telefoon",
		"class_1_email_addresses" 		=> "email",
		"class_1_relation_address_0" 	=> [],
		"class_1_ff_406"				=> "nieuwsbrief", 						//Not in acquaintance form!
		"class_1_ff_523" 				=> ["iban" => "iban", "bic" => "bic"],	//Not in acquaintance form!
		"class_1_ff_1713" 				=> "woonachtig_buitenland", 			//TRUE = BE
		"action_code" 					=> 0
    ];

	/** @var array address_fields */
	private $address_fields = [
		"street" 		=> "straat",
		"number" 		=> "huisnummer",
		"number_add" 	=> "huisnummertoevoeging",
		"postcode" 		=> "postcode",
		"town" 			=> "woonplaats",
		"country" 		=> "woonachtig_buitenland"
	];

	/**
    * Send to Procurios 
	*
	* @param int form_id
	* @param string acquaintance
    *
    * @return void
    */
    public function send_to_procurios($form_id, $acquaintance=""){

        if( in_array( $form_id, [4, 11]) || ($acquaintance && $form_id == 10)){
			// Get access token from Procurios.
			$access_token = ee()->procurios->get_access_token();

			// Set headers using access token.
			ee()->procurios->set_headers($access_token);

			// Get formdata formatted according to Procurios.
            $procurios_form_data = $this->get_formatted_form_data($form_id );

			// Get Procurios form ID & remove it from the form data.
            $procurios_form_id = $procurios_form_data["form_id"];
            unset($procurios_form_data["form_id"]);

			// Validate the form data.
            $validation = ee()->procurios->form_validate($procurios_form_id, $procurios_form_data);

            if(isset($validation["errors"])){

                // Mail errors to monitor, so registration can continue.
				mail("monitor@estdigital.nl", "procurios feedback ".$procurios_form_id, print_r($validation["errors"], true));

            }else{

                // Register user form data.
                $register = ee()->procurios->register($procurios_form_id, $procurios_form_data);

            }
        }
    }

	/**
    * Get Freeform entry data
	*
	* @param int entry_id
	* @param int form_id
    *
    * @return array
    */
	public function get_freeform_entry_data($entry_id, $form_id){

		$select_array = [];
		$form_fields = $this->get_form_fields($form_id);
		foreach($form_fields  as $form_field){
			$select_array[] = "field_" . $form_field['id'] ." AS " . $form_field['handle'];
		}

		$form_data = ee()->db->select(implode(", ", $select_array))
			->from("freeform_next_submissions")
			->where(["id" => $entry_id])
			->get()
			->result_array();

		return $form_data[0];		
	}

	/**
    * Get Freeform fields
	*
	* @param int form_id
    *
    * @return array
    */
	private function get_form_fields($form_id){

        // Get form fields ids by getting form layout json object.
        $forms = ee()->db->select("layoutJson")
                        ->from("freeform_next_forms")
                        ->where("id", $form_id)
                        ->get()
                        ->result_array();

        if(isset($forms[0])){

            // Get form ids from layout json object in array.
            $field_ids = [];
            $form_layout =json_decode($forms[0]['layoutJson'], TRUE);
            foreach( $form_layout['composer']['properties'] as $item){
                if(isset($item['id'])){
                    $field_ids[] = $item['id'];
                }
            }
			
            // Get form handles.
            $form_fields = ee()->db->select("id, handle")
                ->from("freeform_next_fields")
                ->where_in("id", $field_ids)
                ->get();

            // Return array with form field mapping.
            return $form_fields->result_array();
        }
    }

	/**
    * Get formatted form data
	*
	* @param int form_id
    *
    * @return array
    */
    public function get_formatted_form_data($form_id ){

        $procurios_form_data = [];
		$procurios_field_mapping = [];
		
		// Add address fields to Procurios mapping.
		$this->postfields_shared["class_1_relation_address_0"] = $this->address_fields;

		// Get fieldmapping based on form type.
        switch($form_id){
			
			// Registration as a present.
            case 4:
                $procurios_form_data["form_id"] = ee()->procurios->form_types["registration_as_present"];
				// Add address fields to Procurios mapping.
				$this->postfields_registration_as_present["class_3935_address"] = $this->address_fields;
				// Add prefix for subscriber fields.
				$subscriber_postfields_shared = $this->add_subscriber_prefix($this->postfields_shared);
				// Corrections.
				$subscriber_postfields_shared["class_1_relation_insertion"] = "a_voorvoegsels";
				$subscriber_postfields_shared["class_1_ff_523"] = ["iban" => "a_iban", "bic" => "a_bic"];
				// Add prefix to subscriber address fields.
				$subscriber_address_fields = $this->add_subscriber_prefix($this->address_fields);
				// Add address fields to Procurios mapping.
				$subscriber_postfields_shared["class_1_relation_address_0"] = $subscriber_address_fields;
				$procurios_field_mapping = ($this->postfields_registration_as_present + $subscriber_postfields_shared);
                break;
			
			// Registration discount / Registration gift
            case 11:
				if(!isset($_POST["tekst_cadeau"])){
					$procurios_form_data["form_id"] = ee()->procurios->form_types["registration_discount"];
					$procurios_field_mapping = ($this->postfields_registration_discount + $this->postfields_shared);
                }else{
					$procurios_form_data["form_id"] = ee()->procurios->form_types["registration_gift"];
					$procurios_field_mapping = ($this->postfields_registration_gift + $this->postfields_shared);
				}
				break;

			// Registration acquaintance.
			case 10:
				$procurios_form_data["form_id"] = ee()->procurios->form_types["registration_acquaintance"];
				// Unset geboortedatum.
				unset($this->postfields_shared["class_1_ff_447"]);
				// Unset nieuwsbrief.
				unset($this->postfields_shared["class_1_ff_406"]);
				// Unset iban / bic .
				unset($this->postfields_shared["class_1_ff_523"]);
				$procurios_field_mapping = $this->postfields_shared;
				break;
        }

		// map formatted values according to Procurios.
        $procurios_form_data = ($procurios_form_data + $this->map_form_fields($procurios_field_mapping));

        return $procurios_form_data;        
    }

	/**
    * Adds subscriber prefix adds a_ prefix to values.
	*
	* @param array
    *
    * @return array
    */
	private function add_subscriber_prefix($array){
		foreach($array as $key => $value){
			if(!is_array($value)){
				$array[$key] = "a_" . $value;
			}
		}
		return $array;
	}

	/**
    * Map form fields
	*
	* @param array procurios_post_field_mapping
    *
    * @return array
    */
    private function map_form_fields($procurios_post_field_mapping){
		
		$procurios_form_data = [];
        foreach( $procurios_post_field_mapping as $procurios_field_name => $ee_field_name ){
            if(in_array($procurios_field_name, ["class_1_relation_address_0", "class_3935_address", "class_1_ff_523"])){
                // Values for address & IBAN/BIC are in another sub-array.
                $procurios_form_data[$procurios_field_name] = $this->map_form_fields($ee_field_name);
            }else{
                if(isset($_POST[$ee_field_name])){
                    // Fix sex input value.
                    if("class_1_ff_446" == $procurios_field_name || "class_3935_gender" == $procurios_field_name){
						$_POST[$ee_field_name] = ( "m" == $_POST[$ee_field_name][0] ? "m" : "v" );
                    }
                    // Fix paymentMethodOptionDirectDebit input value.
                    if(isset($_POST[$ee_field_name][0]) && preg_match("#_paymentMethod$#", $procurios_field_name)){
						$_POST[$ee_field_name] = ( "ja" == $_POST[$ee_field_name][0] ? "paymentMethodOptionDirectDebit" : "paymentMethodOptionInvoice" );		
                    }                    
                    // Fix acceptDirectDebitAgreement boolean.
                    if(isset($_POST[$ee_field_name]) && preg_match("#_acceptDirectDebitAgreement$#", $procurios_field_name)){
                        $_POST[$ee_field_name] = ( "paymentMethodOptionDirectDebit" == $_POST[$ee_field_name] ? TRUE : FALSE );
                    }
                    // Map fields.
					if(isset($_POST[$ee_field_name])){
                    	$procurios_form_data[$procurios_field_name] = $_POST[$ee_field_name];
					}
					// Membership as present has an optional email address for the new member.
					if("class_3935_emailAddress" == $procurios_field_name && empty($_POST[$ee_field_name])){
						unset($procurios_form_data["class_3935_emailAddress"]);
					}
					// Set newsletter value.
					if("nieuwsbrief" == $ee_field_name){
                        $procurios_form_data[$procurios_field_name] = ["1" => (isset($_POST[$ee_field_name]) ? TRUE : FALSE)];

                    }
					// Add the selected gist to the membership.
					if("tekst_cadeau" == $ee_field_name){
                        $procurios_form_data[$procurios_field_name] = (int) $_POST[$ee_field_name];
					}
					// Parse housenumber as integer.
					if("huisnummer" == $ee_field_name){
                        $procurios_form_data[$procurios_field_name] = (int) $field_input_data[$ee_field_name];
					}
					
                }
				// Fields which must ALSO be set when it's NOT SET in the form:
				// Set country fields.
				if("country" == $procurios_field_name){
					$procurios_form_data[$procurios_field_name] = (isset($_POST[$ee_field_name]) ? "BE" : "NL");
				}
				// Living abroad?
				if("class_1_ff_1713" == $procurios_field_name){
					$procurios_form_data[$procurios_field_name] = ($procurios_form_data["class_1_relation_address_0"]["country"] == "BE" ? TRUE : FALSE);
				}
				// Member receiving registration as present is living abroad?
				if("class_3935_livesAbroad" == $procurios_field_name){
					$procurios_form_data[$procurios_field_name] = ($procurios_form_data["class_3935_address"]["country"] == "BE" ? TRUE : FALSE);
				}
			}
        }
	
        return $procurios_form_data;
    }

}
