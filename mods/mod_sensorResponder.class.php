<?php
	/**
	 * @name: mod_sensorResponder
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";
	require_once MODS_PATH . "/mod_sensorResponder/SpotifyIntegration.class.php";

	class mod_sensorResponder extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_sensorResponder";
		/** @var array field to store the names of modules that are required in order to load the current one */
		const REQUIRED_MODULES = array(
			"mod_fragranceChoice"
		);

		/** @var string field to store the path to the store file for song history capturing */
		const SONGHISTORYSTORE_PATH = MODS_PATH.'/'.self::ACTIVE_MOD.'/songHistoryStore.json';

		/** @var object field to store an instance of the ssh2_crontab_manager class */
		private $CronManager = null;
		/** @var array field to store the history of currently played Spotify songs */
		private $songHistory = null;
		/** @var object field to store an instance of the spotify integration class */
		public $SpotifyIntegration = null;


		public function __construct()
		{

			// Get the translated name of the current module
			global $sensorResponder_modName;

			// Construct the parent
			parent::__construct(
				static::ACTIVE_MOD,
				$sensorResponder_modName[$GLOBALS['lang']]
			);

			// Check if this module has the dependencies it requires
			$this->processDependencyAvailability();

			// Initialize instance variables
			$this->SpotifyIntegration = new SpotifyIntegration();
			$this->songHistory = array();

		} // public function __construct()

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
		 * @throws Exception
		 */
		final public function dispatch($data = array())
		{

			$data = isSizedArray( $data ) ? $data : $_REQUEST;

			// Starts automated song log capturing
			if(isSizedString($data['action']) && $data['action'] === "RequestSpotifyAuthorization")
				$this->SpotifyIntegration->requestSpotifyAuthorization();

			// Starts automated song log capturing
			if(isSizedString($data['action']) && $data['action'] === "SpotifyAuthorizationCallback")
				$this->SpotifyIntegration->spotifyAuthorizationCallback($data);

			// Starts automated song log capturing
			if(isSizedString($data['action']) && $data['action'] === "SongHistoryCapturingAction")
				$this->songHistoryCapturingAction((bool)(xssproof($data['automatic_song_capturing']) === "on") ?: false);

			// Starts automated song log capturing
			if(isSizedString($data['action']) && $data['action'] === "StartSongHistoryCapturing")
				$this->startSongHistoryCapturing();

			// Stops automated song log capturing
			if(isSizedString($data['action']) && $data['action'] === "StopSongHistoryCapturing")
				$this->stopSongHistoryCapturing();

			// Executes actual song log capturing
			if(isSizedString($data['action']) && $data['action'] === "CaptureSongHistory")
				$this->captureSongHistory();

			// Captures the song history and returns it json encoded
			if(isSizedString($data['action']) && $data['action'] === "AJAX_RefreshSongHistory")
				$this->ajax_refreshSongHistory();

			// Adds a music-event
			if(isSizedString($data['action']) && $data['action'] === "AddMEvent")
				$this->addMEvent(xssproof($_REQUEST));

			// Deletes a music-event
			if(isSizedString($data['action']) && $data['action'] === "DeleteMEvent")
				$this->removeMEvent(xssproof($_REQUEST['mevent_id']));

		} // final public function dispatch()

		/**
		 * Middlefunction
		 *
		 * @param bool $isToBeStarted
		 */
		final public function songHistoryCapturingAction(bool $isToBeStarted)
		{

			// Init further proceedure
			if($isToBeStarted) $this->startSongHistoryCapturing();
			else $this->stopSongHistoryCapturing();

			// Refresh to get rid of the get parameters in the url
			$this->refresh();

		} // final public function songHistoryCapturingAction()

		/**
		 * Initializes the song log capturing
		 */
		final public function startSongHistoryCapturing()
		{

			// Initialize the crontab manager if this has not been done before
			if(is_null($this->CronManager)) $this->CronManager = new ssh2_crontab_manager();

			// Check if the cron job has already been added
			$this->readConfig();

			if($this->data['config']['mod_sensorResponder__automaticSongCapturing'] === "enabled")
			{

				global $sensorResponder_automaticSongHistoryCapturing_alreadyEnabled;
				$this->addFrontendError($sensorResponder_automaticSongHistoryCapturing_alreadyEnabled[$GLOBALS['lang']]);

			}

			// Restart the cronjob in case he is already issued
			if(isSizedArray($this->CronManager->cronjob_exists("captureSongHistory")))
				$this->stopSongHistoryCapturing();

			// Add the cron job
			$this->CronManager->append_cronjob(
				// Running every minute
				"* * * * * sudo php -c /etc/php/7.3/fpm/php.ini ".ABSPATH.'/mods/'.static::ACTIVE_MOD.'/cronActions/captureSongHistory.php'
			);

			// Write status to the config
			$this->data['config']['mod_sensorResponder__automaticSongCapturing'] = "enabled";
			$this->writeConfFile($this->data['config'], true);

			// Display status message
			global $sensorResponder_automaticSongHistoryCapturing_activated;
			$this->addFrontendMessage($sensorResponder_automaticSongHistoryCapturing_activated[$GLOBALS['lang']]);

		} // final public function startSongHistoryCapturing()

		/**
		 * Removes the cronjob for song log capturing
		 */
		final public function stopSongHistoryCapturing()
		{

			// Initialize the crontab manager if this has not been done before
			if(is_null($this->CronManager)) $this->CronManager = new ssh2_crontab_manager();

			// Stop cronjob to call $this-->captureSongHistory()
			$this->CronManager->remove_cronjob("captureSongHistory");

			// Remove song history store file
			$this->clearSongHistoryStore();

			// Write status to the config
			$this->data['config']['mod_sensorResponder__automaticSongCapturing'] = "disabled";
			$this->writeConfFile($this->data['config'], true);

			// Display status message
			global $sensorResponder_automaticSongHistoryCapturing_deactivated;
			$this->addFrontendMessage($sensorResponder_automaticSongHistoryCapturing_deactivated[$GLOBALS['lang']]);

		} // final public function stopSongHistoryCapturing()

		/**
		 * Checks the currently played song and writes it to the store if necessary
		 *
		 * @param bool $wasCalledFromCronjob
		 *
		 * @return bool
		 * @throws Exception
		 */
		final public function captureSongHistory($wasCalledFromCronjob = false):bool
		{

			// Save a copy of the old store for later comparison
			$oldStore = $this->getSongHistory();

			// Get current song
			$currentSongData = $this->SpotifyIntegration->getCurrentlyPlayedSong();

			// If it is the first entry, write it to the store
			if(!isSizedArray($oldStore))
				array_push($this->songHistory, $currentSongData);

			// If the same song is being played and the timestamp has not changed, return false
			else if(
				$currentSongData->item->id === @end($oldStore)->item->id &&
				$currentSongData->timestamp === @end($oldStore)->timestamp
			)
				return false;

			// If the same song is being played BUT the timestampt HAS CHANGED, the song is being repeated and has to be added to the store
			else if(
				$currentSongData->item->id === @end($oldStore)->item->id &&
				$currentSongData->timestamp !== @end($oldStore)->timestamp
			)
				array_push($this->songHistory, $currentSongData);

			// If another song is being played, save it to the store
			else if($currentSongData->item->id !== @end($oldStore)->item->id)
				array_push($this->songHistory, $currentSongData);

			// If the last song was changed
			if(@end($oldStore)->item->id !== @$currentSongData->item->id)
			{

				// Add genre data to the latest addition of the songHistory store
				$artist_id = $currentSongData->item->artists[0]->id;
				$genres = $this->getGenresByArtistID($artist_id);
				$currentSongData->item->genres = $genres;
				$this->songHistory[count($this->songHistory) - 1] = $currentSongData;

				// Check whether a planned action has to be executed
				$this->initPlannedMusicEventActions();

				// Write the song history to a store file
				$this->saveSongHistory($wasCalledFromCronjob);

				// If a manual resynchronisation was called, refresh the page to remove GET parameters
				if(!$wasCalledFromCronjob) $this->refresh(true);

			}

			return true;

		} // final public function captureSongHistory()

		/**
		 * Initiates actions that may have been scheduled for certain music events
		 */
		final public function initPlannedMusicEventActions()
		{

			if(!isSizedArray($this->songHistory)) return false;

			$eventsToBeExecuted = array();

			// Get planned events
			$planned_events = $this->getMusicRelatedEvents();

			$current_song = end($this->songHistory);
			$current_song_genres = $current_song->item->genres;

			/**
			 * Checks whether an event has to be executed given its time data and pushes the whole payload to $eventsToBeExecuted
			 *
			 * @param $event_data
			 */
			$checkEventRequirements = function($event_data) use($eventsToBeExecuted)
			{

                // If the event has not been executed yet or the time interval threshold has been exceeded
				if(
					!isSizedString($event_data['mevent_executedAt'])
					||
					new DateTime() > (new DateTime($event_data['mevent_executedAt']))->add(new DateInterval("PT".$event_data['mevent_minIntervalTime']."S"))
				)
				{

					// Push the event data to $eventsToBeExecuted
					if(!in_array($event_data, $eventsToBeExecuted))
						array_push($eventsToBeExecuted, $event_data);

				}

			}; // inline function $checkEventRequirements()

			// Check whether any event is being triggered
			foreach($current_song_genres as $c_genre_name)
			{
				// Adjust the format of the genre name for immediate comparison
				$c_genre_name = str_replace(" ", "-", $c_genre_name);

				// If at least one event is registered for that specific genre name
				if(isSizedArray($planned_events[$c_genre_name]))
				{
					foreach($planned_events[$c_genre_name] as $k => $mevent)
					{

						$checkEventRequirements($mevent);

					}
				}
				else
				{
					// Search for occurences of the genre in a potential sub-genre
					foreach($planned_events as $genre_name => $event_store)
					{

						$genre_name_parts = explode("-", $genre_name);
						$c_genre_name_parts = explode("-", $c_genre_name);

						if(count(array_intersect($genre_name_parts, $c_genre_name_parts)) > 0 && isSizedArray($planned_events[$genre_name]))
						{
							foreach($planned_events[$genre_name] as $k => $mevent)
							{

								if(!in_array($mevent, $eventsToBeExecuted)) array_push($eventsToBeExecuted, $mevent);

								$checkEventRequirements($mevent);

							}
						}

					}
				}
			}

			// If no events have to be executed, exit the function call
			if(!isSizedArray($eventsToBeExecuted)) return false;

			// Execute every waiting event otherwise
			foreach($eventsToBeExecuted as $mevent)
			{

				// Struct an array in the format of the mod_time standards
				$mevent['event_type'] = array_flip(mod_time::EVENT_TYPES)["solo_spray"];
				foreach($mevent as $k => $v)
				{

					unset($mevent[$k]);
					$mevent[str_replace("mevent", "event", $k)] = $v;

				}

				// Init Spray request to all targetUnits with the mevent_fragrance_id
				(new mod_time())->executeEvent($mevent, "mod_sensorresponder", "m");

			}

			// Clean url parameters
			$this->refresh(true);

		} // final public function initPlannedMusicEventActions()

		/**
		 * Updates the song history store file with song data
		 *
		 * @param bool $wasCalledFromCronjob
		 *
		 * @return bool
		 */
		final public function saveSongHistory($wasCalledFromCronjob = false):bool
		{

			$previousStorageContent = is_file(static::SONGHISTORYSTORE_PATH)
				? json_decode(file_get_contents(static::SONGHISTORYSTORE_PATH)) ?: array()
				: array();

			$updatedStorageContent = array_merge($previousStorageContent, $this->songHistory);

			// If the dataset count surpasses the predefined threshold, drop the first payload
			if(count($updatedStorageContent) > (int)$this->data['config']['mod_sensorResponder__songHistoryStoreThreshold'])
				array_shift( $updatedStorageContent);

			$result = (bool)file_put_contents(static::SONGHISTORYSTORE_PATH, json_encode($updatedStorageContent, JSON_PRETTY_PRINT));
			chmod(static::SONGHISTORYSTORE_PATH, 0777);

			return $result;

		} // final public function saveSongHistory()

		/**
		 * Deletes the song history store file
		 *
		 * @return bool
		 */
		final public function clearSongHistoryStore():bool
		{

			return unlink(static::SONGHISTORYSTORE_PATH);

		} // final public function clearSongHistoryStore()

		/**
		 * Retrieves the stored song history
		 *
		 * @return array|false|string
		 */
		final public function getSongHistory() {

			return @json_decode(@file_get_contents(static::SONGHISTORYSTORE_PATH)) ?: array();

		} // final public function getSongHistory()

		/**
		 * Gets what genres the artist is linked with
		 *
		 * @param $artist_id
		 *
		 * @return array
		 * @throws Exception
		 */
		final public function getGenresByArtistID($artist_id)
		{

			$artist_payload = $this->SpotifyIntegration->getArtistByID($artist_id);

			return @$artist_payload->genres ?: array();

		} // final public function getGenresByArtistID()

		/**
		 * Builds a list of genres with associated mevents
		 *
		 * @return array
		 * @throws Exception
		 */
		final public function getMusicRelatedEvents():array
		{

			// Get all official genres from the Spotify Web API
			$spotify_genres = $this->SpotifyIntegration->getRegisteredGenres();

			// Create array for genres with respective subarrays
			$genres = array();
			foreach($spotify_genres as $spotify_genre) $genres[$spotify_genre] = array();

			# Merge the datasets with the official genres
			foreach($this->store as $dataset)
			{

				// Explode comma separated genres
				$hook_genres = explode(",", $dataset['mevent_hookGenres']);

				// Filter the genres that are both present in $hook_genres (db) and $genres (Spotify)
				$matched_official_genres = array_intersect(array_keys($genres), $hook_genres);

				// If identical matches have been found
				if(isSizedArray($matched_official_genres))
				{
					// Add dataset of the current music event to the store of the official genres
					foreach($genres as $genre_name => $genre_store)
					{
						foreach($matched_official_genres as $m_genre_name)
						{

							if($genre_name === $m_genre_name)
							{

								array_push($genres[$genre_name], $dataset);

							}

						}
					}

				}
				else
				{

					// Search for potential genre that the sub-genre of the dataset might fit into
					foreach($genres as $genre_name => $genre_store)
					{
						foreach($hook_genres as $h_genre_name)
						{

							// If one of the hook genres is present in a part of a official genre name string
							if(strpos($genre_name, $h_genre_name) !== false)
							{

								array_push($genres[$genre_name], $dataset);

							}

						}
					}

				}

			}

			return $genres;

		} // final public function getMusicRelatedEvents()

		/**
		 * Requests data from audd api for a song
		 *
		 * @param String $pathToMP3
		 *
		 * @return array
		 *
		 * @see https://docs.audd.io/
		 */
		final public function getDataOfAudioSample(String $pathToMP3): array
		{

			// *Discontinued*

		} // final public function getDataOfAudioSample()

		/**
		 * Adds a music-event to the database
		 *
		 * @param array $data
		 *
		 * @return bool
		 * @throws Exception
		 */
		final public function addMEvent(array $data)
		{

			// Check whether all required values are present
			$requiredData = array(
				"mevent_name",
				"mevent_hookGenres",
				"mevent_targetUnits",
				"mevent_minIntervalTime"
			);

			if(count(array_diff($requiredData, array_keys($data))) !== 0)
			{

				global $form_fill_required_vals;
				return $this->addFrontendError($form_fill_required_vals[$GLOBALS['lang']]);

			}

			// Replace genre IDs with imploded genre names
			$selectedGenreNames = array();
			$spotifyGenres = $this->SpotifyIntegration->getRegisteredGenres();

			foreach(explode(",", $data['mevent_hookGenres']) as $genre_id)
				array_push($selectedGenreNames, $spotifyGenres[$genre_id]);

			$data['mevent_hookGenres'] = implode(",", $selectedGenreNames);

			// Replace min interval time h:min with seconds
			$timeComponents = explode(":", $data['mevent_minIntervalTime']);
			$data['mevent_minIntervalTime'] = $timeComponents[0] * 60 * 60; // Number in front of ":" times 60 minutes times 60 seconds --> x hours
			$data['mevent_minIntervalTime'] += $timeComponents[1] * 60; // Number after ":" times 60 seconds --> x minutes

			// Default Interval of 30 minutes
			if($data['mevent_minIntervalTime'] === 0) $data['mevent_minIntervalTime'] = 30 * 60; // 30 minutes times 60 seconds

			// Ensure the db connection has been established
			$this->init();

			// Check for duplication
			$duplicateFound = false;

			if(isSizedArray($this->store))
			{
				foreach($this->store as $k => $mevent)
				{
					if(
						$mevent['mevent_name'] === $data['mevent_name']
						||
						$mevent['mevent_hookGenres'] === $data['mevent_hookGenres'] &
						$mevent['mevent_targetUnits'] === $data['mevent_targetUnits'] &&
						$mevent['mevent_minIntervalTime'] === $data['mevent_minIntervalTime']
					)
					{

						$duplicateFound = true;

					}
				}
			}

			if($duplicateFound)
			{

				global $time_event__duplication;
				return $this->addFrontendError($time_event__duplication[$GLOBALS['lang']]);

			}

			// Call Model
			$action = $this->callModelFunc("mod", "saveDataForSpecificModule", static::ACTIVE_MOD, array(
				"mevent_name" => $data['mevent_name'],
				"mevent_hookGenres" => $data['mevent_hookGenres'],
				"mevent_targetUnits" => $data['mevent_targetUnits'],
				"mevent_minIntervalTime" => $data['mevent_minIntervalTime']
			));

			// End with status report
			global $time_addEvent__success, $time_addEvent__failure;

			if($action) $this->addFrontendMessage($time_addEvent__success[$GLOBALS['lang']]);
			else $this->addFrontendError($time_addEvent__failure[$GLOBALS['lang']]);

			// Refresh the page so the GET params in the url void
			$this->refresh();

			return true;

		} // final public function addMEvent()

		/**
		 * Deletes a music-event from the database
		 *
		 * @param Int $mevent_id
		 *
		 * @return bool
		 */
		final public function removeMEvent(Int $mevent_id): bool
		{

			global $time_deleteEvent__failure_eventNotExisting;
			global $time_deleteEvent__success, $time_deleteEvent__failure;

			// Ensure DB connection has been established
			$this->init();

			// Check whether mevent exists
			$mevent_exists = false;

			if(!isSizedArray($this->store))
				return $this->addFrontendError($time_deleteEvent__failure_eventNotExisting[$GLOBALS['lang']]);

			foreach($this->store as $k => $mevent)
				if($mevent['mevent_id'] === $mevent_id)
					$mevent_exists = true;

			if($mevent_exists)
				return $this->addFrontendError($time_deleteEvent__failure_eventNotExisting[$GLOBALS['lang']]);

			// Call Model
			$action = $this->callModelFunc("mod", "deleteDataOfSpecificModule", static::ACTIVE_MOD, array(
				"mevent_id" => $mevent_id
			));

			// End with status report
			if($action) $this->addFrontendMessage($time_deleteEvent__success[$GLOBALS['lang']]);
			else $this->addFrontendError($time_deleteEvent__failure[$GLOBALS['lang']]);

			// Refresh the page so the GET params in the url void
			$this->refresh();

		} // final public function removeMEvent()

		/**
		 * Re-captures the song history and returns it json encoded
		 *
		 * @return false|string
		 * @throws Exception
		 */
		final public function ajax_refreshSongHistory()
		{

			// Re-capture the song history and write it to the store if changed
			$this->captureSongHistory();

			// Return the refreshed song history in a json encoded format due to the ajax request origin
			return die(@json_encode($this->getSongHistory()));

		} // final public function ajax_refreshSongHistory()


	} // class mod_sensorResponder