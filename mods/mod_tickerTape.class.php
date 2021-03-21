<?php
	/**
	 * @name: mod_tickerTape
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";

	class mod_tickerTape extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_tickerTape";
		/** @var array field to store the names of modules that are required in order to load the current one */
		const REQUIRED_MODULES = array(
			"mod_fragranceChoice"
		);

		/** @var string field to store the suffix of the table containing all ticker tape entries */
		const TICKERTAPE_DB_SUFFIX = "_tapes";
		/** @var string field to store the suffix of the table containing all ticker-tape-event entries */
		const TEVENTS_DB_SUFFIX = "_tevents";

		public function __construct()
		{

			// Get the translated name of the current module
			global $tickerTape_modName;

			// Construct the parent
			parent::__construct(
				static::ACTIVE_MOD,
				$tickerTape_modName[$GLOBALS['lang']]
			);

			// Check if this module has the dependencies it requires
			$this->processDependencyAvailability();

		} // public function __construct()

		/**
		 * Dispatches a payload being received requests passed by a lower instance
		 *
		 * @param array $data
		 *
		 * @return bool|void
		 * @throws Exception
		 */
		final public function dispatch($data = array())
		{

			$data = isSizedArray( $data ) ? $data : $_REQUEST;

			// Creates a new ticker tape
			if(isSizedString($data['action']) && $data['action'] === "AddTickerTape")
				$this->addUpdateTickerTape(xssproof($data));

			// Deletes an existing ticker tape
			if(isSizedString($data['action']) && $data['action'] === "DelTickerTape")
				$this->deleteTickerTape(@xssproof($data['tt_id']));

			// Attaches an event to a specific ticker tape
			if(isSizedString($data['action']) && $data['action'] === "AddTickerTapeEvent")
				$this->addTickerTapeEvent($data);

		} // final public function dispatch()

		/**
		 * Creates a new ticker tape or updates an existing one
		 *
		 * @param array $payload Dataset concerning the submitted ticker tape
		 *
		 * @return mixed|void
		 */
		final public function addUpdateTickerTape(array $payload)
		{

			// Check for missing input
			$requiredData = array(
				"title",
				"totaltime",
				"timelineDataStoreJSON"
			);

			if(count(array_diff($requiredData, array_keys($payload))) !== 0)
			{

				global $form_fill_required_vals;
				return $this->addFrontendError($form_fill_required_vals[$GLOBALS['lang']]);

			}

			$payload['timelineDataStore'] = json_decode($payload['timelineDataStoreJSON']);

			// Ensure the db connection has been established
			$this->init();

			// Check whether the ticker tape already exists and only has to be updated
			$existingTickerTapeData = false;

			if(isSizedArray($this->store[strtolower(static::ACTIVE_MOD) . static::TICKERTAPE_DB_SUFFIX]))
			{
				foreach($this->store[strtolower(static::ACTIVE_MOD) . static::TICKERTAPE_DB_SUFFIX] as $tickerTape)
				{
					if((string)$tickerTape['tt_id'] === (string)$payload['timelineDataStore']->rows->{0}[0]->tickerTapeRefID)
					{

						$existingTickerTapeData = $tickerTape;

					}
				}
			}

			// If ticker tape already exists, update it with current values
			if($existingTickerTapeData !== false)
			{

				// Determine differences and update or remove ticker-tape-events accordingly
				// TODO

				// Query the DB
				$action = $this->callModelFunc("mod", "updateDataForSpecificModule", static::ACTIVE_MOD . static::TICKERTAPE_DB_SUFFIX, array(
					"tt_name" => $payload['title'],
					"tt_totalTime" => $payload['totaltime'],
					"tt_timelineDataStoreJSON" => $payload['timelineDataStoreJSON']
				), array("tt_id" => $payload['timelineDataStore']->rows->{0}[0]->tickerTapeRefID));

				global $time_updateEvent__success;
				$success_msg = $time_updateEvent__success[$GLOBALS['lang']];

			}
			// Otherwise save it to the database
			else
			{

				// Register all ticker-tape-events
				foreach((array)$payload['timelineDataStore']->rows as $row)
				{
					foreach($row as $tevent)
					{

						$this->addTickerTapeEvent((array)$tevent);

					}
				}

				// Query the DB
				$action = $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD . static::TICKERTAPE_DB_SUFFIX, array(
					"tt_id" => $payload['timelineDataStore']->rows->{0}[0]->tickerTapeRefID,
					"tt_name" => $payload['title'],
					"tt_totalTime" => $payload['totaltime'],
					"tt_timelineDataStoreJSON" => $payload['timelineDataStoreJSON']
				));

				global $time_addEvent__success;
				$success_msg = $time_addEvent__success[$GLOBALS['lang']];

			}

			// End with status report
			global $time_addEvent__failure;

			if($action) $this->addFrontendMessage($success_msg);
			else $this->addFrontendError($time_addEvent__failure[$GLOBALS['lang']]);

			// Refresh the page so the GET params in the url void
			$this->refresh();

		} // final public function addTickerTape()

		/**
		 * Deletes a ticker tape from the database
		 *
		 * @param Int $tickerTapeID     The identifier of the ticker tape to be deleted
		 */
		final public function deleteTickerTape($tickerTapeID)
		{

			// Ensure the db connection has been established
			$this->init();

			// Check if the ticker tape exists
			$doesTTExist = false;

			foreach($this->store[strtolower(static::ACTIVE_MOD . static::TICKERTAPE_DB_SUFFIX)] as $tickerTape)
				if((int)$tickerTapeID === (int)$tickerTape['tt_id'])
					$doesTTExist = true;

			// Call Model
			$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD . static::TICKERTAPE_DB_SUFFIX, array(
				"tt_id" => $tickerTapeID
			));

			// End with status report
			global $tickerTape_delete__failure_ttNotExisting;
			global $tickerTape_delete__success, $tickerTape_delete__failure;

			if($action) $this->addFrontendMessage($tickerTape_delete__success[$GLOBALS['lang']]);
			else if(!$doesTTExist) $this->addFrontendError($tickerTape_delete__failure_ttNotExisting[$GLOBALS['lang']]);
			else $this->addFrontendError($tickerTape_delete__failure[$GLOBALS['lang']]);

			// Refresh the page so the GET params in the url void
			$this->refresh();

		} // final public funciton deleteTickerTape()

		/**
		 * Attaches an event to a specific ticker tape
		 *
		 * @param array $teventData             Dataset containing the reference ticker tape id and the data for the event
		 * @param bool  $isIndependentCall      Determines, whether the method is called directly or is being part of a chain call
		 */
		final public function addTickerTapeEvent(array $teventData, bool $isIndependentCall = false)
		{

			// Ensure the db connection has been established
			$this->init();

			// Check if the event already exists
			$doesTTEvtExist = false;

			foreach($this->store[strtolower(static::ACTIVE_MOD . static::TEVENTS_DB_SUFFIX)] as $tEvt)
				if((int)$teventData['id'] === (int)$tEvt['ttevt_id'])
					$doesTTEvtExist = true;

			if(!$doesTTEvtExist)
			{

				// Call Model
				$action = $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD . static::TEVENTS_DB_SUFFIX, array(
					"ttevt_id" => abs((int)$teventData['id']),
					"ttevt_name" => (string)$teventData['title'],
					"ttevt_fragranceID" => (int)$teventData['fragranceID'],
					"ttevt_unitID" => (int)$teventData['unitID'],
					"ttevt_tickerTapeRefID" => (string)$teventData['tickerTapeRefID'],
					"ttevt_startMin" => (int)$teventData['startCol'],
					"ttevt_endMin" => (int)$teventData['endCol']
				));

			}

			// If frontend output is expected
			if($isIndependentCall)
			{

				// End with status report
				global $tickerTapeEvt_add__failure_duplicate;
				global $tickerTapeEvt_add__success, $tickerTapeEvt_add__failure;

				if($doesTTEvtExist) $this->addFrontendError($tickerTapeEvt_add__failure_duplicate[$GLOBALS['lang']]);
				else if($action) $this->addFrontendMessage($tickerTapeEvt_add__success[$GLOBALS['lang']]);
				else $this->addFrontendError($tickerTapeEvt_add__failure[$GLOBALS['lang']]);

				// Refresh the page so the GET params in the url void
				$this->refresh();

			}

		} // final public function addTickerTapeEvent()

		/**
		 * Deletes a ticker tape event from the database
		 *
		 * @param int  $evt_id              The identifier of the ticker tape event that is to be deleted
		 * @param bool $isIndependentCall   Determines, whether the method is called directly or is being part of a chain call
		 */
		final public function removeTickerTapeEvent(int $evt_id, bool $isIndependentCall = false)
		{

			// Ensure the db connection has been established
			$this->init();

			// Check if the ticker tape exists
			$doesTTEvtExist = false;

			foreach($this->store[strtolower(static::ACTIVE_MOD . static::TEVENTS_DB_SUFFIX)] as $tEvt)
				if($evt_id === (int)$tEvt['tt_id'])
					$doesTTEvtExist = true;

			// Call Model
			$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD . static::TEVENTS_DB_SUFFIX, array(
				"tt_id" => $evt_id
			));

			// If frontend output is expected
			if($isIndependentCall)
			{

				// End with status report
				global $tickerTapeEvt_add__failure_notExisting;
				global $tickerTapeEvt_remove__success, $tickerTapeEvt_remove__failure;

				if($action) $this->addFrontendMessage($tickerTapeEvt_remove__success[$GLOBALS['lang']]);
				else if(!$doesTTEvtExist) $this->addFrontendError($tickerTapeEvt_add__failure_notExisting[$GLOBALS['lang']]);
				else $this->addFrontendError($tickerTapeEvt_remove__failure[$GLOBALS['lang']]);

				// Refresh the page so the GET params in the url void
				$this->refresh();

			}

		} // final public function removeTickerTapeEvent()


	} // class mod_tickerTape