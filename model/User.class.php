<?php
	/**
	 * @name: User
	 * @description: User Model
	 * @author: Florian Götzrath
	 */

	class User extends Model
	{

		/** @var $data array field to store user specific data */
		public $data;

		/**
		 * Loads data
		 *
		 * @param $id
		 */
		public function load($id)
		{

			parent::__construct();
			parent::load($id);

			$this->data = $this->db->selectRow(
				"SELECT * FROM `users` WHERE `id` = ?",
				[ $id ]
			);

		} // public function load($id)


	} // class User