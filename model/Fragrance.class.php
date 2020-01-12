<?php
	/**
	 * @name: User
	 * @description: Fragrance Model
	 * @author: Florian Götzrath
	 */

	class Fragrance extends Model
	{

		/** @var $data array field to store fragrance specific data */
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
				"SELECT * FROM `fragrances` WHERE `id` = ?",
				[ $id ]
			);

		} // public function load($id)

		/**
		 * Loads all rows
		 */
		public function loadAll()
		{

			parent::__construct();

			$this->data = $this->db->select(
				"SELECT * FROM `fragrances`"
			);

		} // public function loadAll()

		/**
		 * Registers a new fragrance
		 *
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function registerFragrance(array $payload)
		{

			parent::__construct();

			// Further code

		} // public function registerFragrance()

		/**
		 * Dynamically updates a fragrance
		 *
		 * @param Int   $fragrance_id
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function updateFragrance(Int $fragrance_id, array $payload)
		{

			parent::__construct();

			foreach($payload as $k => $v)
				if(!strpos($k, 'fragrance_'))
				{

					$payload["fragrance_$k"] = $v;
					unset($payload[$k]);

				}

			return $this->db->update('fragrances', $payload, ["fragrance_id" => $fragrance_id]);

		} // public function updateFragrance()

		/**
		 * Deletes a fragrance
		 *
		 * @param Int $fragrances_id
		 *
		 * @return bool
		 */
		public function deleteFragrance(Int $fragrances_id)
		{

			parent::__construct();

			return $this->db->delete('fragrances', ['fragrances_id' => $fragrances_id]);

		} // public function deleteFragrance()


	} // class Fragrance