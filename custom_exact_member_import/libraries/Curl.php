<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Curl{

    /**
   * CURL library
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

    /** @var array headers for CURL. */
    public $headers = [];

    /** @var int API calls tracking for API limit. */
    private $api_calls_count = 0;

    /**
    * Do the actual CURL.
    *
    * @param string url to be called by CURL.
    * @param array postfields optional fields to be posted by CURL.    
    *
    * @return array
    */
    public function do($url, $postfields = []) {

        $this->api_calls_count++;

        // Initialize CURL.
        $ch = curl_init();

        // Pass URL parameter to CURL.
        curl_setopt($ch, CURLOPT_URL, $url);

        // POST fields to if present, build query if array!
        if (!empty($postfields)) {

            curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($postfields) ? http_build_query($postfields) : $postfields ) );
            curl_setopt($ch, CURLOPT_POST, TRUE );
        }

        // Send headers if present.
        if (is_array($this->headers) && !empty($this->headers)) {
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