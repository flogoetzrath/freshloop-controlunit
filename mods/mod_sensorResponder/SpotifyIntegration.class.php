<?php

	class SpotifyIntegration extends APIController
	{

		const SPOTIFY_CREDS_PATH = MODS_PATH.'/mod_sensorResponder/spotifyCreds.ini';

		const REDIRECT_URI = "http://hub/dashboard/mod_sensorResponder?page=Admin&mod=mod_sensorResponder&action=SpotifyAuthorizationCallback";
		const AUTHORIZE_URI = "https://accounts.spotify.com/authorize";
		const TOKENENDPOINT_URI = "https://accounts.spotify.com/api/token";

		private $client_id;
		private $client_secret;

		public function __construct()
		{

			parent::__construct();

			// Load the spotify web api credentials
			$credentials_data = parse_ini_file(static::SPOTIFY_CREDS_PATH);
			$this->client_id = $credentials_data['client_id'];
			$this->client_secret = $credentials_data['client_secret'];

		} // public function __construct()

		/**
		 * Initiates Spotify Accounts Service Authorization
		 * Aims to use the Authorization Code flow
		 *
		 * @param array $permissionScope
		 *
		 * @see https://developer.spotify.com/documentation/general/guides/authorization-guide/
		 */
		public function requestSpotifyAuthorization($permissionScope = array("user-read-currently-playing"))
		{

			// Build the permission scope string
			$permissionScope = implode(" ", $permissionScope);

			// Redirect to the external Spotify accounts service
			$this->redirect(
				static::AUTHORIZE_URI.
				'?response_type=code'.
				'&client_id='.$this->client_id.
				'&scope='.$permissionScope.
				'&redirect_uri='.urlencode(self::REDIRECT_URI),
				false,
				true
			);

		} // public function requestSpotifyAuthorization()

		/**
		 * Processing of the Spotify Authorization request after getting a response from the Spotify services
		 *
		 * @param array $payload
		 *
		 * @throws Exception
		 *
		 * @see https://developer.spotify.com/documentation/general/guides/authorization-guide/
		 */
		public function spotifyAuthorizationCallback(array $payload)
		{

			// Error Handling
			if(isset($payload['error']))
			{
				if(DEBUG_MODE) die(debug($payload['error']));
				else
				{

					global $sensorResponder_spotifyError_authError;
					$this->addFrontendError($sensorResponder_spotifyError_authError[$GLOBALS['lang']]);

				}
			}

			// Second step according to the Spotify Web Api Code Flow authentication method
			// POST request to TOKENENDPOINT_URI in order to exchange the $payload['code'] with a Spotify valid Token
			$res = json_decode($this->issueAPIReq("POST", static::TOKENENDPOINT_URI, [
				"grant_type" => "authorization_code",   // According to the OAuth 2.0 specifications
				"code" => $payload['code'],
				"redirect_uri" => static::REDIRECT_URI, // Used for validation only
				"client_id" => $this->client_id,
				"client_secret" => $this->client_secret
			]));

			// Error Handling
			if(!isset($res->access_token) || !isset($res->refresh_token))
			{
				if(DEBUG_MODE) die(debug($res));
				else
				{

					global $sensorResponder_spotifyError_authError;
					$this->addFrontendError($sensorResponder_spotifyError_authError[$GLOBALS['lang']]);

				}
			}

			// Calculate Expiration Time (add seconds from $res->expires_in to the current time)
			$expiration_timestamp = (new DateTime())->add(new DateInterval('PT'.xssproof($res->expires_in).'S'));

			// Write data to the config
			$this->readConfig();
			$this->data['config']['mod_sensorResponder__spotifyAccessToken'] = xssproof($res->access_token);
			$this->data['config']['mod_sensorResponder__spotifyAccessTokenExpiration'] = $expiration_timestamp->getTimestamp();
			$this->data['config']['mod_sensorResponder__spotifyRefreshToken'] = xssproof($res->refresh_token);
			$this->writeConfFile($this->data['config'], true);

		} // public function spotifyAuthorizationCallback()

		/**
		 * Refreshes the stored access token
		 *
		 * @see https://developer.spotify.com/documentation/general/guides/authorization-guide/
		 *
		 * @throws Exception
		 */
		public function spotifyRefreshAccessToken()
		{

			$this->readConfig();

			// If no refresh token is present, cancel the function call
			if(!isSizedString($this->data['config']['mod_sensorResponder__spotifyRefreshToken']))
				return false;

			// Make API Call
			$res = json_decode($this->issueAPIReq("POST", static::TOKENENDPOINT_URI, [
				"grant_type" => "refresh_token",
				"refresh_token" => $this->data['config']['mod_sensorResponder__spotifyRefreshToken'],
				"client_id" => $this->client_id,
				"client_secret" => $this->client_secret
			]));

			// Error Handling
			if(!isset($res->access_token))
			{
				if(DEBUG_MODE) die(debug($res));
				else
				{

					global $sensorResponder_spotifyError_authError;
					$this->addFrontendError($sensorResponder_spotifyError_authError[$GLOBALS['lang']]);

				}
			}

			// Calculate Expiration Time (add seconds from $res->expires_in to the current time)
			$expiration_timestamp = (new DateTime())->add(new DateInterval('PT'.xssproof($res->expires_in).'S'));

			// Write data to the config
			$this->readConfig();
			$this->data['config']['mod_sensorResponder__spotifyAccessToken'] = xssproof($res->access_token);
			$this->data['config']['mod_sensorResponder__spotifyAccessTokenExpiration'] = $expiration_timestamp->getTimestamp();
			$this->writeConfFile($this->data['config'], false);

		} // public function spotifyRefreshAccessToken()

		/**
		 * Calculates whether a refresh of the Spotify supplied access token is necessary
		 *
		 * @return bool
		 */
		public function isAccessTokenRefreshNeeded(): bool
		{

			// Get expiration time
			$this->readConfig();

			// Convert timestamp to time
			$expirationTime = strtotime($this->data['config']['mod_sensorResponder__spotifyAccessTokenExpiration']);

			// Compare expiration time to current time
			return (bool)(time() > $expirationTime);

		} // public function isAccessTokenRefreshNeeded()

		/**
		 * Checks whether a Spotify account is connected to the app
		 *
		 * @return bool
		 */
		public function isSpotifyConnected(): bool
		{

			$this->readConfig();

			return (
				isSizedString($this->data['config']['mod_sensorResponder__spotifyAccessToken']) &&
				isSizedString($this->data['config']['mod_sensorResponder__spotifyAccessTokenExpiration']) &&
				isSizedString($this->data['config']['mod_sensorResponder__spotifyRefreshToken'])
			);

		} // public function isSpotifyConnected()

		/**
		 * Sends a currently-playing GET request to the Spotify Web API
		 *
		 * @return array|string|null
		 *
		 * @throws Exception
		 * @see https://developer.spotify.com/console/get-users-currently-playing-track
		 */
		public function getCurrentlyPlayedSong()
		{

			// Refresh access token if necessary
			if($this->isAccessTokenRefreshNeeded()) $this->spotifyRefreshAccessToken();

			return $this->issueModAPIReq("GET", "https://api.spotify.com/v1/me/player/currently-playing", [
				'"Authorization: Bearer '.$this->data["config"]["mod_sensorResponder__spotifyAccessToken"].'"'
			]);

		} // public function getCurrentlyPlayedSong()

		/**
		 * Queries the Spotify Web API with a get-an-artist GET request
		 *
		 * @param $artist_id
		 *
		 * @return string|null
		 * @throws Exception
		 */
		public function getArtistByID($artist_id)
		{

			// Refresh access token if necessary
			if($this->isAccessTokenRefreshNeeded()) $this->spotifyRefreshAccessToken();

			return $this->issueModAPIReq("GET", "https://api.spotify.com/v1/artists/$artist_id", [
				'"Authorization: Bearer '.$this->data["config"]["mod_sensorResponder__spotifyAccessToken"].'"'
			]);

		} // public function getArtistByID()

		/**
		 * Requests an array of officially registered genre seeds
		 *
		 * @return string|null
		 * @throws Exception
		 */
		public function getRegisteredGenres()
		{

			// Refresh access token if necessary
			if($this->isAccessTokenRefreshNeeded()) $this->spotifyRefreshAccessToken();

			return $this->issueModAPIReq("GET", "https://api.spotify.com/v1/recommendations/available-genre-seeds", [
				'"Authorization: Bearer '.$this->data["config"]["mod_sensorResponder__spotifyAccessToken"].'"'
			])->genres;

		} // public function getRegisteredGenres()

	} // class SpotifyIntegration
