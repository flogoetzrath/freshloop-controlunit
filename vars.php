<?php
	/*
	    Name: Freshloop
	    Description: A fully featured, centrally controlled air freshener system
	    Author: Florian Götzrath <info@floriangoetzrath.de>
	    Author URI: http://floriangoetzrath.de
	    Version: 1.0.6
	*/

	session_start();

	define('APP_NAME', 'freshloop');
	define('APP_STATUS', 'development');
	define('APP_VERSION', '1.0.6');

	define('APP_AUTHOR', 'Florian Götzrath');
	define('APP_AUTHOR_URI', 'https://floriangoetzrath.de/');
	define('APP_AUTHOR_CONTACT', 'info@floriangoetzrath.de');

	define('IS_CLI_MASTER', (bool)(php_sapi_name() === 'cli'));

	define('ABSPATH', dirname(__FILE__));
	define('CONFIG_PATH', ABSPATH.'/config');
	define('CONTROLLER_PATH', ABSPATH.'/controller');
	define('LANGUAGES_PATH', ABSPATH.'/lang');
	define('LIBRARY_PATH', ABSPATH.'/lib');
	define('LOGS_PATH', ABSPATH.'/logs');
	define('MODEL_PATH', ABSPATH.'/model');
	define('MODS_PATH', ABSPATH.'/mods');
	define('VIEWS_PATH', ABSPATH.'/views');
	define('VIEWS_MODS_PATH', ABSPATH.'/views/mods');
	define('PUBLIC_PATH', VIEWS_PATH.'/public');
	define('MEDIA_PATH', PUBLIC_PATH.'/media');

	define('STYLESHEET_URI', '/views/public/css');
	define('SCRIPT_URI', '/views/public/js');
	define('MEDIA_URI', '/views/public/media');
	define('LIBUI_URI', '/lib/ui');

	define('VIEWS_MODS_URI', '/views/mods');

	define("VIEWS_HOME_URI", '/home');
	define("VIEWS_AUTH_URI", '/auth');

	define("IMG_UPLOAD_DIR", MEDIA_PATH."/uploads");
	define("MAX_UPLOAD_FILESIZE", 8000000);

	$_SESSION['sid'] = hash("sha256", time());

	$GLOBALS['core_components'] = array(
		"admin" => array( "dashboard" )
	);

	// Read Config File

	$config = parse_ini_file(CONFIG_PATH.'/config.ini');

	$GLOBALS['charset'] = $config['charset'];
	$GLOBALS['lang'] = $config['language'];
	$GLOBALS['unit_fallback_img'] = $config['unit_fallback_img'];

	define("APP_ACTIVATION", $config['app_activation'] === "enabled");
	define('ALLOW_CRONJOBS', $config['allow_cronjobs'] === "enabled");
	define('DEF_API_PORT', (int)$config['default_api_port']);
	define('DEBUG_MODE', $config['show_errors'] === "enabled");
	define('LANG', $GLOBALS['lang']);

	define('DB_HOST', $config['db_host']);
	define('DB_USER', $config['db_user']);
	define('DB_PW', $config['db_pw']);
	define('DB_TABLENAME', $config['db_tblname']);

	define('SSH_HOST', $config['ssh_host']);
	define('SSH_PORT', $config['ssh_port']);
	define('SSH_USER', $config['ssh_user']);
	define('SSH_PW', $config['ssh_pw']);

	// Misc

	in_array(DEBUG_MODE, array("on", "activated", "enabled", 1)) || APP_STATUS === "development"
		? ini_set("display_errors", 1)
		: ini_set("display_errors", 0);

	if(DEBUG_MODE)
	{

		$GLOBALS['unit_ip'] = shell_exec("hostname -I") ?: "127.0.0.1";
		error_reporting(E_ALL);
		ini_set("display_errors", "1");

	}

	// Check for Lang Shortcuts

	$GLOBALS['langs_supported'] = array(
		"en" => "English",
		"de" => "German"
	);

	$GLOBALS['lang_shortcut'] = "";

	switch ($GLOBALS['lang'])
	{

		case 'en':
			$GLOBALS['lang'] = 'English';
			$GLOBALS['lang_shortcut'] = 'en';
			break;

		case 'English':
			$GLOBALS['lang_shortcut'] = 'en';
			break;

		case 'de':
			$GLOBALS['lang'] = 'German';
			$GLOBALS['lang_shortcut'] = 'de';
			break;

		case 'German':
			$GLOBALS['lang_shortcut'] = 'de';
			break;

	}

	// Require Lang Files

	$langDirs = array(

		0 => glob(LANGUAGES_PATH.'/*.php'),
		1 => glob(LANGUAGES_PATH.'/admin/*.php'),
		2 => glob(LANGUAGES_PATH.'/admin/*/*.php'),
		3 => glob(LANGUAGES_PATH.'/admin/*/*/*.php'),
		4 => glob(LANGUAGES_PATH.'/global/*.php'),
		5 => glob(LANGUAGES_PATH.'/global/*/*.php'),
		6 => glob(LANGUAGES_PATH.'/mods/*.php'),
		7 => glob(LANGUAGES_PATH.'/mods/mod_*/*.php')

	);

	foreach ($langDirs as $index => $files)
		foreach ($files as $file) require($file);


	// Require Base

	require_once(LIBRARY_PATH.'/exceptions/Exceptions.php');
	require_once(LIBRARY_PATH.'/exceptions/ErrorHandler.class.php');
	require_once(LOGS_PATH.'/logging.class.php');
	require_once(CONFIG_PATH.'/Database.php');

	require_once(LIBRARY_PATH.'/functions.php');
	require_once(LIBRARY_PATH.'/filter_functions.php');
	require_once(LIBRARY_PATH.'/network_functions.php');

	require_once(MODEL_PATH.'/Model.class.php');

	require_once(CONTROLLER_PATH.'/MainController.class.php');
	require_once(CONTROLLER_PATH.'/APIController.class.php');
	require_once(CONTROLLER_PATH.'/AdminController.class.php');
	require_once(CONTROLLER_PATH.'/TemplateController.class.php');

	require_once(LIBRARY_PATH.'/form_functions.php');

	require_once(CONFIG_PATH.'/Routes.php');

