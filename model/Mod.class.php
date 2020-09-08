<?php
	/**
	 * User: Flo
	 * Date: 07.09.2018
	 * Time: 22:24
	 */

	class Mod extends Model
	{

		/** @var $data array field to store user specific data */
		public $data;
		/** @const table_prefix string field to store the table specific prefix */
		const TABLE_PREFIX = "mod_";

		/**
		 * Loads all rows
		 */
		public function loadAll()
		{

			parent::__construct();

			$this->data = $this->db->select(
				"SELECT * FROM `mods`"
			);

		} // public function loadAll()

		/**
		 * Loads a specific module
		 *
		 * @param String $mod_name
		 *
		 * @return array|bool
		 */
		public function loadSpecificModule(String $mod_name)
		{

			parent::__construct();

			// Struct a valid module name
			if(strpos($mod_name, "mod_") === false)
				$mod_name = "mod_" . $mod_name;

			// Filter all module relevant tables
			$tableNames = [];

			array_walk($this->tables, function($tblName) use($mod_name, &$tableNames)
			{
				if(strpos(strtolower($tblName), strtolower($mod_name)) !== false)
				{

					array_push($tableNames, $tblName);

				}
			});

			if(!in_array(strtolower($mod_name), $tableNames))
			{

				array_push($tableNames, $mod_name);

			}

			// Query all filtered tables
			foreach($tableNames as $tblName)
			{
				try{
					$response = $this->db->select(
						'SELECT * FROM ' . strtolower($tblName)
					) ?? array();

					if(count($tableNames) > 1) $result[$tblName] = $response;
					else $result = $response;
				}
				catch(ModulePDOException $e) {
					// Base table $mod_name most likely not found
					continue;
				}
			}

			return $this->data = array(
				"mod_name" => $mod_name,
				"data" => $result ?? []
			);

		} // public function loadSpecificModule()

		/**
		 * Executes a saving action for a specific module with a given mapping payload
		 *
		 * @param String $mod_name
		 * @param array  $mappings
		 *
		 * @return mixed
		 */
		public function saveDataForSpecificModule(String $mod_name, array $mappings)
		{

			parent::__construct();

			if(strpos($mod_name, "mod_") === false)
				$mod_name = "mod_" . $mod_name;

			// Insert payload into db
			return $this->db->insert(strtolower($mod_name), $mappings);

		} // public function saveDataForSpecificModule()

		/**
		 * Updates given data of a specific module
		 *
		 * @param String $mod_name
		 * @param array  $update_data
		 * @param array  $where_mappings
		 *
		 * @return mixed
		 */
		public function updateDataForSpecificModule(String $mod_name, array $update_data, array $where_mappings)
		{

			parent::__construct();

			return $this->db->update(strtolower($mod_name), $update_data, $where_mappings);

		} // public function updateDataForSpecificModule()

		/**
		 * Deletes data of a specific module
		 *
		 * @param String $mod_name
		 * @param array  $whereMappings
		 *
		 * @return mixed
		 */
		public function deleteDataOfSpecificModule(String $mod_name, array $whereMappings)
		{

			parent::__construct();

			return $this->db->delete(strtolower($mod_name), $whereMappings);

		} // public function deleteDataOfSpecificModule

	} // class Mod()