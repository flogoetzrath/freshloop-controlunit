<?php

	/**
	 * @name: AdminController
	 * @description: The controler dedicated to admin actions
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	class AdminController extends MainController {

		/* @var $data array field to store controller related information */
		public $data;

		/**
		 * AdminController constructor
		 *
		 * @param String $action
		 */
		public function __construct(String $action = '')
		{

			parent::__construct($action);

			$this->data = array();

		} // public function __contstruct()

		/**
		 * Dispatches the admin controller
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function dispatch()
		{

			// If not signed in
			if(!isSizedArray($this->modules['mod_auth'])) $this->loadActiveModules();
			if(!$this->modules['mod_auth']->Auth->isLoggedIn())
				if(php_sapi_name() === "cli") die('Unauthorized');
				else $this->redirect("/login", true, true);

			if(!isSizedString($_REQUEST['action'])) return false;

			switch($_REQUEST['action'])
			{

				# Navbar Search Field Action
				case "navbarSearchRequest":
					$this->navbarSearchRequest(xssproof($_REQUEST['q']));
					break;

				# Create unit
				case "registerUnit":
					$this->registerUnit($_REQUEST);
					break;

				# Connect unit
				case "connectUnit":
					$this->connectExecutingUnit(xssproof($_REQUEST['unit_id']));
					break;

				# Process unit port information message
				case "portInformationReq":
					$this->processUnitPortInfoMsg(xssproof($_REQUEST['api_port']), xssproof($_REQUEST['sender_macaddress']));
					break;

				# Change status of unit
				case "changeStatusUnit":
					$this->changeStatusOfUnit($_REQUEST);
					break;

				# Delete unit
				case "deleteUnit":
					$this->deleteUnit($_REQUEST);
					break;

				# Spray at the first position
				case "forceSpray":
					$this->issueSprayRequest(xssproof($_REQUEST['unit_id']));
					break;

				# Change account settings
				case "updateAccSettings":
					$this->updateAccountSettings($_REQUEST);
					break;

				# Change mod status
				case "updateModStatuses":
					$this->updateModStatuses($_REQUEST);
					break;

				# Change miscellaneous settings
				case "updateMiscSettings":
					$this->updateMiscSettings($_REQUEST);
					break;

			}

		} // public function dispatch()

		/**
		 * Searches for module pages based on the input of the navbar search field
		 *
		 * @param String $query
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function navbarSearchRequest(String $query)
		{

			$module_pages = (new TemplateController())->getTranslatedModNames();
			$response = array();

			foreach($module_pages as $mod_name => $translated_name)
			{
				if(strpos($mod_name, $query) !== false || strpos($translated_name, $query) !== false)
				{

					$response[$translated_name] = "/dashboard/$mod_name";

				}
			}

			return die(json_encode($response));

		} // public function navbarSearchRequest()

		/**
		 * Unit registration entry
		 *
		 * @param array $payload
		 *
		 * @return mixed
		 */
		public function registerUnit(array $payload)
		{

			global $f_err_uploadedfile_not_img, $f_err_uploadedfile_img_too_big;
			global $unit_registration_success, $unit_registration_failure, $unit_registration_failure__duplicate;

			# General Validation
			foreach($payload as $k => $v) $payload[xssproof($k)] = xssproof($v);

			# Ensure DB conn has been established
			$this->init();

			# Prompt user if a unit still has to be connected
			if($this->hasAnyUnitNotBeenConnectedYet())
			{

				global $unit_registration_failure__unitNotConnected;
				return $this->addFrontendError($unit_registration_failure__unitNotConnected[$GLOBALS['lang']]);

			}

			# Check unit duplication
			$occurences = $this->db->selectValue(
				'SELECT COUNT(*) FROM units WHERE unit_name = ?', [$payload['name']]
			);

			if($occurences > 0) return $this->addFrontendError($unit_registration_failure__duplicate[$GLOBALS['lang']]);

			# Move Thumbnail if set
			if(isSizedArray($_FILES['img']) && isSizedString($_FILES['img']['name']))
			{

				$img_name = str_replace(" ", "_", "unit-".$payload['name'] . "." . explode(".", $_FILES['img']['name'])[1]);
				$dest = IMG_UPLOAD_DIR.'/units/'.$_FILES['img']['name'];
				$finalDest = IMG_UPLOAD_DIR.'/units/'.$img_name;

				// Potential upload attack
				if(!@getimagesize($_FILES['img']['tmp_name']))
					return $this->addFrontendError($f_err_uploadedfile_not_img[$GLOBALS['lang']]);

				// Backend size validation
				if($_FILES['img']['size'] <= MAX_UPLOAD_FILESIZE)
				{
					// Check if img is duplicate
					if(!file_exists($dest))
					{

						// Finally move img and add name to payload
						move_uploaded_file($_FILES['img']['tmp_name'], $dest)
							? $payload['img'] = $img_name
							: null;

						// "Rename"
						file_put_contents($finalDest, file_get_contents($dest));
						unlink($dest);

					}
				}
				else return $this->addFrontendError($f_err_uploadedfile_img_too_big[$GLOBALS['lang']]);

			}

			# Query db
			$this->callModelFunc('unit',  'registerUnit', $payload)
				? $this->addFrontendMessage($unit_registration_success[$GLOBALS['lang']])
				: $this->addFrontendError($unit_registration_failure[$GLOBALS['lang']]);

		} // public function registerUnit()

		/**
		 * Connects an executing unit
		 *
		 * @param Int $unit_id
		 *
		 * @return String|Bool
		 */
		public function connectExecutingUnit(Int $unit_id)
		{

			global $unit_connection_success, $unit_connection_failure;

			// Search for IP Address by hostname
			$cmd = escapeshellcmd("python ".LIBRARY_PATH.'/scripts/scanIPAddresses.py');
			$results = decodePythonList(shell_exec($cmd));
			$connectableUnit = false;

			// Get already connected units
			$alreadyConnectedUnits = $this->getConnectedUnits();

			// Filter the first unit that has been caught by the netscan and is not connected yet
			foreach($results as $k => $unit)
			{

				if($connectableUnit !== false) continue;

				if(!in_array($unit, $alreadyConnectedUnits))
					$connectableUnit = $unit;

			}

			// If no IP Addresses could be filtered
			if(!isSizedArray($results) || $connectableUnit === false)
			{

				global $unit_connection_failure__noExecutingUnitsDetected;
				return $this->addFrontendError($unit_connection_failure__noExecutingUnitsDetected[$GLOBALS['lang']]);

			}

			// Init Authorizing proccess
			$api_macaddr = $this->getMacAddressByIP($connectableUnit);
			$api_port = $this->callModelFunc("unit", "getUnknownUnitsPortInfo", $api_macaddr);

			$API = new APIController();
			$b = true;
			$secret = hash_hmac("sha256", rand() * hexdec(crc32($this->getMacAddress())), openssl_random_pseudo_bytes(18, $b));

			$response = $API->issueAPIReq("POST", $connectableUnit.":".$api_port."/api/auth/authenticate", array(
				"secret" => $secret
			));
			$response = json_decode($response);

			// Terminate the process if the unit has already been authenticated
			if(property_exists($response, "message") && isSizedString($response->message))
			{

				global $unit_connection_failure__unitAlreadyAuthenticated;

				if($response->message === $unit_connection_failure__unitAlreadyAuthenticated["English"])
				{

					$this->addFrontendError($unit_connection_failure__unitAlreadyAuthenticated[$GLOBALS['lang']]);
					return $this->refresh(true);

				}

			}

			// Save the mac address of the executing unit to the database
			if(property_exists($response, "success") && (bool)$response->success && property_exists($response, "token"))
			{

				$updateMapping = array(
					"macaddress" => $api_macaddr,
					"secret" => $response->token
				);

				$this->callModelFunc('unit', 'updateUnit', $unit_id, $updateMapping)
					? $this->addFrontendMessage($unit_connection_success[$GLOBALS['lang']])
					: $this->addFrontendError($unit_connection_failure[$GLOBALS['lang']]);

				$this->callModelFunc('unit', 'deleteUnknownUnitPortInfo', $api_macaddr);

			}
			else $this->addFrontendError($unit_connection_failure[$GLOBALS['lang']]);

			// Clean the URL from GET parameters
			return $this->refresh(true);

		} // public function connectExecutingUnit()

		/**
		 * Processes any api port information message received
		 *
		 * @param String $port_number   The transmitted port number of the api
		 * @param String $sender_mac    The ip address the request was submitted from
		 *
		 * @return bool                 The Status
		 */
		public function processUnitPortInfoMsg($port_number, $sender_mac)
		{

			if(!isSizedString($port_number) || !isSizedString($sender_mac)) return false;

			// Get the data of all units
			$this->loadData('unit', 0);
			$units = $this->data['unit'][0];

			// Separate the executing unit the request may be originated to
			$req_origin_unit = false;

			foreach($units as $k => $unit)
				if($unit['unit_macaddress'] === $sender_mac)
					$req_origin_unit = $unit;

			// If a unit could be identified as the origin unit
			// --> The unit has already been connected
			if(isSizedArray($req_origin_unit))
			{
				if($req_origin_unit['unit_api_port'] !== $port_number)
				{

					// Save the port to the database for future outgoing requests
					$updateMapping = array(
						"api_port" => $port_number
					);

					return $this->callModelFunc(
						"unit",
						"updateUnit",
						$req_origin_unit['unit_id'],
						$updateMapping
					);

				}
			}
			else
			{

				// Store it as a port for an unknown unit
				return $this->callModelFunc(
					"unit",
					"registerUnknownUnitPortInfo",
					$sender_mac,
					$port_number
				);

			}

		} // public function processUnitPortInfoMsg()

		/**
		 * Gets the dataset of an unconnected unit
		 */
		public function getUnconnectedUnit()
		{

			$returnUnit = array();

			// Ensure the db connection has been established
			$this->init();

			// Get all unit datasets
			$units = $this->db->select("SELECT * FROM units");

			// Filter units
			foreach($units as $k => $unit)
				if(!isSizedString($unit['unit_secret']) && (bool)$unit['unit_isHub'] === false)
					$returnUnit = $unit;

			return $returnUnit;

		} // public function getUnconnectedUnit()

		/**
		 * Checks whether any unit has to be connected
		 *
		 * @note A database connection has to be ensured before execution
		 */
		public function hasAnyUnitNotBeenConnectedYet()
		{

			return (bool)isSizedArray($this->getUnconnectedUnit());

		} // public function hasAnyUnitNotBeenConnectedYet()

		/**
		 * Returns the dataset of all units that have been connected
		 */
		function getConnectedUnits()
		{

			$returnUnit = array();

			// Ensure the db connection has been established
			$this->init();

			// Get all unit datasets
			$units = $this->db->select("SELECT * FROM units");

			// Filter units
			foreach($units as $k => $unit)
				if(isSizedString($unit['unit_secret']) && (bool)$unit['unit_isHub'] === false)
					array_push($returnUnit, $unit);

			return $returnUnit;

		} // function getConnectedUnits()

		/**
		 * Status change entry
		 *
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function changeStatusOfUnit(array $payload)
		{

			global $unit_changeStatus_success, $unit_changeStatus_failure;

			// Check whether arguments are sized
			if(!isSizedString($payload['newStatus']) || !isSizedString($payload['id']))
				return false;

			// Prepare update set directive
			$updateData = array("unit_isActivated" => (int)$payload['newStatus']);

			// Call update model function
			$this->callModelFunc('unit', 'updateUnit', $payload['id'], $updateData)
				? $this->addFrontendMessage($unit_changeStatus_success[$GLOBALS['lang']])
				: $this->addFrontendError($unit_changeStatus_failure[$GLOBALS['lang']]);

		} // public function changeStatusOfUnit()

		/**
		 * Unit deletion entry
		 *
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function deleteUnit(array $payload)
		{

			global $unit_deletion_success, $unit_deletion_failure;

			// If no id has been set
			if(!isSizedInt($payload['id'])) return false;

			// Delete unit
			if($this->callModelFunc('unit', 'deleteUnit', $payload['id']))
				$this->addFrontendMessage($unit_deletion_success[$GLOBALS['lang']]);
			else return $this->addFrontendError($unit_deletion_failure[$GLOBALS['lang']]);

			// Delete img associated
			$this->loadData('unit', $payload['id']);
			foreach($this->data['unit'] as $id => $unit_data)
				if((int)$id === (int)$payload['id'])
					if(isSizedString($unit_data['unit_img']))
					{

						// FIXME: This path of the code doesnt execute properly
						unlink(realpath(MEDIA_PATH."/uploads/units/".$unit_data['unit_img']));

					}

		} // public function deleteUnit()

		/**
		 * Sprays the scent at the given position of a given unit
		 *
		 * @param int $unit_id
		 * @param int $sprayAtPosition
		 *
		 * @return bool|void
		 */
		public function issueSprayRequest(int $unit_id, int $sprayAtPosition = 1)
		{

			# Ensure db connection has been established
			$this->init();

			# Get the target unit
			$this->loadData('unit', $unit_id);
			$unit = $this->data['unit'][$unit_id];

			// Error checking
			if(!isSizedArray($unit) || (bool)$unit['unit_isHub'])
			{

				global $error_occured;

				$this->$this->addFrontendError($error_occured[$GLOBALS['lang']]);
				return $this->refresh(true);

			}

			if((bool)$unit['unit_isActivated'] === false) return false;

			# Extract important information regarding the api request
			$dest_ip = trim($this->getIPByMacAddress($unit['unit_macaddress']));
			$dest_route = $sprayAtPosition !== 1 ? "/api/actions/sprayAtPosition" : "/api/actions/spray";
			$dest_port = $unit['unit_api_port'] ?: $this->callModelFunc("unit", "getUnknownUnitsPortInfo", $unit['unit_macaddress']);
			$dest = $dest_ip.":".$dest_port.$dest_route;

			$auth_jwt = $unit['unit_secret'];

			# Make the api call
			$API = new APIController();
			$API->issueAPIReq("POST", $dest, ["position" => $sprayAtPosition], ["Authorization" => $auth_jwt]);

			# Update the units statistics
			// Update the unit in the db for sake of statistics
			$this->callModelFunc("unit", "updateUnit", $unit['unit_id'], [
				"unit_timesSprayed" => (string)(((int)$unit['unit_timesSprayed']) + 1),
				"unit_lastSprayed" => dateToDBDate(date("d.m.Y"))
			]);

			$this->refresh(true);

		} // public function issueSprayRequest()

		/**
		 * Updates account settings
		 *
		 * @param array $payload
		 *
		 * @return bool|mixed
		 */
		public function updateAccountSettings(array $payload)
		{

			global $respond_to_email;
			global $f_err_no_vals_changed;
			global $auth_changeUsername__success;

			# General Validation
			// Are values set
			if(!isSizedString($payload['name']) || !isSizedString($payload['email'])) return false;

			// Xss
			foreach($payload as $k => $v)
				$payload[$k] = xssproof($v);

			// Are values changed
			$set_vals = array(
				trim($_SESSION[UserManager::SESSION_FIELD_EMAIL]),
				trim($_SESSION[UserManager::SESSION_FIELD_USERNAME])
			);

			$new_vals = array(
				$payload['email'],
				$payload['name']
			);

			if(!@isSizedArray(array_diff($set_vals, $new_vals)))
				return $this->addFrontendError($f_err_no_vals_changed[$GLOBALS['lang']]);

			# Update Settings
			// Update Email
			if($_SESSION[UserManager::SESSION_FIELD_EMAIL] !== $payload['email'])
			{
				// Send confirmation mail to new address
				try{
					$this->modules['mod_auth']->Auth->changeEmail(
						$payload['email'],
						function($selector, $token) use ($payload)
						{

							// Build the verification url
							$url = '/verify_email?selector=' . urlencode($selector) . '&token=' . urlencode($token);

							// Send an email to the user containing a "verification link" ($url)
							$message = '<div style="width: 80%; margin: 0 auto;">';
							$message .= '<h3>Freshloop Email Verification</h3>';
							$message .= '<br><br>';
							$message .= 'As you tried to update your Email address, you have to verify that it is yours.';
							$message .= "<br>";
							$message .= "<a href='".$url."' style='margin:30px auto 50px auto;padding-bottom:10px;border-bottom:1px solid black;width: fit-content;cursor: pointer;text-decoration:none;color:#4a4a4a;'>Verify Email</a>";
							$message .= '</div>';

							(new TemplateController())->sendMail($payload['email'], "Email Verification", $message);

						}
					);

					$this->addFrontendMessage($respond_to_email[$GLOBALS['lang']]);
				}
				catch(UserAlreadyExistsException $e) {
					global $email_exists;
					$this->addFrontendError($email_exists[$GLOBALS['lang']]);
				}
				catch(TooManyRequestsException $e) {
					global $too_many_requests;
					$this->addFrontendError($too_many_requests[$GLOBALS['lang']]);
				}

			}

			// Update Username
			if($_SESSION[UserManager::SESSION_FIELD_USERNAME] !== $payload['name'])
			{

				// Save in DB
				try{
					$this->db->update(
						$this->modules['mod_auth']->Auth->dbTablePrefix."users",
						["username" => $payload['name']],
						["id" => $this->modules['mod_auth']->Auth->getUserId()]
					);
				}
				catch(Exception $e) {
					global $unknown_error;
					$this->addFrontendError($unknown_error[$GLOBALS['lang']]);
				}

				// Update Session
				$_SESSION[UserManager::SESSION_FIELD_USERNAME] = $payload['name'];

				// End function call
				return $this->addFrontendMessage($auth_changeUsername__success[$GLOBALS['lang']]);

			}

		} // public function updateAccountSettings()


		/**
		 * Updates mod statuses
		 *
		 * @param array $payload
		 *
		 * @return mixed
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function updateModStatuses(array $payload)
		{

			global $f_err_no_vals_changed;
			global $settings_save__success, $settings_save__partial_success;

			$current_mods_data = array();

			# Get current mods
			$queryData = $this->db->select(
				'SELECT * FROM mods'
			);

			foreach($queryData as $k => $mod)
				$current_mods_data[$mod['mod_name']] = $mod['mod_status'];

			# Unify datasets
			foreach($payload as $k => $v)
				if(strpos($k, "mod_") === false) unset($payload[$k]);
				else
				{

					if(!isSizedInt($payload[$k])) $payload[$k] = $v === "on" ? 1 : 0;

				}

			# Get difference(s)
			$diff = array();
			$disabled_dependencies = 0;

			foreach($current_mods_data as $cmod_name => $cmod_status)
				foreach($payload as $pmod_name => $pmod_status)
					if($pmod_name === $cmod_name)
					{

						if((bool)$cmod_status !== (bool)$pmod_status)
							$diff[$pmod_name] = $pmod_status;

					}

			# If nothing was changed
			if(!isSizedArray($diff)) return $this->addFrontendError($f_err_no_vals_changed[$GLOBALS['lang']]);

			# Apply changes
			foreach($diff as $mod_name => $new_status)
				if((bool)$new_status)
				{

					// If the class file can be included
					if(file_exists(MODS_PATH.'/'.$mod_name.'.class.php'))
					{

						// Include it
						include_once MODS_PATH.'/'.$mod_name.'.class.php';

						// If a Reflection of the class can be created
						$reflection = new ReflectionClass($mod_name);
						if($reflection)
						{

							$dependencies_activated = true;

							// Check whether the dependencies are all available and activated
							$instance = $reflection->newInstanceWithoutConstructor();

							if($reflection->hasMethod("processDependencyAvailability"))
								$dependencies_activated = $instance->processDependencyAvailability(true);

							// Activate the module only in case of this condition being forfilled
							if($dependencies_activated) $this->activateMod($mod_name);
							else $disabled_dependencies += 1;

						}
						else $this->activateMod($mod_name);

					}
					else $this->activateMod($mod_name);

				}
				else $this->deactivateMod($mod_name);


			// End function execution with status message
			if($disabled_dependencies > 0 && $disabled_dependencies === count($diff)) return false;
			else if($disabled_dependencies > 0 && $disabled_dependencies < count($diff))
				$this->addFrontendMessage($settings_save__partial_success[$GLOBALS['lang']]);
			else return $this->addFrontendMessage($settings_save__success[$GLOBALS['lang']]);

		} // public function updateModStatuses()

		/**
		 * Updates miscellaneous settings
		 *
		 * @param array $payload
		 *
		 * @return mixed
		 */
		public function updateMiscSettings(array $payload)
		{

			global $f_err_no_vals_changed, $settings_save__success;

			$differences = 0;

			// General validation
			foreach($payload as $k => $v)
				$payload[$k] = xssproof($v);


			// Apply Changes
			foreach($payload as $setting_name => $setting_value)
			{

				if(!array_key_exists($setting_name, $this->data['config'])) continue;

				if($setting_value === "on") $setting_value = $payload[$setting_name] = "enabled";
				if($setting_value === "off") $setting_value = $payload[$setting_name] = "disabled";
				if($setting_name === "language")
					if($setting_value === "0") $setting_value = array_flip($GLOBALS['langs_supported'])[$GLOBALS['lang']];
					else $setting_value = array_keys($GLOBALS['langs_supported'])[$setting_value - 1];

				if($this->data['config'][$setting_name] !== $setting_value)
				{

					$differences ++;
					$this->data['config'][$setting_name] = $setting_value;

					if($setting_name === "app_dashboard_tutorial" && $setting_value === "disabled")
						unlink(LIBRARY_PATH.'/ui/driverjs/store.json');

				}

			}

			// Write config file
			if($differences !== 0) $this->writeConfFile($this->data['config']);

			return $differences === 0
				? $this->addFrontendError($f_err_no_vals_changed[$GLOBALS['lang']])
				: $this->addFrontendMessage($settings_save__success[$GLOBALS['lang']]);

		} // public function updateMiscSettings()

		/**
		 * Initializes the driver.js based interactive tutorial
		 */
		public static function initTutorial()
		{

			$tutorialStorePath = LIBRARY_PATH.'/ui/driverjs/store.json';
			$tutorialSampleStorePath = LIBRARY_PATH.'/ui/driverjs/store.sample.json';

			$tutorialStoreContent = null;
			$pages = array();
			$pages_tutorialPlayed = array();

			// If neither the store nor the sample store exist, cancel function call
			if(!file_exists($tutorialStorePath) && !file_exists($tutorialSampleStorePath)) return false;

			// If no tutorial store is present, create one based on the sample store
			if(!file_exists($tutorialStorePath)) file_put_contents(
				$tutorialStorePath,
				file_get_contents($tutorialSampleStorePath)
			);

			$tutorialStoreContent = json_decode(file_get_contents($tutorialStorePath));

			// If all tutorials were played, delete the dynamic store.json file and set the config entry to "disabled"
			foreach($tutorialStoreContent as $page_name => $page_tutorial_conf)
			{

				$pages[$page_name] = $page_tutorial_conf;

				if($page_tutorial_conf->isPlayed) array_push($pages_tutorialPlayed, $page_name);

			}

			$MC = new MainController();

			if(count($pages_tutorialPlayed) === count($pages))
			{

				$MC->readConfig();
				$MC->data['config']['app_dashboard_tutorial'] === "disabled";
				$MC->writeConfFile();

				return unlink($tutorialStorePath);

			}

			// Check current page type and whether the page type is registered to have a tutorial
			if(!isset($pages[$_SESSION['page_type']]) || (bool)$pages[$_SESSION['page_type']]->isPlayed === true)
				return (new MainController())->addLogMessage("*UNWANTED BEHAVIOR* Method 'AdminController::initTutorial()' called illegally.");

			// If the current page is a module page and the module is not activated, cancel the procedure
			if(strpos($_SESSION['page_type'], "mod_") !== false && !($MC->isActiveMod($_SESSION['page_type'])))
				return false;

			// Get the tutorial for the current page
			$currentPage_tutorial = $pages[$_SESSION['page_type']];

			// Replace the language fillers with the actual translated lang string
			foreach($currentPage_tutorial->driverconf as $k => $full_action_conf)
			{
				foreach($full_action_conf->popover as $propKey => $propVal)
				{

					// If a language variable is to be chosen for this one
					global ${$propVal};

					if(isSizedArray(${$propVal}))
					{

						// Replace the text with the translated string
						$currentPage_tutorial->driverconf[$k]->popover->$propKey = utf8_encode(${$propVal}[$GLOBALS['lang']]) ?: $propVal;

					}

				}
			}

			// Load JS for driver.js
			$js = <<<EOT
				let delay = setTimeout(() => {
					const driver = new Driver({
						doneBtnText: '{{ doneBtnText }}' || 'Done',
						closeBtnText: '{{ closeBtnText }}' || 'Close',
						nextBtnText: '{{ nextBtnText }}' || 'Next',
						prevBtnText: '{{ prevBtnText }}' || 'Previous',
						onReset: () => { 
							document.querySelector("#navbar").setAttribute('style', 'z-index: 999999 !important'); 
						}
					});
					
					driver.defineSteps({{ steps }});
					driver.start();
				}, 200); // The preloader already fades after 400 ms
EOT;

			global $tutorial_doneBtnText, $tutorial_closeBtnText,
			       $tutorial_nextBtnText, $tutorial_prevBtnText;

			// Qeueue JS for delayed output
			$GLOBALS['js']['tutorial_page__'.$_SESSION['page_type']] = array(
				$js,
				[
					"steps" => json_encode($currentPage_tutorial->driverconf),
					"doneBtnText" => utf8_encode($tutorial_doneBtnText[$GLOBALS['lang']]),
					"closeBtnText" => utf8_encode($tutorial_closeBtnText[$GLOBALS['lang']]),
					"nextBtnText" => utf8_encode($tutorial_nextBtnText[$GLOBALS['lang']]),
					"prevBtnText" => utf8_encode($tutorial_prevBtnText[$GLOBALS['lang']])
				]
			);

			// Mark the current page tutorial as done
			$tutorialStoreContent->{$_SESSION['page_type']}->isPlayed = true;

			file_put_contents($tutorialStorePath, json_encode($tutorialStoreContent,JSON_PRETTY_PRINT));

		} // public static function initTutorial()

		public static function triggerSpecificPageTutorial(String $page_name)
		{

			// *Discontinued*

		} // public static function triggerSpecificPageTutorial()


	} // class AdminController extends MainController