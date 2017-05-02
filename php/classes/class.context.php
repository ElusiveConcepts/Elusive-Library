<?php namespace app\lib;

/**
 * Context Helper Class : Provides helpers for contextual dynamic data
 *
 * @package  Utilities
 * @author   Roger Soucy <roger.soucy@elusive-concepts.com>
 * @copyright 2015 Elusive Concepts, LLC
 * @license  MIT License
 * @version 1.0
 */
final class Context
{

	/** @var mixed Current Context */
	static private $_context = FALSE;

	/** @var mixed Context Data */
	static private $_data = FALSE;

	/** @var boolean Context Loaded */
	static private $_loaded = FALSE;

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Set Context
	 *
	 * @param string $context context name
	 * @param string $data context value
	 */
	static public function set($context = FALSE, $data = FALSE)
	{
		self::load();

		self::$_context = $context;
		self::$_data    = $data;

		$_SESSION['context']      = self::$_context;
		$_SESSION['context_data'] = self::$_data;
	}


	/**
	 * Get Context
	 *
	 * @return string
	 */
	static public function get()
	{
		self::load();

		return array(
			'context' => self::$_context,
			'data'    => self::$_data
		);
	}


	/**
	 * Load Context
	 */
	static private function load()
	{
		if(self::$_loaded) { return; }

		if(session_status() == PHP_SESSION_NONE)
		{
			session_start();
		}

		self::$_context = isset($_SESSION['context'])      ? $_SESSION['context']      : FALSE;
		self::$_data    = isset($_SESSION['context_data']) ? $_SESSION['context_data'] : FALSE;

		self::$_loaded = TRUE;
	}
}

