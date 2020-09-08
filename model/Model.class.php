<?php

	class Model {

		protected $db = false;
		protected $tables;

		var $id = false;
		var $data = false;

		/**
		 * Model constructor
		 */
		public function __construct()
		{

			$this->db = $GLOBALS['db'];
			$this->data = array();

			$this->tables = $this->db->select("SHOW TABLES");
			array_walk( $this->tables, function($item, $key) {
					$this->tables[$key] = end($item);
				}
			);

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
		 * Loads a specific item
		 *
		 * @param $id
		 */
		public function load($id)
		{

			$this->id = $id;

		} // public function load($id)


		/**
		 * Checks if an item is already loaded to either this models instance or a given controller instance
		 *
		 * @param $key
		 * @param $controllerInstance
		 *
		 * @return bool
		 */
		public function isLoaded($key, $controllerInstance)
		{

			return isSizedInt($this->data[$key]);

		} // public function isLoaded($key)


	} // class Model