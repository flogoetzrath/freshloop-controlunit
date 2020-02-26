<?php

	/**
	 * @name: APIController
	 * @description: Controller to bundle api actions
	 * @author: Florian Götzrath <info@floriangoetzrath.de>
	 */

	class APIController extends MainController
	{

		/** @var array field to store multiple curl handles for delayed or multiple execution */
		var $curl_handle_store = array();

		/**
		 * APIController constructor
		 */
		public function __construct()
		{

			parent::__construct();

		} // public function __construct()

		/**
		 * Issues multiple requests rapidly fast in an almost concurrent manner as opposed to the synchronous nature of php
		 *
		 * @return array
		 */
		public function issueRapidReqs(): array
		{

			# Check whether the app is allowed to issue requests
			if(!APP_ACTIVATION) return array();

			# Struct the main resource
			$mh = curl_multi_init();

			# Add the subhandles
			foreach($this->curl_handle_store as $k => $ch)
				curl_multi_add_handle($mh, $ch);

			# Execute the subhandles
			do {
				$status = curl_multi_exec($mh, $active);
				if($active) curl_multi_select($mh);
			} while($active && $status === CURLM_OK);

			# Close the handles
			foreach($this->curl_handle_store as $k => $ch)
				curl_multi_close($mh, $ch);

			# Close the main resource
			curl_multi_close($mh);

			# Extract the responses
			$responses = array();

			foreach($this->curl_handle_store as $k => $ch)
				array_push($responses, curl_multi_getcontent($ch));

			# Clear the handle store
			$this->curl_handle_store = array();

			return $responses;

		} // public function issueRapidReqs()

		/**
		 * Sends an array of requests after a respective delay of $delay seconds after each request
		 *
		 * @param int   $delay             The time in seconds that has to pass until the next spray command in a
		 *                                 consecutive event can be issued
		 * @param array $earlier_responses Responses from earlier calls of this function
		 *
		 * @return array                   All responses
		 * @note This function contains recursion
		 */
		public function issueConsecutiveReqs(int $delay, array $earlier_responses = array())
		{

			# Check whether the app is allowed to issue requests
			if(!APP_ACTIVATION) return false;

			// If this is not the first call, sleep for $delay seconds
			if(isSizedArray($earlier_responses)) sleep($delay);

			// Exec the handle
			$current_handle = $this->curl_handle_store[0];

			$res = curl_exec($current_handle);
			array_push($earlier_responses, $res);

			// Remove the current handle from the curl store
			unset($this->curl_handle_store[0]);
			$this->curl_handle_store = array_values($this->curl_handle_store);

			if(!isSizedArray($this->curl_handle_store)) return $earlier_responses;
			else return $this->issueConsecutiveReqs($delay, $earlier_responses);

		} // public function issueConsecutiveReqs()

		/**
		 * Queue a basic request
		 *
		 * @param String $type
		 * @param String $dest
		 * @param array  $bodyData
		 * @param array  $auth e.g. ["Authorization" => {Bearer JWT}] || ["usr" => 1, "pw" => 1]
		 *
		 * @return bool|string
		 */
		public function queueReq(String $type, String $dest, array $bodyData, array $auth = [])
		{

			$curl = curl_init();

			switch (strtoupper($type))
			{
				case "POST":
					curl_setopt($curl, CURLOPT_POST, true);
					if (isSizedArray($bodyData)) curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($bodyData));
					break;

				case "PUT":
					curl_setopt($curl, CURLOPT_PUT, true);
					break;

				case "DELETE":
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
					break;

				default:
					if (isSizedArray($bodyData)) $dest = sprintf("%s?%s", $dest, http_build_query($bodyData));
			}

			# Optional Authentication
			if(isSizedArray($auth))
			{
				if(isSizedString($auth['Authorization']))
				{

					$header = array();
					$header[] = 'Content-type: application/x-www-form-urlencoded';
					$header[] = 'Authorization: ' . trim($auth['Authorization']);

					curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

				}
				else
				{

					curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
					curl_setopt($curl, CURLOPT_USERPWD, $auth['usr'].":".$auth['pw']);

				}
			}

			curl_setopt($curl, CURLOPT_URL, $dest);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			array_push($this->curl_handle_store, $curl);

		} // public function queueReq()

		/**
		 * Executes queued requests
		 *
		 * @return array|bool|string
		 */
		public function issueQueuedReqs()
		{

			# Check whether the app is allowed to issue requests
			if(!APP_ACTIVATION) return false;

			$res = array();
			$handles = $this->curl_handle_store;

			if(count($handles) === 1) $res = curl_exec($handles[0]);
			else
			{
				foreach($handles as $k => $handle)
				{

					$i_res = curl_exec($handle);
					array_push($handles, $i_res);

				}
			}

			// Clear the handle store
			$this->curl_handle_store = array();

			return $res;

		} // public function issueQueuedReqs()

		/**
		 * Shorthand alias for the procedure around sending a simple api request
		 */
		public function issueAPIReq(String $type, String $dest, array $bodyData, array $auth = [])
		{

			$this->queueReq($type, $dest, $bodyData, $auth);
			return $this->issueQueuedReqs();

		} // public function issueAPIReq()

		/**
		 * Issues a module relevant api request
		 *
		 * @param String $type
		 * @param String $dest
		 * @param array  $data
		 *
		 * @return string|null
		 */
		public function issueModAPIReq(String $type, String $dest, array $data)
		{

			# Check whether the app is allowed to issue requests
			if(!APP_ACTIVATION) return false;

			$cmd = 'curl -X "'.strtoupper($type).'" "'.$dest.'" -H "Accept: application/json" "Content-Type: application/json" -H '.implode(' -H ', $data);
			$res = shell_exec($cmd);

			return isJson($res) ? json_decode($res) : $res;

		} // public function issueModAPIReq()

	} // class APIController extends MainController