<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Procurios
{

	/**
	 * Procurios library for ExpressionEngine.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/** @var string token endpoint */
    private $token_endpoint;

	/** @var string client ID */
	private $client_id;

	/** @var string client secret */
	private $client_secret;

	/** @var string scopes */
	private $scopes;

	/** @var string headers fro Procurios */
	private $headers;

	/** @var array Procurios forms */
	private $form_types = [
		'registration_discount' 	=> 19,
		'registration_gift' 		=> 3,
		'registration_as_present' 	=> 20,
		'registration_acquaintance'	=> 21
	];

	/**
    * Constructor
    *
    * @return void
    */
	public function __construct(){
		// Get Procurios credentials form config.
		$this->token_endpoint = ee()->config->item('token_endpoint');
		$this->client_id = ee()->config->item('client_id');
		$this->client_secret = ee()->config->item('client_secret');
		$this->scopes = ee()->config->item('scopes');

	}

	/**
    * Get schema
	*
	* @param int procurios_form_id
    *
    * @return array
    */
	public function get_schema($procurios_form_id=""){

		$url = $this->token_endpoint . "profile_api/registrationset/" . $procurios_form_id . "/schema";
		$form = $this->curl($url);
		return $form;
	}

	/**
    * Register
	*
	* @param int procurios_form_id
	* @param array procurios_form_data
    *
    * @return void
    */
    public function register($procurios_form_id, $procurios_form_data){

		$url = $this->token_endpoint . "profile_api/registrationset" . (empty($procurios_form_id)? "" : "/" . $procurios_form_id . "/registration");
		$this->curl($url, json_encode($procurios_form_data));

	}

	/**
    * Form validate
	*
	* @param int procurios_form_id
	* @param array procurios_form_data
    *
    * @return array
    */
	public function form_validate($procurios_form_id, $procurios_form_data){

		$url = $this->token_endpoint . "profile_api/registrationset/" . $procurios_form_id . "/validate";
		// Send JSON encoded field data.
		$validation = $this->curl($url, json_encode($procurios_form_data));

		return $validation;
	}

	/**
    * Set headers
	*
	* @param string access_token
    *
    * @return void
    */
	public function set_headers($access_token){
		
		$this->headers = array(
			"Accept:application/vnd.procurios.profile+json; version=1",
			sprintf('Authorization: Bearer %s', $access_token)
		);

	}

	/**
    * Get access token
    *
    * @return string
    */
	public function get_access_token(){

		$url = $this->token_endpoint . "oauth2/token";
		//Prepare POST fields.
		$postfields = [
			"grant_type"	=> "client_credentials",
			"scope"			=> $this->scopes,
			"client_id"		=> $this->client_id,
			"client_secret"	=> $this->client_secret
		];

		$client_credentials = $this->curl($url, $postfields);

		return $client_credentials["access_token"];
	}

	/**
    * CURL
	*
	* @param string url
	* @param array postfields
    *
    * @return array
    */
	private function curl($url, $postfields=""){	

		// Initialize CURL.
		$ch = curl_init();

		// Pass URL parameter to CURL.
		curl_setopt($ch, CURLOPT_URL, $url);

		// POST fields to if present, build query if array!
		if(!empty($postfields)){
			curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($postfields) ? http_build_query($postfields) : $postfields ) );
		}

		// Send headers if present.
		if(is_array($this->headers) && !empty($this->headers)){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		}

		// Return string value.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		// Receive value;
		$server_output = curl_exec($ch);

		// Close CURL handler.
		curl_close ($ch);

		// Conver JSON object to associative array.
		return json_decode($server_output, TRUE);
	}
}
