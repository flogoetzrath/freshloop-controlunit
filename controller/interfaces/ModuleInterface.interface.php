<?php

	interface ModuleInterface {

		/**
		 * @function loadModules()
		 *           Loads every modules, that does not require additional parameters
		 *
		 * @return mixed
		 */
		function loadModules();
		// function loadModules

		/**
		 * @function loadModule()
		 *           Loads a specific module with a given parameters
		 *
		 * @param String $mod_name
		 * @param array  $params
		 *
		 * @return mixed
		 */
		function loadModule(String $mod_name, $params = null);
		// function loadModule()

		/**
		 * @function loadActiveModules()
		 *           Loads all active modules
		 *
		 * @return mixed
		 */
		function loadActiveModules();
		// function loadActiveModules()

		/**
		 * @function syncModStatuses()
		 *           Synchronizes the preconfigured mod statuses with the corresponding data of the instance
		 *
		 * @return mixed
		 */
		function syncModStatuses();
		// function syncModStatuses()

		/**
		 * @function isActivateMod()
		 *           Checks whether a given module is currently active
		 *
		 * @param $mod_name
		 *
		 * @return mixed
		 */
		function isActiveMod($mod_name);
		// function isActiveMod()

		/**
		 * @function activateMod()
		 *           Activates a given module
		 *
		 * @param $mod_name
		 *
		 * @return mixed
		 */
		function activateMod($mod_name);
		// function activateMod()

		/**
		 * @function deactivateMod()
		 *           Deactivated a given Module
		 *
		 * @param $mod_name
		 *
		 * @return mixed
		 */
		function deactivateMod($mod_name);
		// function deactivateMod()

	}