<?php

	class AuthException extends Exception
	{

		/**
		 * @var MainController instance field to store a MainController instance
		 */
		protected $MC;

		/**
		 * AuthException constructor
		 */
		public function __construct()
		{

			parent::__construct(
				$this->getMessage(),
				$this->getCode(),
				$this->getPrevious()
			);

			$this->MC = new MainController();

		} // public function __construct()

		/**
		 * Adds both backend and frontend error
		 */
		public function initErrors()
		{

			$this->MC->addBackendError($this->getMessage() . ": " . $this->getCode());
			$this->MC->addFrontendError($this->getMessage());

		} // public function initErrors()

	} // class AuthException extends Exception

	class UnknownIdException extends AuthException {}

	class InvalidEmailException extends AuthException {}

	class UnknownUsernameException extends AuthException {}

	class InvalidPasswordException extends AuthException {}

	class EmailNotVerifiedException extends AuthException {}

	class EmailAlreadyExistsException extends AuthException {}

	class UserAlreadyExistsException extends AuthException {}

	class NotLoggedInException extends AuthException {}

	class InvalidSelectorTokenPairException extends AuthException {}

	class TokenExpiredException extends AuthException {}

	class TooManyRequestsException extends AuthException { public function __construct(String $msg, Int $estimatedWaitingTimeSeconds) { parent::__construct(); } }

	class DuplicateUsernameException extends AuthException {}

	class AmbiguousUsernameException extends AuthException {}

	class AttemptCancelledException extends AuthException {}

	class ResetDisabledException extends AuthException {}

	class ConfirmationRequestNotFound extends AuthException {}

	class AuthError extends Exception {}

	class DatabaseError extends AuthError {}

	class DatabaseDriverError extends DatabaseError {}

	class MissingCallbackError extends AuthError {}

	class HeadersAlreadySentError extends AuthError {}

	class EmailOrUsernameRequiredError extends AuthError {}