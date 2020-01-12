<?php
	/**
	 * User: Flo
	 * Date: 08.07.2018
	 * Time: 12:50
	 */

	final class Role
	{

		const ADMIN = 1;
		const SUPERVISOR = 2;
		const COLLABORATOR = 4;
		const CONSULTANT = 8;
		const CONSUMER = 16;
		const CONTRIBUTOR = 32;
		const COORDINATOR = 64;
		const CREATOR = 128;
		const DEVELOPER = 256;
		const EDITOR = 512;
		const EMPLOYEE = 1024;
		const MAINTAINER = 2048;
		const MANAGER = 4096;
		const MODERATOR = 8192;
		const SUBSCRIBER = 16384;
		const TRANSLATOR = 32786;

		/**
		 * Returns an array mapping the numerical role values to their descriptive names
		 *
		 * @return array|null
		 * @throws ReflectionException
		 */
		public static function getMap()
		{

			$reflectionClass = new ReflectionClass(static::class);
			return array_flip($reflectionClass->getConstants());

		} // public static function getMap()

		/**
		 * Returns the descriptive role names
		 *
		 * @return array
		 * @throws ReflectionException
		 */
		public static function getNames()
		{

			$reflectionClass = new ReflectionClass(static::class);
			return array_keys($reflectionClass->getConstants());

		} // public static function getNames()

		/**
		 * Returns the numerical role values
		 *
		 * @return array
		 * @throws ReflectionException
		 */
		public static function getValues()
		{

			$reflectionClass = new ReflectionClass(static::class);
			return array_values($reflectionClass->getConstants());

		} // public static function getValues()

		private function __construct() {}


	} // final class Role