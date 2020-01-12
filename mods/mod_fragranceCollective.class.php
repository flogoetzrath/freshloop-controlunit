<?php
	/**
	 * @name: mod_fragranceCollective
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";

	class mod_fragranceCollective extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_fragranceCollective";
		/** @var int field to store the index of this modules' event type for general use */
		const MODULE_EVENT_TYPE_INDEX = 3;
		/** @var array field to store the names of modules that are required in order to load the current one */
		const REQUIRED_MODULES = array(
			"mod_time",
			"mod_fragranceChoice"
		);

		public function __construct()
		{

			// Get the translated name of the current module
			global $fragranceCollective_modName;

			// Construct the parent
			parent::__construct(
				static::ACTIVE_MOD,
				$fragranceCollective_modName[$GLOBALS['lang']]
			);

			$this->processDependencyAvailability();

		} // function __construct()

		/**
		 * Module reaction to its dependency status
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function processDependencyAvailability($throwMsg = false)
		{

			// Check whether the required module(s) is/are enabled
			$this->mod_dependencies = static::REQUIRED_MODULES;
			$dependenciesCheck = parent::checkDependencies();

			if($dependenciesCheck['status'] === false)
			{

				$this->mod_isOperable = false;
				if($throwMsg) $this->addFrontendError($dependenciesCheck['msg']);

				return false;

			}

			return true;

		} // public function processDependencyAvailability()

		/**
		 * Dispatches a payload being received requests passed by a lower instance
		 *
		 * @param array $data
		 *
		 * @return bool|void
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final public function dispatch($data = array())
		{

			$data = isSizedArray( $data ) ? $data : $_REQUEST;

			// Assign a specifc number of fragrances to an event
			if(isSizedString($data['action']) && $data['action'] === "AssignNFragrancesToEvent")
				$this->assignNFragrancesToEvent(xssproof($data));

		} // final public function dispatch()

		/**
		 * Assign a specifc number of fragrances to an event
		 *
		 * @param array $data
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final public function assignNFragrancesToEvent(array $data)
		{

			# Checks internal function availability

			if($this->mod_isOperable === false) return false;

			# Validation

			// If a reference event is passed
			if(isSizedString($data['ref_event_id']))
			{

				// Check whether the supplied id actually refers to an existing event

				// Load mod_time if it has not been instantiated yet
				if(empty($this->modules['mod_time']) || empty($this->modules['mod_time']))
					$this->loadModule("mod_time");

				// Get the event
				$ref_event = $this->modules["mod_time"]->getEventByID($data['ref_event_id']);

				// If the event is empty/non existing, cancel function call
				if(!isSizedArray($ref_event)) return false;

			} else return false;

			# Capsuled saving

			/**
			 * Saves an association between fragrance and fragrance collective event to the db
			 *
			 * @param $fragrance_id
			 */
			$save_entry = function($fragrance_id) use($data)
			{

				// Save to db
				$this->db->insert(
					strtolower(static::ACTIVE_MOD),
					[
						"ref_event_id" => $data['ref_event_id'],
						"ref_fragrance_id" => $fragrance_id
					]
				);

			}; // anonymous function $save_entry()

			# Capsuled removing

			/**
			 * Deletes a referenced fragrance from the module table
			 *
			 * @param $fragrance_id
			 */
			$del_entry = function($fragrance_id) use($data)
			{

				// Delete the column
				$this->db->delete(strtolower(static::ACTIVE_MOD), ["ref_fragrance_id" => $fragrance_id]);

			}; // anonymous function $del_entry()

			# Processing

			// Var initiation
			$req_affected_fragrances = array();
			$stored_entries = array();
			$duplicates = 0;

			// Separate fragrances from one and another
			foreach($data as $k => $v)
				if(strpos($k, "id_") !== false && strpos($k, "__fragrance-") !== false)
					$req_affected_fragrances[explode("-", $k)[1]] = $v;

			// Get all entries for the current event from the db
			$stored_entries = $this->getFragranceCollectiveByEventID($data['ref_event_id']);

			// Iterate over all fragrances affected by the request
			foreach($req_affected_fragrances as $fr_id => $fr_activation)
			{

				// If requested fragrance is already in db --> it is already attached to the event
				if(in_array($fr_id, $stored_entries))
				{
					// If the fragrance is requested to get activated
					if($fr_activation === "on")
					{

						// Duplicate detected
						$duplicates ++;

					}
					else
					{

						// Duplicate is issued to get removed
						$del_entry($fr_id);

					}
				}
				else
				{
					if($fr_activation === "on")
					{

						// Duplicate is to be activated
						$save_entry($fr_id);

					}
				}

			}

			// If not a single value has been changed
			if($duplicates === count($req_affected_fragrances))
			{

				global $f_err_no_vals_changed;
				$this->addFrontendError($f_err_no_vals_changed[$GLOBALS['lang']]);

			}

			$this->refresh();

		} // final public function assignNFragrancesToEvent()

		/**
		 * Filters all events for ones that have this current module enabled
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final public function getFragranceCollectiveEnabledModules()
		{

			if($this->mod_isOperable === false) return false;

			$returnArr = array();

			$this->loadModule("mod_time");
			$event_data = $this->modules["mod_time"]->store;

			foreach($event_data as $k => $event)
			{

				if((int)$event['event_type'] === static::MODULE_EVENT_TYPE_INDEX)
					array_push($returnArr, $event);

			}

			return $returnArr;

		} // final public function getFragranceCollectiveEnabledModules()

		/**
		 * Gets all reference fragrances that are attached to an event of type fragrance collective
		 *
		 * @param int $event_id
		 *
		 * @return array
		 */
		final public function getFragranceCollectiveByEventID(int $event_id)
		{

			# Get all fragrances of the collective attached to a given event
			// Query DB
			$res = $this->db->select(
				"SELECT * FROM ".strtolower(static::ACTIVE_MOD)." WHERE ref_event_id = ?",
				[$event_id]
			);

			if(!isSizedArray($res)) return array();

			// Filter for fragrances
			$fragrances_attached = array();

			foreach($res as $k => $event_ref)
			{

				if(isSized($event_ref['ref_fragrance_id']))
					array_push($fragrances_attached, $event_ref['ref_fragrance_id']);

			}

			return $fragrances_attached;

		} // final public function getFragranceCollectiveByEventID()

		/**
		 * Builds a dataset in the form of fragranceCollective_fragranceID => (array)unit_fragrance_positions
		 *
		 * @param $event_data
		 * @param $involved_units
		 * @param $fragrances_store
		 *
		 * @return array
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final public function buildFragrancesPerUnitByEventID($event_data, $involved_units, $fragrances_store)
		{

			// Init an instance of mod_fragranceChoice
			$this->loadModule("mod_fragranceChoice");

			// Get all involved fragrances
			$frCollectiveFragranceIDs = $this->getFragranceCollectiveByEventID($event_data['event_id']);

			// Init func vars
			$returnArr = array();
			$involved_units_ids = array();
			$assignedFragrances_per_frCollectiveFragrance = array_flip($frCollectiveFragranceIDs);

			// Get all involved unit ids
			foreach($involved_units as $unit) array_push($involved_units_ids, $unit['unit_id']);

			// Sort available fragrances of the demanded type by the units they are assigned to
			foreach($frCollectiveFragranceIDs as $frColl_generalFrID)
			{
				foreach($fragrances_store as $fr_dataset)
				{
					if($frColl_generalFrID === $fr_dataset['fragrance_id'])
					{

						$assignedFragrances = $this->modules['mod_fragranceChoice']->getAssignedFragrancesOfGeneralFType($frColl_generalFrID);

						// Eliminate all fragrances that are assigned to units that are not involved in the event
						foreach($assignedFragrances as $fr)
						{

							if(!in_array($fr['fragrance_unit'], $involved_units_ids))
								unset($assignedFragrances[array_search($fr, $assignedFragrances)]);

						}

						// Push the remaining data
						if(isSizedArray($assignedFragrances_per_frCollectiveFragrance[$frColl_generalFrID]))
							array_push($assignedFragrances_per_frCollectiveFragrance[$frColl_generalFrID], $assignedFragrances);
						else $assignedFragrances_per_frCollectiveFragrance[$frColl_generalFrID] = array($assignedFragrances);

					}
				}
			}

			// Build the returnArray
			foreach($assignedFragrances_per_frCollectiveFragrance as $generalFrID => $assignedFragrancesWrapper)
			{
				foreach($assignedFragrancesWrapper as $assignedFragrances)
				{
					foreach($assignedFragrances as $assignedFragrance)
					{

						if(!isSizedArray($returnArr[$assignedFragrance['fragrance_unit']]))
							$returnArr[$assignedFragrance['fragrance_unit']] = array($assignedFragrance['fragrance_position']);
						else
						{

							if(!in_array($assignedFragrance['fragrance_position'], $returnArr[$assignedFragrance['fragrance_unit']]))
								array_push($returnArr[$assignedFragrance['fragrance_unit']], $assignedFragrance['fragrance_position']);

						}

					}
				}
			}

			return $returnArr;

		} // final public function buildFragrancesPerUnitByEventID()


	} // class mod_fragranceCollective