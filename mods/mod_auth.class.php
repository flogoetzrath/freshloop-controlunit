<?php
	/**
	 * @name: mod_auth
	 * @description: Module high-level class to act as a mod controller
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	require_once "struct_mod.class.php";

	require_once MODS_PATH . "/mod_auth/Administration.class.php";
	require_once MODS_PATH . "/mod_auth/Auth.class.php";

	final class mod_auth extends struct_mod
	{

		/** @var string active mod field for eventual identification */
		const ACTIVE_MOD = "mod_auth";

		/** @var object field for the auth class instance */
		public $Auth = null;
		/** @var object field for the admin class instance */
		public $Admin = null;

		/**
		 * mod_auth constructor
		 *
		 * @throws AuthError
		 * @throws DatabaseError
		 */
		public function __construct()
		{

			global $auth_modName;

			parent::__construct(
				static::ACTIVE_MOD,
				$auth_modName[$GLOBALS['lang']]
			);

			if(!isset($this->db) || empty($this->db)) $this->init();

			$this->Auth = new Auth();
			$this->Admin = new Administration();

		} // public function __construct()

		/**
		 * Dispatches requests regarding this mod
		 *
		 * @param array $data
		 *
		 * @throws AmbiguousUsernameException
		 * @throws AttemptCancelledException
		 * @throws AuthError
		 * @throws TooManyRequestsException
		 * @throws UnknownIdException
		 * @throws ReflectionException
		 */
		final public function dispatch($data = array())
		{

			// Basic Login Request
			if(isSizedString($data['action']) && $data['action'] === "login")
				$this->basicLoginRequest(array(
					"email" => isSizedString($data['email']) ? $data['email'] : false,
					"password" => isSizedString($data['password']) ? $data['password'] : false
				));

			// Basic Register Request
			if(isSizedString($data['action']) && $data['action'] === "register")
				$this->registerRequest(array(
					"name" => isSizedString($data['name']) ? $data['name'] : false,
					"email" => isSizedString($data['email']) ? $data['email'] : false,
					"password" => isSizedString($data['password']) ? $data['password'] : false,
					"password2" => isSizedString($data['password2']) ? $data['password2'] : false
				));

			// Recover PW Request
			if(isSizedString($data['action']) && $data['action'] === "recoverPW")
				$this->recoverPWRequest(array(
					"email" => isSizedString($data['email']) ? $data['email'] : false
				));

			// Logout Request
			if(isSizedString($data['action']) && $data['action'] === "logout")
				$this->basicLogOutRequest();

		} // final public function dispatch()

		/**
		 * Picks the actual thrown error out of given possible errors
		 * and bundles the error into a returned payload
		 *
		 * @param String $error
		 * @param array  $possible_errors
		 *
		 * @return array|bool
		 */
		public function organiseErrorListing(String $error, array $possible_errors)
		{

			if(($match = array_search($error, $possible_errors)) !== false)
				$error_payload = array(
					"err_status" => "true",
					"err_msg" => $possible_errors[$match]
				);

			return isSizedArray($error_payload)
				? $error_payload
				: false;

		} // public function organiseErrorListing()

		/**
		 * Stores auth errors in $this->view for templating
		 */
		public function buildAuthErrors()
		{

			// Get error messages
			global $unknown_error, $invalid_email,
			       $invalid_password, $unknown_username,
			       $ambiguous_username, $email_or_username_required;

			// Declare inline deletion function
			$delCurrentErr = function($identifier, bool $filter_arr = false)
			{

				unset($_SESSION['frontend_errors'][$identifier]);
				unset($_SESSION['frontend_errors']['conf'][$identifier]);

				if($filter_arr) $_SESSION['frontend_errors'] = array_filter($_SESSION['frontend_errors']);

			};

			// If exceptions are set
			if (isSizedArray($_SESSION['frontend_errors']))
			{
				foreach($_SESSION['frontend_errors'] as $identifier => $err)
				{

					if(!isSizedString($err)) $delCurrentErr($identifier, true);
					if($identifier === "conf" || !isSizedString($err)) continue;

					$email_errors = array(
						$unknown_username[$GLOBALS['lang']],
						$ambiguous_username[$GLOBALS['lang']],
						$invalid_email[$GLOBALS['lang']],
						$email_or_username_required[$GLOBALS['lang']],
						$unknown_error[$GLOBALS['lang']]
					);

					if(!isSizedArray($this->view['email_err']))
						$this->view['email_err'] = $this->organiseErrorListing($err, $email_errors);

					$password_errors = array(
						$invalid_password[$GLOBALS['lang']],
						$email_or_username_required[$GLOBALS['lang']],
						$unknown_error[$GLOBALS['lang']]
					);

					if(!isSizedArray($this->view['password_err']))
						$this->view['password_err'] = $this->organiseErrorListing($err, $password_errors);

					$delCurrentErr($identifier);

				}
			}

		} // public function buildAuthErrors()

		/**
		 * Creates a login request to the authentication system
		 * Logs the event if successful
		 *
		 * @param $payload
		 *
		 * @throws AmbiguousUsernameException
		 * @throws AttemptCancelledException
		 * @throws AuthError
		 * @throws TooManyRequestsException
		 * @throws UnknownIdException
		 */
		final protected function basicLoginRequest($payload)
		{

			global $unknown_error;

			// Validation and securing content safeness
			foreach($payload as $k => $v)
				$payload[xssproof($k)] = trim(xssproof($v));
			$isValidEmail = filter_var(filter_var($payload['email'], FILTER_VALIDATE_EMAIL), FILTER_SANITIZE_EMAIL);

			if(isSizedString($isValidEmail))
			{

				$this->Auth->login($payload['email'], $payload['password']);

				if($this->Auth->isLoggedIn())
				{

					$this->addLogMessage("Successfull login of the user with the email address ".$payload['email']."");
					$this->redirect("/dashboard", true);

				}
				else $this->addFrontendError($unknown_error[$GLOBALS['lang']]);

			}
			else
			{

				$this->Auth->loginWithUsername($payload['name'], $payload['password']);

				if($this->Auth->isLoggedIn())
				{

					$this->addLogMessage("Successfull login of the user with the name of ".$payload['name']."");
					$this->redirect("/dashboard", true);

				}
				else $this->addFrontendError($unknown_error[$GLOBALS['lang']]);

			}

		} // final protected function basicLoginRequest()

		/**
		 * Creates a register request
		 *
		 * @param $payload
		 *
		 * @return bool
		 * @throws AuthError
		 * @throws TooManyRequestsException
		 */
		final protected function registerRequest($payload)
		{

			// Validation and securing safe content
			foreach($payload as $k => $v)
				$payload[$k] = xssproof($v);

			if($payload['password'] !== $payload['password2']) return false;
			if(!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) return false;

			// Issue registration
			if(!isSizedString($payload['name']))
				$usr_id = $this->Auth->register($payload['email'], $payload['password']);
			else
				$usr_id = $this->Auth->registerWithUniqueUsername($payload['email'], $payload['password'], $payload['name']);

			// End proccess
			if(isSizedInt($usr_id))
			{

				$_SESSION['auth_usr_id'] = $usr_id;
				$this->redirect('/login', true);

			}
			else return false;

		} // final protected function registerRequest()

		/**
		 * Issues a recover pw request
		 *
		 * @param $payload
		 *
		 * @throws AuthError
		 */
		final protected function recoverPWRequest($payload)
		{

			foreach($payload as $k => $v)
				$payload[$k] = xssproof($v);

			try{
				$this->Auth->forgotPassword($payload['email'], function($selector, $token) use($payload) {

					// Build the recovery url
					$url = '/reset_password?selector=' . urlencode($selector) . '&token=' . urlencode($token);

					// Send an email to the user containing a "recovery link" ($url)
					$message = '<div style="width: 80%; margin: 0 auto;">';
					$message .= '<h3>Freshloop Password Recovery</h3>';
					$message .= '<br><br>';
					$message .= 'As you filed a password recovery request, we are pleased to send you a recovery link.';
					$message .= "<br><br>";
					$message .= "<a href='".$url."' style='margin:30px auto 50px auto;padding-bottom:10px;border-bottom:1px solid black;width: fit-content;cursor: pointer;text-decoration:none;color:#4a4a4a;'>Reset Password</a>";
					$message .= '</div>';

					(new TemplateController())->sendMail($payload['email'], "Password Recovery", $message);

					// Clean the url from parameters
					$this->refresh(true);

				});
			}
			catch(InvalidEmailException $err) {
				global $invalid_email;
				$this->addFrontendError($invalid_email[$GLOBALS['lang']]);
			}
			catch(EmailNotVerifiedException $err) {
				global $email_not_verified;
				$this->addFrontendError($email_not_verified[$GLOBALS['lang']]);
			}
			catch(ResetDisabledException $err) {
				global $reset_disabled;
				$this->addFrontendError($reset_disabled[$GLOBALS['lang']]);
			}
			catch(TooManyRequestsException $err) {
				global $too_many_requests;
				$this->addFrontendError($too_many_requests[$GLOBALS['lang']]);
			}

		} // final protected function recoverPWRequest()

		/**
		 * Creates a basic logout request
		 *
		 * @return bool
		 * @throws AuthError
		 * @throws ReflectionException
		 */
		final protected function basicLogOutRequest()
		{

			$this->Auth->logOut();

			if($this->Auth->isLoggedIn()) return false;
			else $this->addLogMessage("Logout performed successfully.");

			if($this->isActiveMod("mod_sensorResponder"))
			{

				// Stop potentially running cron jobs
				$this->loadModule("mod_sensorResponder");
				@$this->modules['mod_sensorResponder']->stopSongHistoryCapturing();

			}

			return true;

		} // final protected function basicLogOutRequest()

	} // class mod_auth