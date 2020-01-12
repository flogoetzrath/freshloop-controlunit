<?php

	require_once(ABSPATH.'/vars.php');
	require_once('AuthExceptions.php');

	class UserManager extends MainController
	{

		/** @var string session field for whether the client is currently signed in */
		const SESSION_FIELD_LOGGED_IN = 'auth_logged_in';
		/** @var string session field for the ID of the user who is currently signed in (if any) */
		const SESSION_FIELD_USER_ID = 'auth_user_id';
		/** @var string session field for the email address of the user who is currently signed in (if any) */
		const SESSION_FIELD_EMAIL = 'auth_email';
		/** @var string session field for the display name (if any) of the user who is currently signed in (if any) */
		const SESSION_FIELD_USERNAME = 'auth_username';
		/** @var string session field for the status of the user who is currently signed in (if any) as one of the constants from the {@see Status} class */
		const SESSION_FIELD_STATUS = 'auth_status';
		/** @var string session field for the roles of the user who is currently signed in (if any) as a bitmask using constants from the {@see Role} class */
		const SESSION_FIELD_ROLES = 'auth_roles';
		/** @var string session field for whether the user who is currently signed in (if any) has been remembered (instead of them having authenticated actively) */
		const SESSION_FIELD_REMEMBERED = 'auth_remembered';
		/** @var string session field for the UNIX timestamp in seconds of the session data's last resynchronization with its authoritative source in the database */
		const SESSION_FIELD_LAST_RESYNC = 'auth_last_resync';
		/** @var string session field for the counter that keeps track of forced logouts that need to be performed in the current session */
		const SESSION_FIELD_FORCE_LOGOUT = 'auth_force_logout';

		/** @var string the prefix for the names of all database tables used by this component */
		public $dbTablePrefix;

		/**
		 * @param string|null $dbTablePrefix the prefix for the names of all database tables used by this component
		 */
		public function __construct($dbTablePrefix = null)
		{

			parent::__construct();

			if(!isset($this->db) || empty($this->db)) $this->init();
			$this->dbTablePrefix = (string) $dbTablePrefix;

		} // protected function __construct($databaseConnection, $dbTablePrefix)


		/**
		 * Creates a new user
		 *
		 * If you want the user's account to be activated by default, pass `null` as the callback
		 *
		 * If you want to make the user verify their email address first, pass an anonymous function as the callback
		 *
		 * The callback function must have the following signature:
		 *
		 * `function ($selector, $token)`
		 *
		 * Both pieces of information must be sent to the user, usually embedded in a link
		 *
		 * When the user wants to verify their email address as a next step, both pieces will be required again
		 *
		 * @param bool          $requireUniqueUsername whether it must be ensured that the username is unique
		 * @param string        $email                 the email address to register
		 * @param string        $password              the password for the new account
		 * @param string|null   $username              (optional)        the username that will be displayed
		 * @param callable|null $callback              (optional)        the function that sends the confirmation email
		 *                                             to the user
		 *
		 * @return int the ID of the user that has been created (if any)
		 * @throws InvalidEmailException        if the email address has been invalid
		 * @throws InvalidPasswordException     if the password has been invalid
		 * @throws DuplicateUsernameException   if it was specified that the username must be unique while it was *not*
		 * @throws AuthError                    if an internal problem occurred*@throws EmailAlreadyExistsException
		 * @throws EmailAlreadyExistsException
		 *
		 * @see confirmEmail
		 * @see confirmEmailAndSignIn
		 */
		protected function createUserInternal($requireUniqueUsername, $email, $password, $username = null, callable $callback = null)
		{

			ignore_user_abort(true);

			$email = self::validateEmailAddress($email);
			$password = self::validatePassword($password);

			$username = isset($username) ? trim($username) : null;
			if ($username === '') $username = null;
			if ($requireUniqueUsername && $username !== null)
			{

				$occurrencesOfUsername = $this->db->selectValue(
					'SELECT COUNT(*) FROM ' . $this->dbTablePrefix . 'users WHERE username = ?',
					[ $username ]
				);

				if ($occurrencesOfUsername > 0) throw new DuplicateUsernameException();

			}

			$occurrencesOfEmail = $this->db->selectValue(
				'SELECT COUNT(*) FROM ' . $this->dbTablePrefix . 'users WHERE email = ?',
				[ $email ]
			);

			if($occurrencesOfEmail > 0) throw new EmailAlreadyExistsException();

			$password = password_hash($password, PASSWORD_DEFAULT);
			$verified = is_callable($callback) ? 0 : 1;

			try {
				$this->db->insert(
					$this->dbTablePrefix . 'users',
					[
						'email' => $email,
						'password' => $password,
						'username' => $username,
						'verified' => $verified,
						'roles_mask' => 16,          // Standard role for consumers { @see Role.class.php }
						'created_at' => time()
					]
				);
			}

			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

			$newUserId = (int) $this->db->getLastInsertId();

			if ($verified === 0) $this->createConfirmationRequest($newUserId, $email, $callback);

			return $newUserId;

		} // createUserInternal($requireUniqueUsername, $email, $password, $username, callable $callback)


		/**
		 * Updates the given user's password by setting it to the new specified password
		 *
		 * @param   int     $userId         the ID of the user whose password should be updated
		 * @param   string  $newPassword    the new password
		 * @throws  UnknownIdException      if no user with the specified ID has been found
		 * @throws  AuthError               if an internal problem occurred
		 */
		protected function updatePasswordInternal($userId, $newPassword)
		{

			$newPassword = password_hash($newPassword, PASSWORD_DEFAULT);

			try {
				$affected = $this->db->update(
					$this->dbTablePrefix . 'users',
					[ 'password' => $newPassword ],
					[ 'id' => $userId ]
				);

				if ($affected === 0) throw new UnknownIdException();
			}

			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

		} // protected function updatePasswordInternal($uderId, $newPassword)


		/**
		 * Called when a user has successfully logged in
		 *
		 * @param int       $userId         the ID of the user
		 * @param string    $email t        he email address of the user
		 * @param string    $username       the display name (if any) of the user
		 * @param int       $status         the status of the user as one of the constants from the {@see Status} class
		 * @param int       $roles          the roles of the user as a bitmask using constants from the {@see Role} class
		 * @param int       $forceLogout    the counter that keeps track of forced logouts that need to be performed in the current session
		 * @param bool      $remembered     whether the user has been remembered (instead of them having authenticated actively)
		 */
		protected function onLoginSuccessful($userId, $email, $username, $status, $roles, $forceLogout, $remembered)
		{

			// re-generate the session ID to prevent session fixation attacks (requests a cookie to be written on the client)
			Session::regenerate(true);

			$_SESSION[self::SESSION_FIELD_LOGGED_IN] = true;
			$_SESSION[self::SESSION_FIELD_USER_ID] = (int) $userId;
			$_SESSION[self::SESSION_FIELD_EMAIL] = $email;
			$_SESSION[self::SESSION_FIELD_USERNAME] = $username;
			$_SESSION[self::SESSION_FIELD_STATUS] = (int) $status;
			$_SESSION[self::SESSION_FIELD_ROLES] = (int) $roles;
			$_SESSION[self::SESSION_FIELD_FORCE_LOGOUT] = (int) $forceLogout;
			$_SESSION[self::SESSION_FIELD_REMEMBERED] = $remembered;
			$_SESSION[self::SESSION_FIELD_LAST_RESYNC] = time();

		} // onLoginSuccessful($userId, $email, $username, $status, $roles, $forceLogout, $remembered)


		/**
		 * Returns the requested user data for the account with the specified username (if any)
		 *
		 * @param   string      $username the username to look for
		 * @param   array       $requestedColumns the columns to request from the user's record
		 * @return  array       the user data (if an account was found unambiguously)
		 * @throws UnknownUsernameException     if no user with the specified username has been found
		 * @throws AmbiguousUsernameException   if multiple users with the specified username have been found
		 * @throws AuthError                    if an internal problem occurred
		 */
		protected function getUserDataByUsername($username, array $requestedColumns) {

			try {
				$projection = deep_implode(', ', $requestedColumns);

				$users = $this->db->select(
					'SELECT ' . $projection . ' FROM ' . $this->dbTablePrefix . 'users WHERE username = ? LIMIT 2 OFFSET 0',
					[ $username ]
				);
			}

			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

			if (empty($users))throw new UnknownUsernameException();
			else
			{

				if (count($users) === 1) return $users[0];
				else throw new AmbiguousUsernameException();

			}

		} // getUserDataByUsername($username, array $requestedColumns)


		/**
		 * Validates an email address
		 *
		 * @param   string  $email          the email address to validate
		 * @return  string                  the sanitized email address
		 * @throws  InvalidEmailException   if the email address has been invalid
		 */
		protected static function validateEmailAddress($email)
		{

			if (empty($email)) throw new InvalidEmailException();

			$email = trim($email);

			if (!filter_var($email, FILTER_VALIDATE_EMAIL))
				throw new InvalidEmailException();

			return xssproof($email);

		} // validateEmailAddress($email)


		/**
		 * Validates a password
		 *
		 * @param   string $password            the password to validate
		 * @return  string                      the sanitized password
		 * @throws  InvalidPasswordException    if the password has been invalid
		 */
		protected static function validatePassword($password)
		{

			if (empty($password)) throw new InvalidPasswordException();

			$password = trim($password);

			if (strlen($password) < 1) throw new InvalidPasswordException();

			return xssproof($password);

		} // validatePassword($password)


		/**
		 * Creates a request for email confirmation
		 *
		 * The callback function must have the following signature:
		 *
		 * `function ($selector, $token)`
		 *
		 * Both pieces of information must be sent to the user, usually embedded in a link
		 *
		 * When the user wants to verify their email address as a next step, both pieces will be required again
		 *
		 * @param int       $userId     the user's ID
		 * @param string    $email      the email address to verify
		 * @param callable  $callback   the function that sends the confirmation email to the user
		 * @throws AuthError if an internal problem occurred
		 */
		protected function createConfirmationRequest($userId, $email, callable $callback)
		{

			$selector = createRandomString(16);
			$token = createRandomString(16);
			$tokenHashed = password_hash($token, PASSWORD_DEFAULT);
			$expires = time() + 60 * 60 * 24;

			try {
				$this->db->insert(
					$this->dbTablePrefix . 'users_confirmations',
					[
						'id' => (int) $userId,
						'user_id' => (int) $userId,
						'email' => $email,
						'selector' => $selector,
						'token' => $tokenHashed,
						'expires' => $expires
					]
				);
			}

			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

			if (is_callable($callback)) $callback($selector, $token);
			else throw new MissingCallbackError();

		} // protected function createConfirmationRequest($userId, $email, callable $callback)


		/**
		 * Clears an existing directive that keeps the user logged in ("remember me")
		 *
		 * @param int       $userId                 the ID of the user who shouldn't be kept signed in anymore
		 * @param string    $selector (optional)    the selector which the deletion should be restricted to
		 * @throws AuthError if an internal problem occurred
		 */
		protected function deleteRememberDirectiveForUserById($userId, $selector = null)
		{

			$whereMappings = [];

			if (isset($selector)) $whereMappings['selector'] = (string) $selector;

			$whereMappings['user'] = (int) $userId;

			try {
				$this->db->delete(
					$this->dbTablePrefix . 'users_remembered',
					$whereMappings
				);
			}

			catch (Error $err) {
				throw new DatabaseError($err->getMessage());
			}

		} // protected function deleteRememberDirectiveForUserById($userId, $selector)


		/**
		 * Triggers a forced logout in all sessions that belong to the specified user
		 *
		 * @param   int     $userId     the ID of the user to sign out
		 * @throws  AuthError if an internal problem occurred
		 */
		protected function forceLogoutForUserById($userId)
		{

			$this->deleteRememberDirectiveForUserById($userId);

			$this->db->exec(
				'UPDATE ' . $this->dbTablePrefix . 'users SET force_logout = force_logout + 1 WHERE id = ?',
				[ $userId ]
			);

		} // protected function forceLogoutForUserById($userId)


	} // class UserManager extends MainController