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

			// Creates a new Ticker Tape
			if(isSizedString($data['action']) && $data['action'] === "AddTickerTape")
				$this->addTickerTape(xssproof($data));

			// Attaches an event to a specific ticker tape
			if(isSizedString($data['action']) && $data['action'] === "AddTickerTapeEvent")
				$this->addTickerTapeEvent($data);

		} // final public function dispatch()

		/**
		 * Creates a new ticker tape
		 *
		 * @param array $payload Dataset concerning the submitted ticker tape
		 *
		 * @return mixed|void
		 */
		final public function addTickerTape(array $payload)
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
					if($tickerTape['tt_id'] === $payload['timelineDataStore']->rows->{0}[0]->tickerTapeRefID)
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
				$action = $this->callModelFunc("mod", "updateDataForSpecificModule", static::ACTIVE_MOD, array(
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
		 * Attaches an event to a specific ticker tape
		 *
		 * @param array $teventData     Dataset with the reference ticker tape id and the data for the event
		 */
		final public function addTickerTapeEvent(array $teventData)
		{

			//debug($teventData);
			//die();

			// TODO

		} // final public function addTickerTapeEvent()

		final public function removeTickerTapeEvent() {  } // final public function removeTickerTapeEvent()


	} // class mod_tickerTape