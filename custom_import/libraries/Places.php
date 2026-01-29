<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

Class Places{

    /**
	 * Import places into EE.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

    /** @var int EE channel ID for places */
    private $channel_id = 46;

    /** @var int EE department place field ID */
    private $department_place_field_id = 440;

    /**
    * Import places from CSV into EE.
    *
    * @return void
    */
    function import(){

        $ee_fields_mapping = [
            "place_department_nr"   => ["csv_column_index" => 0],
            "place_name"            => ["csv_column_index" => 2],
            "place_zipcode_from"    => ["csv_column_index" => 3, "ee_field_id" => 447],
            "place_zipcode_to"      => ["csv_column_index" => 4, "ee_field_id" => 448]
        ];

        $channel_data_excludes = [
            "place_name",
            "place_department_nr"
        ];

        // Read file into  temp table
        $local_file = "wpl.csv";
        $file_path = "/domains/sftp.web57.estserver.nl/inkomend/";
        $places = ee()->shared->get_sftp_csv_to_array("wpl.csv", $file_path, "|");

        foreach($places as $place){

            // Check if place is present.
            $place_name = ucfirst(strtolower(utf8_decode(trim($place[$ee_fields_mapping["place_name"]["csv_column_index"]]))));
            if(!empty($place_name)){

                $ee_place = ee()->shared->get_ee_place($place_name);

                if($ee_place){
                    
                    // Update EE entry.
                    $entry_id = $ee_place["entry_id"];
                    $filtered_ee_fields_mapping = ee()->shared->exclude_fields($ee_fields_mapping, $channel_data_excludes);
                    ee()->shared->update_channel_data_fields($filtered_ee_fields_mapping, $place, $entry_id);

                }else{
                    
                    // Insert EE entry.
                    $entry_id = ee()->shared->insert_channel_title($this->channel_id, $place_name);
                    $filtered_ee_fields_mapping = ee()->shared->exclude_fields($ee_fields_mapping, $channel_data_excludes);
                    ee()->shared->insert_channel_data($this->channel_id, $filtered_ee_fields_mapping, $place, $entry_id);

                }

                $place_department_nr = $place[$ee_fields_mapping["place_department_nr"]["csv_column_index"]];
                $ee_department = ee()->shared->get_ee_department_by_number($place_department_nr);
                if($ee_department){
                    ee()->shared->add_relationship($ee_department["entry_id"], $entry_id, $this->department_place_field_id);
                }

            }

        }

        // Ping https://healthchecks.io/
        if(ENV == "prod"){
            file_get_contents('https://hc-ping.com/1c2a1381-97d9-4396-904c-661cd6d27f1a');
        }
    }

}