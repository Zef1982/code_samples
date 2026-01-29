<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Custom_procurios_ext
{

    var $version = 1.0;

	public function __construct(){

		// Load procurios libraries.
		ee()->load->library('procurios');
		ee()->load->library('freeformProcurios', NULL, 'freeformProcurios');
	}

	/**
	 *   Send form data to procurios.
	 *
	 * @access	public
	 * @param	void
	 * @return	void
	 */
	public function freeform_next_submission_after_save($model, $is_new)
	{
		ee()->freeformProcurios->send_to_procurios($model->formId);	
	}

	/**
	 *   Activate extension
	 *
	 * @access	public
	 * @param	void
	 * @return	bool
	 */
	public function activate_extension()
	{

		// Support the Solspace Freeform hook
		ee()->db->insert('extensions',
			array(
				'class'        => __CLASS__,
				'method'       => 'freeform_next_submission_after_save',
				'hook'         => 'freeform_next_submission_after_save',
				'settings'     => '',
				'priority'     => 1,
				'version'      => 1.0,
				'enabled'      => 'y'
			)
		);
	}


	/**
	 *   Update extension
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	public function update_extension($current = '')
	{
		return TRUE;
	}


	/**
	 *   Disable extension
	 *
	 * @access	public
	 * @param	void
	 * @return	void
	 */
	public function disable_extension()
	{
		ee()->db->where('class', __CLASS__);
    	ee()->db->delete('extensions');
	}

}
// END CLASS

/* End of file ext.recaptcha.php */
/* Location: ./system/expressionengine/third_party/recaptcha/ext.recaptcha.php */
