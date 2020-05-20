<?php
	/**
	 * @name: struct_mod
	 * @description: Module Foundation
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	class struct_mod extends MainController
	{

		/** @var string mod name field to store the name of the currently used module */
		public $mod_name;
		/** @var string mod name translated field to store the translated name of the currently used module */
		public $mod_name_translated;
		/** @var bool mod active field for checking whether this mod is actually activated */
		public $mod_isActive;
		/** @var bool mod activatable field to store whether the module can be toggled in the frontend */
		public $mod_isToggleable;
		/** @var array module dependencies field to store the names of modules that are required to run the current module */
		public $mod_dependencies;
		/** @var bool mod isoperable field to store whether the current module can be operated considering that dependencies might not be present */
		public $mod_isOperable;
		/** @var array module missing dependencies field to store the names of modules that are required but not loaded */
		public $mod_missing_dependencies;
		/** @var array conf field to store mod specific config settings */
		public $conf;
		/** @var array store field to hold mod specific data */
		public $store;

		public function __construct(String $activeMod = "", String $translatedModName = "")
		{

			parent::__construct($activeMod);

			// Read Config
			if(!isSizedArray($this->data['config'])) $this->readConfig();
			foreach($this->data['config'] as $setting => $value)
			{
				if(strpos($setting, $activeMod) !== false)
				{

					$this->conf[$setting] = $value;

				}
			}

			// Assign front facing values
			$this->mod_name = $activeMod;
			$this->mod_name_translated = $translatedModName;
			$this->mod_isActive = $this->isActiveMod($activeMod);
			$this->mod_isToggleable = $this->conf[$this->mod_name.'__toggleable'] === "enabled" ? true : false;
			$this->mod_dependencies = array();
			$this->mod_isOperable = true;
			$this->mod_missing_dependencies = array();

			// Load mod related data if any
			$this->callModelFunc("mod", "loadSpecificModule", static::ACTIVE_MOD);
			$this->store = @$this->data['mod']["loadSpecificModule"][static::ACTIVE_MOD] ?: null;

		} // public function __construct()

		public function __call($method, $args)
		{

			if(function_exists("_methodCalled")) _methodCalled($method, $args);
			else return false;
			
			$actualMethod = "_$method";
			call_user_func_array(array($this, $actualMethod), $args);

		} // public function __call()

		/**
		 * Custom magic function dedicated to be the middleman in method execution of modules
		 *
		 * @note: Only function with an underscore as prefix are affected
		 *
		 * @param $method
		 * @param $args
		 */
		public function __methodCalled($method, $args)
		{

			if($this->mod_isActive === false)
			{

				$err = "Method $method illegaly called on disabled module $this->mod_name.";

				if(DEBUG_MODE) die(debug($err));
				else $this->addLogMessage($err);

			}

		} // public function __methodCalled()

		/**
		 * Checks if all modules the current one relies on are enabled
		 *
		 * @param array $dependencies   Array of names of modules that need to be activated
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function checkDependencies(array $dependencies = [])
		{

			if(!isSizedArray($dependencies))
				$dependencies = $this->mod_dependencies;

			// Init payload
			$returnVal = [
				"status" => true,
				"msg" => "",
				"required_but_disabled" => array()
			];

			// If no dependencies are registered for the current module
			if(!isSizedArray($dependencies)) return $returnVal;

			// Loop through the dependencies and check whether they are all activated
			foreach($dependencies as $dependency_name)
			{
				if(!(bool)$this->isActiveMod($dependency_name))
				{

					$returnVal['status'] = false;
					array_push($returnVal['required_but_disabled'], $dependency_name);

				}
			}

			// In case of failure
			if($returnVal['status'] === false)
			{

				// Build a message for immediate status report after class instantiation
				global $mods_enableFailure__requiredModulesNotEnabled;
				$returnVal['msg'] = $mods_enableFailure__requiredModulesNotEnabled[$GLOBALS['lang']] . implode(", ", $returnVal['required_but_disabled']);

			}

			// Save missing dependencies to instance for further analysis
			$this->mod_missing_dependencies = $returnVal['required_but_disabled'];

			return $returnVal;

		} // public function checkDependencies()

		/**
		 * Resets the database table for the current module
		 *
		 * @note If this function is called and is successful, the replaced data is lost irreversibly
		 */
		public final function resetModule():bool
		{

			// Check whether a reset sql file is present
			$mod_path = MODS_PATH.'/'.$this->mod_name;
			if(!(is_dir($mod_path)) || !(is_file($mod_path.'/default.sql'))) return false;

			// Otherwise run the sql file
			$stmt = $this->db->conn->prepare(
				file_get_contents($mod_path.'/default.sql')
			);

			return (bool)$stmt->execute();

		} // public final function resetModule()

	} // class struct_mod extends MainController