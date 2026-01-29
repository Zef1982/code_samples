<?php

 /**
   * XML export plugin for ExpressionEngine used to get trucks from an EE generate XML file.
   * The images from the XML file wil be saved to an FTP server, 
   * and the XML contents will be saved to an actual XML file which will be posted to the API.
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */
class Xml_export{
	
	/** @var object|null will contain the EE instance for handeling template data. */
	private $EE = null;

	/** @var object|null $return_data will contain the parsed EE template tagdata. */
    private $return_data = null;

	/** @var string $ee_xml_url will contain the URL of the EE-generated XML file which needs to be imported. */
	private $ee_xml_url = "";

	/** @var string $xml_export_file will contain the URL of the XML file which needs to be filled with data. */
	public $xml_export_file = "";

	/** @var string $images_dir will contain the URL of the image dir where the images will be saved. */
	public $images_dir = "";

	/** @var string $ftp_server will contain name of the FTP server where the images will be saved. */
	public $ftp_server = "";
	
	/** @var string $username will contain username of the FTP server where the images will be saved. */
	public $username = "";
	
	/** @var string $password will contain password of the FTP server where the images will be saved. */
	public $password = "";
	
	/** @var string $api_url will contain URL of th API where the exported XML will be posted to. */
	public $api_url = "";
	
	/**
       * 
       * Constructor fills class variables to be used by this class.
       *
       * @return void
       */
	function __construct(){
		// Load EE instance to handle template data.
		$this->EE =& get_instance();

		// Get the URL of the EE generated XML file. 
		// This must be an absolute path in order to get EE parsed content!
		$this->ee_xml_url = "http://".$_SERVER["SERVER_NAME"].$this->ee_xml_url;
	}

	/**
       * Get EE generated XML contents, 
	   * filter the images from this contents and save the image files to teh FTP server.
	   * Save the fetched XML file to an actual file and post this file to the API using CURL.
       *
       * @return void
       */
	public function send(){
		
		// Get contents of EE generated XML file.
		$generated_xml = $this->get_ee_generated_xml();
	
		// Write contents to XML file.
		file_put_contents($this->xml_export_file, $generated_xml); 
		
		// Array to keep track of the trucks which have images.
		$trucks = Array();
		// Array collect all of the truck images in.
		$images = Array();
		// Get truck images from the EE generated XML file.
		$trucks_images = $this->get_trucks_images($generated_xml);
		foreach($trucks_images as $entry_id => $entry_images){
			$trucks[] = $entry_id;
			$images = array_merge($images, $entry_images);
		}

		// Upload truck images from EE geberated XML to FTP server if not present or newer than old one.
		// Image URLs of upload fails will be collected in failed_ftp_puts array.
		$failed_ftp_puts = $this->upload_images($images);
		
		// Post EE generated XML to API using CURL 
		$this->return_data = $this->export_xml();	
		$this->return_data.= $generated_xml;
		
		// Get the EE channel fields in order to update the EE database.
		$this->ee_fields = $this->fields();

		// Update EE database to indicate if record has been send to the API.
		foreach($trucks as $entry_id){
			$this->EE->db->update(
			    'channel_data',
			    array(
			        $this->ee_fields["sent"]  => "Yes"                     
			    ),
			    array(
			        'entry_id' => $entry_id
			    )
			);
		}
		
		// Show the return data on screen.
		echo $this->return_data; exit;
	
	}

	/**
       * Get contents of EE generated XML file using CURL.
	   * 
       *
       * @return output contents of EE generated XML file.
       */
	private function get_ee_generated_xml(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $this->ee_xml_url);   
		$output = curl_exec($ch);
		$resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $output;
	}

	/**
       * Check if image file name exists on teh FTP server.
	   * If the file doesn't exist, upload the image to the FT server.
	   * If the file does exist and the image from the XML is newer, 
	   * replace the image on the FTP with the newer one.
	   * 
	   * @param array images containing URLs of truck images to be uploaded.
       *
       * @return array failed_ftp_puts array containing URLs of truck images which failed to upload.
       */
	private function upload_images($images){

		// Array to keep track of failed image uploads.
		$failed_ftp_puts = Array();     

		// Connect to FTP server.
		$conn_id = ftp_connect($this->ftp_server);
		$login_result = ftp_login($conn_id, $this->username, $this->password);

		// Get array of truck images which are present on the FTP server.
		$ftp_images = ftp_nlist($conn_id, "/");  
		
		// Loop through truck images from EE generated XML file.
		foreach($images as $local_image){
			// If truck image file exists in the images directory...
			if(file_exists($this->images_dir . $local_image)){
				// ...and the truck image is not present in the array of FTP truck images...
				if(!in_array($local_image, $ftp_images)){
					// ... upload the truck image to the FTP server.
					if(!$this->ftp_put_image($conn_id, $local_image, $local_image)){
						$failed_ftp_puts[] = $local_image;
					}							
				}else{
					// If the truck image is already present on the FTP server, but it's newer...
					if(filemtime($local_image) > ftp_mdtm($conn_id, $local_image)){
						// ... upload the truck image to the FTP server.
						if(!$this->ftp_put_image($conn_id, $local_image)){
							$failed_ftp_puts[] = $local_image;
						}	
					}
				}	
			}	
		}	    
		// Close the FTP connection.
		ftp_close($conn_id);

		return $failed_ftp_puts;
	}

	/**
       * Save file to the FTP server.
	   * 
	   * @param object $conn_id of the FTP server.
	   * @param string $local_image truck image from the EE generated XML file.
       *
       * @return boolean
       */
	private function ftp_put_image($conn_id, $local_image){		
		if(!ftp_put($conn_id, $local_image, $this->images_dir.$local_image , FTP_BINARY)){
			return false;
		}

		return true;
	}
	
	/**
       * Post EE generated XML contents to API using CURL.
       *
       * @return string var API answer.
       */
	private function export_xml(){
		// Var to be filled with API answer on success or errors if CURL failed.
		$var = "";

		// Credentials to connect to the API.
		$post_fields = array(
			'userID' => $this->username,
			'muddledPassword' => $this->password,
			'XMLfile' => "@".$this->xml_export_file
		);		
		// Connect to API using CURL.
		$ch = curl_init($this->api_url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_URL, $this->api_url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		
		// Get errors into var on fail or API answer into var on success.
		if($output === false){
	        $var.= "Error Number:".curl_errno($ch)."<br>";
	        $var.= "Error String:".curl_error($ch);
	    }else{
	    	$var.= $output;
	    }

		// Closer CURL.
    	curl_close($ch);

		return $var;
	}
	

	/**
       * Get truck images from EE generated XML string.
	   * 
	   * @param string generated_xml contains the contents of the EE generated XML file.
       *
       * @return trucks_images array containing URLs of truck images.
       */
	private function get_trucks_images($generated_xml){
		// Array to fill with truck image URLs.
		$trucks_images = Array();

		// Create object from EE generated XML string.
		$xml_obj = new SimpleXMLElement($generated_xml);

		// Loop through XML object to get truck images.
		foreach($xml_obj->vendor->item as $truck){

			// Get truck attributes.
			$truck_arrtibutes = $truck->attributes();

			// Loop through truck photos and write image URLs to trucks_images array.
			foreach($truck->photo as $photo){
				$trucks_images[(string)$truck_arrtibutes->AProdID][]  = (string)$photo->photoFileName;
			}
		}

		return $trucks_images;
	}
	
	/**
       * Get truck images from EE generated XML string. 
       *
       * @return array fieldnames which will later be used to update the EE database record.
       */
	private function fields(){
		$sql = 'select field_id,field_name from exp_channel_fields';
		$query = $this -> EE -> db -> query($sql);

		if ($query -> num_rows() > 0) {
			foreach ($query->result_array() as $row) {
				$fieldnames[$row['field_name']] = 'field_id_' . $row['field_id'];
			}
		}
		
		return $fieldnames;
	}
	
}