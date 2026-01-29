<?php

class Custom_import
{

	/**
	 * Custom import for EE, importing members, secretaries, departments, events and places.
	 * 
	 * @author     Zef Oudendorp <zef1982@gmail.com>
	 */

	/**
	* Constructor to load used libraries.
	*
	* @return void
	*/
	public function __construct(){
		ee()->load->library('shared');
	}
	
	/**
	* Import members into EE.
	*
	* @return void
	*/
	public function members(){

		ee()->load->library('members');
		ee()->members->import();

	}

	/**
	* Import secretaries into EE.
	*
	* @return void
	*/
	public function secretaries(){

		ee()->load->library('secretaries');
		ee()->secretaries->import();

	}

	/**
	* Import departments into EE.
	*
	* @return void
	*/
	public function departments(){

		ee()->load->library('departments');
		ee()->departments->import();

	}

	/**
	* Import events into EE.
	*
	* @return void
	*/
	public function events(){

		ee()->load->library('events');
		ee()->events->import();

	}

	/**
	* Import places into EE.
	*
	* @return void
	*/
	public function places(){

		ee()->load->library('places');
		ee()->places->import();

	}

}
