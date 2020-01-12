<?php

	interface MessagesInterface {

		/**
		 * @function addLogMessage()
		 *           Adds a log message
		 *
		 * @param String $msg
		 *
		 * @param String $log_path
		 *
		 * @return mixed
		 */
		function addLogMessage(String $msg, String $log_path = LOGS_PATH."/events.log");
		// function addLogMessage()

		/**
		 * @function addFrontendMessage()
		 *           Adds a Message to the direct UI
		 *
		 * @param String $msg
		 * @param String $msg_identifier
		 * @param array  $conf
		 *
		 * @return mixed
		 */
		function addFrontendMessage(String $msg, String $msg_identifier = "", array $conf = array());
		// function addFrontendMessage()

		/**
		 * @function addFrontendError()
		 *           Adds an error message to the direct UI
		 *
		 * @param String $err
		 * @param String $err_identifier
		 * @param array  $conf
		 *
		 * @return mixed
		 */
		function addFrontendError(String $err, String $err_identifier = "", array $conf = array());
		// function addFrontendError()

	}