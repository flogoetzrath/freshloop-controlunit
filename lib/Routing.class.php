<?php

	class Routing extends MainController {

		/* @var $req_uri string field to store the current site's request */
		public $req_uri;
		/* @var $api_actions array field to store actions that are eligible to call by an api when being unauthorized  */
		public $api_actions;
		/* @var $route_history array field to store successfull routing actions */
		public $route_history;

		/**
		 * Routing constructor
		 */
		public function __construct()
		{

			parent::__construct("Routing");

			$req_uri = explode('?', $_SERVER['REQUEST_URI'], 2);
			$this->req_uri = str_replace("/".APP_NAME."/", "/", $req_uri[0]);

			// Load api whitelisted routes
			$this->api_actions = json_decode(file_get_contents(CONFIG_PATH.'/apiWhitelistedActions.json'));

			$this->route_history = array();

		} // public function __construct()

		/**
		 * Returns the current route
		 *
		 * @return bool|string
		 */
		public static function get_current_route()
		{

			$req_uri = $_SERVER['REQUEST_URI'];
			$appName_mask = "/".APP_NAME."/";

			// Cut app name
			$req_uri = substr($req_uri, (strpos($req_uri, $appName_mask) + strlen($appName_mask)), strlen($appName_mask));
			// Cut additional params
			$req_uri = substr($req_uri, 0, strpos($req_uri, "?"));

			return $req_uri;

		} // public static function get_current_route()

		/**
		 * Checks whether a given route is whitelisted for api usage also when being unauthenticated
		 *
		 * @param String $route
		 *
		 * @return bool
		 */
		public function isApiWhitelistedAction(String $route)
		{

			return isSizedString($_REQUEST['action'])
				? in_array($_REQUEST['action'], $this->api_actions)
				: false;

		} // public function isApiWhitelistedAction()

		/**
		 * Adds a route
		 *
		 * @param String $route
		 * @param String $template_path
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function addRoute(String $route, String $template_path)
		{

			if($this->req_uri === $route)
			{

				$this->renderTemplate($template_path);

			}

		} // public function addRoute()

		/**
		 * Adds a route that can only be accessed when being logged in
		 *
		 * @param String $route
		 * @param String $template_path
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function addAuthorizedRoute(String $route, String $template_path)
		{

			if($this->req_uri === $route)
			{

				$this->loadActiveModules();

				if($this->modules['mod_auth']->Auth->isLoggedIn() || $this->isApiWhitelistedAction($route))
				{

					$this->renderTemplate($template_path);

				}
				else
				{

					$this->redirect("/login", true);

				}

			}

		} // public function addAuthorizedRoute()

		/**
		 * Adds a route with dynamic parameters
		 *
		 * @param String $dynamicRoute
		 * @param array  $associatedPaths
		 * @param float  $percentage The minimum percentage the dynamic route has to be similar to the actual url
		 *
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function addDynamicRoute(String $dynamicRoute, array $associatedPaths, float $percentage = 66.6)
		{

			# Decide if route applies
			similar_text($dynamicRoute, $this->req_uri, $p);

			if($p >= $percentage)
			{

				$dynamicVars = [];

				# Get Dynamic Values
				$pattern = "/\{\{[^\´]*\}\}/";
				preg_match_all($pattern, $dynamicRoute, $dynamicVals);

				# Remove curly braces
				foreach($dynamicVals as $k => $v) $dynamicVals[$k] = str_replace(["{{", "}}"], "", $v);
				$dynamicVals = deep_explode(",", deep_implode(",", open_array($dynamicVals)));

				# Split URL and Route
				$reqParts = deep_explode("/", $this->req_uri);
				$routeParts = deep_explode("/", $dynamicRoute);

				$diffs = array_diff($reqParts, $routeParts);

				// Manipulate data and add dynamicVars entry
				foreach($diffs as $k => $diff)
				{

					$i = array_search($diff, $reqParts);
					preg_match($pattern, $routeParts[$i], $match);

					foreach($dynamicVals as $_k => $val)
					{
						if($val === str_replace(["{{", "}}"], "", $match[0]))
						{

							$dynamicVars[$val] = array(
								'title' => $diff,
								'path' => $associatedPaths[$val] . "/$diff/$diff.phtml"
							);

						}
					}

				}

				# Proceed to render the template
				foreach($dynamicVars as $k => $var)
					foreach($var as $property => $v)
						if($property === 'path' && isSizedString($v))
							$this->renderTemplate($v);

			}

		} // public function addDynamicRoute()

		/**
		 * Route to redirect if no route matched
		 * IMPORTANT: If this function is called, a template will be loaded no matter what
		 *
		 * @param String $template_path
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function addFailureRoute(String $template_path)
		{

			if(!isSizedArray($this->route_history))
			{

				// Load originally requested file (useful for ajax calls etc.)
				if(file_exists($template_path))
				{

					$this->renderTemplate($template_path);
					return true;

				}

				// Load given fallback tempalte
				$this->renderTemplate($template_path);

			}

		} // public function addFailureRoute()

	}