<?php
	/**
	 * User: Flo
	 * Date: 08.07.2018
	 * Time: 12:17
	 */

	/**
	 * @param String $val
	 *
	 * @return bool
	 */
	function isValidEmail(String &$val)
	{

		if(isSizedString($val) && strpos($val, '@') !== false && filter_var($val, FILTER_VALIDATE_EMAIL)) return true;
		else return false;

	} // function isValidEmail($val)


	/**
	 * @param $val
	 *
	 * @return array|string
	 */
	function xssproof($val)
	{

		if(is_array($val))
			foreach($val as $k => $v)
				$val[$k] = xssproof($v);
		else
			$val = strip_tags(@trim($val));

		return $val;

	} // function xssproof($val)


	/**
	 * @param $val
	 *
	 * @return array
	 */
	function transformToUtf8($val)
	{

		if(is_array($val))
		{
			foreach($val as $k => $v)
			{
				$val[$k] = transformToUtf8($v);
			}
			return $val;
		}
		else
		{
			return transformToUtf8($val);
		}

	} // transformToUtf8($val)


	/**
	 * @param String $str
	 *
	 * @return String
	 */
	function htmlspc(String $str)
	{

		if(isset($str) && gettype($str) === 'string')
		{
			return htmlspecialchars($str);
		}
		else return '';

	} // function htmlspc($str)


	/**
	 * Format Date to DB-Ready Date
	 *
	 * @param $date
	 *
	 * @return false|string
	 */
	function dateToDBDate($date)
	{

		if (is_numeric($date))
		{
			return date('Y-m-d', $date);
		}
		else if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $date))
		{
			$arDate = explode('.', $date);

			return $arDate[2].'-'.$arDate[1].'-'.$arDate[0];
		}
		else if (strtotime($date) > 0)
		{
			return date('Y-m-d', strtotime($date));
		}

		return '0000-00-00';

	} // function dateToDBDate($date)


	/**
	 * Format DB Date to Output-Ready Date
	 *
	 * @param      $date
	 * @param bool $dateOnly
	 *
	 * @return false|string
	 */
	function DBDatetoDate($date, $dateOnly = true)
	{

		if ($date == '0000-00-00') return '';

		if ($dateOnly && strtotime($date) != 0) return date('d.m.Y', strtotime($date));
		else if (strtotime($date) != 0) return date('d.m.Y H:i:s', strtotime($date));

	} // function DBDatetoDate($date, $dateOnly)


	function getDateTimeDiff($datetime1, $datetime2, $getTotalMins = true)
	{

		if(
			!is_object($datetime1)
			||
			is_object($datetime1) &&
			get_class($datetime1) !== "DateTime"
		) $datetime1 = new DateTime($datetime1);

		if(
			!is_object($datetime2)
			||
			is_object($datetime2) &&
			get_class($datetime2) !== "DateTime"
		) $datetime2 = new DateTime($datetime2);

		$since_datetime1 = $datetime1->diff($datetime2);

		if($getTotalMins)
		{

			$minutes = $since_datetime1->days * 24 * 60;
			$minutes += $since_datetime1->h * 60;
			$minutes += $since_datetime1->i;

			return $minutes;

		}
		return $since_datetime1;

	} // function getDateTimeDiff()


	/**
	 * @param $del
	 * @param $val
	 *
	 * @return array
	 */
	function deep_explode($del, $val)
	{

		if(is_array($val) && !arrayHasSizedElements($val)) $val = "";
		$arVal = explode($del, $val);

		foreach($arVal as $k => $v)
		{
			if(trim($v) == "") unset($arVal[$k]);
		}

		return $arVal;

	} // funtion deep_explode($del, $val)


	/**
	 * Explode with fallback if $del is not present
	 *
	 * @param $del
	 * @param $str
	 *
	 * @return array
	 */
	function save_explode($del, $str)
	{

		return strpos($str, $del) !== false
			? explode($del, $str)
			: array((string)$str);

	} // function save_explode($del, $str)


	/**
	 * @param $del
	 * @param $ar
	 *
	 * @return string
	 */
	function deep_implode($del, $ar)
	{

		foreach($ar as $k => $v)
		{
			if(trim($v) == '') unset($ar[$k]);
		}

		return implode($del, $ar);

	} // function deep_implode($del, $ar)


	/**
	 * Removes Empty Elements in $val Array
	 *
	 * @param        $val
	 * @param string $clearEntry
	 *
	 * @return array|string
	 */
	function deep_trim($val, $clearEntry = '')
	{

		if(!is_array($clearEntry)) $testClearEntry = array($clearEntry);
		else $testClearEntry = $clearEntry;

		if(is_array($val))
		{
			foreach($val as $k => $v)
			{
				$val[$k] = deep_trim($v, $clearEntry);
				if($clearEntry !== false && in_array($val[$k], $testClearEntry)) unset($val[$k]);
			}
		}
		else
		{
			$val = trim($val);
		}

		return $val;

	} // function deep_trim($val, $clearEntry)


	/**
	 * Destructures an array if it only contains another array of random quantity
	 *
	 * @param array $arr
	 *
	 * @return array
	 */
	function open_array(array &$arr)
	{

		$i = 0;
		$a = false;

		foreach($arr as $k => $v)
		{

			$i ++;
			if(!isSizedArray($v)) $a = true;
			if($i === count($arr))
				if($a) return $arr;
				else return open_array($arr[0]);

		}

	} // function open_array(&$arr)


	/**
	 * Return Formatted File Size
	 *
	 * @param $filename
	 *
	 * @return string
	 */
	function formatSize($filename)
	{

		if (is_string($filename) && !is_numeric($filename)) $size = filesize($filename);

		$mod = 1024;
		$units = explode(' ', 'B KB MB GB TB PB');

		for ($i = 0; $size > $mod; $i++)
		{
			$size /= $mod;
		}

		return round($size, 2).' '.$units[$i];

	} // function formatSize($filename)


	function debug($val)
	{

		if(is_array($val) || is_object($val))
		{

			echo '<pre class="posMessage debug">';
			print_r($val);
			echo '</pre>';

		} else {

			echo '<pre class="negMessage debug">'.var_dump($val).'</pre>';

		}

	} // function debug($val)


	function activate_debug_mode()
	{

		error_reporting(E_ALL | E_WARNING | E_NOTICE);
		ini_set('display_errors', 1);

		flush();

	} // activate_debug_mode()


	function isset_true(&$val)
	{

		return (isset($val) && $val === true);

	} // function isset_true($val)


	function func_get_args_name(String $funcName, String $className = "")
	{

		$result = array();

		if($className)
			$f = new ReflectionMethod($className, $funcName);
		else
			$f = new ReflectionFunction($funcName);

		foreach ($f->getParameters() as $param) $result[] = $param->name;

		return $result;

	} // function func_get_args_name()


	function isSizedDouble(&$val)
	{

		if(!isset($val)) return false;

		$dVal = doubleval($val);

		if($dVal <= 0) return false;

		return true;

	} // function isSizedDouble($val)


	function isSized(&$val)
	{

		if(isset($val) && $val > 0) return true;

		return false;

	} // function isSized($val)


	function isSizedInt(&$int, $val = false)
	{

		$isset = true;

		if(!isset($int) || !is_numeric($int)) $isset = false;
		else if($int <= 0) $isset = false;

		if($isset === true && $val !== false)
		{
			if($val == $int) return true;
			else return false;
		}

		return $isset;

	} // function isSizedInt($int, $val)


	function isSizedArray(&$array, $size = 1)
	{

		if(isset($array) && is_array($array) && count($array) >= $size)
		{
			return true;
		}

		return false;

	} // function isSizedArray($array, $val)


	function isSizedString(&$val)
	{

		if(!isset($val)) return false;
		if(is_int($val)) return false;
		if(gettype($val) !== 'string') return false;
		if(strlen($val) <= 0) return false;

		return true;

	} // function isSizedString($val)


	function getInt(&$val, $def = 0)
	{

		if(isset($val) && !is_numeric($val)) return $def;

		return intval($val);

	} // function isSizedArray($array, $val)


	function getStr(&$val, $def = '')
	{

		if(!isset($val) || !isSizedString($val)) return strval($def);

		return $val;

	} // function getStr($array, $val)


	/**
	 * @param   int $month
	 * @param   int $year
	 *
	 * @return false|string
	 */
	function countDaysOfMonth($month, $year)
	{

		$time = mktime(0, 0, 0, $month, 1, $year);
		return date('t', $time);

	} // function countDaysOfMonth($month, $year)


	/**
	 * Like scandir, but removes dots and form common components
	 *
	 * @param $path (relative to project root)
	 *
	 * @return bool|string
	 */
	function getFilesInDir($path)
	{

		if(!isSizedString($path)) return false;

		$path = ABSPATH . $path;

		$containedFiles = scandir($path);

		unset($containedFiles[array_search('Form', $containedFiles)]);

		return array_slice(array_filter($containedFiles), 2);

	} // function getFilesInDir($path)


	/**
	 * Encodes the supplied data to Base64
	 *
	 * @param $data
	 *
	 * @return bool|string
	 */
	function encode(String $data)
	{

		return base64_encode($data) ?? false;

	} // function encode($data)


	/**
	 * Decodes the supplied data from Base64
	 *
	 * @param $data
	 *
	 * @return bool|string
	 */
	function decode(String $data)
	{

		return base64_decode($data) ?? false;

	} // function decode($data)


	/**
	 * Url safe version of Base64 encoding method
	 *
	 * @param String $data
	 *
	 * @return string
	 */
	function encodeUrlSafe(String $data)
	{

		$encoded = encode($data);

		return strtr(
			$encoded,
			'+/=',
			'-_~'
		);

	} // function encodeUrlSafe($data)


	/**
	 * @param $data
	 *
	 * @return string
	 */
	function encodeUrlSafeWithoutPadding($data)
	{

		$encoded = encode($data);

		$encoded = rtrim(
			$encoded,
			substr('+/=', -1)
		);

		return strtr(
			$encoded,
			substr('+/=', 0, -1),
			substr('-_~', 0, -1)
		);

	} // function encodeUrlSafeWithoutPadding($data)


	/**
	 * Url save version of Base64 decoding method
	 *
	 * @param String $data
	 *
	 * @return bool|string
	 */
	function decodeUrlSafe(String $data)
	{

		$data = strtr(
			$data,
			'-_~',
			'+/='
		);

		return decode($data);

	} // function decodeUrlSafe($data)


	/**
	 * @param string $md5
	 *
	 * @return false|int
	 */
	function isValidMd5($md5 = '')
	{

		return preg_match('/^[a-f0-9]{32}$/', $md5);

	} // function isValidMd5


	/**
	 * Creates a random string
	 *
	 * @param int $maxLength
	 *
	 * @return mixed
	 */
	function createRandomString($maxLength = 24)
	{

		$bytes = floor((int) $maxLength / 4) * 3;

		$data = openssl_random_pseudo_bytes($bytes);

		return encodeUrlSafe($data);

	} // function createRandomString($maxLength)

	/**
	 *
	 * @param      $needle
	 * @param      $haystack
	 * @param bool $countOccurences
	 *
	 * @return bool|int
	 */
	function inMultDimArr($needle, &$haystack, $countOccurences = false)
	{

		$occurences = 0;

		if(strpos($needle, "/") !== false)
			$needle = str_replace("/", "\\", $needle);

		foreach($haystack as $k => $v)
		{

			if($k === $needle || $v === $needle) $occurences ++;

		}

		if($countOccurences) return $occurences;
		if($occurences > 0) return true;
		else return false;

	} // function valInMultDimArr($haystack, $needle)

	/**
	 * @param $pair
	 * @param $haystack
	 *
	 * @return bool
	 */
	function keyValuePairExists($pair, &$haystack)
	{

		$occurences = 0;

		foreach($haystack as $k => $v)
		{

			if($k === $pair[0] && (int)$v === (int)$pair[1]) $occurences ++;

		}

		if($occurences > 0) return true;
		else return false;

	} // function keyValuePairExists()

	/**
	 * strpos meets in_array
	 *
	 * @param $needle
	 * @param $haystack
	 *
	 * @return array|bool
	 */
	function str_in_array($needle, $haystack)
	{

		foreach($haystack as $k => $v)
		{
			if(strpos($v, $needle) !== false)
			{

				return array(
					"strpos" => array(
						"key" => $k,
						"pos" => strpos($v, $needle)
					),
					"target_full_str" => $v
				);

			}
		}

		return false;

	} // function str_in_array()

	function arrayHasSizedElements(array &$arr)
	{

		foreach($arr as $el)
			if(isSized($el))
				return true;

		return false;

	} // function arrayHasSizedElements()

	/**
	 * Alias for inMultDimArr suited for returning the number of key occurences
	 *
	 * @param array  $arr
	 * @param String $needle
	 *
	 * @return bool|int
	 */
	function array_count_keyes(array $arr, String $needle)
	{

		return inMultDimArr($needle, $arr, true);

	} // function array_count_keyes()

	/**
	 * Checks, whether app is running on localhost
	 *
	 * @param array $whitelist
	 *
	 * @return bool
	 */
	function isLocalhost($whitelist = array('127.0.0.1', '::1'))
	{

		if(php_sapi_name() === "cli") return false;

		return (in_array($_SERVER['REMOTE_ADDR'], $whitelist));

	} // function isLocalhost()

	/**
	 * Prepares an array for saving to an ini file
	 *
	 * @param array $a
	 * @param array $parent
	 *
	 * @return string
	 */
	function arr2ini(array $a, array $parent = array())
	{

		$out = '';
		foreach ($a as $k => $v)
		{
			if (is_array($v))
			{

				# Subsection case
				// Merge all the sections into one array...
				$sec = array_merge((array) $parent, (array) $k);
				// Add section information to the output
				$out .= '[' . join('.', $sec) . ']' . PHP_EOL;
				// Recursively traverse deeper
				$out .= arr2ini($v, $sec);

			}
			else
			{

				// Plain key->value case
				if(strpos($k, "pw") !== false) $v = '"'.$v.'"';
				$out .= "$k=$v" . PHP_EOL;

			}
		}

		return $out;

	} // function arr2ini()

	/**
	 * Checks if a given string is json encoded
	 *
	 * @param $str
	 *
	 * @return bool
	 */
	function isJson($str)
	{

		json_decode($str);
		return (json_last_error() == JSON_ERROR_NONE);

	} // function isJson()

	/**
	 * Decodes a python list to a generic php array
	 *
	 * @param $listStr
	 *
	 * @return array
	 */
	function decodePythonList($listStr): array
	{

		if(!isSizedString($listStr)) return array();

		$listStr = str_replace(array("u'", "[", "]", "{", "}", "'"), array("'", ""), $listStr);
		$listStrToPHPArray = str_getcsv($listStr, ",", "'");

		foreach($listStrToPHPArray as $k => $v)
		{
			if(strpos($v, ":") !== -1)
			{

				$valueParts = explode(": ", $v);
				$listStrToPHPArray[trim($k)] = trim($valueParts[1]);
				$listStrToPHPArray[trim($valueParts[0])] = trim($valueParts[1]);

			}
		}

		return $listStrToPHPArray;

	} // function decodePythonList()

	/**
	 * Workaround to make all functions accessable in heredoc syntax
	 *
	 * @param $fn
	 *
	 * @return mixed
	 */
	$execFn = function($fn)
	{

		return $fn;

	}; // inline function execFn()

	/**
	 * Version of $execFn that catches echo'd content
	 *
	 * @param $fn
	 *
	 * @return false|string
	 */
	$execFn_ob = function($fn)
	{

		ob_start();
		$fn;
		$returnVal = ob_get_contents();
		ob_end_clean();

		return $returnVal;

	}; // inline function execFn_ob()