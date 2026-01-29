<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

Class Departments{

    /**
	 * Import departments into EE.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

    /**
	* Import departments into EE.
	*
	* @return void
	*/
    function import(){

        // ini_set('memory_limit', '2048M');
        set_time_limit(0);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);

        $channel_id = 47;

        $ee_fields_mapping = [
            "department_number"         => ["csv_column_index" => 1, "ee_field_id" => 439],
            "department_place"          => ["csv_column_index" => 2, "ee_field_id" => 440],
            "department_email"          => ["csv_column_index" => 4, "ee_field_id" => 441],
            "department_website"        => ["csv_column_index" => 5, "ee_field_id" => 442],
            "department_status"         => ["csv_column_index" => 9],
            "department_search_place"   => ["csv_column_index" => 10, "ee_field_id" => 450],
            "department_latitude"       => ["csv_column_index" => 12, "ee_field_id" => 443],
            "department_longitude"      => ["csv_column_index" => 13, "ee_field_id" => 444]
        ];

        $channel_data_excludes = [
            "department_place",
            "department_search_place",
            "department_status"
        ];

        $file_path = "/domains/sftp.web57.estserver.nl/inkomend/";
        $departments = ee()->shared->get_sftp_csv_to_array("afdrec.csv", $file_path, "|");        

        foreach($departments as $department){

            // Get GEO codes.
            $department_search_place = $this->filter_department_search_place($department[$ee_fields_mapping["department_search_place"]["csv_column_index"]]);
            $geo_codes = $this->get_geocode($department_search_place, $department[$ee_fields_mapping["department_place"]["csv_column_index"]]);
            $department[12] = (!empty($geo_codes["latitude"]) ? $geo_codes["latitude"] : "");
            $department[13] = ( !empty($geo_codes["longitude"]) ? $geo_codes["longitude"] : "");

            // Fix Website & email to lowercase.
            $department[$ee_fields_mapping["department_email"]['csv_column_index']] = strtolower($department[$ee_fields_mapping["department_email"]['csv_column_index']]);
            $department[$ee_fields_mapping["department_website"]['csv_column_index']] = strtolower($department[$ee_fields_mapping["department_website"]['csv_column_index']]);

            // Check if department is present.
            $department_number = $department[$ee_fields_mapping["department_number"]["csv_column_index"]];
            $ee_department = ee()->shared->get_ee_department_by_number($department_number);

            if($ee_department){
                
                // Update EE entry.
                $entry_id = $ee_department["entry_id"];
                $filtered_ee_fields_mapping = ee()->shared->exclude_fields($ee_fields_mapping, $channel_data_excludes);
                ee()->shared->update_channel_data_fields($filtered_ee_fields_mapping, $department, $entry_id);

            }else{
                
                // Insert EE entry.
                $department_title = ucfirst(strtolower($department[$ee_fields_mapping["department_place"]["csv_column_index"]]));
                $department_status = $department[$ee_fields_mapping["department_status"]["csv_column_index"]];
                $entry_id = ee()->shared->insert_channel_title($channel_id, $department_title, $department_status);
                $filtered_ee_fields_mapping = ee()->shared->exclude_fields($ee_fields_mapping, $channel_data_excludes);
                ee()->shared->insert_channel_data($channel_id, $filtered_ee_fields_mapping, $department, $entry_id);

            }

            $search_place = ee()->shared->get_ee_place($department_search_place);
            if($search_place){
                ee()->shared->add_relationship($entry_id, $search_place["entry_id"], $ee_fields_mapping["department_search_place"]["ee_field_id"]);
            }

        }

        // Ping https://healthchecks.io/
        if(ENV == "prod"){
            // file_get_contents('');
        }

    }

    /**
	* Transform department search place.
    *
    * @param string department_search_place
	*
	* @return string
	*/
    private function filter_department_search_place($department_search_place){
        $department_search_place = ucfirst(strtolower($department_search_place));
        // Remove apostrophes, multiple spaces etc.
        $department_search_place = str_replace('\'','', $department_search_place);
        $department_search_place = preg_replace('!\s+!', ' ', $department_search_place);
        $department_search_place = str_replace(' nederland','', $department_search_place);
        return $department_search_place;
    }

    /**
	* Get Google maps geocode for given place.
    *
    * @param string department_search_place
	*
	* @return array|boolean
	*/
    private function get_geocode($department_search_place, $department_place) {

        // Google maps API limit!
        // sleep(1);
    
        $geo = file_get_contents("https://maps.google.com/maps/api/geocode/json?address=".urlencode($department_search_place)."&sensor=false&key=" . ee()->config->item('gmaps_key'));
 
        $coords = json_decode($geo);

        if($coords->status != "ZERO_RESULTS") {
            // die($department_place);
            return [
                "latitude" => $coords->results[0]->geometry->location->lat, 
                "longitude" => $coords->results[0]->geometry->location->lng
            ];
        }

        return FALSE;
    
    }

}