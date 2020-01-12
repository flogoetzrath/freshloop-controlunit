<?php

	function repl_first_occ($targetPattern, $replacement, $content)
	{

		if(isSizedString($targetPattern) && isSizedString($replacement) && isSizedString($content))
		{

			$from = '/'.preg_quote($targetPattern, '/').'/';
			return preg_replace($from, $replacement, $content, 1);

		}

		return false;

	} // function repl_first_occ($str)


	/**
	 * Creates an excerpt of a given string
	 *
	 * @param String $str
	 * @param Int    $len
	 *
	 * @return String
	 */
	function set_excerpt(String &$str, Int $len = 150)
	{

		$charAtPosition = "";
		$strLen = strlen($str);

		if($strLen < $len) return $str;

		do {
			$len++;
			$charAtPosition = substr($str, $len, 1);
		} while ($len < $strLen && $charAtPosition != " ");

		return substr($str, 0, $len) . '...';

	} // function set_excerpt(&$str, $len)


