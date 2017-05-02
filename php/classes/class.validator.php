<?php namespace app\lib;

/**
 * Data Validation Class : Helper class for validating data
 *
 * @package  Utilities
 * @author   Roger Soucy <roger.soucy@elusive-concepts.com>
 * @copyright 2010-2015 Elusive Concepts, LLC
 * @license  MIT License
 * @version 1.0
 */
class Validator
{
	/*==[ VALIDATION REGULAR EXPRESSIONS ]=========================*/
	const REGEX_REQUIRED     = '/^(\S\s?)+$/';
	const REGEX_OPENTEXT     = '/^(\S\s?)+$/';
	const REGEX_ALPHA        = '/^[A-Za-z]+$/';
	const REGEX_ALPHAPLUS    = '/^[A-Za-z][A-Za-z\/\s\.\,_-]*$/';
	const REGEX_ALPHANUMERIC = '/^\w[\w\s\.\,-]*$/';
	const REGEX_NUMERIC      = '/^[+-]?\d+(\,\d{3})*\.?\d*\%?$/';
	const REGEX_ZIPCODE      = '/^(?!0{5})(\d{5})(?!-?0{4})(-?\d{4})?$/';
	const REGEX_DATE         = '/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19|20)\d\d$/';
	const REGEX_PHONE        = '/^(1[-\.\s]?)?\(?\d{3}\)?([-\.\s])?\d{3}([-\.\s])?\d{4}$/';
	const REGEX_EMAIL        = '/^\w[\w\.-]+@\w[\w\.-]+\.[a-zA-Z]{2,}$/';
	const REGEX_MULTIEMAIL   = '/^(\w[\w\.-]+@\w[\w\.-]+\.[a-zA-Z]{2,}(\,\s?)?)+$/';
	const REGEX_IP           = '/^((\d{1,2}|1\d\d|2[0-4]\d|25[0-5])\.){3}(\d{1,2}|1\d\d|2[0-4]\d|25[0-5])$/';
	const REGEX_DEA          = '/^[ABCDEFGHJKLMNPRSTUX][A-Z]\d{7}$/i';

	private $rules = array();
	private $data  = array();

	//private $DataAccess = NULL;

	/*==[ CONSTRUCTOR ]============================================*/
	public function __construct()
	{
		$this->rules = parse_ini_file(CONFIG_PATH . '/validation.ini', TRUE);
		if($this->rules === FALSE) { die("Validation configuration error"); }

		//$this->DataAccess = new AccountDataAccess();
	}


	/*==[ PROCESS DATA FUNCTION ]==================================*/
	// Cleans, then Checks all items in array against validation rules
	public function process(&$data)
	{
		$errors = array();

		$this->data = &$data;

		foreach($this->data as $key => &$value)
		{
			if(is_array($value))
			{
				$result = $this->process($value);
				if(is_array($result) && count($result) > 0)
				{
					$errors[$key] = $this->process($value);
				}
			}
			else
			{
				$value = $this->sanitize($value);

				if(isset($this->rules[$key]))
				{
					for($i=0; $i < count($this->rules[$key]['validation_rules']); $i++)
					{
						if(empty($value) && !in_array("required", $this->rules[$key]['validation_rules']))
						{
							break;
						}

						if(!$this->validate($value, $this->rules[$key]['validation_rules'][$i]))
						{
							$errors[$key] = $this->rules[$key]['validation_errors'][$i];
							break;
						}
					}
				}
			}
		}

		return $errors;
	}

	/*==[ DATA SANITIZATION FUNCTION ]=============================*/
	public function sanitize($str)
	{
		if($str != "")
		{
			$str = trim(urldecode($str));
			$str = filter_var($str, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
		}
		return htmlentities($str);
	}

	/*==[ VALIDATION FUNCTION ]====================================*/
	public function validate($str, $type)
	{
		if(strpos($type, '|') !== FALSE)
		{
			list($type, $special) = explode('|', $type);
		}

		$REGEX = defined('self::REGEX_' . strtoupper($type)) ? constant('self::REGEX_' . strtoupper($type)) : NULL;

		switch(strtoupper($type))
		{
			case "COMPARE":
				if(!isset($this->data[$special])) { return false; }
				return ($str === $this->data[$special]) ? true : false;
				break;

			case "UNIQUE":
				//if(!isset($this->data[$special])) { return false; }
				//$user_id = isset($this->data['user_id']) ? $this->data['user_id'] : 0;
				//$result  = $this->DataAccess->check_unique($special, $str, $user_id);
				//return $result;
				return TRUE;
				break;

			case "DEA":
				if($REGEX == NULL) { return false; }
				if(!preg_match($REGEX, $str)) { return false; }
				$d = preg_split('//', $str, -1, PREG_SPLIT_NO_EMPTY);
				// Checksum Algorithm
				$checksum = ((($d[2]+$d[4]+$d[6])+(($d[3]+$d[5]+$d[7])*2))%10);
				return ($checksum == $d[8]) ? true : false;
				break;

			default:
				if($REGEX == NULL) { return false; }
				return (preg_match($REGEX, $str)) ? true : false;
				break;
		}
	}
}

?>
