<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Custom_123test {

   /**
   * Custom 123test plugin for ExpressionEngine.
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

   /** @var string will contain the 123test API domain. */
  private $api_domain;

  /** @var string will contain the 123test user id. */
  private $api_user_id;

  /** @var string will contain the 123test public key. */
  private $api_public_key;

  /** @var string will contain the 123test private key. */
  private $api_private_key;

  /** @var string 123test API version. */
  private $api_version = "v2";

  /** @var int Craft database field ID of the 123test UUID. */
  private $member_uuid_field_id = 6;

  /** @var int Craft database channel ID of the 123test. */
  private $member_test_channel_id = 11;

  /** @var int Craft database member field ID of the 123test. */
  private $member_test_member_field_id = 63;

  /** @var int Craft database product field ID of the 123test. */
  private $member_test_product_field_id = 64;

  /** @var int Craft database test complete field ID of the 123test. */
  private $member_test_complete_field_id = 66;

  /** @var int Craft database product access code field ID of the 123test. */
  private $member_test_product_access_code_field_id = 67;

  /** @var int Craft database test channel ID of the 123test. */
  private $test_channel_id = 12;

  /** @var int Craft database title field ID of the 123test. */
  private $test_title_field_id = 74;

  /** @var int Craft database product field ID of the 123test. */
  private $test_product_field_id = 69;

  /**
    * Fill API credentials with vars from the Craft config.
    *
    * @return void
    */
  function __construct() {

    // Get credentials form ENV config.
    $this->api_domain = ee()->config->item('api_domain');
    $this->api_user_id = ee()->config->item('api_user_id');
    $this->api_public_key =  ee()->config->item('api_public_key');
    $this->api_private_key = ee()->config->item('api_private_key');

    // English vars override.
    if("en" == ee()->uri->segment(1)){
      $this->member_test_channel_id = 32;
      $this->member_test_member_field_id = 146;
      $this->member_test_product_field_id = 149;
      $this->member_test_complete_field_id = 150;
      $this->member_test_product_access_code_field_id = 152;
      $this->test_channel_id = 31;
      $this->test_title_field_id = 145;
      $this->test_product_field_id = 129;
    }
  }

  public function get_api_domain(){
    return $this->api_domain;
  }

  public function get_api_public_key(){
    return $this->api_public_key;
  }

  /**
   * Saves product overview data for current member.
   * 
   * @return void
   */

  public function save_product() {

    // This product access code is member specific!
    $product_access_code = ee()->TMPL->fetch_param('product_access_code');

    if ($this->is_valid_uuid($product_access_code)) { 

      // Get overview data from 123test API.
      $product_id = ee()->TMPL->fetch_param('product_id');

      // Get product info for specific test.
      $overview = $this->get_product_overview($product_access_code);

      // Any complete tests in here for this specific test?
      $complete = null;
      foreach($overview["reports"] as $report){
        if($report["complete"]){
           $complete = "yes";
        }
      }

      // Check if test is present for current member.
      $member_id = ee()->session->userdata('member_id');
      $data = [
          "channel_id" => $this->member_test_channel_id,
          "field_id_" . $this->member_test_member_field_id => $member_id,
          "field_id_" . $this->member_test_product_access_code_field_id => $product_access_code,
          "field_id_" . $this->member_test_product_field_id => $product_id,
          "site_id" => ee()->config->item("site_id")
      ];

      // Update or insert the test to the database.
      $member_test = $this->latest_member_test_from_db($data);      
      if (isset($member_test["entry_id"]) && is_numeric($member_test["entry_id"])) {

        $data["field_id_" . $this->member_test_complete_field_id] = $complete;
        ee()->db->update('channel_data', $data, ['entry_id' => $member_test["entry_id"]]);

      } else {

        $data["field_id_" . $this->member_test_complete_field_id] = $complete;
        $data["entry_id"] = $this->insert_member_test($member_id, $product_id);
        ee()->db->insert('channel_data', $data);

      }

    }
  }

  /**
   * Gets latest report HTML from teh 123test API based on the product ID.
   * 
   * @return void|string
   */

  public function get_latest_report() {

    $type = ee()->TMPL->fetch_param('type');
    $product_id = ee()->TMPL->fetch_param('product_id');

    $wheres_array = [
      "field_id_" . $this->member_test_product_field_id => $product_id,
      "field_id_" . $this->member_test_complete_field_id => "yes",
      "channel_id" => $this->member_test_channel_id,
      "field_id_" . $this->member_test_member_field_id => ee()->session->userdata('member_id'),
      "site_id" => ee()->config->item("site_id")
    ];

    $latest_member_test = $this->latest_member_test_from_db($wheres_array);

    
    if (isset($latest_member_test["product_access_code"])) {

      if($type == "report_present"){

        return "yes";

      }else{

        $reports = [];

        $overview_array = $this->get_product_overview($latest_member_test["product_access_code"]);

        foreach ($overview_array["reports"] as $overview_report) {

          // PDF type reports according to 123test.
          $pdf_report_types = [3, 121, 122, 123, 221, 222, 223];
          if (($type == "pdf") && in_array($overview_report["type"], $pdf_report_types)) { 

            // Spit out HTML or PDF download.
            header("Content-Disposition:attachment;filename=" . $product_id . "_report.pdf");
            echo $this->get_report($overview_report["access_code"]);
            exit;

          }

          // HTML type reports according to 123test.
          $html_report_types = [1, 2, 112, 113, 212, 213];
          if ($type == "html" && in_array($overview_report["type"], $html_repot_types)) { 

            echo $this->get_report($overview_report["access_code"]);
            exit;

          }
        }
      }
    } else {

      if($type == "report_present"){
        return "no";
      }

    }
  }

  /**
   * Gets completed tests for logged in member
   * Produces variables: completed_count, completed_total_results, product_entry_id, title & product_title, completed_entry_date
   * 
   * @return object ee with parsed template variables.
   */
  public function get_completed_tests() {

    $member_id = ee()->session->userdata('member_id');

    // Get all COMPLETED tests for specific member.
    $wheres_array = [
      "test_by_member_data.field_id_" . $this->member_test_member_field_id => $member_id,
      "test_by_member.channel_id" => $this->member_test_channel_id,
      "test_by_member_data.field_id_" . $this->member_test_complete_field_id => "yes",
      "test_by_member_data.site_id" => ee()->config->item("site_id")
    ];

    $member_tests = ee()->db->select('123test.entry_id as product_entry_id,
                                      123test.title AS default_title, 
                                      123test_data.field_id_' . $this->test_title_field_id . ' AS product_title,
                                      test_by_member.entry_date AS completed_entry_date')
      ->from('channel_data AS test_by_member_data')
      ->join('channel_data AS 123test_data', '123test_data.field_id_' . $this->test_product_field_id . ' = test_by_member_data.field_id_' . $this->member_test_product_field_id)
      ->join('channel_titles AS 123test', '123test.entry_id = 123test_data.entry_id')
      ->join('channel_titles AS test_by_member', 'test_by_member.entry_id = test_by_member_data.entry_id')
      ->where($wheres_array)
      ->order_by('123test.title', 'asc')
      ->get()
      ->result_array();

    $latest_tests = $this->filter_latest_tests($member_tests);

    // Add total results and remove keys.
    $parse_array = [];
    foreach ($latest_tests as $latest_test) {
      $latest_test["completed_results"] = count($latest_tests);
      $parse_array[] = $latest_test;
    }

    // Parse variables in template.
    if (count($parse_array) > 0) {
      return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $parse_array);
    } else {
      return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, [["completed_no_results" => "none whatsoever"]]);
    }
  }

  /**
   * Get tests that still need to be done according to 123test channel entries
   * Produces variables product_entry_id, product_id, default_title, product_title, todo_count, todo_total_results, completed
   * 
   * @return object ee with parsed template variables.
   */

  public function get_incomplete_tests() {

    $member_id = ee()->session->userdata('member_id');

    // Get ALL tests.
    $tests = ee()->db->select('123test.entry_id as product_entry_id,
                              123test_data.field_id_' . $this->test_product_field_id . ' AS product_id,
                              123test.title AS default_title,
                              123test_data.field_id_' . $this->test_title_field_id . ' AS product_title')
      ->from('channel_titles AS 123test')
      ->where(["123test.channel_id" => $this->test_channel_id])
      ->join("channel_data AS 123test_data", "123test_data.entry_id = 123test.entry_id")
      ->join("structure AS struct", "struct.entry_id = 123test.entry_id")
      ->order_by('struct.lft', 'asc')
      ->get();

    $tests_array = $tests->result_array();
    foreach ($tests_array as $test_key => $test) {

      // Check if member passed this test.
      $member_tests = ee()->db->select("field_id_" . $this->member_test_complete_field_id . " AS completed")
        ->from("channel_data")
        ->where([
          "channel_id" => $this->member_test_channel_id,
          "field_id_" . $this->member_test_member_field_id => $member_id,
          "field_id_" . $this->member_test_product_field_id => $test["product_id"],
          "site_id" => ee()->config->item("site_id")
        ])
        ->get();

      if ($member_tests->num_rows() > 0) {

        $difference = 0;

        foreach ($member_tests->result_array() as $member_test) {

          // Unset the test if completed.
          if ($member_test["completed"] == "yes") {

            unset($tests_array[$test_key]);
            $difference++;

          } else {

            // .. Otherwise set status to pending.
            $tests_array[($test_key - $difference)]["completed"] = "pending";

          }
        }
      } else {

        // If no attempts present, set status to NO.
        $tests_array[$test_key]["completed"] = "no";

      }
    }

    // Add total_results & count.
    $count = 0;
    $parse_array = [];
    foreach ($tests_array as $test_key => $test) {

      if (isset($test['product_entry_id'])) {

        $count++;
        $test["todo_count"] = $count;
        $test["todo_total_results"] = count($tests_array);
        $parse_array[] = $test;
        
      }
    }

    // Parse variables in template.
    if (count($parse_array) > 0) {

      return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $parse_array);

    } else {

      return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, [["todo_no_results" => "none whatsoever"]]);

    }
  }

  /**
   * Checks if current member has an UUID in it's  custom member data field, otherwise generates one and saves it to member data     * 
   * 
   * @return string member UUID.
   */
  public function get_member_uuid() {

    $member_id = ee()->session->userdata('member_id');

    // Select uuid custom member field from current member's data
    $members = ee()->db->select('m_field_id_' . $this->member_uuid_field_id . " AS 123test_uuid")
      ->from('member_data')
      ->where(array(
          'member_id' => $member_id
      ))
      ->get()
      ->result_array();

    foreach ($members as $member) {

      // Return uuid if valid or present? Otherwise create one, save and return it.
      if ($this->is_valid_uuid($member["123test_uuid"])) {

        return $member["123test_uuid"];

      } else {

        $uuid = $this->generate_uuid();

        // Update UUID for member!
        ee()->db->update(
          'member_data', 
          ['m_field_id_' . $this->member_uuid_field_id => $uuid], 
          ['member_id' => $member_id]
        );

        return $uuid;

      }
    }
  }

  /**
   * Filter latest tests.
   * 
   * @param array member_tests.
   * 
   * @return array latetst tests.
   */
  private function filter_latest_tests($member_tests){

    // Create array to collect latest tests.
    $latest_tests = [];

    $count = 0;
    foreach ($member_tests as $member_test) {

      // Keep track of the number of tests.
      $count++;

      // Add to array if not present yest or newer than same test in array.
      if (!isset($latest_tests[$member_test['product_entry_id']]) || 
          $member_test['completed_entry_date'] > $latest_tests[$member_test['product_entry_id']]['completed_entry_date']
      ) {

        // If test IS present, we don't have to count it again!
        if (isset($latest_tests[$member_test['product_entry_id']])) {
          $count--;
        }
        $member_test["completed_count"] = $count;

        $latest_tests[$member_test['product_entry_id']] = $member_test;

      }
    }

    return $latest_tests;
  }

  /**
   * Get report (HTML OR PDF) from 123test API based on report access code.
   * 
   * @param array string report_access_code.
   * 
   * @return object
   */

  private function get_report($report_access_code) {
    $handler =ee()->load->library('Its123Handler', [$this->api_user_id, $this->api_private_key, true]);
    $handler->setEndPoint('https', $this->api_domain, $this->api_version);
    return $handler->requestAction('/report/' . $report_access_code);
  }

  /**
   * Get LATEST member test from the database 
   * Used by this->save_product() & this->get_latest_report_html()
   * 
   * @param array wheres_array SQL segment.
   * 
   * @return array|boolean
   */

  private function latest_member_test_from_db($wheres_array) {

    $member_tests = ee()->db->select('entry_id, field_id_' . $this->member_test_product_access_code_field_id . ' AS product_access_code')
      ->from('channel_data')
      ->where($wheres_array)
      ->order_by('entry_id', 'desc')
      ->limit(1)
      ->get()
      ->result_array();

    foreach ($member_tests as $member_test) {
      return $member_test;
    }

    return false;

  }

  /**
   * Inserts member test in EE channel 123 member test channel.
   * 
   * @param int member_id
   * @param int product_id
   * 
   * @return int ID of inserted member test.
   */

  private function insert_member_test($member_id, $product_id) {

    $members = ee()->db->select('username')
      ->from('members')
      ->where(["member_id" => $member_id])
      ->get()
      ->result_array();

    foreach ($members as $member) {

      $url_title = ee('Format')->make('Text', $member["username"] . "-" . $product_id."-" . time())->urlSlug()->compile();

      ee()->db->insert(
        "channel_titles", 
        [
          "site_id" => ee()->config->item("site_id"),
          "channel_id" => $this->member_test_channel_id,
          "author_id" => $member_id,
          "title" => $member["username"] . " " . $product_id . " " . time(),
          "url_title" => $url_title,
          "status" => "open",
          "status_id" => 1,
          "versioning_enabled" => "n",
          "allow_comments" => "n",
          "sticky" => "n",
          "entry_date" => time(),
          "year" => date("Y"),
          "month" => date("m"),
          "day" => date("d")
        ]
      );

      return ee()->db->insert_id();
    }

  }

  /**
   * Get product overview for product access code
   * Used by this->save_product & this->get_latest_report_html
   * 
   * @param string product_access_code
   * 
   * @return array
   */

  private function get_product_overview($product_access_code = "") {

    //get overview data from 123test API
    $handler = ee()->load->library('Its123Handler', [$this->api_user_id, $this->api_private_key, true]);
    $handler = new Its123Handler($this->api_user_id, $this->api_private_key, true);
    $handler->setEndPoint('https', $this->api_domain, $this->api_version);
    return json_decode($handler->requestAction('/product/' . $product_access_code . '/overview'), true);

  }

  /**
   * Check if UUID is valid.
   * 
   * @param string uuid
   * 
   * @return boolean
   */

  private function is_valid_uuid($uuid) {

    $regex = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

    if (preg_match($regex, $uuid)) {
      return true;
    }

    return false;

  }

  /**
   * Generate UUID.
   * 
   * @return string
   */
  private function generate_uuid() {

    // return 'b99b17b2-6b53-44cb-a88d-d24e5a5f811c'; //test

    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      // 32 bits for "time_low"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      // 16 bits for "time_mid"
      mt_rand(0, 0xffff),
      // 16 bits for "time_hi_and_version",
      // four most significant bits holds version number 4
      mt_rand(0, 0x0fff) | 0x4000,
      // 16 bits, 8 bits for "clk_seq_hi_res",
      // 8 bits for "clk_seq_low",
      // two most significant bits holds zero and one for variant DCE1.1
      mt_rand(0, 0x3fff) | 0x8000,
      // 48 bits for "node"
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

  }

}
