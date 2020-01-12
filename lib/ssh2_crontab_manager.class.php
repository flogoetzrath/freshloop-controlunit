<?php

	/**
	 * @name: ssh2_crontab_manager.class.php
	 * @description: Endpoint for cron management
	 */

	class ssh2_crontab_manager {

		const HOST = SSH_HOST;
		const PORT = SSH_PORT;
		const USERNAME = SSH_USER;
		const PASSWORD = SSH_PW;

		private $connection;
		private $path;
		private $handle;
		private $cron_file;

		/**
		 * ssh2_crontab_manager constructor
		 *
		 * @param null $host
		 * @param null $port
		 * @param null $username
		 * @param null $password
		 */
		function __construct($host = self::HOST, $port = self::PORT, $username = self::USERNAME, $password = self::PASSWORD)
		{

			if(ALLOW_CRONJOBS === false) return false;

			$path_length = strrpos(__FILE__, "/");
			$this->path = substr(__FILE__, 0, $path_length) . '/';
			$this->handle = 'crontab.txt';
			$this->cron_file = "{$this->path}{$this->handle}";

			try {
				if (is_null($host) || is_null($port) || is_null($username) || is_null($password))
					throw new Exception("The host, port, username and password arguments must be specified!");

				$this->connection = @ssh2_connect($host, $port);
				if (!$this->connection)
					throw new Exception("The SSH2 connection could not be established.");

				$authentication = @ssh2_auth_password($this->connection, $username, $password);
				if (!$authentication)
					throw new Exception("Could not authenticate '{$username}' using pasword: '{$password}'.");
			}
			catch (Exception $e) {
				$this->error_message($e->getMessage());
			}

		} // function __construct()

		/**
		 * Executes a command
		 *
		 * @return $this
		 */
		public function exec()
		{

			$argument_count = func_num_args();

			try {
				if (!$argument_count)
					throw new Exception("There is nothing to exececute, no arguments specified.");

				$arguments = func_get_args();

				$command_string = ($argument_count > 1)
					? implode(" && ", $arguments)
					: $arguments[0];

				$stream = @ssh2_exec($this->connection, $command_string);

				if (!$stream)
					throw new Exception("Unable to execute the specified commands: <br />{$command_string}");
			}
			catch (Exception $e) {
				$this->error_message($e->getMessage());
			}

			return $this;

		} // public function exec()

		/**
		 * Writes to a cron manager file
		 *
		 * @param null $path
		 * @param null $handle
		 *
		 * @return $this
		 */
		public function write_to_file($path = null, $handle = null)
		{

			if (!$this->crontab_file_exists())
			{

				$this->handle = (is_null($handle)) ? $this->handle : $handle;
				$this->path = (is_null($path)) ? $this->path   : $path;
				$this->cron_file = "{$this->path}{$this->handle}";

				$init_cron = "crontab -l > {$this->cron_file} && [ -f {$this->cron_file} ] || > {$this->cron_file}";

				$this->exec($init_cron);

			}

			return $this;

		} // public function write_to_file()

		/**
		 * Removes the cron manager file
		 *
		 * @return $this
		 */
		public function remove_file()
		{

			if ($this->crontab_file_exists()) $this->exec("rm {$this->cron_file}");

			return $this;

		} // public function remove_file()

		/**
		 * Appends a cronjob
		 *
		 * @param null $cron_jobs
		 *
		 * @return $this
		 */
		public function append_cronjob($cron_jobs = null)
		{

			if (is_null($cron_jobs))
				$this->error_message("Nothing to append! Please specify a cron job or an array of cron jobs.");

			$append_cronfile = "echo '";
			$append_cronfile .= (is_array($cron_jobs)) ? implode("\n", $cron_jobs) : $cron_jobs;
			$append_cronfile .= "'  >> {$this->cron_file}";

			$install_cron = "crontab {$this->cron_file}";

			$this->write_to_file()->exec($append_cronfile, $install_cron)->remove_file();

			return $this;

		} // public function append_cronjob()

		/**
		 * Filters for a regex cronjob name and returns the full cron command if registered
		 *
		 * @param null $cron_jobs
		 *
		 * @return bool
		 */
		public function cronjob_exists($cron_jobs = null)
		{

			if (is_null($cron_jobs))
				$this->error_message("Nothing to remove!  Please specify a cron job or an array of cron jobs.");

			$this->write_to_file();

			$cron_array = file($this->cron_file, FILE_IGNORE_NEW_LINES);

			if (empty($cron_array))
				$this->remove_file()->error_message("Nothing to remove!  The cronTab is already empty.");

			if (is_array($cron_jobs))
				foreach ($cron_jobs as $cron_regex)
					$cron_array = preg_grep($cron_regex, $cron_array, PREG_GREP_INVERT);
			else $cron_array = preg_grep($cron_jobs, $cron_array, PREG_GREP_INVERT);

			return $cron_array ?: false;

		} // public function cronjob_exists()

		/**
		 * Removes a cronjob
		 *
		 * @param null $cron_jobs
		 *
		 * @return ssh2_crontab_manager
		 */
		public function remove_cronjob($cron_jobs = null)
		{

			if (is_null($cron_jobs))
				$this->error_message("Nothing to remove!  Please specify a cron job or an array of cron jobs.");

			$this->write_to_file();

			$cron_array = file($this->cron_file, FILE_IGNORE_NEW_LINES);

			if (empty($cron_array))
				$this->remove_file()->error_message("Nothing to remove!  The cronTab is already empty.");

			$original_count = count($cron_array);
			$cron_array = $this->cronjob_exists($cron_jobs);

			return ($original_count === count($cron_array))
				? $this->remove_file()
				: $this->remove_crontab()->append_cronjob($cron_array);

		} // public function remove_cronjob()

		/**
		 * Removes the whole crontab
		 *
		 * @return $this
		 */
		public function remove_crontab()
		{

			$this->remove_file()->exec("crontab -r");

			return $this;

		} // public function remove_crontab()

		/**
		 * Determines whether the crontab exists
		 *
		 * @return bool
		 */
		private function crontab_file_exists()
		{

			return file_exists($this->cron_file);

		} // private function crontab_file_exists()

		/**
		 * Basic error handler
		 *
		 * @param $error
		 */
		private function error_message($error)
		{

			die("<pre style='color:#EE2711'>ERROR: {$error}</pre>");

		} // private function error_message()

	} // class ssh2_crontab_manager