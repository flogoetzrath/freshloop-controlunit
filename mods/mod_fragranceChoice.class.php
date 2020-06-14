<?php
	/**
	 * @name: mod_fragranceChoice
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";

	class mod_fragranceChoice extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_fragranceChoice";
		/** @var array field to store the names of modules that are required in order to load the current one */
		const REQUIRED_MODULES = array(
			"mod_time"
		);

		/**
		 * mod_fragranceChoice constructor
		 */
		public function __construct()
		{

			global $fragranceChoice_modName;

			parent::__construct(
				static::ACTIVE_MOD,
				$fragranceChoice_modName[$GLOBALS['lang']]
			);

			$this->processDependencyAvailability();

		} // public function __construct()

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

			$data = isSizedArray($data) ? $data : $_REQUEST;

			// Action of assigning either a frangrances to a very unit
			if(isSizedString($data['action']) && $data['action'] === "FragranceUnitAssignmentAction")
				$this->fragranceUnitAssignmentAction(xssproof($data));

			// Action of creating fragrances and assigning them to events
			if(isSizedString($data['action']) && $data['action'] === "FragranceManagementAction")
				$this->fragranceManagementAction(xssproof($data));

			// Action of deleting a general fragrance
			if(isSizedString($data['action']) && $data['action'] === "deleteGeneralFragrance")
				$this->deleteGeneralFragrance(xssproof($data['fragrance_id']));

			// Action of assigning a fragrance to an event
			if(isSizedString($data['action']) && $data['action'] === "assignFragranceToEvent")
				$this->assignFragranceToEvent(xssproof($data));

		} // final public function dispatch()

		/**
		 * Module reaction to its dependency status
		 *
		 * @param bool $throwMsg
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
		 * Gets called when a fragrance has been assigned to a position of a fragrance wheel of a unit
		 *
		 * @param $payload
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final protected function fragranceUnitAssignmentAction($payload)
		{

			global $fChoice_unitAssignmentAction__success, $fChoice_unitAssignmentAction__failure;

			# Validation
			if(in_array(null, array($payload['unit_id'], $payload['pos'], $payload['pos_fragrance'])))
				return false;

			# Load Module
			if(!isSizedArray($this->store))
				$this->loadModule(static::ACTIVE_MOD);

			# Create data holders
			$unit_id = $payload['unit_id'];
			$unit_pos = $payload['pos'];
			$fragrance_at_pos_key = (int)$payload['pos_fragrance'] === 0 ? null : $payload['pos_fragrance'] - 1;
			$fragrance_at_pos = $this->store[$fragrance_at_pos_key];
			$fragrance_at_pos__general = $this->getGeneralFragrances()[$fragrance_at_pos_key];

			// If $fragrance_at_pos is wrong due to key delivery of the fragrance_unit_assignment_widget
			if($fragrance_at_pos !== $fragrance_at_pos__general)
			{

				$fragrance_at_pos = $fragrance_at_pos__general;
				$fragrance_at_pos['fragrance_unit'] = $unit_id;
				$fragrance_at_pos['fragrance_position'] = $unit_pos;

			}

			# Util anonymous function
			$cloneAndSave = function($name, $scent, $unit, $position) {
				return $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD, array(
					"fragrance_name" => $name,
					"fragrance_scent" => $scent,
					"fragrance_unit" => $unit,
					"fragrance_position" => $position
				));
			};

			# Call Model
			// If fragrance already has unit
			if(isSizedString($fragrance_at_pos['fragrance_unit']))
			{

				// Delete old row if present
				$deleteFragrance = false;

				foreach($this->store as $k => $fragrance)
					if($fragrance['fragrance_unit'] === $unit_id && $fragrance['fragrance_position'] === $unit_pos)
						$deleteFragrance = $fragrance['fragrance_id'];

				if($deleteFragrance !== false)
					$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD, array(
						"fragrance_id" => $deleteFragrance
					));

				// Clone fragrance and insert new unit + position
				$action = $cloneAndSave(
					$fragrance_at_pos['fragrance_name'],
					$fragrance_at_pos['fragrance_scent'],
					$unit_id,
					$unit_pos
				);

			}
			// If it was selected to clear $unit_pos from the wheel
			else if(is_null($fragrance_at_pos_key))
			{

				// Drop current fragrance from db
				$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD, array(
					"fragrance_unit" => $unit_id,
					"fragrance_position" => $unit_pos
				));

			}
			// If there is only a general type fragrance selected due to the lack of standard ones
			else if((bool)$fragrance_at_pos['fragrance_isGeneralFragrance'] === true)
			{

				$action = $cloneAndSave(
					$fragrance_at_pos['fragrance_name'],
					$fragrance_at_pos['fragrance_scent'],
					$unit_id,
					$unit_pos
				);

			}
			// Default case
			else
			{

				// Update fragrance to add unit and position fields
				$action = $this->callModelFunc("mod", "updateDataForSpecificModule", static::ACTIVE_MOD, array(
					"fragrance_unit" => $unit_id,
					"fragrance_position" => $unit_pos
				), array("fragrance_id" => $fragrance_at_pos["fragrance_id"]));

			}

			# End with status report and reload
			if($action) $this->addFrontendMessage($fChoice_unitAssignmentAction__success[$GLOBALS['lang']]);
			else $this->addFrontendError($fChoice_unitAssignmentAction__failure[$GLOBALS['lang']]);

			return $this->refresh();

		} // final protected function fragranceUnitAssignmentAction()

		/**
		 * Gets called when a general fragrance is added due to the submit of the corresponding modal
		 *
		 * @param $payload
		 *
		 * @return bool|mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final protected function fragranceManagementAction($payload)
		{

			global $fragranceChoice_fragranceSave__failure___duplication;
			global $fragranceChoice_fragranceSave__success, $fragranceChoice_fragranceSave__failure;

			# Validation
			if(in_array(null, array($payload['fr_name'], $payload['fr_scent'])))
				return false;

			# Get registered general fragrances
			$generalFragrances = $this->getGeneralFragrances();

			# Check duplication
			$duplicate = false;

			foreach($generalFragrances as $k => $fragrance)
				if(
					trim($payload['fr_name']) === $fragrance['fragrance_name'] &&
					trim($payload['fr_scent']) === $fragrance['fragrance_scent']
				) $duplicate = true;

			if($duplicate) return $this->addFrontendError($fragranceChoice_fragranceSave__failure___duplication[$GLOBALS['lang']]);

			# Save new general fragrance
			$action = $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD, array(
				"fragrance_name" => trim($payload['fr_name']),
				"fragrance_scent" => trim($payload['fr_scent']),
				"fragrance_isGeneralFragrance" => 1
			));

			# End with status report
			if($action) $this->addFrontendMessage($fragranceChoice_fragranceSave__success[$GLOBALS['lang']]);
			else $this->addFrontendError($fragranceChoice_fragranceSave__failure[$GLOBALS['lang']]);

			$this->refresh();

		} // final protected function fragranceManagementAction()

		/**
		 * Deletes a fragrance and all assigned fragrances of its type if it happens to be of type general
		 *
		 * @param $fragrance_id
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final protected function deleteGeneralFragrance($fragrance_id)
		{

			global $fragranceChoice_fragranceDeletion__success, $fragranceChoice_fragranceDeletion__failure;

			# Determine whether fragrance is of type general and there are fragrances assigned to it
			$fragrances_deletion = array($fragrance_id);

			$assigned_fragrances = $this->getAssignedFragrancesOfGeneralFType($fragrance_id);

			if(isSizedArray($assigned_fragrances))
				foreach($assigned_fragrances as $k => $fragrance)
					array_push($fragrances_deletion, $fragrance['fragrance_id']);

			# Delete Fragrance(s)
			$status = array();

			foreach($fragrances_deletion as $k => $f_id)
			{

				$del_status = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD, array(
					"fragrance_id" => $f_id,
				));

				array_push($status, $del_status);

			}

			# End with status report
			if(!in_array(false, $status))
				$this->addFrontendMessage($fragranceChoice_fragranceDeletion__success[$GLOBALS['lang']]);
			else $this->addFrontendError($fragranceChoice_fragranceDeletion__failure[$GLOBALS['lang']]);

			$this->refresh();

		} // final protected function deleteGeneralFragrance()

		/**
		 * Assigns a fragrance to an event
		 *
		 * @param $payload
		 *
		 * @return mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		final protected function assignFragranceToEvent($payload)
		{

			global $error_occured;
			global $fragranceChoice_frEventAssignment__failure___noChanges;
			global $fragranceChoice_frEventAssignment__success, $fragranceChoice_frEventAssignment__failure;
			global $fragranceChoice_frEventAssignmentDel__success, $fragranceChoice_frEventAssignmentDel__failure;

			# Filter event statuses
			$mod_time = new mod_time();
			$event_data = $mod_time->store;

			$mod_sensorResponder = new mod_sensorResponder();
			$mevent_data = $mod_sensorResponder->store;

			$event_fr_status = array();
			$mevent_fr_status = array();

			foreach($payload as $k => $v)
			{

				if(strpos($k, "event-") === false) continue;

				$str_id_len = strlen($k) - strpos($k, "-") - 1;
				$event_id = substr($k, strpos($k, "-") + 1, $str_id_len);
				$event_status = $v === "on" ? true : false;

				if(strpos($k, "mevent") !== false) $mevent_fr_status[$event_id] = $event_status;
				else $event_fr_status[$event_id] = $event_status;

			}

			# Validation
			if(
				in_array(null, $event_fr_status, true)
				||
				!isSizedArray($event_fr_status)
				||
				!isSizedString($payload['fragrance_id'])
				||
				!isSizedArray($event_data)
				||
				!$this->isActiveMod('mod_time')
			)
				return $this->addFrontendError($error_occured[$GLOBALS['lang']]);

			# Determine changes and call model to update values
			$add_actions = array();
			$remove_actions = array();

			// mod_time or mod_fragranceCollective related events
			foreach($event_fr_status as $event_id => $status)
				foreach($event_data as $k => $event)
					if(
						$event['event_id'] == $event_id
					)
					{

						if((bool)$status === true)
						{

							array_push(
								$add_actions,
								$this->callModelFunc("mod", "updateDataForSpecificModule", 'mod_time', array(
									"event_fragrance_id" => $payload['fragrance_id']
								), array("event_id" => $event_id))
							);

						}
						else if((bool)$status === false)
						{

							if(!is_null($event['event_fragrance_id']) && $event['event_fragrance_id'] !== $payload['fragrance_id'])
								continue;

							array_push(
								$remove_actions,
								$this->callModelFunc("mod", "updateDataForSpecificModule", 'mod_time', array(
									"event_fragrance_id" => null
								), array("event_id" => $event_id))
							);

						}

					}

			// mod_sensorResponder based music-events
			foreach($mevent_fr_status as $mevent_id => $status)
				foreach($mevent_data as $k => $mevent)
					if(
						(int)$mevent['mevent_id'] === (int)$mevent_id
					)
					{
						if((bool)$status === true)
						{

							array_push(
								$add_actions,
								$this->callModelFunc("mod", "updateDataForSpecificModule", 'mod_sensorresponder', array(
									"mevent_fragrance_id" => $payload['fragrance_id']
								), array("mevent_id" => $mevent_id))
							);

						}
						else if((bool)$status === false)
						{

							if(!is_null($mevent['mevent_fragrance_id']) && $mevent['mevent_fragrance_id'] !== $payload['fragrance_id'])
								continue;

							array_push(
								$remove_actions,
								$this->callModelFunc("mod", "updateDataForSpecificModule", 'mod_sensorresponder', array(
									"mevent_fragrance_id" => null
								), array("mevent_id" => $mevent_id))
							);

						}
					}

			# End with status report
			if(!isSizedArray($add_actions) && !isSizedArray($remove_actions)) $this->addFrontendError($fragranceChoice_frEventAssignment__failure___noChanges[$GLOBALS['lang']]);
			// Add actions
			else if(in_array(true, $add_actions)) $this->addFrontendMessage($fragranceChoice_frEventAssignment__success[$GLOBALS['lang']]);
			else if(!in_array(true, $add_actions)) $this->addFrontendError($fragranceChoice_frEventAssignment__failure[$GLOBALS['lang']]);
			// Remove actions
			else if(in_array(true, $remove_actions)) $this->addFrontendMessage($fragranceChoice_frEventAssignmentDel__success[$GLOBALS['lang']]);
			else if(!in_array(true, $remove_actions)) $this->addFrontendError($fragranceChoice_frEventAssignmentDel__failure[$GLOBALS['lang']]);

			$this->refresh();

		} // final protected function assignFragranceToEvent()

		/**
		 * Gets all fragrances of a specific type
		 *
		 * @param $type: Valid options are "general" and [others]
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		private function getFragrancesOfType($type)
		{

			# Load Module
			if(!isSizedArray($this->store))
				$this->loadModule(static::ACTIVE_MOD);

			# Define Comparison
			$comparison = $type === "general" ? true : false;

			# Create payload
			$returnArr = array();

			# Fill payload
			foreach($this->store as $k => $fragrance)
				if((bool)$fragrance['fragrance_isGeneralFragrance'] === $comparison)
					array_push($returnArr, $fragrance);

			return $returnArr;

		} // private function getFragrancesOfType()

		/**
		 * Alias of $this->getFragrancesOfType suited for general fragrances
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 *
		 * @see $this->getFragrancesOfType
		 */
		public final function getGeneralFragrances()
		{

			return $this->getFragrancesOfType('general');

		} // private function getGeneralFragrances()

		/**
		 * Alias of $this->getFragrancesOfType suited for assigned fragrances
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 *
		 * @see $this->getFragrancesOfType()
		 */
		public final function getAssignedFragrances()
		{

			return $this->getFragrancesOfType('assigned');

		} // public function getAssignedFragrances()

		/**
		 * Returns all general fragrance types, that have active assigned fragrances
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function getAssignedGeneralFragrances()
		{

			$returnArr = [];
			$assigned_fr = $this->getFragrancesOfType('assigned');

			// Push the corresponding general fragrance id if this type has not already been added
			foreach($assigned_fr as $fragrance)
			{

				$isFrUniqueInReturnArr = true;

				foreach($returnArr as $returnVal)
				{

					if($returnVal['fragrance_name'] === $fragrance['fragrance_name'])
						$isFrUniqueInReturnArr = false;

				}

				if($isFrUniqueInReturnArr)
				{

					array_push(
						$returnArr,
						$this->getGeneralFragranceByAssignedID($fragrance['fragrance_id'])
					);

				}

			}

			return $returnArr;

		} // public final function getAssignedGeneralFragrances()

		/**
		 * Returns an array of all fragrances that are children of the general fragrance with the id $generalFragranceID supplied
		 *
		 * @param $generalFragranceID
		 *
		 * @return array|null
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function getAssignedFragrancesOfGeneralFType($generalFragranceID)
		{

			# Create payloads
			// Init return val
			$returnArr = array();

			// Get sorted fragrance module data
			$generalFragrances = $this->getGeneralFragrances();
			$assignedFragrances = $this->getAssignedFragrances();

			// Get general fragrance by id
			foreach($generalFragrances as $k => $fragrance)
				if($fragrance['fragrance_id'] === $generalFragranceID)
					$generalFragrance = $fragrance;

			if(!isset($generalFragrance)) return null;

			# Filter
			foreach($assignedFragrances as $k => $fragrance)
				if(
					$fragrance['fragrance_name'] === $generalFragrance['fragrance_name'] &&
					$fragrance['fragrance_scent'] === $generalFragrance['fragrance_scent']
				)
					array_push($returnArr, $fragrance);

			return $returnArr;

		} // public final function getAssignedFragrancesOfGeneralFType()

		/**
		 * Retrieves a general fragrance by assigned fragrance id
		 *
		 * @param $assigned_id
		 *
		 * @return mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function getGeneralFragranceByAssignedID($assigned_id)
		{

			# Init return payload
			$returnVal = null;

			# Get data
			$assigned_fragrance = $this->getFragranceByID($assigned_id);
			$general_fragrances = $this->getGeneralFragrances();

			# Filter for target
			foreach($general_fragrances as $k => $fragrance)
				if(
					@$fragrance['fragrance_name'] === @$assigned_fragrance['fragrance_name'] &&
					@$fragrance['fragrance_scent'] === @$assigned_fragrance['fragrance_scent']
				)
					$returnVal = $fragrance;

			return $returnVal ?: array();

		} // public final function getGeneralFragranceByAssignedID()

		/**
		 * Gets the data of a fragrance by fragrance id
		 *
		 * @param $fragrance_id
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public final function getFragranceByID($fragrance_id)
		{

			# Load Module
			if(!isSizedArray($this->store))
				$this->loadModule(static::ACTIVE_MOD);

			# Init return val
			$returnVal = array();

			# Find searched fragrance
			foreach($this->store as $k => $fragrance)
				if($fragrance['fragrance_id'] === $fragrance_id)
					$returnVal = $fragrance;

			return $returnVal;

		} // public final function getFragranceByID()

	} // class mod_fragranceChoice