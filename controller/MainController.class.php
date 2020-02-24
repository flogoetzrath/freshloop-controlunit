<?php

	/**
	 * @name: MainController
	 * @description: Controller to grant fundamental, system functionality
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once(realpath(__DIR__).'/../vars.php');

	require_once('interfaces/MessagesInterface.interface.php');
	require_once('interfaces/ModuleInterface.interface.php');

	class MainController implements MessagesInterface, ModuleInterface
	{

		/* @var $action string field to trace back the main use of this controller */
		public $action;
		/* @var $db Database field to store the db connection */
		public $db;
		/* @var $data array field to store controller related information */
		public $data;
		/* @var $view array field to store values for the eventual use in the frontend */
		public $view;
		/* @var $lang string field to store the currently selected language */
		public $lang;

		/* @var $modules array field to store mod instances */
		var $modules = array();
		/* @var $loaded_modules array field to store loaded mod references */
		var $loaded_modules = array();
		/* @var $mods_statuses array field to store mod activation statuses */
		var $mods_statuses = array();

		public function __construct(String $action = '')
		{

			if(isSizedString($action)) $this->action = $action;

			$this->data = array();
			$this->view = array();
			$this->modules = array();
			$this->loaded_modules = array();

			$this->checkLang();
			$this->lang = $GLOBALS['lang'];

			$this->data['is_localhost'] = isLocalhost();

		} // public function __construct()

		/**
		 * Initializes the prevalent instance
		 */
		public function init(){

			# Checks Config
			if(!$this->configIsBuilt()) $this->buildConfig();

			# Init Config
			$this->readConfig();

			# Init DB
			if(empty($this->db) || !is_object($this->db))
			{

				$this->db = new Database();

				if(!isset($GLOBALS['db']))
					$GLOBALS['db'] = $this->db;

			}

		} // public function init()

		/**
		 * Dispatches any possible request sent
		 */
		public function dispatch()
		{

			if(!isSizedArray($_REQUEST)) return false;

			# Mod Action
			$str_in_array = str_in_array("mod_", $_REQUEST);
			if(isSizedArray($str_in_array))
			{

				$inst = new $str_in_array['target_full_str']();

				if($inst instanceof $str_in_array['target_full_str'])
					@$inst->dispatch($_REQUEST);

			}

			# Admin action
			if(isSizedString($_REQUEST['page']) && $_REQUEST['page'] === "Admin")
				(new AdminController())->dispatch();

			# Delete Frontend Error
			if(isSizedString($_REQUEST['action']) && $_REQUEST['action'] === 'delFrontendErr')
				@$this->deleteFrontendError($_REQUEST['id'], true);

			# Delete Frontend Message
			if(isSizedString($_REQUEST['action']) && $_REQUEST['action'] === 'delFrontendMsg')
				@$this->deleteFrontendMessage($_REQUEST['id'], true);

		} // public function dispatch()

		/**
		 * Stores config settings in the data array
		 */
		public function readConfig()
		{

			if(!isset($this->data['config']) || empty($this->data['config']))
				$this->data['config'] = parse_ini_file(CONFIG_PATH.'/config.ini');

			// If something went wrong before this particular instance
			if(count($this->data['config']) <= 1) $this->buildConfig();

		} // public function readConfig()

		/**
		 * Compares set and active language and
		 * writes set lang to config if neccessary
		 *
		 * @return bool
		 */
		public function checkLang()
		{

			if(!isset($GLOBALS['lang']) || !isset($_GET['lang'])) return false;

			if(strlen($_GET['lang']) <= 3) $activeLang = $GLOBALS['lang_shortcut'];
			else $activeLang = $GLOBALS['lang'];

			if($activeLang !== $_GET['lang'])
			{

				if(!isSizedArray($this->data['config'])) $this->init();

				$this->data['config']['language'] = $_GET['lang'];

				$this->writeConfFile();

			}

		} // public function checkLang()

		/**
		 * Adds a log message to the default log or any other given log
		 *
		 * @param String $msg
		 * @param String $log_path
		 *
		 * @return bool
		 */
		public function addLogMessage(String $msg, String $log_path = LOGS_PATH."/events.log"){

			$inst = new Logging();

			$inst->lfile($log_path);
			$process = $inst->lwrite($msg);

			if($process === false) return false;

			$inst->lclose();
			return true;

		} // public function addLogMessage()

		/**
		 * Adds a new message to the frontend
		 *
		 * @param String $msg
		 * @param String $msg_identifier
		 * @param array  $conf
		 */
		public function addFrontendMessage(String $msg, String $msg_identifier = "", array $conf = array()){

			if(!isSizedString($msg_identifier)) $msg_identifier = md5($msg);

			if(!isSizedArray($_SESSION['frontend_messages'])) $_SESSION['frontend_messages'] = array();
			if(!isSizedArray($_SESSION['frontend_messages'][$msg_identifier]))
				$_SESSION['frontend_messages'][$msg_identifier] = $msg;

			if(isSizedArray($conf))
			{

				if(!isSizedArray($_SESSION['frontend_messages']['conf'][$msg_identifier]))
					$_SESSION['frontend_messages']['conf'][$msg_identifier] = $conf;

			}

		} // public function addFrontendMessage()

		/**
		 * Deletes a frontend message
		 *
		 * @param      $id
		 * @param bool $reload
		 */
		public function deleteFrontendMessage($id, $reload = false)
		{

			if(@isSizedString($_SESSION['frontend_messages'][$id]))
			{

				unset($_SESSION['frontend_messages'][$id]);
				unset($_SESSION['frontend_messages']['conf'][$id]);

			}
			else $_SESSION['frontend_messages'] = array("conf" => array());

			// FIXME: Find rare case where this is necessary
			if($reload) $this->redirect("/".Routing::get_current_route(), true);

			$this->refresh();

		} // public function deleteFrontendMessage()

		/**
		 * Counts set frontend messages excluding the conf field
		 *
		 * @return array|int
		 */
		public static function countFrontendMessages()
		{

			return $nMsgs = isSizedArray($_SESSION['frontend_messages'])
				? count($_SESSION['frontend_messages']) - array_count_keyes($_SESSION['frontend_messages'], "conf")
				: 0;

		} // public static function countFrontendMessages()

		/**
		 * Adds a new error to the frontend
		 *
		 * @param String $err
		 * @param String $err_identifier
		 * @param array  $conf
		 */
		public function addFrontendError(String $err, String $err_identifier = "", array $conf = array()){

			if(!isSizedString($err_identifier)) $err_identifier = md5($err);

			if(!isSizedArray($_SESSION['frontend_errors'])) $_SESSION['frontend_errors'] = array();
			if(!isSizedArray($_SESSION['frontend_errors'][$err_identifier]))
				$_SESSION['frontend_errors'][$err_identifier] = $err;

			if(isSizedArray($conf))
			{

				if(!isSizedArray($_SESSION['frontend_errors']['conf'][$err_identifier]))
					$_SESSION['frontend_errors']['conf'][$err_identifier] = $conf;

			}

		} // public function addFrontendError()

		/**
		 * Deletes a frontend error
		 *
		 * @param      $id
		 * @param bool $reload
		 */
		public function deleteFrontendError($id, $reload = false)
		{

			if(@isSizedString($_SESSION['frontend_errors'][$id]))
			{

				unset($_SESSION['frontend_errors'][$id]);
				unset($_SESSION['frontend_errors']['conf'][$id]);

			}
			else $_SESSION['frontend_errors'] = array("conf" => array());

			// FIXME: Find rare case where this is necessary
			if($reload) $this->redirect("/".Routing::get_current_route(), true);

			$this->refresh();

		} /// public function deleteFrontendError()

		/**
		 * Counts set frontend errors excluding the conf field
		 *
		 * @return array|int
		 */
		public static function countFrontendErrors()
		{

			return $nErrs = isSizedArray($_SESSION['frontend_errors'])
				? count($_SESSION['frontend_errors']) - array_count_keyes($_SESSION['frontend_errors'], "conf")
				: 0;

		} // public static function countFrontendErrors()

		/**
		 * Redirects to a given template or route
		 *
		 * @param String $url (Relative to Project Root)
		 * @param bool   $route
		 * @param bool   $forceRedirect
		 *
		 * @return bool
		 */
		public function redirect(String $url, Bool $route = false, Bool $forceRedirect = false)
		{

			if(headers_sent())
			{

				if($forceRedirect && !$route)
					echo "<script>window.location.replace('".$url."');</script>";
				else return false;

			}

			if(!$route && !file_exists($url)) return false;

			if($this->data['is_localhost'])
				$url = str_replace(
					ABSPATH,
					(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://localhost/" . APP_NAME,
					$url
				);

			if(isSizedString($url))
			{

				unset($this->db);
				header("Location: ".$url);
				exit;

			}

			return true;


		} // public function redirect()

		/**
		 * Refreshes the current page
		 *
		 * @param bool $forceReload
		 * @param bool $cleanParams
		 */
		public function refresh(bool $forceReload = true, bool $cleanParams = true)
		{

			if(headers_sent() && $forceReload)
			{

				if(!$cleanParams) echo "<script>location.reload();</script>";
				else echo "<script>window.location = window.location.href.split('?')[0];</script>";

			}
			else
			{

				$url = $actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

				if($cleanParams)
					$url = $actual_link = substr($url, 0, strpos($url, "?")) ?: $url;

				@header("Location: " . $url);

			}

		} // public function refresh()

		/**
		 * @param $uri          String  Relative to Project Root
		 * @param $dist_name    String  Distribution of template (e.g. mod_auth, admin)
		 *                              This variable is primarily used in the common template 'header.phtml'
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function renderTemplate(String $uri, String $dist_name = "")
		{

			if(strpos($uri, ABSPATH) === false) $path = ABSPATH . $uri;
			else $path = $uri;

			if(isSizedString($path) && file_exists($path))
			{

				if($dist_name === "admin") $AC = new AdminController();
				$TC = new TemplateController(@$_SESSION['page_type'], "TemplateManagement");
				$TC->syncModStatuses();

				if(!$TC->activeModulesLoaded() || !isSizedArray($TC->modules)) $TC->loadActiveModules();

				include_once $path;

			}

			return false;

		} // renderTemplate($uri)

		/**
		 * Checks whether the config file is present and not empty
		 */
		public function configIsBuilt()
		{

			$path = CONFIG_PATH.'/config.ini';

			return (bool)(file_exists($path) && filesize($path) !== 0);

		} // public function configIsBuilt()

		/**
		 * Creates a copy of the sample config
		 */
		public function buildConfig()
		{

			// Init final arr
			$sampleArr = array();
			// Get sample content
			$sampleRaw = file_get_contents(CONFIG_PATH.'/config.sample.ini');

			// Format and distribute default content
			foreach(explode("\n", $sampleRaw) as $k => $line)
				if($line[0] !== ";" && !empty(trim($line)))
					array_push($sampleArr, $line);

			// Create config file and write to it
			return $this->writeConfFile($sampleArr);

		} // public function buildConfig()

		/**
		 * Writes an array to the conf ini file
		 *
		 * @param array $payload
		 * @param bool  $forceReload
		 *
		 * @return bool|int
		 */
		public function writeConfFile(array $payload = [], $forceReload = true)
		{

			if(!isSizedArray($payload)) $payload = $this->data['config'];
			$confPath = CONFIG_PATH.'/config.ini';

			$processedPayload = arr2ini($payload);

			file_put_contents($confPath, $processedPayload);

			$this->refresh($forceReload);

			return true;

		} // public function writeConfFile()

		/**
		 * Loads all modules no matter what
		 * Note, that if a certain module needs parameters to struct, it will not be loaded
		 *
		 * @throws ReflectionException
		 * @throws DatabaseError
		 */
		public final function loadModules()
		{

			$this->syncModStatuses();

			$loadedModules = array();
			$files_arr = array();

			$dir_contains = array_diff(scandir(MODS_PATH), array(".", ".."));

			foreach($dir_contains as $k => $file)
			{
				if(!is_dir(MODS_PATH."/".$file) && preg_match("/(.*)\.class\.php/i", $file))
				{

					require_once MODS_PATH."/".$file;

					$className = str_replace(".class.php", "", $file);
					$reflect = new ReflectionClass($className);
					if(!empty($reflect->getConstructor()->getParameters())) continue;

					if(@in_array(MODS_PATH."/".$file, get_included_files()))
						array_push($files_arr, str_replace(".class.php", "", $file));

				}
			}

			foreach($files_arr as $k => $file)
			{

				$loadedModules[$file] = new $file();
				array_push($this->loaded_modules, $file);
				$this->loadModuleRoutes($file);

			}

			if($this->modules !== $loadedModules) $this->modules = $loadedModules;

		} // public function loadModules()

		/**
		 * Loads a single module
		 *
		 * @param String $mod_name
		 * @param        $params
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function loadModule(String $mod_name, $params = null)
		{

			$this->syncModStatuses();

			foreach(get_included_files() as $k => $file)
				if($file === str_replace("/", "\\", MODS_PATH."\\".$file))
					return false;


			$dir_contains = array_diff(scandir(MODS_PATH), array(".", ".."));

			foreach($dir_contains as $k => $file)
			{
				if(!is_dir(MODS_PATH."/".$file) && preg_match("/(.*)\.class\.php/i", $file) && $file === "$mod_name.class.php" && $file !== "struct_mod.class.php")
				{

					$occurence = 0;
					foreach($this->modules as $mod_name => $mod_inst)
						if($mod_name === $file) $occurence ++;


					if($occurence === 0)
					{

						include_once MODS_PATH."/".$file;

						if(isSizedArray($params))
						{

							if(in_array("db", $params) && !isset($this->db)) $this->init();
							$file = str_replace(".class.php", "", $file);

							try {
								$reflection = new ReflectionClass($file);
								$this->modules[$file] = $reflection->newInstanceArgs($params);
							}
							catch(ReflectionException $err) {
								$this->addLogMessage("An error occured while creating an instance of the class $file");
							}

						}
						else
						{

							$file = str_replace(".class.php", "", $file);

							if(class_exists($file)) $this->modules[$file] = new $file($params);
							else if(DEBUG_MODE) die("Class $file not found. MainController::loadModule");

						}

						$file = str_replace(".class.php", "", $file);
						$this->loadModuleRoutes($file);

						array_push($this->loaded_modules, $file);

					}

				}
			}

			return true;

		} // public function load_module()

		/**
		 * Loads all active modules
		 */
		public function loadActiveModules()
		{

			if(!isSizedArray($this->mods_statuses))
				$this->syncModStatuses();

			foreach($this->mods_statuses as $mod_name => $mod_status)
			{
				if((bool)$mod_status === true)
				{

					if($mod_name === "mod_auth") $this->loadModule("mod_auth", $this->db);
					else $this->loadModule($mod_name);

				}
			}

		} // public function loadActiveModules()

		/**
		 * Checks whether all active modules have been loaded
		 *
		 * @return bool
		 */
		public function activeModulesLoaded()
		{

			$deficits = 0;

			foreach($this->mods_statuses as $mod_name => $mod_status)
			{
				if($mod_status == true)
				{

					if(!in_array($mod_name, $this->loaded_modules))
						$deficits ++;

				}
			}

			return $deficits > 1 ? false : true;

		} // public function activeModulesLoaded

		/**
		 * Loads the Routes.php in a specific mod's view directory
		 *
		 * @param $mod_name
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function loadModuleRoutes($mod_name)
		{

			if(!$this->isActiveMod($mod_name)) return false;
			if(!isSizedArray($this->modules)) $this->loadActiveModules();

			$dir_contains = array_diff(scandir(MODS_PATH), array(".", ".."));

			foreach($dir_contains as $k => $element)
			{
				if($element === $mod_name && !preg_match("/(.*)\.class\.php/i", $element))
				{

					$_dir_contains = array_diff(scandir(VIEWS_MODS_PATH."/$mod_name"), array(".", ".."));

					foreach($_dir_contains as $k => $file_name)
					{
						if($file_name === "Routes.php")
							require_once VIEWS_MODS_PATH."/$mod_name/$file_name";
					}

				}
			}

		} // public function loadModuleRoutes()

		/**
		 * Synchronises the preconfigured mod statuses
		 * with stored ones of this class instance
		 *
		 * @return mixed|void
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function syncModStatuses()
		{

			$this->init();

			$config = $this->data['config'] ?: @array();

			if(!isset($this->data['mods_statuses_save_technique']))
				$this->data['mods_statuses_save_technique'] = $config['mods_statuses_save_technique'];

			if($this->data['mods_statuses_save_technique'] === "config")
			{

				$preview = array();

				foreach($config as $setting => $val)
					if(strpos($setting, "mod_") !== false) $preview[$setting] = $val;

				if($preview !== $this->mods_statuses) $this->mods_statuses = $preview;

			}

			if($this->data['mods_statuses_save_technique'] === "db")
			{

				try {
					$mods_data = $this->db->select(
						'SELECT * FROM mods'
					);
				}
				catch (Error $err) {
					throw new Error($err->getMessage());
				}

				if($mods_data === "" || empty($mods_data))
				{

					if(empty($this->modules) || !isSizedArray($this->modules))
					{
						$this->loadActiveModules();
						$this->loadModule("mod_auth", $this->db);
					}

					foreach($this->modules as $mod_name => $mod_inst)
					{
						if($mod_name === "struct_mod") continue;
						try {
							$this->db->insert(
								'mods',
								[
									"mod_name"   => $mod_name,
									"mod_status" => false
								]
							);
						}
						catch (Error $err) {
							throw new DatabaseError($err->getMessage());
						}
						$this->syncModStatuses();
					}

				}
				else
				{
					$preview = array();

					foreach($mods_data as $index => $mod)
						$preview[$mod['mod_name']] = $mod['mod_status'];

					if($preview !== $this->mods_statuses) $this->mods_statuses = $preview;
				}

			}

		} // public final function syncModStatuses()

		/**
		 * Synchronises mod statuses data from the DB with the config file
		 *
		 * @return bool|int
		 */
		public final function writeDBModStatusesToConf()
		{

			$db_mods_statuses = array();

			// Prepare mod statuses data
			$this->loadData('mod', 0);

			// Transfer status data
			foreach($this->data['mod'][0] as $k => $mod)
				$db_mods_statuses[$mod['mod_name']] = (bool)$mod['mod_status'] === true ? "enabled" : "disabled";

			// Write to conf payload
			foreach($db_mods_statuses as $mod_name => $mod_status)
				$this->data['config'][$mod_name] = $mod_status;

			// Save conf payload
			return $this->writeConfFile();

		} // public final function writeDBModStatusesToConf()

		/**
		 * Checks whether a mod is activated
		 *
		 * @param $mod_name
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function isActiveMod($mod_name)
		{

			if(empty($this->mods_statuses)) $this->syncModStatuses();

			$comparableModStatuses = $this->mods_statuses;

			foreach($comparableModStatuses as $k => $v)
				$comparableModStatuses[strtolower($k)] = $v;

			return keyValuePairExists(
				array(strtolower($mod_name), 1),
				$comparableModStatuses
			);

		} // public final function isActiveMod()

		/**
		 * Activate a specific mod
		 *
		 * @param $mod_name
		 *
		 * @return mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function activateMod($mod_name)
		{

			global $mods_toggleFailure__togglingDisabled;

			// If toggling is illegal according to the module affected
			if(!isset($this->modules[$mod_name])) $this->loadModule($mod_name);
			if($this->modules[$mod_name]->mod_isToggleable === false)
				return $this->addFrontendError($mods_toggleFailure__togglingDisabled[$GLOBALS['lang']]);

			$this->data['mods_statuses_save_technique'] = "db";

			try {
				$this->db->update(
					'mods',
					[ 'mod_status' => 1 ],
					[ 'mod_name' => $mod_name ]
				);
			}
			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

			$this->syncModStatuses();
			$this->writeDBModStatusesToConf();

		} // public final function activateMod()

		/**
		 * Deactivate a specific mod
		 *
		 * @param $mod_name
		 *
		 * @return mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function deactivateMod($mod_name)
		{

			global $mods_toggleFailure__togglingDisabled;

			// If toggling is illegal according to the module affected
			if(!isset($this->modules[$mod_name])) $this->loadModule($mod_name);
			if($this->modules[$mod_name]->mod_isToggleable === false)
				return $this->addFrontendError($mods_toggleFailure__togglingDisabled[$GLOBALS['lang']]);

			$this->data['mods_statuses_save_technique'] = "db";

			try {
				$this->db->update(
					'mods',
					[ 'mod_status' => 0 ],
					[ 'mod_name' => $mod_name ]
				);
			}
			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

			$this->syncModStatuses();
			$this->writeDBModStatusesToConf();

		} // public final function deactivateMod()

		/**
		 * Calls a given function of a given mod
		 *
		 * @param       $mod
		 * @param       $func
		 * @param array $params
		 *
		 * @return bool
		 */
		public final function callModFunc($mod, $func, $params = array())
		{

			if(empty($this->modules)) $this->loadActiveModules();
			if(!inMultDimArr($mod, $this->modules)) return false;
			if(substr($func, -2) == "()") $func = substr($func, 0, -2);

			foreach($this->modules as $mod_name => $mod_inst)
			{
				if($mod === $mod_name && method_exists($mod_inst, $func))
				{

					$call = call_user_func_array(array($mod_inst, $func), $params);
					if($call == -2) return false;

				}
			}

			return true;

		} // public final function callModFunc($mod, $func)

		/**
		 * Returns all module static and language names
		 *
		 * @return array
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public static function getTranslatedModNames(): array
		{

			// Init return array
			$returnVal = array();

			// Init new MC instance an load modules
			$MC = new MainController();
			$MC->loadModules();

			// Build return val
			foreach($MC->modules as $mod_name => $mod_obj)
				$returnVal[$mod_name] = $mod_obj->mod_name_translated;

			return $returnVal;

		} // public static function getTranslatedModNames()

		/**
		 * Returns the translated name of a module identified by its application name (e.g. mod_time)
		 *
		 * @param String $name      The application name
		 *
		 * @return String
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public static function getTranslatedModNameByName(String $name): String
		{

			// Get the translated names of all registerd modules
			$translated_names = MainController::getTranslatedModNames();

			return @$translated_names[$name] ?: "";

		} // public static function getTranslatedModNameByName()

		/**
		 * Returns an instance of a specific model and includes it if neccessary
		 *
		 * @param String $modelName
		 *
		 * @return mixed
		 */
		public function getModelInstance(String $modelName)
		{

			$this->init();

			$model_filename = strpos($modelName, ".class.php") !== false
				? ucfirst($modelName)
				: ucfirst($modelName) . ".class.php";

			$model_path = str_replace("/", "\\", MODEL_PATH)."\\".ucfirst($model_filename);

			if(!@in_array($model_path, get_included_files()))
			{

				$models = array_diff(scandir(MODEL_PATH), array(".", ".."));

				/* This has been altered when migrating to the Raspberry PI */
				foreach($models as $k => $model)
					if(strtoupper($model) === strtoupper($model_filename) && in_array($model_filename, scandir(MODEL_PATH)))
						require_once MODEL_PATH . "/$model_filename";

			}

			/* This has been altered when migrating to the Raspberry PI */
			return class_exists($modelName) 
				? new $modelName() 
				: null;

		} // public final function getModelInstance()

		/**
		 * Stores parsed data in the controller instance
		 *
		 * @param String $modelName
		 * @param int    $identifier
		 *
		 * @return void
		 */
		public function loadData(String $modelName, int $identifier = 1)
		{

			$model_inst = $this->getModelInstance($modelName);

			if($identifier === 0 && method_exists($model_inst, 'loadAll')) $model_inst->loadAll();
			else $model_inst->load($identifier);

			$this->data[strtolower($modelName)][$identifier] = $model_inst->data;

		} // public function loadData($component, $identifier)

		/**
		 * Calls a function of a given model
		 *
		 * @param String $modelName
		 * @param String $funcName
		 * @param        $params
		 *
		 * @return bool
		 */
		public function callModelFunc(String $modelName, String $funcName, ...$params)
		{

			// Get model instance
			$model = $this->getModelInstance($modelName);

			// Call Method/Func
			if(method_exists($model, $funcName))
				if(count($params) > 1) $returnVal = call_user_func_array(array($model, $funcName), $params);
				else $returnVal = $model->$funcName($params[0]);
			else return false;

			if(isset($returnVal) && isSized($returnVal))
			{

				if(isset($returnVal["mod_name"]) && isset($returnVal["data"]))
				{

					// Further identification of origin
					$this->data[strtolower($modelName)][$funcName][$returnVal['mod_name']] = $returnVal['data'];

				}
				else $this->data[strtolower($modelName)][$funcName] = $returnVal;

				if((bool)$returnVal !== false) return true;
				else return false;

			}

		} // public function callModelFunc()

		/**
		 * Returns the mac address of this client
		 */
		public function getMacAddress()
		{

			return shell_exec("cat /sys/class/net/$(ip route show default | awk '/default/ {print $5}')/address");

		} // public function getMacAddress()

		/**
		 * Returns the ip address of a device in the network having a given mac address
		 *
		 * @param $mac_address
		 *
		 * @return string
		 */
		public function getIPByMacAddress($mac_address): String
		{

			$hostnameI = shell_exec("hostname -I");

			$ip = explode(" ", $hostnameI)[0];
			$ipParts = explode(".", $ip);
			$obsoletePart = $ipParts[count($ipParts) - 1];

			$relevantIP = str_replace($obsoletePart, "", $ip);

			return shell_exec("nmap -sP $relevantIP.0/24 >/dev/null && arp -an | grep $mac_address | awk '{print $2}' | sed 's/[()]//g'") ?: "";

		} // public function getIPByMacAddress()

		/**
		 * Gets the mac address of the known device having a given ip
		 *
		 * @param $ip
		 *
		 * @return String
		 */
		public function getMacAddressByIP($ip)
		{

			$mac_addr = "";
			$arp_info = explode("\n", shell_exec(escapeshellcmd("arp -a")));

			foreach($arp_info as $lineNumber => $lineContent)
			{

				preg_match('#\((.*?)\)#', $lineContent, $current_ip);

				if(isSizedArray($current_ip) && strpos($current_ip[1], "(") === false)
					$current_ip = $current_ip[1];

				if($current_ip === $ip)
				{

					$lineContentParts = explode(" ", $lineContent);
					$connectionTypeIndex = array_search("[ether]", $lineContentParts) ?: array_search("[wlan]", $lineContentParts);

					if(!isSizedString($mac_addr))
						$mac_addr = $lineContentParts[$connectionTypeIndex - 1];

				}

			}

			return $mac_addr;

		} // public function getMacAddressByIP()


	} // class MainController