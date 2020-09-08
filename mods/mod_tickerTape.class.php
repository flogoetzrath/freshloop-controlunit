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
		/** @var string field to store the suffix of the table containing all ticker tape entries */
		const TICKERTAPE_DB_SUFFIX = "_tapes";
		/** @var string field to store the suffix of the table containing all ticker-tape-event entries */
		const TEVENTS_DB_SUFFIX = "_tevents";
		/** @var array field to store the names of modules that are required in order to load the current one */
		const REQUIRED_MODULES = array(
			"mod_fragranceChoice"
		);

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

			// Ensure the db connection has been established
			$this->init();

			// Check whether the ticker tape already exists and only has to be updated

			// If ticker tape already exists, update it with current values


			debug($this->store);

			die(debug($payload));

		} // final public function addTickerTape()

		/**
		 * Attaches an event to a specific ticker tape
		 *
		 * @param array $payload    Dataset with the reference ticker tape id and the data for the event
		 */
		final public function addTickerTapeEvent(array $payload)
		{

			die(debug($payload));

			// TODO

		} // final public function addTickerTapeEvent()


	} // class mod_tickerTape