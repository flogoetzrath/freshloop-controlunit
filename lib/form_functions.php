<?php
	/**
	 * User: Flo
	 * Date: 09.09.2018
	 * Time: 09:22
	 *
	 * @param String $form_key
	 * @param String $action_url
	 * @param String $wrap_class
	 * @param array  $addSettings
	 */

	/**
	 * Initializes a new form
	 *
	 * @param String $action_url
	 * @param array  $addSettings
	 */
	function renderForm_init(String $action_url = "", array $addSettings = array())
	{

		if(!isSizedString($addSettings['title'])) $addSettings['title'] = "";
		if(!isSizedString($addSettings['wrap_class'])) $addSettings['wrap_class'] = "";
		$file_upload = "enctype=\"multipart/form-data\"" ?? "";
		$form_class = $addSettings['form_class'] ?? "";


		$method = isset($addSettings['method']) && isSizedString($addSettings['method'])
			? $addSettings['method']
			: 'post';

		if(isset($addSettings['deactivate_redirect']) && isSizedString($addSettings['deactivate_redirect']))
			echo "<form method='$method' action='$action_url' target='".$addSettings['deactivate_redirect']." $file_upload'>";
		else echo "<form method='$method' action='$action_url' $file_upload class='$form_class'>";

		if(!isset($addSettings['activate_card_wrap']) || $addSettings['activate_card_wrap'] === true)
		{

			echo "<div class='card ".$addSettings['wrap_class']."'>";
			echo    "<div class='card-body'>";

			if(isset($addSettings['title']) && isSizedString($addSettings['title']))
				echo "<h3 class='card-title'>".$addSettings['title']."</h3>";

		}

	} // function renderForm_init()

	/**
	 * Catches echo'd renderForm_init code
	 *
	 * @param String $action_url
	 * @param array  $addSettings
	 *
	 * @return false|string
	 */
	function renderForm_init_ob(String $action_url = "", array $addSettings = array())
	{

		ob_start();

		renderForm_init($action_url, $addSettings);

		$form_init_ob = ob_get_contents();

		ob_end_clean();

		return $form_init_ob;

	} // function renderForm_init_ob()

	/**
	 * Closes a form
	 *
	 * @param String $submit_value  The value of the submit button
	 * @param array  $addSettings   Additional settings
	 */
	function renderForm_close(String $submit_value = "", array $addSettings = array())
	{

		global $submit_value;

		if(!isSizedString($submit_value)) $submit_value = $submit_value[$GLOBALS['lang']];
		//if(!isset($addSettings['deactivate_redirect'])) $addSettings['deactivate_redirect'] = "hidden_form";
		if(!isset($addSettings['submit_class']) || !isSizedString($addSettings['submit_class'])) $addSettings['submit_class'] = "";
		if(!isset($addSettings['activate_submit_btn'])) $addSettings['activate_submit_btn'] = true;

		if($addSettings['activate_submit_btn'])
		{

			if(isset($addSettings['submit_name']) && isSizedString($addSettings['submit_name']))
				echo "<input type='submit' name='".$addSettings['submit_name']."' value='$submit_value' class='btn btn-light btn-submit ".$addSettings['submit_class']."'>";
			else
				echo "<input type='submit' value='$submit_value' class='btn btn-light btn-submit ".$addSettings['submit_class']."'>";

		}

		if(!isset($addSettings['activate_card_wrap']) || $addSettings['activate_card_wrap'] == true)
		{

			echo    "</div>"; # .card-body
			echo "</div>"; # .card

		}

		if(isset($addSettings['deactivate_redirect']) && isSizedString($addSettings['deactivate_redirect']))
			echo "<iframe name='".$addSettings['deactivate_redirect']."' width='0' height='0' border='0' style='display:none;'></iframe>";

		echo "</form>";

	} // function renderForm_close

	/**
	 * Catches echo'd renderForm_close code
	 *
	 * @param String $submit_value
	 * @param array  $addSettings
	 *
	 * @return false|string
	 */
	function renderForm_close_ob(String $submit_value = "", array $addSettings = array())
	{

		ob_start();

		renderForm_close($submit_value, $addSettings);

		$form_close_ob = ob_get_contents();

		ob_end_clean();

		return $form_close_ob;

	} // function renderForm_close_ob()

	/**
	 * Renders a basic input field
	 *
	 * @param       $field_name
	 * @param       $field_label
	 * @param       $field_value
	 * @param array $conf
	 */
	function renderForm_input($field_name, $field_label, $field_value = "", $conf = array())
	{

		$default = array(
			'type' => false,
			'placeholder' => false,
			'maxlength' => false,
			'required' => false,
			'container' => false
		);

		foreach($default as $prop => $option) if(!array_key_exists($prop, $conf)) $conf[$prop] = $option;

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_value'] = $field_value;
		$MC->view['field_config'] = $conf;

		include VIEWS_PATH."/common/form/input.phtml";

	} // function renderForm_input()

	function renderForm_textarea($field_name, $field_label, $field_value = "", $conf = array())
	{

		$default = array(
			"placeholder" => false,
			"readonly" => false,
			"maxlength" => false,
			"required" => true,
			"container" => true
		);

		foreach($default as $prop => $option)
			if(!array_key_exists($prop, $conf)) $conf[$prop] = $option;

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_value'] = $field_value;
		$MC->view['field_config'] = $conf;

		include VIEWS_PATH."/common/form/textarea.phtml";

	} // function renderForm_textarea()

	function renderForm_timepicker($field_name, $field_label, $field_value = "", $conf = array())
	{

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_value'] = $field_value;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_config'] = $conf;

		if(
			!isset($GLOBALS['js']['timepicker']) ||
			!isSizedString($GLOBALS['js']['timepicker'])
		)
			$GLOBALS['js']['timepicker'] = array("<script>".file_get_contents(LIBRARY_PATH.'/ui/timepicker/timepicker.js')."</script>");

		include VIEWS_PATH."/common/form/timepicker.phtml";

	} // function renderForm_timepicker()

	function renderForm_checkbox()
	{



	} // function renderForm_checkbox()

	function renderForm_toggleSwitch($field_name, $field_label, $field_value = "", $conf = array())
	{

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_value'] = (bool)$field_value;
		$MC->view['field_config'] = $conf;

		include VIEWS_PATH."/common/form/toggle_switch.phtml";

	} // function renderForm_toggleSwitch()

	function renderForm_select($field_name, $field_label, $field_values, $field_value = "", $conf = array())
	{

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_value'] = $field_value;
		$MC->view['field_values'] = $field_values;
		$MC->view['field_config'] = $conf;
		$MC->view['atts_select'] = '';

		if (isSizedString($conf['onchange']))
		{

			$MC->view['atts_select'] .= ' onchange="'.$conf['onchange'].'" ';

		}

		if (isSizedInt($conf['multiple']))
		{

			$MC->view['field_name'] .= '[]';
			$MC->view['atts_select'] .= ' size="'.$conf['multiple'].'" multiple=""multiple" ';

		}

		else include VIEWS_PATH."/common/form/select.phtml";

	} // function renderForm_select()

	function renderForm_upload($field_name, $field_label, $field_value = "", $conf = array())
	{

		$default = array(
			"maxlength" => false,
			"container" => true,
			"required" => false
		);

		foreach($default as $prop => $option) if(!array_key_exists($prop, $conf)) $conf[$prop] = $option;

		$MC = new MainController();

		$MC->view['field_name'] = $field_name;
		$MC->view['field_label'] = $field_label;
		$MC->view['field_id'] = mb_substr(md5($field_name), 0, 10);
		$MC->view['field_value'] = $field_value;
		$MC->view['field_config'] = $conf;

		include VIEWS_PATH."/common/form/upload.phtml";

	} // function renderForm_upload()