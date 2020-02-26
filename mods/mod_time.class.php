<?php
	/**
	 * @name: mod_time
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";
	require_once LIBRARY_PATH."/ssh2_crontab_manager.class.php";

	class mod_time extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_time";
		/** @var array constant field to declare implemented event types */
		const EVENT_TYPES = array(
			0 => "solo_spray",
			1 => "group_concurrent_spray",
			2 => "group_consecutive_spray"
		);
		/** @var string field to assign event types non-hacker names */
		public $event_types_lang;
		/** @var object field to store an instance of the ssh2_crontab_manager class */
		private $CronManager = null;

		public function __construct()
		{

			// Access language store
			global $time_modName;
			global $time_types__soloSpray,
			       $time_types_concurrentSpray,
			       $time_types_consecutiveSpray,
			       $time_types_fragranceCollectiveSpray;

			// Init parent
			parent::__construct(
				static::ACTIVE_MOD,
				$time_modName[$GLOBALS['lang']]
			);

			// Structure event types with naming
			$this->event_types_lang = array(
				self::EVENT_TYPES[0] => $time_types__soloSpray[$GLOBALS['lang']],
				self::EVENT_TYPES[1] => $time_types_concurrentSpray[$GLOBALS['lang']],
				self::EVENT_TYPES[2] => $time_types_consecutiveSpray[$GLOBALS['lang']]
			);

			// Add fragrance collective spray type if the corresponding module is active
			if($this->isActiveMod("mod_fragranceCollective"))
				$this->event_types_lang["mod_fragranceCollective"] = $time_types_fragranceCollectiveSpray[$GLOBALS['lang']];

		}

		/**
		 * Dispatches a payload
		 *
		 * @param array $data
		 *
		 * @return bool|void
		 */
		final public function dispatch($data = array())
		{

			// Add Event Request
			if(isSizedString($data['action']) && $data['action'] === "addEvent")
				$this->addEvent(array(
					"name" => trim(xssproof($data['name'])) ?: null,
					"type" => xssproof($data['type']) ?: null,
					"units_csv" => strip_tags($data['units']) ?: null,
					"date" => xssproof($data['date']) ?: null,
					"time" => xssproof($data['time']) ?: null,
					"loop" => xssproof($data['loop']) ?: null
				));

			// Delete Event Request
			if(isSizedString($data['action']) && $data['action'] === "deleteEvent")
				$this->deleteEvent(xssproof($data['event_id']));

		} // final public function dispatch()

		/**
		 * Checks whether any event needs to be executed
		 */
		public function isAnyEventInNeedOfExecution()
		{

			$eventsToBeExecuted = array();

			$current_his = date('H:i:s', time());
			$current_his_parts = explode(":", $current_his);
			$current_his = $current_his_parts[0].":".$current_his_parts[1].":00";

			// Loop through all saved events
			foreach($this->store as $k => $event) {
				// Date and time calculations
				$datetime_execution = $event['event_plannedExecution_time'];
				$event['time_his'] = date("H:i:s", strtotime($datetime_execution));
				$event['time_dbdate'] = DBDatetoDate($event['event_plannedExecution_date']);
				$event['time_cdate'] = date("d.m.Y");

				// If the event has not been executed yet and date AND time are equal to the current one
				if(
					!isSizedString($event['event_executedAt']) &&
					$event['time_dbdate'] === $event['time_cdate'] &&
					$event['time_his'] === $current_his
				)
					array_push($eventsToBeExecuted, $event);

				// If the event has already been executed, the date is wrong, but the time is right and loop is enabled
				if(
					isSizedString($event['event_executedAt']) &&
					$event['time_dbdate'] !== $event['time_cdate'] &&
					$event['time_his'] === $current_his &&
					(bool)$event['event_loop'] === true
				)
					array_push($eventsToBeExecuted, $event);
			}

			return $eventsToBeExecuted;

		} // public function initCronSystem

		/**
		 * Executes an event
		 *
		 * @param array  $event_data            The dataset of the event to be executed
		 * @param string $event_db_tblname      The tablename of the event
		 * @param string $event_db_keyprefix    A prefix for keys when it comes to database insertion
		 *
		 * @return array                        The API Responses
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function executeEvent($event_data, $event_db_tblname = "mod_time", $event_db_keyprefix = "")
		{

			// Query the data of the specific units involved
			$involved_units = explode(",", $event_data['event_targetUnits']);
			$unconnected_unit_keys = array();
			$deactivated_unit_keys = array();

			foreach($involved_units as $k => $unit_id)
			{

				$this->loadData('unit', $unit_id);
				$involved_units[$k] = $this->data['unit'][$unit_id];

				if(!isSizedString($involved_units[$k]['unit_secret']))
					array_push($unconnected_unit_keys, $k);

			}

			// If units are involved that are not connected to the control unit yet
			if(isSizedArray($unconnected_unit_keys))
			{

				global $time_executeEvent__failure_involvedUnitsNotConnected;

				$involved_units = array_diff_key($involved_units, $unconnected_unit_keys);
				$this->addFrontendError($time_executeEvent__failure_involvedUnitsNotConnected[$GLOBALS['lang']]);

			}

			// If units are involved that were manually deactivated
			foreach($involved_units as $k => $unit_id)
				if((bool)$involved_units[$k]['unit_isActivated'] === false)
					array_push($deactivated_unit_keys, $k);

			if(isSizedArray($deactivated_unit_keys))
				$involved_units = array_diff_key($involved_units, $deactivated_unit_keys);

			// Load the fragrances if possible
			if($this->isActiveMod("mod_fragranceChoice"))
			{

				$this->loadModule("mod_fragranceChoice");
				$fragrances_store = $this->modules['mod_fragranceChoice']->store ?: array();

			}

			// If the event type only demands issuing the spray command for a single unit, but multiple were handed, just use the first one
			if($event_data['event_type'] === 0 && isSizedArray($involved_units))
				$involved_units = array($involved_units[0]);

			// If the event type demands further sorting of the sequence of the spray commands, issue that sorting
			if($event_data['event_type'] === 2)
			{

				// Sort ascending from lowest to highest unit priorities
				usort($involved_units, function($a, $b) {
					if($a['unit_priority'] === $b['unit_priority']) return 0;

					return ($a['unit_priority'] < $b['unit_priority']) ? -1 : 1;
				});

			}

			// If the event type demands different fragrances to be sprayed in one event
			if($event_data['event_type'] === 3)
			{

				$this->loadModule("mod_fragranceCollective");
				$fragrances_per_unit = $this->modules['mod_fragranceCollective']->buildFragrancesPerUnitByEventID($event_data, $involved_units, $fragrances_store);

			}

			// Send API Request to the executing units
			$API = new APIController();

			foreach($involved_units as $k => $unit)
			{

				// Build the api url
				$dest_ip = trim($this->getIPByMacAddress($unit['unit_macaddress']));
				$dest_route = "/api/actions/sprayAtPosition";
				$dest_port = $unit['unit_api_port'] ?: $this->callModelFunc("unit", "getUnknownUnitsPortInfo", $unit['unit_macaddress']) ?: DEF_API_PORT;
				$dest = $dest_ip.":".$dest_port.$dest_route;

				// Get the position of the demanded fragrance on the unit
				$fragrance_position = 1;

				if($this->isActiveMod("mod_fragranceChoice") && !isset($fragrances_per_unit))
				{
					foreach($fragrances_store as $_k => $fr_data)
					{

						$corresponding_generalFr_id = $this->modules['mod_fragranceChoice']->getGeneralFragranceByAssignedID($fr_data['fragrance_id'])['fragrance_id'];

						if(
							(bool)$fr_data['fragrance_isGeneralFragrance'] === false &&
							$fr_data['fragrance_unit'] === $unit['unit_id'] &&
							$corresponding_generalFr_id === $event_data['event_fragrance_id']
						)
							$fragrance_position = (int)$fr_data['fragrance_position'];

					}
				}

				// If a fragrance collective is involved
				if(isset($fragrances_per_unit))
				{

					// If errors while building the dataset occured and there are no fragrance positions for the current unit
					if(!isSizedArray($fragrances_per_unit[$unit['unit_id']])) $fragrance_position = 1;

					// Assign the corresponding fragrance positions
					if(count($fragrances_per_unit[$unit['unit_id']]) === 1) $fragrance_position = $fragrances_per_unit[$unit['unit_id']][0];
					else
					{

						// If multiple positions per unit were registered, queue the api requests except the last one thanks to the upcoming code
						foreach($fragrances_per_unit[$unit['unit_id']] as $_k => $current_fr_position)
						{

							if($_k === (count($fragrances_per_unit[$unit['unit_id']]) - 1)) continue;
							else $API->queueReq("POST", $dest, ["position" => $current_fr_position], ["Authorization" => $unit['unit_secret']]);

						}

						// Set the last/missing fragrance position as $fragrance_position to be queued by the upcoming code
						$fragrance_position = $fragrances_per_unit[$unit['unit_id']][count($fragrances_per_unit[$unit['unit_id']]) - 1];

					}

				}

				// Queue the request for eventual execution
				$API->queueReq("POST", $dest, ["position" => $fragrance_position], ["Authorization" => $unit['unit_secret']]);

				// Update the unit in the db for sake of statistics
				$this->callModelFunc("unit", "updateUnit", $unit['unit_id'], [
					"unit_timesSprayed" => (string)(((int)$unit['unit_timesSprayed']) + 1),
					"unit_lastSprayed" => dateToDBDate(date("d.m.Y"))
				]);

			}

			// If the requests are thought to go off at the same time
			if($event_data['event_type'] !== 2) $responses = $API->issueRapidReqs();
			else $responses = $API->issueConsecutiveReqs((int)$this->conf['mod_time__defaultConsecutiveSprayDelay']);

			// Search for errors in the responses
			$res_errors = array();

			foreach($responses as $k => $msg)
			{
				if($msg !== "{\"success\":true}")
				{

					array_push($res_errors, $msg);

				}
			}

			// Write the time of execution to the db
			$this->db->update($event_db_tblname, [
				$event_db_keyprefix."event_executedAt" => dateToDBDate(date("d.m.Y")),
				$event_db_keyprefix."event_executed_status" => isSizedArray($res_errors) ? implode("\n", $res_errors) : true
			], [
				$event_db_keyprefix."event_id" => $event_data['event_id']
			]);

			return $responses;

		} // public function executeEvent()

		/**
		 * Creates a specific cron job for event checking
		 */
		public function startListeningForEvents()
		{

			// Initialize the crontab manager if this has not been done before
			if(is_null($this->CronManager)) $this->CronManager = new ssh2_crontab_manager();

			// Check if the cron job has already been added
			$this->readConfig();

			if(
				$this->data['config']['mod_time__checkTimeEventExecutionNeedsCron'] === "enabled" &&
				isSizedArray($this->CronManager->cronjob_exists("checkTimeEventExecutionNeeds"))
			)
			{

				// Cancel the execution of the function
				return false;

			}

			// Restart the cronjob in case he is already issued
			if(isSizedArray($this->CronManager->cronjob_exists("checkTimeEventExecutionNeeds")))
				$this->stopListeningForEvents();

			// Add the cron job
			$this->CronManager->append_cronjob(
			// Running every minute
				"* * * * * sudo php -c /etc/php/7.3/fpm/php.ini ".ABSPATH.'/mods/'.static::ACTIVE_MOD.'/cronActions/checkTimeEventExecutionNeeds.php'
			);

			// Write status to the config
			$this->data['config']['mod_time__checkTimeEventExecutionNeedsCron'] = "enabled";
			$this->writeConfFile($this->data['config'], true);

		} // public function startListeningForEvents()

		/**
		 * Remove the cron job for event checking
		 */
		public function stopListeningForEvents()
		{

			// Initialize the crontab manager if this has not been done before
			if(is_null($this->CronManager)) $this->CronManager = new ssh2_crontab_manager();

			// Stop cronjob to call $this-->captureSongHistory()
			$this->CronManager->remove_cronjob("checkTimeEventExecutionNeeds");

			// Write status to the config
			$this->data['config']['mod_time__checkTimeEventExecutionNeedsCron'] = "disabled";
			$this->writeConfFile($this->data['config'], true);

		} // public function stopListeningForEvents()

		/**
		 * Event Addition Entry
		 *
		 * @param $payload
		 *
		 * @return mixed|void
		 */
		final protected function addEvent($payload)
		{

			global $time_event__duplication, $form_fill_required_vals;
			global $time_addEvent__success, $time_addEvent__failure;

			if(in_array(null, array_values($payload)))
				return $this->addFrontendError($form_fill_required_vals[$GLOBALS['lang']]);

			# Compensation for label of select as first item
			if($payload['type'] !== 0) $payload['type'] -= 1;
			else return $this->addFrontendError($form_fill_required_vals[$GLOBALS['lang']]);

			# Get Type
			$type = array(
				"id" => $payload['type'],
				"name" => $payload['type'] === 3 ? "fragranceCollective_spray" : static::EVENT_TYPES[$payload['type']]
			);

			# Ensure DB conn has been established
			$this->init();

			# Check unit duplication
			$occurences = $this->db->selectValue(
				'SELECT COUNT(*) FROM mod_time WHERE event_name = ?', [$payload['name']]
			);

			if($occurences >= 1)
				return $this->addFrontendError($time_event__duplication[$GLOBALS['lang']]);

			# Call Model
			$action = $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD, array(
				"event_name" => $payload['name'],
				"event_type" => $type['id'],
				"event_targetUnits" => $payload['units_csv'],
				"event_loop" => $payload['loop'] === "on" ? true : false,
				"event_plannedExecution_time" => $payload['time'],
				"event_plannedExecution_date" => dateToDBDate($payload['date']),
				"event_executedAt" => null
			));

			# End with status report
			if($action) $this->addFrontendMessage($time_addEvent__success[$GLOBALS['lang']]);
			else $this->addFrontendError($time_addEvent__failure[$GLOBALS['lang']]);

			$this->startListeningForEvents();

			$this->refresh();

		} // final protected function addEvent()

		/**
		 * Performs a delete action regarding a given event
		 *
		 * @param Int $event_id
		 *
		 * @return mixed|void
		 */
		final protected function deleteEvent(Int $event_id)
		{

			global $time_deleteEvent__success,
			       $time_deleteEvent__failure,
			       $time_deleteEvent__failure_eventNotExisting;

			# Ensure DB conn has been established
			$this->init();

			# Check whether event exists
			$occurences = $this->db->selectValue(
				'SELECT COUNT(*) FROM mod_time WHERE event_id = ?', [$event_id]
			);

			if($occurences <= 0)
				return $this->addFrontendError($time_deleteEvent__failure_eventNotExisting[$GLOBALS['lang']]);

			# Call Model
			$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD, array(
				"event_id" => $event_id
			));

			# End with status report
			if($action) $this->addFrontendMessage($time_deleteEvent__success[$GLOBALS['lang']]);
			else $this->addFrontendError($time_deleteEvent__failure[$GLOBALS['lang']]);

			# If no events are registered, stop the cron job checking whether any event needs to be executed
			$nEvents = (int)$this->db->select('SELECT COUNT(*) FROM mod_time')[0][0];
			if($nEvents === 0 || $nEvents === null) $this->stopListeningForEvents();

			$this->refresh();

		} // final protected function deleteEvent()

		/**
		 * Determines an event matching a given time algo
		 *
		 * @param $algo_name
		 *
		 * @return mixed|null
		 * @throws Exception
		 */
		final public function getEventByTimeAlgo($algo_name)
		{

			$options = array();

			switch($algo_name)
			{

				// Get the last event
				case "previous_event":
				case "last_event":
				default:

					// Get all valid options
					foreach($this->store as $k => $event)
						if(isSizedString($event['event_executedAt']))
						{

							$event['time_passed'] = getDateTimeDiff(new DateTime(), new DateTime($event['event_executedAt']));
							array_push($options, $event);

						}

					/**
					 * Loops through $options and unshifts items if predecessor has higher time passed since execution
					 */
					$reorder = function() use(&$options)
					{

						foreach($options as $k => $event)
						{

							if($k === 0) continue;
							if($options[$k-1]['time_passed'] > $options[$k]['time_passed'])
							{

								$event = $options[$k];

								unset($options[$k]);
								array_unshift($options, $event);

							}

						}
					}; // function reorder()

					break;

				/**
				 * Get the upcoming event
				 */
				case "next_event":
				case "upcoming_event":

					$now = time();
					$current_his = date('H:i:s', $now);
					$current_his_parts = explode(":", $current_his);
					$current_his_number = (int)($current_his_parts[0].$current_his_parts[1]);

					$isEventLeftToday = false;

					// Get all valid options
					foreach($this->store as $k => $event)
						if(!isSizedString($event['event_executedAt']) || (bool)$event['event_loop'] === true)
						{

							$datetime_execution = $event['event_plannedExecution_time'];
							$event['time'] = strtotime($datetime_execution);
							$event['time_his'] = date("H:i:s", $event['time']);
							$event['time_left'] = getDateTimeDiff($current_his, $event['time_his']);

							$time_his_parts = explode(":", $event['time_his']);
							$event['time_his_number'] = (int)($time_his_parts[0].$time_his_parts[1]);

							array_push($options, $event);

							if($event['time'] > $now && $event['time'] < mktime(23, 59))
							{

								$isEventLeftToday = true;

							}

						}

					/**
					 * Loops through $options and unshifts items if predecessor has higher time passed since execution
					 */
					$reorder = function($isLastCall = false) use(&$options, $current_his_number, $isEventLeftToday)
					{

						foreach($options as $k => $event)
						{

							if($k === 0) continue;
							if(
								$options[$k-1]['time_left'] > $options[$k]['time_left'] &&
								($options[$k-1]['time_his_number'] - $current_his_number) < 0
							)
							{

								$event = $options[$k];

								unset($options[$k]);
								array_unshift($options, $event);

							}

						}

						if($isLastCall && !$isEventLeftToday) $options = array_reverse($options);

					}; // function reorder()

					break;

			}

			if(!isSizedArray($options)) return null;
			if(count($options) === 1) return $options[0];

			for($i = 0; $i < count($options); $i++)
			{

				$isLastCall = $i === (count($options) - 1);
				$reorder($isLastCall);

			}

			return $options[0];

		} // final public function getEventByTimeAlgo()

		/**
		 * Alias of $this->getEventByTimeAlgo for specific use case
		 *
		 * @return mixed|null
		 * @throws Exception
		 */
		final public function getLastEvent()
		{

			return $this->getEventByTimeAlgo("last_event");

		} // final public function getLastEvent()

		/**
		 * Alias of $this->getEventByTimeAlgo for specific use case
		 *
		 * @return mixed|null
		 * @throws Exception
		 */
		final public function getNextEvent()
		{

			return $this->getEventByTimeAlgo("next_event");

		} // final public function getNextEvent()

		/**
		 * Returns an event by its ID
		 *
		 * @param Int $event_id
		 *
		 * @return array|bool
		 */
		final public function getEventByID(Int $event_id)
		{

			# Validation
			if(!isSizedInt($event_id)) return false;

			# Get Event
			$occurences = $this->db->select(
				'SELECT * FROM mod_time WHERE event_id = ?', [$event_id]
			);

			return $occurences ?: array();

		} // final public function getEventByID()

		/**
		 * Returns the number of events that are to be executed in the future
		 *
		 * @return int
		 */
		final public function countPlannedEvents()
		{

			$validEvents = array();

			foreach($this->store as $k => $event)
			{

				if(
					!isSizedString($event['event_executedAt'])
					||
					isSizedString($event['event_executedAt']) &&
					(bool)$event['event_loop'] === true
				)
					array_push($validEvents, $event);

			}

			return count($validEvents);

		} // final public function countPlannedEvents()

		/**
		 * Gets all events that are associated to a specific fragrance
		 *
		 * @param $fragrance_id
		 *
		 * @return array
		 * @throws DatabaseError
		 * @throws ReflectionException
		 *
		 * @note Includes music-events from mod_sensorResponder if existing
		 */
		final public function getEventsByFragranceID($fragrance_id)
		{

			# Create payload
			$returnVal = array();

			# Load Module
			if(!isSizedArray($this->store))
				$this->loadModule(static::ACTIVE_MOD);

			$mod_sensorResponder = new mod_sensorResponder();

			# Filter events for fragrance id
			foreach($this->store as $k => $event)
				if($event['event_fragrance_id'] === $fragrance_id)
					array_push($returnVal, $event);

			foreach($mod_sensorResponder->store as $k => $mevent)
				if($mevent['mevent_fragrance_id'] === $fragrance_id)
					array_push($returnVal, $mevent);

			// Clear memory
			unset($mod_sensorResponder);

			return $returnVal;

		} // final public function getEventsByFragranceID()


	} // class mod_time