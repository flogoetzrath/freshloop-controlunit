<?php
	class SystemException extends Exception
	{

		/**
		 * @var MainController instance field
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
			$this->MC->init();

			if($this->MC->data['config']['log_exceptions'] === "enabled")
				$this->logException();

		} // public function __construct()

		/**
		 * Logs the Exception
		 */
		public function logException()
		{

			$this->MC->addLogMessage(
				"Exception ".get_class($this)." thrown: ".$this->getMessage()
			);

		} // public function logException()

	} // class SystemException extends Exception

	class ModulePDOException extends SystemException {}