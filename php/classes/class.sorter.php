<?php namespace app\lib;

/**
 * Sorting Helper Class : Helper class for sorting collections
 *
 * This class provides static utility functions for sorting items
 * by various criteria.
 *
 * @package  Utilities
 * @author   Roger Soucy <roger.soucy@elusive-concepts.com>
 * @copyright 2015 Elusive Concepts, LLC
 * @license  MIT License
 * @version 1.0
 */
class Sorter
{
	const ASCENDING  =  1;
	const DESCENDING = -1;
	const ASC        = 1;
	const DESC       = -1;
	const NUMERIC    = "NUMERIC";
	const STRING     = "STRING";

	static private $key  = null;
	static private $keys = array();


	/**
	 * Custom sort associative array
	 *
	 * Expects 'key' to be a direct member of the array
	 *
	 * @param array $array Array to sort
	 * @param string $key name of key to sort by
	 * @param int $direction Ascending (Sorter::ASC) or Descending (Sorter::DESC)
	 *                       Default: ASC
	 * @param string $comparison Numeric (Sorter::NUMERIC) or String (Sorter::STRING)
	 *                           Default: NUMERIC
	 * @param bool $reindex Reindex (usort) or maintain keys (uasort) Default: true (reindex)
	 */
	static public function subkey_sort(array &$array, $key, $dir = self::ASC, $comparison = self::NUMERIC, $reindex = TRUE)
	{
		self::$key = $key;

		$func = "";
		switch ($comparison)
		{
			case self::NUMERIC: $func = "numeric_"; break;
			case self::STRING:  $func = "string_";  break;
		}

		$func .= "by_key";

		switch ($dir)
		{
			case self::ASC:  $func .= "_asc";  break;
			case self::DESC: $func .= "_desc"; break;
		}

		if($reindex) { usort($array, array('self', $func)); }
		else         { uasort($array, array('self', $func)); }

		self::$key = null;
	}


	/**
	 * Recursive custom sort associative array
	 *
	 * Expects 'sort_key' and 'recurse_key' to be direct members of the array
	 *
	 * @param array $array Array to sort
	 * @param string $sort_key name of key to sort by
	 * @param string $recurse_key name of key to check for recursion
	 * @param int $direction Ascending (Sorter::ASC) or Descending (Sorter::DESC)
	 *                       Default: ASC
	 * @param string $comparison Numeric (Sorter::NUMERIC) or String (Sorter::STRING)
	 *                           Default: NUMERIC
	 * @param bool $reindex Reindex (usort) or maintain keys (uasort) Default: true (reindex)
	 */
	static public function recursive_subkey_sort(array &$array, $sort_key, $recurse_key, $dir = self::ASC, $comparison = self::NUMERIC, $reindex = TRUE)
	{
		foreach($array as &$a)
		{
			if(isset($a[$recurse_key]) && is_array($a[$recurse_key]))
			{
				self::recursive_subkey_sort($a[$recurse_key], $sort_key, $recurse_key, $dir, $comparison, $reindex);
			}
		}

		self::subkey_sort($array, $sort_key, $dir, $comparison, $reindex);
	}


	/**
	 * USORT associative array of numeric values by given key (ascending)
	 */
	static public function numeric_by_key_asc($a, $b)
	{
		if(isset($a[self::$key], $b[self::$key]))
		{
			if($a[self::$key] == $b[self::$key]) { return 0; }
			return ($a[self::$key] > $b[self::$key]) ? 1 : -1;
		}

		return 0;
	}


	/**
	 * USORT associative array of numeric values by given key (descending)
	 */
	static public function numeric_by_key_desc($a, $b)
	{
		if(isset($a[self::$key], $b[self::$key]))
		{
			if($a[self::$key] == $b[self::$key]) { return 0; }
			return ($a[self::$key] > $b[self::$key]) ? -1 : 1;
		}

		return 0;
	}


	/**
	 * USORT associative array of string values by given key
	 */
	private function string_by_key_asc($a, $b)
	{
		// Sort by name
		if(isset($a[self::$key], $b[self::$key]))
		{
			return strcmp($a[self::$key], $b[self::$key]);
		}

		return 0;
	}


	/**
	 * USORT associative array of string values by given key
	 */
	private function string_by_key_desc($a, $b)
	{
		// Sort by name
		if(isset($a[self::$key], $b[self::$key]))
		{
			return strcmp($a[self::$key], $b[self::$key]) * -1;
		}

		return 0;
	}
}
