<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Shared{

    /**
	 * Library for shared functionality in this plugin.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

    /**
    * Get CSV contents from SFTP and pour into array.
    *
    * @param string file_name
    * @param string file_path
    * @param string delimiter
    * @param boolean archive
    * @param boolean local
    *
    * @return array|boolean
    */
    public function get_sftp_csv_to_array($file_name, $file_path, $delimiter=";", $archive=FALSE, $local=FALSE) {

		$sftp_host = ee()->config->item('sftp_host');
		$sftp_user = ee()->config->item('sftp_user');
		$sftp_password = ee()->config->item('sftp_password');

		if($local){
			$file = $file_path . $file_name;
		}else{
			$file = "ftp://" . $sftp_user . ":" . $sftp_password . "@" . $sftp_host . $file_path . $file_name;
		}
		
		if (file_exists($file)) {

			if($archive){
				// Archive file.
				$archive_file = explode(".", $file_name);
				$archive_file_name = $archive_file[0] . "_" . date("d-m-Y") . "." . $archive_file[1];
				$archive_file = "ftp://" . $sftp_user . ":" . $sftp_password . "@" . $sftp_host . $file_path . $archive_file_name;
				copy($file, $archive_file);
			}

			// Create array with data.
			$data = [];
			if (($handle = fopen($file, 'r')) !== FALSE) {
				while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
					$data[] = $row;
				}
				fclose($handle);
			}
			return $data;
		}else{
            echo $file . " Bestand bestaat niet";
            exit;
        }
	}

    /**
    * Exclude fields from EE field mapping.
    *
    * @param array ee_fields_mapping
    * @param array channel_data_excludes
    *
    * @return array
    */
	public function exclude_fields($ee_fields_mapping, $channel_data_excludes){
        foreach($ee_fields_mapping as $key => $ee_field_mapping){
            if(in_array($key, $channel_data_excludes)){
                unset($ee_fields_mapping[$key]);
            }
        }
        return $ee_fields_mapping;
    }

    /**
    * Insert channel title into EE.
    *
    * @param int channel_id
    * @param string title
    * @param int status
    * @param int enddate
    *
    * @return int ID of inserted title
    */
    public function insert_channel_title($channel_id, $title, $status=1, $enddate=0){

        $channel_title_fields = $this->get_channel_title_fields($channel_id, $title, $status, $enddate);

        ee()->db->insert(
            "channel_titles",
            $channel_title_fields
        );

        return ee()->db->insert_id();

    }

    /**
    * Update channel title into EE.
    *
    * @param int channel_id
    * @param string title
    * @param int status
    * @param int enddate
    *
    * @return void
    */
    public function update_channel_title($channel_id, $entry_id, $title, $status=1, $enddate=0){

        $channel_title_fields = $this->get_channel_title_fields($channel_id, $title, $status, $enddate);

        ee()->db->update(
			"channel_titles",
			$channel_title_fields,
			["entry_id" => $entry_id]
		);
    }

    /**
    * Update channel data into EE.
    *
    * @param int channel_id
    * @param array ee_fields_mapping
    * @param array department
    * @param int entry_id
    *
    * @return void
    */
    public function insert_channel_data($channel_id, $ee_fields_mapping, $department, $entry_id){

        // Insert channel data.
        ee()->db->insert(
            "channel_data",
            [
                "site_id" => 1,
                "entry_id" => $entry_id,
                "channel_id" => $channel_id
            ]
        );

        // Insert channel data fields.
        foreach($ee_fields_mapping as $key => $ee_field_mapping){
            
                ee()->db->insert(
                    "channel_data_field_" . $ee_field_mapping["ee_field_id"],
                    [
                        "field_id_" . $ee_field_mapping["ee_field_id"] => $department[$ee_field_mapping["csv_column_index"]],
                        "entry_id" => $entry_id
                    ]
                );
                

        }
    }

    /**
    * Update channel data fields in EE.
    *
    * @param array ee_fields_mapping
    * @param array csv_data
    * @param int entry_id
    *
    * @return void
    */
	public function update_channel_data_fields($ee_fields_mapping, $csv_data, $entry_id){

        foreach($ee_fields_mapping as  $key => $ee_field_mapping){

            ee()->db->update(
                "channel_data_field_" . $ee_field_mapping["ee_field_id"],
                ["field_id_" . $ee_field_mapping["ee_field_id"] => $csv_data[$ee_field_mapping["csv_column_index"]]],
                ["entry_id" => $entry_id]
            );

        }

    }

    /**
    * Get department from EE.
    *
    * @param int department_number
    *
    * @return array|boolean
    */
    public function get_ee_department_by_number($department_number){

        $ee_departments = ee()->db->select("entry_id")
            ->from("channel_data_field_439")
            ->where("field_id_439", $department_number)
            ->get()
            ->result_array();

        return (isset($ee_departments[0]) ? $ee_departments[0] : FALSE);

    }

    /**
    * Get place from EE.
    *
    * @param string place_name
    *
    * @return array|boolean
    */
    public function get_ee_place($place_name){

        $places = ee()->db->select("entry_id")
            ->from("channel_titles")
            ->where(
                [
                    'url_title' => ee('Format')->make('Text', $place_name)->urlSlug()->compile(),
                    'channel_id'=> 46
                ]
            )
            ->get()
            ->result_array();

        return (isset($places[0]) ? $places[0] : FALSE);
    }

    /**
    * Add relationship to EE.
    *
    * @param int parent_id
    * @param int child_id
    * @param int field_id
    *
    * @return void
    */
    public function add_relationship($parent_id, $child_id, $field_id){

        // Insert relationship if not present.
        $relationship = $this->get_relationship($parent_id, $child_id, $field_id);
        if(!$relationship){
            ee()->db->insert(
                "relationships",
                [
                    "parent_id" => $parent_id,
                    "child_id"  => $child_id,
                    "field_id"  => $field_id
                ]
            );
        }

    }

    /**
    * Get channel title fields from EE.
    *
    * @param int channel_id
    * @param string title
    * @param int status
    * @param int enddate
    *
    * @return array
    */
    private function get_channel_title_fields($channel_id, $title, $status=1, $enddate=0){

        $channel_title_fields = [
            "site_id"           => 1,
            "channel_id"        => $channel_id,
            "author_id"         => 2144360,
            "title"             => $title,
            "url_title"         => ee('Format')->make('Text', $title)->urlSlug()->compile(),
            "ip_address"        => $_SERVER["REMOTE_ADDR"],
            "status"            => ($status == 1? "open" : "closed"),
            "status_id"         => ($status == 1? 1 : 2),
            "entry_date"        => time(),
            "year"              => date("Y"),
            "month"             => date("m"),
            "day"               => date("d"),
            "expiration_date" 	=> $enddate,
        ];

		return $channel_title_fields;
	}

    /**
    * Get relationship from EE.
    *
    * @param int parent_id
    * @param int child_id
    * @param int field_id
    *
    * @return array|boolean
    */
    private function get_relationship($parent_id, $child_id, $field_id){
        
        $relationships = ee()->db->select("*")
                ->from("relationships")
                ->where(
                    [
                        "parent_id" => $parent_id,
                        "child_id"  => $child_id,
                        "field_id"  => $field_id
                    ]
                )
                ->get()
                ->result_array();
        
        return (isset($relationships[0]) ? $relationships[0] : FALSE);
    }

}