<?php
	/**
	 * @name: User
	 * @description: User Model
	 * @author: Florian Götzrath
	 */

	class Unit extends Model
	{

		/** @var $data array field to store user specific data */
		public $data;
		/** @const table_prefix string field to store the table specific prefix */
		const TABLE_PREFIX = "unit_";

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
				"SELECT * FROM `units` WHERE `unit_id` = ?",
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
				"SELECT * FROM `units`"
			);

		} // public function loadAll()

		/**
		 * Registers a new unitE
		 *
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function registerUnit(array $payload)
		{

			parent::__construct();

			// General Valiation
			foreach($payload as $k => $v) $payload[xssproof($k)] = xssproof($v);
			$payload = open_array($payload);

			if($payload['ishub'] === 0 || $payload['ishub'] === 2) $isHub = 0;
			else $isHub = 1;

			// If there is already a hub registered
			$hubSet = 0;
			$this->loadAll();

			foreach($this->data as $k => $unit)
				if((bool)$unit['unit_isHub']) $hubSet ++;

			if($isHub === 1 && $hubSet >= 1) $isHub = 0;

			// Insert payload into db
			return $this->db->insert('units', [
				"unit_name" => $payload['name'] ?? "",
				"unit_isHub" => $isHub,
				"unit_room" => $payload['room'] ?? "",
				"unit_priority" => $payload['priority'] ?? 0,
				"unit_img" => $payload['img'] ?? ""
			]);

		} // public function registerUnit()

		/**
		 * Returns all entries or a specifc entry by macaddress from the unknown_units_portinfo table
		 *
		 * @param null $query_macaddr       If set, queries for the specific entry with that mac address, queries for all existing entries otherwise
		 *
		 * @return mixed
		 */
		public function getUnknownUnitsPortInfo($query_macaddr = null)
		{

			if(isSizedString($query_macaddr))
			{

				return $this->db->selectRow(
					"SELECT * FROM `unknown_units_portinfo` WHERE `uupi_macaddr` = ?",
					[ $query_macaddr ]
				);

			}
			else return $this->db->select("SELECT * FROM unknown_units_portinfo");

		} // public function getAllUnknownUnitsPortInfo

		/**
		 * Registers or updates an entry to the unknown_units_portinfo table
		 *
		 * @param String $uupi_macaddr
		 * @param String $uupi_port
		 *
		 * @return mixed
		 */
		public function registerUnknownUnitPortInfo(String $uupi_macaddr, String $uupi_port)
		{

			parent::__construct();

			$uupi_macaddr = xssproof($uupi_macaddr);
			$uupi_port = xssproof($uupi_port);

			$targetEntry = $this->getUnknownUnitsPortInfo($uupi_macaddr);

			if(isSizedArray($targetEntry))
			{

				return $this->db->update(
					"unknown_units_portinfo",
					["uupi_port" => $uupi_port],
					["uupi_macaddr" => $uupi_macaddr]
				);

			}
			else return $this->db->insert('unknown_units_portinfo', [
				"uupi_macaddr" => $uupi_macaddr,
				"uupi_port" => $uupi_port
			]);

		} // public function registerUnknownUnitPortInfo()

		/**
		 * Dynamically updates a unit
		 *
		 * @param Int   $unit_id
		 * @param array $payload
		 *
		 * @return bool
		 */
		public function updateUnit($unit_id, array $payload)
		{

			parent::__construct();

			foreach($payload as $k => $v)
			{

				if(!strpos($k, "unit_")) $k = 'unit_'.$k;

				$payload[$k] = $v;
				unset($payload[$k]);

			}

			return $this->db->update('units', $payload, ["unit_id" => (int)$unit_id]);

		} // public function updateUnit()

		/**
		 * Deletes a unit
		 *
		 * @param Int $unit_id
		 *
		 * @return bool
		 */
		public function deleteUnit(Int $unit_id)
		{

			parent::__construct();

			return $this->db->delete('units', ['unit_id' => $unit_id]);

		} // public function deleteUnit()

		/**
		 * Deletes an entry from the unknown_units_portinfo table
		 *
		 * @param String $uupi_macaddr
		 *
		 * @return mixed
		 */
		public function deleteUnknownUnitPortInfo(String $uupi_macaddr)
		{

			return $this->db->delete('unknown_units_portinfo', ['uupi_macaddr' => $uupi_macaddr]);

		} // public function deleteUnknownUnitPortInfo()


	} // class Unit