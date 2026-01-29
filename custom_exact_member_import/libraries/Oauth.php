<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Oauth {

/**
   * Oauth library
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

    /** @var string Excact enpoint. */
    private $exact_endpoint;

    /** @var string Oauth enpoint. */
    private $oauth_endpoint;

    /** @var int Oauth cleint ID. */
	private $oauth_client_id;

    /** @var string Oauth client secret. */
	private $oauth_client_secret;

    /** @var string Oauth redirect URL. */
    private $oauth_redirect_uri;

    /** @var array headers for Oauth. */
    private $headers = [];

    /** @var int API calls are limited to 200 per token. Save 1 for a token refresh! */
	private $time_start = 0;

     /** @var int 60 calls per minute. */
    private $api_minute_threshold = 60;

    /**
    * Check access token.
    *
    * @return void
    */
    public function check_access_token(){

        // Get the access token and set the CURL headers.
        if(ee()->curl->api_calls_count == 0){

            // Reset the API calls timer.
            $this->time_start = time();

            // Reset headers!
            $this->headers = [];
            // Get the OAUTH2 token.
            $access_token = $this->get_oauth_access_token();
            // echo "NEW access token = " . $access_token. "<br />";

            // Set headers with acces token.
            $this->set_headers($access_token);
        }

        // The number of API calls is over the minute trheshold, should we sleep the rest of the minute?
        if(ee()->curl->api_calls_count%$this->api_minute_threshold == 0){

            if(isset($_Get['debug'])){
                echo "API calls over " . $this->api_minute_threshold . "<br />";
            }

            // Has a minute passed yet?
            $time_now = time() - $this->time_start;

            // Sleep the remaining time...         
            $sleepy_time = (65 - $time_now);

            if(isset($_Get['debug'])){
                echo "Sleeping for " . ($sleepy_time > 0 ? $sleepy_time : 0) . " seconds...<br />";
            }

            sleep($sleepy_time > 0 ? $sleepy_time : 0);
        }


    }

    /**
    * Set headers for Oauth.
    *
    * @param string access_token
    *
    * @return void
    */
    private function set_headers($access_token){
        // Set the appropriate CURL headers using the OAUTH token.
        $this->headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: " . $access_token
        ];
    }

    /**
    * Get access token for Oauth.
    *
    * @return string
    */
    private function get_oauth_access_token() {

        // Get OATH2 credentials form config.
        $this->oauth_endpoint = $this->exact_endpoint . "oauth2/";
        $this->oauth_client_id = ee()->config->item('oauth_client_id');
        $this->oauth_client_secret = ee()->config->item('oauth_client_secret');
        $this->oauth_redirect_uri = ee()->config->item('oauth_redirect_uri');

        // Return access token after redirect, based on the fetched code.
        if(isset($_GET["code"]) && !empty($_GET["code"])) {

            $response = $this->get_oauth_token($_GET['code']);

            // Save oauth refresh token.
            $this->save_oauth_refresh_token($response['refresh_token']);

            // here's your token to use in API requests
            return $response['token_type'] . " " . $response['access_token'];
        }

        // Return access token based on refresh token from the database, if present.
        $field_data = ee()->db->select('field_id_385 AS refresh_token')
        ->from('channel_data_field_385')
        ->where('entry_id', 1)
        ->get()
        ->result_array();

        if (!empty($field_data[0]['refresh_token'])) {

            $response = $this->refresh_oauth_token($field_data[0]['refresh_token']);

            $refresh_token = $response['refresh_token'];

            // Save oauth refresh token.
            $this->save_oauth_refresh_token($refresh_token);

            // here's your token to use in API requests
            return $response['token_type'] . " " . $response['access_token'];
        }

        // Get oauth token and await callback
        // This will be a redirect with the code in a GET parameter.
        $this->get_oauth_code();
    }

    /**
    * Get Oauth code.
    *
    * @return void
    */
    private function get_oauth_code(){
        
        //Prepare URL query.
        $queryfields = [
            "response_type"	=> "code",
            "client_id"		=> $this->oauth_client_id,
            "redirect_uri" 	=> $this->oauth_redirect_uri,
            "force_login"	=> 1
        ];

        // Redirect.
        header("Location: " . $this->oauth_endpoint . "auth?" . http_build_query($queryfields)); 
    }

    /**
    * Get Oauth token.
    *
    * @param string code
    *
    * @return array
    */
    private function get_oauth_token($code) {

        //Prepare postfields.
        $postfields = [
            "grant_type"	=> "authorization_code",
            "code"			=> rawurldecode($code),
            "client_id"		=> $this->oauth_client_id,
            "client_secret" => $this->oauth_client_secret,
            "redirect_uri" 	=> $this->oauth_redirect_uri
        ];

        $response = ee()->curl->do($this->oauth_endpoint . "token", $postfields);

        if (isset($response['error'])) {
            echo "<pre>";
            print_r($response);
            echo "</pre>";
            die($response['error']);
        }
        
        return $response;
    }

    /**
    * Save Oauth refresh token to EE.
    *
    * @param string refresh_token
    *
    * @return void
    */
    private function save_oauth_refresh_token($refresh_token) {
        if (!empty($refresh_token)) {
            // Save refresh token.
            ee()->db->update(
                'channel_data_field_385',
                ['field_id_385'  => $refresh_token],
                ['entry_id' => '1']
            );
        }
    }

    /**
    * Refresh Oauth token.
    *
    * @param string refresh_token
    *
    * @return array
    */
    private function refresh_oauth_token($refresh_token) {

        //Prepare postfields.
        $postfields = [
            "grant_type"	=> "refresh_token",
            "refresh_token"	=> $refresh_token,
            "client_id"		=> $this->oauth_client_id,
            "client_secret" => $this->oauth_client_secret
        ];

        $response = ee()->curl->do($this->oauth_endpoint . "token", $postfields);

        // We can refresh the token only 570 seconds after receiving the previous token.
        $this->time_start = time();

        if (isset($response['error'])) {
            echo "<pre>";
            print_r($response);
            echo "</pre>";
            die($response['error']);
        }

        return $response;
    }
}