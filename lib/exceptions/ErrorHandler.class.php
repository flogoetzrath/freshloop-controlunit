<?php

	class ErrorHandler {

		/**
		 * Rethrows an error occured
		 *
		 * @param $err
		 */
		public static function rethrow(String $err)
		{

			debug($err);

			echo "<br><br>";

			debug(debug_backtrace());

			die();

		} // public static function rethrow()

	} // class ErrorHandler