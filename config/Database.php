<?php
	/**
	 * User: Flo
	 * Date: 08.07.2018
	 * Time: 12:17
	 */

	require_once('PDODatabase.php');


	class Database extends PdoDatabase
	{

		protected $host = DB_HOST,
			$user = DB_USER,
			$pw   = DB_PW,
			$dbname = DB_TABLENAME;

		public $conn;


		/**
		 * Database constructor
		 */
		public function __construct()
		{

			try {
				$this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->dbname, $this->user, $this->pw);
				$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch (PDOException $err) {
				if(DEBUG_MODE) throw new PDOException($err->getMessage(), (int)$err->getCode());
				else
				{

					global $db_fatal_error;
					die(@$db_fatal_error[$GLOBALS['lang']] ?: $db_fatal_error['English']);

				}
			}

		} // public function __construct()


		/**
		 * Prevent class forcingly from cloning
		 */
		public final function __clone () { }
		// public final function __clone()


	} // class Database
