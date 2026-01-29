<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

Class Events{

	/**
	 * Import events into EE.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/** @var int EE channel ID for events. */
 	private $channel_id = 5;

	/** @var array EE category IDs for events. */
    private $category_mapping = array(
		"Beurzen/tentoonstellingen" => 104,
		"Cursussen/Workshops" 		=> 103,
		"Workshops" 				=> 0,
		"Excursies" 				=> 102,
		"Lezingen" 					=> 105,
		"Open tuinen"				=> 101,
		"Overige activiteiten" 		=> 100
	);

	/** @var array EE field IDs for events. */
	private $field_mapping = [
		"description"		=> 58,
		"social_media"  	=> 59,
		"startdate"			=> 69,
		"enddate"			=> 70,
		"show_time"			=> 71,
		"name"				=> 62,
		"address"			=> 63,
		"zipcode"			=> 64,
		"city"				=> 79,
		"region"			=> 66,
		"location"			=> 73,
		"price"				=> 68,
		"department"		=> 158,
		"department_id"		=> 159,
		"uid"				=> 353
	];
	
	/**
    * Import events from XML into EE.
    *
    * @return void
    */
	public function import(){

		// Get XML into array.
		$xml_string = file_get_contents("agenda_export.xml");

		// Remove annoying characters.
		$invalid_characters = '/[^\x9\xa\x20-\xD7FF\xE000-\xFFFD]/';
		$xml_string = preg_replace($invalid_characters, '', $xml_string );

		// Trun it into a nice array.
		$events_xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
		$events_json = json_encode($events_xml);
		$events = json_decode($events_json, TRUE);

		// GET UID => entry_id mapping for events in EE.
		$ee_eent_uids = $this->get_ee_event_uids();

		foreach($events['entry'] as $event){

			// Apparently descriptions can accidetally be arrays sometimes...
			$event['description'] = (is_array($event['description']) ? "" : $event['description']);

			if($event['enddate'] > strtotime('1 month ago')){

				// Create title and url_title.
				$event['title'] = html_entity_decode(mb_convert_encoding(stripslashes($event['title']), "HTML-ENTITIES", 'UTF-8'));
				$date = ($event['startdate'] > 0? $event['startdate'] : $event['enddate']);

				if($event['creation_date'] >= strtotime('1 month ago')){

					if(isset($ee_eent_uids[$event['uid']])){
						
						$entry_id = $ee_eent_uids[$event['uid']];

						$this->update_event($event, $entry_id);

						$this->attach_categories($entry_id, $event['activity_type']);

					}else{

						$entry_id = $this->insert_event($event);

						$this->attach_categories($entry_id, $event['activity_type']);

					}

					$this->attach_categories($entry_id, $event['activity_type']);

				}elseif($event['mutation_date'] >= strtotime('1 month ago')){

					if(!isset($ee_eent_uids[$event['uid']])){

						$entry_id = $this->insert_event($event);

						$this->attach_categories($entry_id, $event['activity_type']);

					}
					
				}
			}
		}

		if(ENV == "prod"){
			// Ping https://healthchecks.io/
			// file_get_contents('');
			exit;
		}

	}

	/**
    * Insert event into EE.
    *
    * @return int inserted entry ID.
    */
	private function insert_event($event){

		$entry_id = ee()->shared->insert_channel_title($this->channel_id, $event['title'], 1, $event['enddate']);

		$channel_data_fields = $this->get_channel_data_fields($event);
		$channel_data_fields['entry_id'] = $entry_id;
		$channel_data_fields['channel_id'] = $this->channel_id;

		ee()->db->insert(
			"channel_data",
			$channel_data_fields
		);

		return $entry_id;

	}

	/**
    * Update event in EE.
    *
    * @return void
    */
	private function update_event($event, $entry_id){

		// Update channel title.
		ee()->shared->update_channel_title($this->channel_id, $entry_id, $event['title'], 1, $event['enddate']);

		// Update channel data.
		ee()->db->update(
			"channel_data",
			$this->get_channel_data_fields($event),
			["entry_id" => $entry_id]
		);
	}

	/**
    * Get event UUIDs from EE.
    *
    * @return array
    */
	private function get_ee_event_uids(){

		$ee_events = ee()->db->select("entry_id, field_id_353 AS uid")
			->from("channel_data")
			->where([
				"channel_id" 		=> 5,
				"field_id_353 != "	=> ""
			])
			->get()
			->result_array();

		$ee_event_uids = [];
		foreach($ee_events as $ee_event){
			$ee_event_uids[$ee_event['uid']] = $ee_event['entry_id'];
		}

		return $ee_event_uids;
	}

	/**
    * Get channel data fields from EE.
    *
    * @return array
    */
	private function get_channel_data_fields($event){

		// Get the full address.
		$address = (!empty($event['address'])? ucfirst($event['address']) : "");
		$address.= (!empty($event['housenumer'])? " " . $event['housenumer'] : "");
		$address.= (!empty($event['postcode']) ? " " . $event['postcode'] : "");
		$address.= (!empty($event['city']) ? " " . ucfirst($event['city']) : "");

		// Transform values.
		$values = [
			"description"		=> utf8_encode($this->clean_content($event['description'], $event['department'])),
			"social_media" 		=> "Ja",
			"startdate" 		=> ($event['startdate'] > 0? $event['startdate'] : $event['enddate']),
			"enddate" 			=>  $event['enddate'],
			"show_time" 		=> "Nee",
			"name" 				=> (!empty($event['location'])? addslashes($event['location']) : ""),
			"address" 			=> addslashes($address),
			"zipcode" 			=> $event['postcode'],
			"city"				=> (!empty($event['city']) ? addslashes(ucfirst($event['city'])) : ""),
			"region"			=> addslashes(ucfirst($event['province'])),
			"price"				=> $event['price'],
			"department"		=> addslashes(ucfirst($event['department'])),
			"department_id"		=> $event['department_id'],
			"uid"				=> $event['uid'],
		];

		// Make data fields EE conform.
		$data_fields = [];
		foreach($values as $key => $value){
			$data_fields["field_id_" . $this->field_mapping[$key]] = $value;
		}

		return $data_fields;
	
	}

	/**
    * Strip unwanted content, tags and strange characters, and modify links
	*
	* @param string description
	* @param string department.
    *
    * @return string
    */
	private function clean_content($description, $department){
		// Set content
		$content = ( isset($description) ? @stripslashes($description) : "" );
		$content = iconv("UTF-8", "ISO-8859-1//IGNORE",$content);
		$content = str_replace("�","",$content);
		$content = str_replace("&nbsp;"," ",$content);
		$content = str_replace("(JavaScript moet ingeschakeld zijn om dit e-mail adres te bekijken)","",$content);

		/* VANG LINKS AF */

		$department_domain = strtolower($department)";
		//<link 60822 - imp-button-gfx-132px>
		$pattern = "/link ([0-9]*) - imp-button-gfx-132px/";
		$replacement = "a href='http://".$department_domain."/index.php?id=$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link 57772 - imp-button-gfx-96px>
		$pattern = "/link ([0-9]*) - imp-button-gfx-96px/";
		$replacement = "a href='http://".$department_domain."/index.php?id=$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link 57849 - internal-link>
		$pattern = "/link ([0-9]*) - internal-link/";
		$replacement = "a href='http://".$department_domain."/index.php?id=$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link http://www.hemerocallis.nl  _blank external-link-new-window>
		$pattern = "/link ([^@>]*) ?_blank external-link-new-window/";
		$replacement = "a href='$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link http://waterweg-noord.groei.nl/index.php?id=60817 - external-link-new-window>
		$pattern = "/link ([^@>]*) - external-link-new-window/";
		$replacement = "a href='$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link http://afdeling.groei.nl/?id=31256#c177310 _blank>
		$pattern = "/link ([^@>]*) _blank/";
		$replacement = "a href='$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link fileadmin/Afdelingen/Meppel/Foto_Album/2013/Trog_maken/trog.bmp - download>
		$pattern = "/link ([^@>]*) - download/";
		$replacement = "a href='http://".$department_domain."/$1' target='_blank'";
		$content = preg_replace($pattern, $replacement, $content);

		//<link http://www.veluwezoom.groei.nl>
		$pattern = '/<link ([^@>]*)>/';
		$replacement = "<a href='$1' target='_blank'>";
		$content = preg_replace($pattern, $replacement, $content);

		$content = str_replace("</link>", "</a>", $content);

		return strip_tags($content,"<p><br><table><tr><td><a>");
	}

	/**
    * Attach categories to events in EE database for filtering on the website.
	*
	* @param int entry_id
	* @param string activity_type.
    *
    * @return void
    */
	private function attach_categories($entry_id, $activity_type){

		// Reset categories.
		ee()->db->delete(
			"category_posts",
			["entry_id" => $entry_id]
		);

		if(in_array($activity_type, $this->category_mapping) && isset($this->category_mapping[$activity_type])){

			$cat_ids = [];
			$cat_id = $this->category_mapping[$activity_type];
			$cat_ids[] = $cat_id;

			// Get all parent categories.
			$this->get_category_family($cat_id, $cat_ids);

			foreach($cat_ids as $cat_id){
				ee()->db->insert(
					"category_posts",
					[
						"entry_id" 	=> $entry_id,
						"cat_id"	=> $cat_id
					]
				);
			}
		}
	}

	/**
    * Recursively get all category IDs for specific cat ID.
	*
	* @param int cat_id
	* @param array cat_ids
    *
    * @return void
    */
	private function get_category_family($cat_id, &$cat_ids){

		$categories = ee()->db->select("parent_id")
			->from("categories")
			->where("cat_id", $cat_id)
			->get()
			->result_array();

		if(isset($categories[0]['parent_id'])){
			$parent_cat_id = $categories[0]['parent_id'];
			$cat_ids[] = $parent_cat_id;
			$this->get_category_family($parent_cat_id, $cat_ids);
		}

	}

}