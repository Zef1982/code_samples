<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Secretaries{

	/**
	 * Import secretaries into EE.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */
	
	/**
    * Import secretaries from CSV into EE.
    *
    * @return void
    */
	function import(){
		
		// Security key.
		if(isset($_GET['key']) && $_GET['key'] == ee()->config->item('import_secretaries_key')){
		
			// Get member mutations from SFTP intro array.
			$file_name ="secretarydatatest.csv";
			$file_path = "/domains/sftp.web57.estserver.nl/inkomend/";
			$secretaries = ee()->shared->get_sftp_csv_to_array($file_name, $file_path, ";", FALSE);

			// Import members in EE.
			foreach($secretaries as $key => $secretary) {
	
				// Skip the header row.
				if($key > 0){

					// Get member ID.
					$member_number = $secretary[2];
					$member = $this->get_member_by_id($member_number);

					// Get department ID.
					$department_number = $secretary[1];
					$department = ee()->shared->get_ee_department_by_number($department_number);

					if($department && $member){

						// Check if secretary is present for department.
						$secretaries = ee()->db->select("entry_id")
							->from("channel_data_field_445")
							->where(
								[
									"field_id_445" 	=> $member["member_id"],
									"entry_id" 		=> $department["entry_id"]
								]
							)
							->get()
							->result_array();
						
						// Insert if not present.
						if(!isset($secretaries[0])){

							ee()->db->insert(
								"channel_data_field_445",
								[
									"field_id_445" 	=> $member["member_id"],
									"entry_id" 		=> $department["entry_id"]
								]
							);
						}
						

					}
					
					
				}
			}

			if(ENV == "prod"){
				// Ping https://healthchecks.io/
				file_get_contents('https://hc-ping.com/73e9d0de-3abc-44e9-84ec-b76993d376b5');
			}

		}else{

			header("location:/");

		}

	}

	/**
    * Get member by secretary member nr from EE.
	*
	* @param int secretary_member_nr
    *
    * @return array
    */
	private function get_member_by_id($secretary_member_nr){

		$member_data = ee()->db->select("member_id")
			->from("member_data")
			->where("m_field_id_59", $secretary_member_nr )
			->get()
			->result_array();

		return (isset($member_data[0])? $member_data[0] : FALSE);
	}


}