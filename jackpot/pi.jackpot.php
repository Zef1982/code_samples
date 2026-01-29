<?php   

 /**
   * Jackpot plugin for ExpressionEngine.
   * 
   * @author     Zef Oudendorp <zef1982@gmail.com>
   */
class Jackpot
{

	/** @var object|null will contain the EE instance for handeling template data. */
	private $EE = null;

	/** @var object|null $return_data will contain the parsed EE template tagdata. */
    private $return_data = null;

	/** @var string $xml contains the XML contents containing the casino data. */
    public $xml = '';

	
	/**
       * 
       * Constructor pours casino data from an XML file into an array.
	   * The array is ordered by descending highest casino score before it is 
	   * parsed into the EEE template tagdata.
       *
       * @return void
       */
    public function __construct()
    {

		// Create object based on the XML contents from the XML string.
		$xml_obj = simplexml_load_file($this->xml);

		// Return if XML string is empty.
		if(empty($xml_obj)){return;}

		// Load EE instance to handle template data.
        $this->EE =& get_instance();

		// Get limit param from EE template data.
		$limit = $this->EE->TMPL->fetch_param("limit");

		// Pour XML casino jackpots object into an array.
		$casino_array = get_object_vars ($xml_obj->casinoJackpots);

		// Create an array containing unique game data ordered by descending score. 
		$filtered_games = $this->get_games_by_score($casino_array["CasinoJackpotData"], $limit);		
		
		// Parse ordered games into the EE template.
		$variable_row["games"] = Array();
		foreach($filtered_games as $filtered_game){
			$variable_row["games"][] = Array(
				"name" => $filtered_game["gameName"], 
				"amount" => number_format($filtered_game["amount"],0,"", "."), 
				"currency" => $filtered_game["currency"],
				"id" => $filtered_game["gameId"],
				"img_src" => $filtered_game["gameImg"]
			);	
		}

		// Return the EE template with parsed variables.
		$variables[] = $variable_row;
		$this->return_data = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $variables);
	}	 
	
	/**
       * 
       * Get games by score gets unique casino games from the casino jackpot data
	   * and orders them by decending score.
	   * 
	   * @param array casino_jackpot_data contains non-unique jackpot casino game data.
	   * @param int limit a limit param from the EE template for the returned array.
       *
       * @return array filtered_games
       */
	private function get_games_by_score($casino_jackpot_data, $limit) 
	{

		// Array to return the casino jackpot games ordered by descending highest score.
		$filtered_games = Array();

		// Array to keep track of unique games from the casino jackpot data.
		$unique_games = Array();

		// Array to help ordering the casino games.
		$order_by = Array();

		// Loop through the casino jackpot data to fill the unique_games array.
		// If a game has a duplicate, get the game with the highest score.
		foreach($casino_jackpot_data as $xml_key => $game){

			// Cast game into an array.
			$game = (array)$game;

			// Boolean to keep track of found games.
			$found = false;

			// Loop through filtered_games array to check if the game from the casino array is present.
			foreach($unique_games as $key => $unique_game){

				// If the game name is present in the filtered game array...
				if($game["gameName"] == $unique_game["gameName"]){

					$found = true;

					// ..and the core is greater than the present one..
					if($game["amount"] > $unique_game["amount"]){

						// ..then unset the present one..
						unset($unique_game[$key]);

						// ..and and add the game with the greater score to the filtered_game array.
						$unique_game[$xml_key]= $game;

						// Add the greatest score to the order_by array using the same key!
						$order_by[$xml_key] = $game["amount"];
					}
				}		
			}

			// If the game isn't present in the filtered_games array yet...
			if(!$found){
				
				// ..Add game to filtered games and...
				$unique_games[$xml_key]= $game;	

				// ...Add the greatest score to the order_by array using the same key!
				$order_by[$xml_key] = $game["amount"];
			}
		}
		
		// Sort array descending score while keeping the index association.
		arsort($order_by);	

		// Limit the order_by array using the limit from parameter fetched form the tmeplate.
		if(is_numeric($limit)){
			$order_by = array_slice($order_by, 0, $limit, true);
		}	

		// Order the unique games by descending score into the filtered_games array by score 
		// using the order_by array.
		foreach($order_by as $key => $amount){
			$filtered_games[] = $unique_games[$key];
		}

		return $filtered_games;
	}
}