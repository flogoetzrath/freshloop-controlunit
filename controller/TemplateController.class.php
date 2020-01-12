<?php
	/**
	 * User: Flo
	 * Date: 29.06.2018
	 * Time: 22:21
	 */

	class TemplateController extends MainController
	{

		/* @var $page_type string field to determine the purpose of the instance */
		public $page_type = null;
		/* @var $js array field to store javascript code for delayed execution */
		public $js;
		/* @var $html array field to store additional html code for delayed execution */
		public $html;


		/**
		 * TemplateController constructor
		 *
		 * @param String|null $page_type
		 * @param String      $action
		 */
		public function __construct(String $page_type = null, String $action = "")
		{

			parent::__construct($action);

			$this->action = $action ?? null;
			$this->page_type = $page_type ?? '';

			$this->additional_js = [];

		} // public function __construct()

		/**
		 * Basic Templating Engine
		 *
		 * @param      $file
		 * @param      $data
		 * @param bool $isFile
		 *
		 * @return bool|mixed|string
		 */
		public function load_content($file, $data, Bool $isFile = true)
		{

			if($isFile) $template = file_get_contents($file);
			else $template = $file;

			if(!isSizedArray($data)) return $template;

			foreach($data as $key => $value)
			{

				if($value instanceof Database) continue;
				if(is_object($value)) serialize($value);
				$template = str_replace('{{ '.$key.' }}', $value ?? 0, $template);

			}

			return $template;

		} // public function load_content($file, $data)

		/**
		 * Evaluates php code inside a string that is wrapped inside: [[ [^\´]* ]]
		 *
		 * @param String
		 * @param bool $removeCode
		 *
		 * @return bool
		 */
		public function execute_template_php(String $php, Bool $removeCode = false)
		{

			// Get php expressions
			preg_match_all("/\[\[[^\´]*\]\]/", xssproof($php), $matches);
			if(!isSizedArray($matches)) return $php;

			// Format matches
			$lineArr = array_values(deep_explode("\n", open_array($matches)[0]));

			// Interprete code
			foreach($lineArr as $k => $match)
			{

				if($removeCode) $php = str_replace($match, '', $php);
				if(!strpos($match, '[[') && !strpos($match,']]')) continue;
				$match = str_replace(array('[[', ']]'), '', $match);

				try{
					if(isSizedString($match)) eval(deep_trim($match));
				}
				catch(ParseError $e){
					if(DEBUG_MODE)
					{

						debug($match);
						throw $e;

					}
				}

			}

			if($removeCode) return $php;
			else return true;

		} // public function execute_php()

		/**
		 * Renders a given widget with given data
		 *
		 * @note If $origin is ["mod_name" => "", "widget_name" => ""] --> method looking for a module widget
		 *
		 * @param string|array $origin
		 * @param array|object $data
		 * @param bool         $useTemplateEngine
		 * @param string       $default_dir
		 *
		 * @return bool
		 */
		public function load_widget($origin, $data = [], $useTemplateEngine = true, $default_dir = VIEWS_PATH.'/admin/dashboard/widgets/*.phtml')
		{

			if(is_array($origin))
			{

				$name = $origin['widget_name'];
				$widget = $path = VIEWS_PATH."/admin/dashboard/mods/".$origin['mod_name']."/widgets/$name.phtml";

			}
			else
			{

				// Clarification
				$name = $origin;

				// Get all widget files
				$widgetFiles = glob($default_dir);

				foreach($widgetFiles as $k => $file)
				{

					// Get chosen widget file
					$iterationName = @end(explode("/", str_replace(".phtml", "", $file)));
					if($iterationName === $name) $widget = $path = $file;

				}

			}

			// If no widget matching the given name was found
			if(!isset($widget)) return false;

			// Manage widget id
			if(!isset($this->view['widget_counter'])) $this->view['widget_counter'] = 0;
			else $this->view['widget_counter'] ++;

			if(is_array($data)) $data['widget_id'] = uniqid();
			//if(is_array($data)) $data['widget_id'] = $this->view['widget_counter'];

			if($useTemplateEngine)
			{

				// Fill data
				$widgetContents = $this->load_content($widget, $data);
				// Eval php code inside widget
				$widgetContents = $this->execute_template_php($widgetContents, true);
				// Render widget
				echo $widgetContents;

			}
			else
			{

				// Pass class instance to prevent global changes
				$TC = $this;
				// Render Widget
				include $path;

			}

		} // public function load_widget()

		/**
		 * Alias of function load_widget for different use case
		 *
		 * @param String $component_name
		 * @param array  $data
		 * @param bool   $useTemplateEngine
		 * @param string $defaultDir
		 *
		 * @return bool
		 */
		public function load_component(String $component_name, array $data, $useTemplateEngine = true, $defaultDir = VIEWS_PATH.'/common/components/*.phtml')
		{

			return $this->load_widget(
				$component_name,
				$data,
				$useTemplateEngine,
				$defaultDir
			);

		} // public function load_component()

		/**
		 * Evaluates javascript code that has been collected during app execution
		 *
		 * @param null $payload
		 *
		 * @return boo
		 */
		public function evalAdditionalJS($payload = null)
		{

			if(!isset($payload)) $payload = $this->js;
			if(!isSizedArray($payload)) return false;

			foreach($payload as $id => $codePayload)
			{
				if(isset($codePayload[1]))
				{

					echo "<script>";
					echo $this->load_content($codePayload[0], $codePayload[1], false);
					echo "</script>";

				}
				else
					echo $codePayload[0];
			}

		} // public function evalAdditionalJavaScript()

		/**
		 * Renders additional templating html code
		 *
		 * @param null $payload
		 *
		 * @return bool
		 */
		public function renderAdditionalTemplating($payload = null)
		{

			if(!isset($payload)) $payload = $this->html;
			if(empty($payload)) return false;

			if(isSizedArray($payload))
				foreach($payload as $k => $html)
					echo $html;
			else echo $payload;

			return true;

		} // public function renderAdditionalTemplating()

		/**
		 * If Controller Action === TemplateManagement: Render Header Template
		 * If Controller Action !== TemplateManagement: Return Header Template URL
		 *
		 * @param String Distribution of template (e.g. mod_auth)
		 *
		 * @return bool|string
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_header(String $dist_name = "")
		{

			$header_path = '/views/common/Header.phtml';

			# Check if origin of file belongs to the core
			if(file_exists(PUBLIC_PATH.'/css/'.$dist_name.'.css'))
			{

				$style = file_get_contents(PUBLIC_PATH.'/css/'.$dist_name.'.css');
				//$style = preg_replace('/\s+/', '', $style);

				echo "<style>";
				echo $style;
				echo "</style>";

			}

			# Render the template eventually if it was the task
			if($this->action === 'TemplateManagement')
			{

				return $this->renderTemplate($header_path, $dist_name);

			}

			return $header_path;

		} // public function the_header()

		/**
		 * If Controller Action === TemplateManagement: Render Footer Template
		 * If Controller Action !== TemplateManagement: Return Footer Template URL
		 *
		 * @param String $dist_name
		 *
		 * @return bool|string
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_footer(String $dist_name = "")
		{

			$footer_url = '/views/common/Footer.phtml';

			if($this->action === 'TemplateManagement')
			{

				if($this->renderCoreTemplate('Footer.phtml', $dist_name)) return true;
				return $this->renderTemplate($footer_url);

			}

			return $footer_url;

		} // public function the_footer()

		/**
		 * If Controller Action === TemplateManagement: Render Navbar Template
		 * If Controller Action !== TemplateManagement: Return Navbar Template URL
		 *
		 * @param String $dist_name
		 *
		 * @return bool|string
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_navbar(String $dist_name = "")
		{

			$navbar_url = '/views/common/Navbar.phtml';

			if($this->action === 'TemplateManagement')
			{

				if($this->renderCoreTemplate('Navbar.phtml', $dist_name)) return true;
				return $this->renderTemplate($navbar_url);

			}

			return $navbar_url;

		} // public function the_navbar()

		/**
		 * Renders the mod header template
		 * Notice: renderCoreTemplate method can not be used since the template engine needs to be called
		 *
		 * @param array $data
		 * @param array $conf
		 *
		 * @return string|bool
		 */
		public function the_mod_header(array $data, array $conf = ["checkbox_disabled" => 0])
		{

			global $mods_mod;

			$mod_header_path = ABSPATH.'/views/admin/dashboard/common/Mod_Header.phtml';
			$isUsedFromAdminComponent = false;

			// If function is called on admin component
			foreach($GLOBALS['core_components'] as $component => $_components)
				foreach($_components as $subComponent)
					if(strpos($_SERVER['REQUEST_URI'], $subComponent) !== false)
						$isUsedFromAdminComponent = true;

			if($this->action !== "TemplateManagement" || !$isUsedFromAdminComponent) return $mod_header_path;
			else
			{

				$requiredData = array("mod_name", "mod_name_lang", "mod_description");
				if(@isSizedArray(array_diff($requiredData, array_keys($data)))) return false;

				$data['mods_mod'] = $mods_mod[$GLOBALS['lang']];
				$data['mod_status'] = (string)$this->mods_statuses[$data['mod_name']];

				$templateContents = $this->load_content($mod_header_path, array_merge($data, $conf));
				$templateContents = $this->execute_template_php($templateContents, true);

				echo $templateContents;

			}

		} // public function the_mod_header()

		/**
		 * Renders a sidebar if the template is available due to the requests origin
		 *
		 * @param String $dist_name
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_sidebar(String $dist_name = "")
		{

			if($this->renderCoreTemplate('Sidebar.phtml', $dist_name)) return true;

		} // public function the_sidebar()

		/**
		 * Renders - if existing - a common template of the project core
		 *
		 * @param String $template_name
		 * @param String $core_package
		 *
		 * @return bool
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function renderCoreTemplate(String $template_name, String $core_package)
		{

			$success = false;
			if(!in_array($core_package, array_keys($GLOBALS['core_components']))) return false;

			$possible_paths = array(
				0   =>  glob(VIEWS_PATH."/$core_package/*/$template_name"),
				1   =>  glob(VIEWS_PATH."/$core_package/*/*/$template_name"),
				2   =>  glob(VIEWS_PATH."/$core_package/*/*/*/$template_name"),
				3   =>  glob(VIEWS_PATH."/*/$core_package/*/$template_name"),
				4   =>  glob(VIEWS_PATH."/*/$core_package/*/*/$template_name"),
				5   =>  glob(VIEWS_PATH."/*/$core_package/*/*/*/$template_name")
			);

			foreach($possible_paths as $k => $v)
			{
				foreach($v as $k => $path)
				{
					if(file_exists((string)$path))
					{

						$this->renderTemplate($path);
						$success = true;

					}
				}
			}

			return $success;

		} // public function renderCoreTemplate()

		/**
		 * If Controller Action === TemplateManagement: Render Flash Template
		 * If Controller Action !== TemplateManagement: Return Flash Template URL
		 *
		 * @param String $dist_name
		 *
		 * @return bool|string
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_flash_area(String $dist_name = "")
		{

			$flash_url = '/views/common/Flash.phtml';

			if($this->action === 'TemplateManagement')
			{

				if($this->renderCoreTemplate('Flash.phtml', $dist_name)) return true;
				return $this->renderTemplate($flash_url);

			}

			return $flash_url;

		} // public function the_flash_area()

		/**
		 * If Controller Action === TemplateManagement: Render Preloader Template
		 * If Controller Action !== TemplateManagement: Return Preloader Template URL
		 *
		 * @return bool|string
		 * @throws DatabaseError
		 * @throws ReflectionException
		 */
		public function the_preloader()
		{

			$preloader_url = '/views/admin/dashboard/common/Preloader.phtml';

			if($this->action === 'TemplateManagement')
			{

				return $this->renderTemplate($preloader_url);

			}

			return $preloader_url;

		} // public function the_preloader()

		/**
		 * Returns the name of the current template file
		 */
		public static function get_current_template()
		{

			$files = get_included_files();

			$ignoredMatches = getFilesInDir('/views/common');

			$templateNameDir = '';

			foreach($files as $index => $file)
			{
				if(strpos($file, '.phtml') !== false)
				{
					foreach($ignoredMatches as $key => $ignoredMatch)
					{
						if(strpos($file, $ignoredMatch) == false && empty($templateNameDir))
							$templateNameDir = $file;

					}
				}
			}

			if(isSizedString($templateNameDir) && gettype($templateNameDir) !== 'int')
			{

				$templateNameArr = deep_explode('\\', $templateNameDir);
				$finalItem = strtolower(str_replace('.phtml', '', end($templateNameArr)));

				return @end(explode("/", $finalItem));

			}

			return null;

		} // function get_current_template()

		/**
		 * Returns the url of the current template file
		 */
		public static function get_current_template_url()
		{

			$files = get_included_files();

			$ignoredMatches = getFilesInDir('/views/common');

			$templateNameDir = '';

			foreach($files as $index => $file)
			{
				if(strpos($file, '.phtml') !== false)
				{
					foreach($ignoredMatches as $key => $ignoredMatch)
					{
						if(strpos($file, $ignoredMatch) == false && empty($templateNameDir))
						{

							$templateNameDir = $file;

						}
					}
				}
			}

			return $templateNameDir ?? false;

		} // function get_current_template()

		/**
		 * Returns the path of a template relative to project root
		 *
		 * @param bool $includePrjRoot
		 *
		 * @return bool|string
		 */
		public static function get_current_template_path($includePrjRoot = false)
		{

			$url = TemplateController::get_current_template_url();

			if($includePrjRoot === false)
				return "/" . substr($url, strpos($url, APP_NAME), strlen($url));
			else
				return substr($url, strpos($url, APP_NAME) + strlen(APP_NAME), strlen($url));

		} // public static function get_current_template_path()

		/**
		 * Get Path to Template File
		 *
		 * @param $file
		 * @param $subdir (Relative to project root)
		 *
		 * @return bool
		 */
		public function get_template_path($file, $subdir = null)
		{

			if(isSizedString($subdir))
				$templateFiles = getFilesInDir(ABSPATH.$subdir);
			else
				$templateFiles = getFilesInDir('/views');

			if(in_array($file, $templateFiles))
				return $subdir ?? VIEWS_PATH . '/' . $templateFiles[array_search($file, $templateFiles)];

			return false;

		} // public function get_template_path($file)

		/**
		 * Sends a mail
		 *
		 * @param String $to
		 * @param String $subject
		 * @param String $message
		 */
		public function sendMail(String $to, String $subject, String $message)
		{

			$headers = "From: " . strip_tags("noreply@freshloop.de") . "\r\n";
			//$headers .= "Reply-To: ". strip_tags("noreply@freshloop.de") . "\r\n";
			//$headers .= "CC: noreply@freshloop.de\r\n";
			//$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

			mail($to, $subject, $message, $headers);

		} // public function sendMail()


	} // class TemplateController()