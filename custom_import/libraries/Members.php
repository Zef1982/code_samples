<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Members{

	/**
	 * Import members into EE.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/** @var array Mapping of CSV fields & EE member data fields. */
	private $ee_fields_mapping = [
		"afd_nr"        => ["csv_column_index" => 1, "ee_field_id" => 28],
		"lid_nr"        => ["csv_column_index" => 2, "ee_field_id" => 59],
		"geslacht"      => ["csv_column_index" => 3, "ee_field_id" => 7],
		"voornaam"      => ["csv_column_index" => 4, "ee_field_id" => 8],
		"voorletters"   => ["csv_column_index" => 5, "ee_field_id" => 25],
		"voorvoegsels"  => ["csv_column_index" => 6, "ee_field_id" => 26],
		"naam"       	=> ["csv_column_index" => 7, "ee_field_id" => 2],
		"adres"      	=> ["csv_column_index" => 9, "ee_field_id" => 3],
		"huis_nr"       => ["csv_column_index" => 10, "ee_field_id" => 21],
		"postcode_1"    => ["csv_column_index" => 11, "ee_field_id" => 22],
		"postcode_2"    => ["csv_column_index" => 12, "ee_field_id" => 22],
		"woonplaats"    => ["csv_column_index" => 13, "ee_field_id" => 23],
		"tel_nr"   		=> ["csv_column_index" => 15, "ee_field_id" => 4],
		"soort_lid"     => ["csv_column_index" => 16, "ee_field_id" => 33],
		"email"      	=> ["csv_column_index" => 23, "ee_field_id" => 30]
	];

	/**
    * Import members from CSV into EE.
    *
    * @return void
    */
    public function import(){
		
        // Security key.
        if(isset($_GET['key']) && $_GET['key'] == ee()->config->item('import_members_key')){
    
            // Get member mutations from SFTP intro array.
            $file_name ="ledengemuteerd.csv";
            $file_path = "/domains/sftp.web57.estserver.nl/inkomend/";
            $member_mutations = ee()->shared->get_sftp_csv_to_array($file_name, $file_path, "|", TRUE);

            // Import members in EE.
            foreach($member_mutations as $key => $member_mutation) {
                // Skip the header row.
                if($key > 0){

                    $email = strtolower($member_mutation[$this->ee_fields_mapping['email']['csv_column_index']]);

                    if(!empty($email)){
                        
                        // Get enddate cell value.
                        $einddatum = $member_mutation[21];

                        // Check if member is present in EE.
                        $members = $this->get_member_by_email($email);
                        $member_id = (empty($members[0]['member_id'])? FALSE: $members[0]['member_id']);

                        if(is_numeric($member_id)){

                            // Check if member needs to be deleted.
                            if(!empty($einddatum) && strtotime($einddatum) < time()){


                                // Delete member
                                $this->delete_member($member_id);

                            }else{

                                // Update member.
                                $this->update_member($member_mutation, $member_id);
                            }
                            
                        }elseif(empty($einddatum)){

                            // Else, if no enddate is present (!), insert member!
                            $password = random_string('alnum', 10);
                            $member_id = $this->insert_member($email, $password, $member_mutation);		
                            if(is_numeric($member_id)){
                                $this->email_new_member($email, $password);
                            }

                        }

                    }
                }
            }

            // Ping https://healthchecks.io/
			if(ENV == "prod"){
				file_get_contents('https://hc-ping.com/729ceebc-0062-46a1-bac8-23f10e02afd2');
			}

        }else{

            header("location:/");

        }
    }

	/**
    * Get member by email from EE.
	*
	* @param string email
    *
    * @return array
    */
    private function get_member_by_email($email){
		return ee()->db->select('member_id')
				->from('members')
				->where(["email" => $email])
				->get()
				->result_array();
	}

	/**
    * Delete member from EE.
	*
	* @param int member_id
    *
    * @return void
    */
	private function delete_member($member_id){		
		// Delete member data.
		ee()->db->delete(
			'member_data',
			array(
				'member_id' => $member_id
			)
		);

		// Delete member.
		ee()->db->delete(
			'members',
			array(
				'member_id' => $member_id
			)
		);
	}

	/**
    * Insert member in EE.
	*
	* @param string email
	* @param string password
	* @param array member_mutation
    *
    * @return int new member ID
    */
	private function insert_member($email, $password, $member_mutation){

		ee()->load->model('member_model');
		ee()->load->library('auth');

		$password_array = ee()->auth->hash_password($password);

		$new_member = [];				
		$new_member['username'] = $email;
		$new_member['email'] = $email;			
        $new_member['role_id'] = 5;
        $new_member['screen_name'] = $email;
        $new_member['password'] = $password_array['password'];
        $new_member['unique_id'] = ee('Encrypt')->generateKey();		
        $new_member['join_date'] = ee()->localize->now;
        $new_member['language'] = ee()->config->item('deft_lang');		
        $new_member['timezone']   = (ee()->config->item('default_site_timezone') && ee()->config->item('default_site_timezone') != '') ? ee()->config->item('default_site_timezone') : ee()->config->item('server_timezone');
        $new_member['time_format'] = (ee()->config->item('time_format') && ee()->config->item('time_format') != '') ? ee()->config->item('time_format') : 'us';
		
		$custom_fields = $this->get_member_data_fields($member_mutation);

		return ee()->member_model->create_member($new_member, $custom_fields);	
	}

	/**
    * Update member in EE.
	*
	* @param array member_mutation
	* @param int member_id
    *
    * @return void
    */
	private function update_member($member_mutation, $member_id){
		$custom_fields = $this->get_member_data_fields($member_mutation);
		// Update member.
		ee()->db->update(
			'member_data',
			$custom_fields,
			['member_id' => $member_id]
		);
	}

	/**
    * Get array of fields to be updated using mapping array.
	*
	* @param array member_mutation
    *
    * @return array
    */
	private function get_member_data_fields($member_mutation){
		$custom_fields = [];
		foreach($this->ee_fields_mapping as $name => $ee_field_mapping){
			// Merge postcode fields into 1 field.
			if("postcode_1" == $name){
				$value = $member_mutation[$ee_field_mapping['csv_column_index']];
			}elseif("postcode_2" == $name){
				$value .= " " . $member_mutation[$ee_field_mapping['csv_column_index']];
			}elseif("lid_nr" == $name){
				// Lid_nr must be an integer!
				$value = (empty($member_mutation[$ee_field_mapping['csv_column_index']])? 0 : $member_mutation[$ee_field_mapping['csv_column_index']]);
			}else{
				// Default value.
				$value = $member_mutation[$ee_field_mapping['csv_column_index']];
			}	
			$custom_fields['m_field_id_' . $ee_field_mapping['ee_field_id']] = $value;
		}
		return $custom_fields;
	}

	/**
    * Send email to new member.
	*
	* @param string email
	* @param string password
    *
    * @return void
    */
	private function email_new_member($email, $password){

		// Prepare email template.
		$variables = [
			"email"	=> $email,
			"password" => $password
		];
		$mail_template = ee()->TMPL->fetch_template('email', '_member_import', FALSE);
		$parsed_mail_template = ee()->TMPL->parse_variables($mail_template, [$variables]);

		// ENV mail security!
		if(ENV == "prod"){
			$this->send_mail($email, 'Inloggegevens', $parsed_mail_template);
		}
	}
	
	/**
    * Send email using EE library.
	*
	* @param string email
	* @param string subject
	* @param string body
	* @param string from
	* @param string from_name
	* @param array attachments
    *
    * @return boolean
    */
	private function send_mail($email, $subject, $body, $from = "", $from_name = "", $attachments = []){			
		ee()->load->library('email');		
		ee()->email->clear(true);
		ee()->email->initialize(['mailtype' => 'html']);		
		ee()->email->from($from, $from_name);
		ee()->email->to($email);
		ee()->email->reply_to($from, $from_name);		
		ee()->email->subject($subject);
		ee()->email->message($body);		
		foreach((array)$attachments as $attachment){					
			ee()->email->attach($attachment);
		}		
		if(ee()->email->send()){
			return true;
		}else{
			return false;
		}		
	}

}