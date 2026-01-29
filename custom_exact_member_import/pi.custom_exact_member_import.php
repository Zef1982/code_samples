<?php

/**
   * ExpressionEngine cronjob plugin to import members from Exact.
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

$plugin_info = [
    'pi_name' => 'Member import',
    'pi_version' => '0.1.0',
    'pi_author' => 'Est Digital',
    'pi_author_url' => 'http://www.estdigital.nl/',
    'pi_description' => 'Custom Exact member import functionality',
    'pi_usage' => '{exp:custom_exact_member_import}'
];

class Custom_exact_member_import
{
	
	/**
   * Custom 123test plugin for ExpressionEngine.
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */

	/**
		* Constructor to load used libraries.
		*
		* @return void
		*/
	function __construct(){
		ee()->load->library('oauth');
		ee()->load->library('curl');
		ee()->load->library('exact');
	}

	/**
		* Import members into EE.
		*
		* @return void
		*/
	public function import_members(){

		// A bit of of security.
		if(isset($_GET['key']) && $_GET['key'] == ee()->config->item('member_import_key')){

			$new_member_emails = ee()->exact->get_new_members();
			
			foreach ($new_member_emails as $new_member_email){
				if(isset($new_member_email)){
					// Clean the emails!
					$new_member_email = ee()->db->escape_str(trim(strtolower($new_member_email)));
					if(!empty($new_member_email) && filter_var($new_member_email, FILTER_VALIDATE_EMAIL)){
						
						// Pasword to be encryped during member insert AND to be sent in email to the member!
						$password = random_string('alnum', 10);
						$member_id = $this->insert_ee_member($new_member_email, $password);		
						if(is_numeric($member_id)){
							
							// Email new member.	
							$variables = [
								"email"	=> $new_member_email,
								"password" => $password
							];
							$mail_template = ee()->TMPL->fetch_template('email', 'member_import', FALSE);
							$parsed_mail_template = ee()->TMPL->parse_variables($mail_template, [$variables]);
							
							// ENV mail security!
							switch(ENV){
								
								case "prod":
									$this->sendMail($new_member_email, 'digitale Onze Taal', $parsed_mail_template);
									break;
									
								case "dev":
									die($parsed_mail_template);
									break;
								
								default:
									$this->sendMail("zef@estdigital.nl", 'digitale Onze Taal', $parsed_mail_template);
							}
						}	
					}else{
						echo $new_member_email." is not valid<br />";
					}
				}
			}
		}

		// Ping https://healthchecks.io/
		file_get_contents('https://hc-ping.com/ce87ae60-49ec-455e-81e7-90cffd4c9d49');

		exit;
	}

	/**
		* Delete members from EE.
		*
		* @return void
		*/
	public function delete_members(){

		// A bit of of security.
		if(isset($_GET['key']) && $_GET['key'] == ee()->config->item('member_import_key')){

			// Get deleted members from Exact.
			$deleted_member_emails = ee()->exact->get_deleted_members();

			// Sometimes there's too many deletes!
			if(count($deleted_member_emails) > 50 && !isset($_GET["ignore_ex_member_limit"])) {

				$variables = [
					'member_import_key' => ee()->config->item('member_import_key'),
					'count_deleted_member_emails' => count($deleted_member_emails)
				];
				$mail_template = ee()->TMPL->fetch_template('email', 'member_delete', FALSE);
				$parsed_mail_template = ee()->TMPL->parse_variables($mail_template, [$variables]);
				
				// ENV mail security!
				switch(ENV){
					
				 	case "prod":
						// Save ex members to CSV file.
						$attachment = dirname($_SERVER["DOCUMENT_ROOT"]) . "/unsubscriptions/unsubscriptions.csv";
						$handle = fopen($attachment, "w");
						fwrite($handle, implode(PHP_EOL, $deleted_member_emails));

						$this->sendMail("Leonie.Flipsen@onzetaal.nl, zef+onzetaalapi@estdigital.nl", "Teveel uitschrijvingen Onze Taal", $parsed_mail_template, "webredactie@onzetaal.nl", "Onze Taal", $attachment);
						break;

					case "dev":
						die($parsed_mail_template);
						break;
						
					default:
						$this->sendMail("zef+onzetaalapi@estdigital.nl", "Teveel uitschrijvingen Onze Taal", $parsed_mail_template);
				}

			}else{

				// Delete members.
				foreach($deleted_member_emails as $deleted_member_email){

					$this->delete_member($deleted_member_email);

				}
			}
		}

		// Ping https://healthchecks.io/
		file_get_contents('https://hc-ping.com/e5133fca-8b4b-4b78-a551-0e470cfcce7e');

		exit;
	}

	/**
		* Insert member into EE.
		*
		* @return int member ID of created member.
		*/
	private function insert_ee_member($new_member_email, $password){
		ee()->load->model('member_model');
		ee()->load->library('auth');
		$password_array = ee()->auth->hash_password($password);
        // Required: username, password, email, screen_name, role_id		
		$new_member = [];				
		$new_member['username'] = $new_member_email;
		$new_member['email'] = $new_member_email;			
        $new_member['role_id'] = 7; // = Digitaal tijdschrift!
        $new_member['screen_name'] = $new_member_email;
        $new_member['password'] = $password_array['password'];
        $new_member['unique_id'] = ee('Encrypt')->generateKey();		
        $new_member['join_date'] = ee()->localize->now;
        $new_member['language'] = ee()->config->item('deft_lang');		
        $new_member['timezone']   = (ee()->config->item('default_site_timezone') && ee()->config->item('default_site_timezone') != '') ? ee()->config->item('default_site_timezone') : ee()->config->item('server_timezone');
        $new_member['time_format'] = (ee()->config->item('time_format') && ee()->config->item('time_format') != '') ? ee()->config->item('time_format') : 'us';
		// Check if email address isn't present already.
		$present_members = ee()->db->select('email')
			->from('members')
			->where("role_id", 7)
			->where("email", $new_member_email)
			->get();
		if($present_members->num_rows() == 0){
        	return ee()->member_model->create_member($new_member, []);	
		}
	}
	
	/**
		* Send control email.
		*
		* @return boolean
		*/
	private function sendMail($email, $subject, $body, $from = "webredactie@onzetaal.nl", $fromname = "Onze Taal", $attach = []){
			
		ee()->load->library('email');
		$config['mailtype'] = 'html';		
		ee()->email->clear(true);
		ee()->email->initialize($config);		
		ee()->email->from($from, $fromname);
		ee()->email->to($email);
		ee()->email->reply_to($from, $fromname);		
		ee()->email->subject($subject);
		ee()->email->message($body);	

		foreach((array)$attach as $attachment){					
			ee()->email->attach($attachment);
		}		
		
		if(ee()->email->send()){
			return true;
		}else{
			return false;
		}		
	}

	/**
		* Delete member from EE.
		*
		* @return void
		*/
	private function delete_member($deleted_member_email){
		$deleted_member_email = ee()->db->escape_str(trim(strtolower($deleted_member_email)));

		$members = ee()->db->select("member_id")
			->from("members")
			->where("email", $deleted_member_email)
			->get()
			->result_array();

		foreach($members as $member){

			// Delete member data.
			ee()->db->delete(
				"member_data",
				["member_id" => $member["member_id"]]
			);

			// Delete member.
			ee()->db->delete(
				"members",
				["member_id" => $member["member_id"]]
			);

		}
	}

}
