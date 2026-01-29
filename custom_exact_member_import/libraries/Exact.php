<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Exact {
	
	/**
   * Exact library
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

	/** @var string to be filled with EExact API endpoint. */
    private $exact_endpoint;

	public function __construct() {
		// Wait 3 minutes if neccasary.
		set_time_limit(0);

		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);

		// Set the exact endpoint.
		$this->exact_endpoint = ee()->config->item('exact_endpoint');
		ee()->oauth->exact_endpoint = $this->exact_endpoint;

	}

	/**
    * Get members from Exact. 
    *
    * @return array
    */
	public function get_new_members() {
		
		// Select email and other properties we need to filter later on.
		$select = [
			'ClassificationCode',
			'OrderedBy',
			'EndDate'
		];

		// Get all URL parameters.
		$params_array = [
			'$select'		=> implode(",", $select),
			'$inlinecount'	=> 'allpages',
			'$filter'		=> 'Modified ge datetime\'' . date("Y-m-d\TH:i:s", strtotime("previous week monday")) . '\'',
			'$orderby'		=> 'Created desc'
		];

		// Make paramater string.
		$params = [];
		foreach($params_array as $key => $value){
			$params[] = $key . "=" . $value;
		}

		$url = $this->exact_endpoint . 'v1/1882874/subscription/Subscriptions?' . implode("&", $params);
		
		$import_subscriptions = [];
		$this->get_subscriptions($url, $import_subscriptions);
		
		// Pass email addresses array by reference to make it easier to fill it recursively!
		$email_addresses = [];
		$this->get_new_accounts($import_subscriptions, $email_addresses);
		
		// Make email addresses lower and unique.
		$email_addresses = array_unique($email_addresses);
		foreach($email_addresses as $key => $email){
			$email_addresses[$key] = strtolower($email);
		}
		
		return $email_addresses;
	}

	/**
    * Get deleted members from Exact.   
    *
    * @return array
    */
	public function get_deleted_members() {
		
		// Select email and other properties we need to filter later on.
		$select = [
			'ClassificationCode',
			'OrderedBy',
		];

		// Get all URL parameters.
		$params_array = [
			'$select'		=> implode(",", $select),
			'$inlinecount'	=> 'allpages',
			'$filter'		=> 'Modified ge datetime\'' . date("Y-m-d\TH:i:s", strtotime("1 month ago")) . '\' and EndDate le datetime\'' . date("Y-m-d\TH:i:s") . '\'',
			'$orderby'		=> 'Created desc'
		];

		// Make paramater string.
		$params = [];
		foreach($params_array as $key => $value){
			$params[] = $key . "=" . $value;
		}

		$url = $this->exact_endpoint . 'v1/1882874/subscription/Subscriptions?' . implode("&", $params);
		
		$import_subscriptions = [];
		$this->get_subscriptions($url, $import_subscriptions);
		
		// Pass email addresses array by reference to make it easier to fill it recursively!
		$email_addresses = [];
		$this->get_old_accounts($import_subscriptions, $email_addresses);

		return $email_addresses;

	}

	/**
    * Get subscriptions from Exact.
    *
    * @param string url to be called by CURL.
    * @param array import_subscriptions collect subscriptions by reference.    
    *
    * @return void
    */
	private function get_subscriptions($url, &$import_subscriptions){
		
		// Get new access token if needed and set the header with it.
		ee()->oauth->check_access_token();
		ee()->curl->headers = ee()->oauth->headers;
		$subscriptions = ee()->curl->do($url);

		if (isset($subscriptions['d']['results'])) {
			foreach ($subscriptions['d']['results'] as $subscription) {
				$import_subscriptions[] = $subscription;
			}
		}

		// Loop through next page if present.
		if (isset($subscriptions['d']['__next']) && !empty($subscriptions['d']['__next'])) {
			$next_url = $subscriptions['d']['__next'];
			$this->get_subscriptions($next_url, $import_subscriptions);
		}
	}

	/**
    * Get new accounts from Exact.
    *
    * @param array import_subscriptions
    * @param array email_addresses collect subscriptions by reference.    
    *
    * @return void
    */
	private function get_new_accounts($import_subscriptions, &$email_addresses){

		foreach ($import_subscriptions as $subscription) {

			// Get EndDate value to make conditionals with.
			preg_match("#\/Date\((\d+)\)\/#", $subscription['EndDate'], $enddate_matches);

			if (					
					(
						// Enddate isn't present OR has yet to pass.
						(!empty($enddate_matches[1]) && ($enddate_matches[1]/1000 > time())) ||
						empty($subscription['EndDate'])

					) && 

					// Paper & Digital OR Digital.
					in_array($subscription['ClassificationCode'], ['PADI','DIGI'])
			){			

				// Get the corresponding account email address.
				if(!empty($subscription['OrderedBy'])){

					$url = $this->exact_endpoint . 'v1/1882874/CRM/Accounts(guid\'' . $subscription['OrderedBy'] . '\')?$select=Email';

					// Get new access token if needed and set the header with it.
					ee()->oauth->check_access_token();
					ee()->curl->headers = ee()->oauth->headers;					
					$contact = ee()->curl->do($url);

					if (isset($contact['d']['Email'])) {
						$email_addresses[] = $contact['d']['Email'];
					}

				}								
				
			}
		}
	}

	/**
    * Get old accounts from Exact.
    *
    * @param array import_subscriptions
    * @param array email_addresses collect subscriptions by reference.    
    *
    * @return void
    */
	private function get_old_accounts($import_subscriptions, &$email_addresses){

		foreach ($import_subscriptions as $subscription) {

			if(in_array($subscription['ClassificationCode'], ['PADI','DIGI'])){

				// Get the corresponding account email address.
				if(!empty($subscription['OrderedBy'])){

					$url = $this->exact_endpoint . 'v1/1882874/CRM/Accounts(guid\'' . $subscription['OrderedBy'] . '\')?$select=Email';

					// Get new access token if needed and set the header with it.
					ee()->oauth->check_access_token();
					ee()->curl->headers = ee()->oauth->headers;
					$contact = ee()->curl->do($url);	

					if (isset($contact['d']['Email'])) {
						$email_addresses[] = $contact['d']['Email'];
					}

				}
			}
		}
	}

	
}
