<?php namespace app\lib;

use \elusive\debug\Debug;

/**
 * Email Handler : Library class for sending emails
 *
 * @package  Utilities
 * @author   Roger Soucy <roger.soucy@elusive-concepts.com>
 * @copyright 2011-2017 Elusive Concepts, LLC
 * @license  MIT License
 * @version 1.0
 */
class Email
{
	/*==[ DECLARATION: Constants ]=================================*/

	/* Default Sender's Email Address and Name */
	const DEFAULT_EMAIL = "noreply@{$_SERVER['HTTP_HOST']}";
	const DEFAULT_NAME  = $_SERVER['HTTP_HOST'];

	const MIME_VERSION  = "MIME-Version: 1.0";
	const MIME_NOTICE   = "Notice: This is a multi-part message in MIME format. If you are reading this text, your mail reader does not support MIME multipart messages.\n";
	const CONTENT_MIME  = "Content-Type: multipart/alternative;";
	const CONTENT_HTML  = "Content-Type: text/html;";
	const CONTENT_TEXT  = "Content-Type: text/plain;";
	const TRANSFER_ENC  = "Content-transfer-encoding: 7bit";
	const CHARACTER_SET = 'charset="ISO-8859-1"';
	const CRLF          = "\n";


	/*==[ DECLARATION: Private Members ]===========================*/
	/** @var array $to_emails Array of recipient email addresses */
	private $to_emails     = array();

	/** @var array $to_names Array of reciepient names */
	private $to_names      = array();

	/** @var string|null $from_email Sender email address */
	private $from_email    = null;

	/** @var string|null $from_name Sender name */
	private $from_name     = null;

	/** @var string|null $subject Email subject line */
	private $subject       = null;

	/** @var string|null $html_message Email body [HTML version] */
	private $html_message  = null;

	/** @var string|null $text_message Email body [TEXT version] */
	private $text_message  = null;

	/** @var boolean $mp_message If message contains multipart data */
	private $mp_message  = false;

	/** @var string $mp_boundary Multipart message boundary string */
	private $mp_boundary = "";

	/** @var array $template_tags Array of email template variable tags */
	private $template_tags = array();

	/** @var boolean $debug Debug mode */
	private $debug = FALSE;


	/**
	 * Constructor
	 *
	 * @param boolean $debug Debug mode
	 *
	 * @todo Allow setting of options on instantiation
	 */
	public function __construct($debug=FALSE)
	{
		$this->debug = $debug;

		$this->from_email = self::DEFAULT_EMAIL;
		$this->from_name  = self::DEFAULT_NAME;

		$this->mp_boundary = date("YmdHis") . ".MimeBoundarY";
	}


	/**
	 * Add email recipient addresses
	 *
	 * Adds one or more addresses and names to the recipient list.
	 *
	 * @param string|array $emails Array or comme delimited string of recipient addresses
	 * @param string|array $names Array or comma delimited string or recipient names
	 *
	 * @return boolean TRUE on success, FALSE on error
	 */
	public function add_address($emails, $names="")
	{
		if(is_string($emails))
		{
			$emails = explode(",", $emails);

			if(is_string($names) && $names != "")
			{
				$names = explode(",", $names);
			}
		}

		if(!is_array($emails) || ($names != "" && !is_array($names)))
		{
			trigger_error("Unable to add recipient addresses", E_USER_WARNING);
			return false;
		}

		if($names != "")
		{
			if(count($names) != count($emails))
			{
				trigger_error("Unable to add recipient addresses, count mismatch", E_USER_WARNING);
				return false;
			}

			for($i = 0; $i < count($emails); $i++)
			{
				$email = $this->clean_email($emails[$i]);
				$name  = $this->clean_name($names[$i]);

				if($email && $name)
				{
					$this->to_emails[] = $email;
					$this->to_names[] = $name;
				}
			}
		}
		else
		{
			for($i = 0; $i < count($emails); $i++)
			{
				$email = $this->clean_email($emails[$i]);

				if($email) { $this->to_emails[] = $email; }
			}
		}

		return true;
	}


	/**
	 * Set email sender address
	 * Sets the address and (optionally) the name for the email sender.
	 *
	 * @param string $emails Sender email address (defaults to noreply at current domain)
	 * @param string $names Sender name (defaults to "")
	 *
	 * @return boolean TRUE on success, FALSE on error
	 */
	public function set_sender($email=self::DEFAULT_EMAIL, $name=self::DEFAULT_NAME)
	{
		if($email != self::DEFAULT_EMAIL)
		{
			$email = $this->clean_email($email);

			if($email) { $this->from_email = $email; }
		}

		if($name != self::DEFAULT_NAME)
		{
			$name = $this->clean_name($name);

			if($name) { $this->from_name = $name; }
		}

		return true;
	}


	/**
	 * Set the email subject line
	 *
	 * @param string $subject Subject line
	 *
	 * @return boolean TRUE on success, FALSE on error
	 */
	public function set_subject($subject)
	{
		$subject = urldecode($subject);
		$subject = trim($subject);
		$subject = preg_replace("/[\n\r]|0x0A|0x0D/", "", $subject);

		if(strlen($subject) > 255)
		{
			trigger_error("Email subject is too long - truncating", E_USER_WARNING);
			$subject = substr($subject, 0, 255);
		}

		$this->subject = $subject;

		return true;
	}


	/**
	 * Set the email message body
	 *
	 * Sets the email message body in either HTML or TEXT mode.
	 * Optionally can accept a file path as the message.
	 *
	 * @param string $message Email message body or file path
	 * @param string $type Message type ("text" or "html") [default: "text"]
	 * @param boolean $is_file If $message contains file path instead of text or html
	 *
	 * @return boolean TRUE on success, FALSE on error
	 */
	public function set_body($message, $type="text", $is_file=FALSE)
	{
		if($is_file)
		{
			if(is_readable($message))
			{
				$message = file_get_contents($message);
			}
			else
			{
				trigger_error("Unable to open file: {$message}", E_USER_WARNING);
				return false;
			}
		}

		$type = strtolower($type);

		switch($type)
		{
			case "html":
				$this->html_message = $message;
				$this->mp_message   = true;
				break;

			case "text":
			default:
				$this->text_message = $message;
				break;
		}

		return true;
	}


	/**
	 * Add a template tag
	 *
	 * Adds a template tag and value to the list of template tags for parsing.
	 * Template tags use a regex to replace the tag with the value in the email
	 * message body. This is useful for inserting email customizations and
	 * dynamic content, such as the recipient name, into premade email templates.
	 *
	 * @param string $tag Template tag name
	 * @param string $value Template tag value
	 */
	public function add_template_tag($tag, $value)
	{
		$this->template_tags[] = array($tag, $value);
	}


	/**
	 * Apply template tags to email templates
	 *
	 * Parses the supplied content and uses regex to replace any occurrances
	 * of template tags with their given values.
	 *
	 * @param string $content Content to apply template tags to
	 *
	 * @return string Updated content
	 */
	public function apply_templates($content)
	{
		for($i = 0; $i < count($this->template_tags); $i++)
		{
			$pattern = "/" . $this->template_tags[$i][0] . "/";
			$replace = $this->template_tags[$i][1];

			$content = preg_replace($pattern, $replace, $content);
		}

		return $content;
	}


	/**
	 * Send the email
	 *
	 * Checks for required information, then applies template tags to the
	 * subject and message body (both HTML and TEXT if present), then assembles
	 * attempts and sends the email.
	 *
	 * @return boolean TRUE on success, FALSE on error
	 */
	public function send()
	{
		if(!isset($this->to_emails[0], $this->subject, $this->text_message))
		{
			trigger_error("Could not send email (missing required data)", E_USER_WARNING);
			return false;
		}

		for($i = 0; $i < count($this->to_emails); $i++)
		{
			$to      = "";
			$subject = "";
			$message = "";
			$headers = "";

			if(isset($this->to_names[$i]))
			{
				$to .= $this->to_names[$i] . " <" . $this->to_emails[$i] . ">";
			}
			else
			{
				$to .= "<" . $this->to_emails[$i] . ">";
			}

			$subject .= $this->apply_templates($this->subject);

			$headers .= "From: " . $this->from_name . " <" . $this->from_email . ">" . self::CRLF;

			if($this->mp_message && isset($this->html_message))
			{
				$headers .= self::MIME_VERSION . self::CRLF;
				$headers .= self::CONTENT_MIME . " ";
				$headers .= 'boundary="' . $this->mp_boundary . '"';

				$message .= self::MIME_NOTICE . self::CRLF;
				$message .= "--" . $this->mp_boundary . self::CRLF;
				$message .= self::CONTENT_TEXT . " ";
				$message .= self::CHARACTER_SET . self::CRLF;
				$message .= self::TRANSFER_ENC . self::CRLF;
				$message .= $this->apply_templates($this->text_message) . self::CRLF;
				$message .= "--" . $this->mp_boundary . self::CRLF;
				$message .= self::CONTENT_HTML . " ";
				$message .= self::CHARACTER_SET . self::CRLF;
				$message .= self::TRANSFER_ENC . self::CRLF;
				$message .= $this->apply_templates($this->html_message) . self::CRLF;
				$message .= "--" . $this->mp_boundary . "--" . self::CRLF;
			}
			else
			{
				$message .= self::CONTENT_TEXT . self::CRLF;
				$message .= self::CHARACTER_SET . self::CRLF;
				$message .= self::TRANSFER_ENC . self::CRLF;
				$message .= $this->apply_templates($this->text_message) . self::CRLF;
			}

			if(!mail($to, $subject, $message, $headers))
			{
				trigger_error("Could not send email (server error)", E_USER_WARNING);
				return false;
			}
		}

		return true;
	}

	/**
	 * Clean email addresses
	 *
	 * Validates and cleans email addresses for proper format.
	 *
	 * @param string $email Email address
	 *
	 * @return string|boolean Cleaned email address or FALSE if invalid
	 *
	 * @todo Use PHP filtering to simplify and clean up
	 */
	public function clean_email($email)
	{
		$email = urldecode($email);
		$email = trim($email);

		if(strpos($email, "@") != strrpos($email, "@") || strpos($email, "@") == -1)
		{
			trigger_error("Invalid email address", E_USER_NOTICE);
			return false;
		}

		$at_pos  = strpos($email, "@");
		$dot_pos = strrpos($email, ".");

		if(preg_match("/0x0D|0x0A|[^a-zA-Z0-9_\-\.@]/", $email))
		{
			trigger_error("Invalid email address", E_USER_NOTICE);
			return false;
		}

		if($at_pos+2 > $dot_pos || $at_pos < 2 || $dot_pos > strlen($email)-2)
		{
			trigger_error("Invalid email address", E_USER_NOTICE);
			return false;
		}

		return $email;
	}


	/**
	 * Validates names for invalid characters
	 *
	 * @param string $name Name to validate
	 *
	 * @return string|boolean Cleaned name or FALSE on invalid name
	 *
	 * @todo Make this actually clean names instead of just validating them.
	 */
	public function clean_name($name)
	{
		$name = urldecode($name);
		$name = trim($name);

		if(preg_match("/0x0D|0x0A|[^a-zA-Z\s\-]/", $name))
		{
			trigger_error("Invalid recipient name", E_USER_NOTICE);
			return false;
		}

		return $name;
	}
}

