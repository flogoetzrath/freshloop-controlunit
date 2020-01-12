<?php

	final class ResponseHeader
	{

		private function __construct() { }

		/**
		 * Returns the header with the specified name (and optional value prefix)
		 *
		 * @param string $name the name of the header
		 * @param string $valuePrefix the optional string to match at the beginning of the header's value
		 * @return string|null the header (if found) or `null`
		 */
		public static function get($name, $valuePrefix = '')
		{

			$nameLength = strlen($name);
			$valuePrefixLength = strlen($valuePrefix);

			$headers = headers_list();

			foreach ($headers as $header)
			{
				if (substr($header, 0, $nameLength) === $name) {

					if (substr($header, $nameLength + 2, $valuePrefixLength) === $valuePrefix)
						return $header;

				}
			}

			return null;

		} // public static function get()

		/**
		 * Sets the header with the specified name and value
		 *
		 * @param string $name the name of the header
		 * @param string $value the corresponding value for the header
		 */
		public static function set($name, $value)
		{

			header($name.': '.$value);

		} // public static function set()

		/**
		 * Adds the header with the specified name and value
		 *
		 * @param string $name the name of the header
		 * @param string $value the corresponding value for the header
		 */
		public static function add($name, $value)
		{

			header($name.': '.$value, false);

		} // public static function add()

		/**
		 * Removes the header with the specified name (and optional value prefix)
		 *
		 * @param string $name the name of the header
		 * @param string $valuePrefix the optional string to match at the beginning of the header's value
		 */
		public static function remove($name, $valuePrefix = '')
		{

			if (empty($valuePrefix)) header_remove($name);
			else
			{

				$found = self::get($name, $valuePrefix);

				if (isset($found)) header_remove($name);

			}

		} // public static function remove()

		/**
		 * Returns and removes the header with the specified name (and optional value prefix)
		 *
		 * @param string $name the name of the header
		 * @param string $valuePrefix the optional string to match at the beginning of the header's value
		 * @return string|null the header (if found) or `null`
		 */
		public static function take($name, $valuePrefix = '')
		{

			$found = self::get($name, $valuePrefix);

			if (isset($found))
			{

				header_remove($name);

				return $found;

			}
			else return null;

		} // public static function take()

	}
