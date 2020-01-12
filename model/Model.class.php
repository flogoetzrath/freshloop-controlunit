<?php

	class Model {

		protected $db = false;

		var $id = false;
		var $data = false;

		/**
		 * Model constructor
		 */
		public function __construct()
		{

			$this->db = $GLOBALS['db'];
			$this->data = array();

		} // public function __construct()


		/**
		 * Store a piece of information
		 *
		 * @param $name
		 *
		 * @return mixed|null
		 */
		public function __get($name)
		{

			if(array_key_exists($name, (array)$this->data))
			{

				return $this->data[$name];

			}

			return null;

		} // public function __get()


		/**
		 * Saves a piece of information
		 *
		 * @param $name
		 * @param $value
		 */
		public function __set($name, $value)
		{

			$this->data[$name] = $value;

		} // public function __set()


		/**
		 * Sets a specifc user as target by id
		 *
		 * @param $id
		 */
		public function load($id)
		{

			$this->id = $id;

		} // public function load($id)


		/**
		 * Checks if a specifc user
		 *
		 * @param $key
		 *
		 * @return bool
		 */
		public function isLoaded($key)
		{

			return isSizedInt($this->data[$key]);

		} // public function isLoaded($key)


	} // class Model