<?php


$plugin_info = array(
    'pi_name' => 'Custom Mollie',
    'pi_version' => '1.0',
    'pi_author' => 'Estdigital',
    'pi_author_url' => 'http://www.estdigital.nl/',
    'pi_description' => 'Freeform Mollie payments',
    'pi_usage' => '');

class Custom_mollie
{

    /**
	 * Mollie payment plugin for ExpressionEngine.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

    /** @var boolean is_loaded */
    public static $is_loaded = false;

    /** @var array form_types which require Mollie payments */
    private $form_types = Array(
        "kennismakingsactie" 	=> 10,
        "zaad_bestellen"		=> 18,
        "agenda_order"		    => 20,
        "specials"				=> 21,
        "jubileumtulp"			=> 22
    );

    /**
    * Constructor 
    *
    * @return void
    */
    public function __construct()
    {
        
        $this->site_url = ee()->functions->fetch_site_index(TRUE);

        if (!self::$is_loaded)
        {
            self::$is_loaded = true;
        }
    }

    /**
    * Update apyment status & send email if succesfull
    *
    * @return void
    */
    function webhook(){

        if(ENV == "prod" || ENV == "prod_test"){
            require_once dirname($_SERVER['DOCUMENT_ROOT']) . "/webmanager/user/addons/custom_mollie/vendor/autoload.php";
            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey(ee()->config->item((ENV == "prod"? "prod_api_key" : "dev_api_key"), "mollie_settings" ));
        }
        try {
            /*
             * Retrieve the payment's current state.
             */
            if(ENV == "prod" || ENV == "prod_test"){
                $payment = $mollie->payments->get($_POST['id']);
                $order_id = $payment->metadata->order_id;
                $form_type = $payment->metadata->form_type;
                $email = $payment->metadata->email;
                $payment_status = $payment->status;
                $payment_paid = $payment->isPaid();
                $payment_failed = $payment->isFailed();
                $payment_expired = $payment->isExpired();
                $payment_cancelled = $payment->isCanceled();
                $refund = ($payment->hasRefunds()? TRUE : FALSE);
                $payment_method = $payment->method;
            }else{
                $order_id = $_GET["order_id"];
                $form_type = "agenda_order";
                $email = "zef+klant@kees-tm.nl";
                $payment_status = "paid";
                $payment_method = "ideal";
                $payment_paid = true;
                $payment_failed = false;
                $payment_expired = false;
                $payment_cancelled = false;
                $refund = false;
            }

            if(!empty($order_id) && !$refund){
                $entry_id = explode("-", $order_id)[1];
                $form_id = explode("-", $order_id)[0];
                // Update payment status                
                ee()->db->update(
                    'freeform_next_submissions',
                    [
                        'field_67'  => $payment_status,
                        'field_66'  => $payment_method
                    ],
                    [
                        'formId'    => $form_id,
                        'id'        => $entry_id
                    ]
                );
                if ($payment_paid) {   
                    // Send customer email if paid.
                    $this->send_templated_email($form_id, $entry_id, (isset($_GET["zaaien"])? "":""));
                    switch(array_flip($this->form_types)[$form_id]){
                        case "zaad_bestellen":
                            // Send email to seed provider.
                            $this->send_templated_email($form_id, $entry_id, "");
                            break;

                        case "agenda_order":
                            $this->send_templated_email($form_id, $entry_id, "");
                            break;

                        case "kennismakingsactie":
                            // Customer receives email after automated member import.
                            return "acquaintance|" . $form_id . "|" .$entry_id;
                            break;

                        case "specials":
                            $this->send_templated_email($form_id, $entry_id, "");
                            break;

                        case "jubileumtulp":
                            $this->send_templated_email($form_id, $entry_id, "");
                            break;
                    }
                }
            }else{
                throw new Exception("payment form type not found");
            }

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            throw new Exception(\htmlspecialchars($e->getMessage()));
        }
    }

    /**
    * Send templated email.
    *
    * @param int form_id
    * @param int entry_id
    * @param string email
    *
    * @return void
    */
    private function send_templated_email($form_id, $entry_id, $email=""){
        $form_type = array_flip($this->form_types)[$form_id];
        $variables = $this->map_template_variables($form_id, $entry_id);
        if(count($variables) > 0){      
            $variables['order_nr'] = $form_id."-".$entry_id;    
            ee()->TMPL->depth = 1;        
            switch(array_flip($this->form_types)[$form_id]){
                case "kennismakingsactie":
                    $template_name = "." . $form_type . "_email";
                    $from = 'From: <>' . "\r\n";
                    $subject = "Bevestiging kennismakingsactie";
                    $reply_to = "";
                    break;
                
                case "zaad_bestellen":
                    $subject = "Zaad bestellen order #".$variables['order_nr'];
                    if(!empty($email)){
                        $template_name = "." . $form_type . "_zaaien_email";
                        $subject = $subject." voor ".($variables['geslacht'] == "M"? " Dhr.":" Mevr.")." ".$variables['voornaam'].(!empty($variables['voorvoegsels'])? " ".$variables['voorvoegsels']:'' )." ".$variables['achternaam'];
                        $reply_to = 'Reply-To: '. $variables['email'] . "\r\n";
                    }else{
                        $template_name = "." . $form_type . "_email";
                        $subject = "Bevestiging ".strtolower($subject);
                        $reply_to = "";
                    }
                    $from = 'From:<>' . "\r\n";                     
                    break;

                case "agenda_order":
                    $subject = "Agenda bestelling #".$variables['order_nr'];
                    if(!empty($email)){
                        $template_name = "." . $form_type . "_webmaster_email";
                        $subject = $subject." voor ".($variables['geslacht'] == "M"? " Dhr.":" Mevr.")." ".$variables['voornaam'].(!empty($variables['voorvoegsels'])? " ".$variables['voorvoegsels']:'' )." ".$variables['achternaam'];
                        $reply_to = 'Reply-To: '. $variables['email'] . "\r\n";
                    }else{
                        $template_name = "." . $form_type . "_email";
                        $subject = $subject;
                        $reply_to = "";
                    }
                    $from = 'From: <>' . "\r\n";                     
                    break;
                
                case "specials":
                    $subject = "Specials bestelling #".$variables['order_nr'];
                    if(!empty($email)){
                        $template_name = "." . $form_type . "_webmaster_email";
                        $subject = $subject." voor ".($variables['geslacht'] == "M"? " Dhr.":" Mevr.")." ".$variables['voornaam'].(!empty($variables['voorvoegsels'])? " ".$variables['voorvoegsels']:'' )." ".$variables['achternaam'];
                        $reply_to = 'Reply-To: '. $variables['email'] . "\r\n";
                    }else{
                        $template_name = "." . $form_type . "_email";
                        $subject = $subject;
                        $reply_to = "";
                    }
                    $from = 'From: <>' . "\r\n";                     
                    break;

                case "jubileumtulp":
                    $subject = "jubileumtulp #".$variables['order_nr'];
                    if(!empty($email)){
                        $template_name = "." . $form_type . "_webmaster_email";
                        $subject = $subject." voor ".($variables['geslacht'] == "M"? " Dhr.":" Mevr.")." ".$variables['voornaam'].(!empty($variables['voorvoegsels'])? " ".$variables['voorvoegsels']:'' )." ".$variables['achternaam'];
                        $reply_to = 'Reply-To: '. $variables['email'] . "\r\n";
                    }else{
                        $template_name = "." . $form_type . "_email";
                        $subject = $subject;
                        $reply_to = "";
                    }
                    $from = 'From: <>' . "\r\n";                     
                    break;
            }
            $tagdata = ee()->TMPL->fetch_template('payment', $template_name, FALSE);
            $parsed_template = ee()->TMPL->parse_variables($tagdata, [$variables]);
                // To send HTML mail, the Content-type header must be set
                $headers  = 'MIME-Version: 1.0' . "\r\n";
                $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                $headers .= $from;
                $headers .= $reply_to;
            if(ENV == "prod"){
                mail((empty($email)? $variables["email"] : $email), $subject, $parsed_template, $headers);
            }
        }
    }

    /**
    * Get form fields from EE.
    *
    * @param int form_id
    *
    * @return array
    */
    private function get_form_fields($form_id){

        // Get form fields ids by getting form layout json object.
        $forms = ee()->db->select("layoutJson")
                        ->from("freeform_next_forms")
                        ->where("id", $form_id)
                        ->get()
                        ->result_array();

        if(isset($forms[0]))
        {
            // Get form ids from layout json object in array.
            $field_ids = [];
            $form_layout =json_decode($forms[0]['layoutJson'], TRUE);
            foreach( $form_layout['composer']['properties'] as $item){
                if(isset($item['id'])){
                    $field_ids[] = $item['id'];
                }
            }
            // Get form handles.
            $form_fields = ee()->db->select("id, handle")
                ->from("freeform_next_fields")
                ->where_in("id", $field_ids)
                ->get();

            // Return array with form field mapping.
            return $form_fields->result_array();
        }
    }

    /**
    * Redirect to page if paid or not.
    *
    * @return void
    */
    function redir(){        

        if(!isset($_GET["order_id"]) || !is_numeric(explode("-", $_GET["order_id"])[1])){
            exit('Ongeldig entry_id!');
        }

        $status = "default";
        $form_id = explode("-", $_GET["order_id"])[0];
        $entry_id = explode("-", $_GET["order_id"])[1];
        
        $mollie_status =$this->get_mollie_status($form_id, $entry_id);
        if($mollie_status){
            switch($mollie_status){

                case 'paid':                    
                    switch(array_flip($this->form_types)[$form_id]){
                        case "kennismakingsactie":
                            $redir_url = 'word-lid-van-groei-bloei/kennismakingsactie/bevestiging-kennismakingsactie';
                            break;

                        case "zaad_bestellen":
                            // Delete shopping basket cookie
                            if (isset($_COOKIE['shoppingBasket'])) {
                                unset($_COOKIE['shoppingBasket']); 
                                setcookie('shoppingBasket', null, -1, '/'); 
                            } 
                            $redir_url = 'bevestiging-zaad-bestellen';
                            break;

                        case "agenda_order":
                            $redir_url = 'bevestiging-agenda-bestellen';
                            break;

                        case "specials":
                            $redir_url = 'bevestiging-special-bestellen';
                            break;

                        case "jubileumtulp":
                            $redir_url = 'bevestiging-jubileumtulp-bestellen';
                            break;
                    }
                    header('location: '.$this->site_url.$redir_url);
                break;

                default:
                case 'canceled':
                case 'pending':
                case 'expired':
                case 'failed':
                    header('location: '.$this->site_url);
                break;
            }
        }

        die('Betaling niet gevonden!');

    }

    /**
    * Get Mollie status.
    *
    * @param int form_id
    * @param int entry_id
    *
    * @return string|boolean
    */
    private function get_mollie_status($form_id, $entry_id){
        $form_entries = ee()->db->select("field_67 AS mollie_status")
                        ->from("freeform_next_submissions")
                        ->where(
                            [
                                "formId"    => $form_id,
                                "id"        => $entry_id
                            ]
                        )
                        ->get();
        $orders = $form_entries->result_array();
        if(isset($orders[0])){
            return $orders[0]['mollie_status'];
        }
        return false;
    }
    
    /**
    * Map template variables
    *
    * @param int form_id
    * @param int entry_id
    *
    * @return array
    */
    private function map_template_variables($form_id, $entry_id){
        $variables = array();
        $order = $this->get_order($form_id, $entry_id);
        if(isset($order[0])){
            $form_fields = $this->get_form_fields($form_id);
            foreach($form_fields as $form_field)
            {
                if(isset($order[0]["field_".$form_field["id"]])){
                    //change 'weird' characters into html entities, except for the br-tag in our summary!
                    $variables[$form_field["handle"]] = str_replace(["&lt;", "&gt;"], ["<", ">"], htmlentities($order[0]["field_".$form_field["id"]]));
                }else{
                    $variables[$form_field["handle"]] = "";
                }
            }
        }
        return $variables;
    }

    /**
    * Get order
    *
    * @param int form_id
    * @param int entry_id
    *
    * @return array
    */
	private function get_order($form_id, $entry_id){
        $form_entries = ee()->db->select("*")
                                    ->from("freeform_next_submissions")
                                    ->where(
                                        [
                                            "formId"    => $form_id,
                                            "id"  => $entry_id
                                        ]
                                    )
                                    ->get();
        return $form_entries->result_array();
    }

}
