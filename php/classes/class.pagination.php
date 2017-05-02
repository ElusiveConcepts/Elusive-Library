<?php namespace app\lib;

use \elusive\core\Request;

/**
 * Pagination Helper : Helper class for handling and rendering pagination
 *
 * @package  Utilities
 * @author   Roger Soucy <roger.soucy@elusive-concepts.com>
 * @copyright 2014-2015 Elusive Concepts, LLC
 * @license  MIT License
 * @version 1.0
 */
class Pagination
{
	/*==[ DECLARATION: Private Members ]=========================*/

	/** @var \elusive\core\Request|null $req Elusive request object */
	private $req     = NULL;

	/** @var int $_page Current page number */
	private $_page   = 1;

	/** @var int $_offset Current item offset */
	private $_offset = 0;

	/** @var int $_limit Item limit per page */
	private $_limit  = 50;

	/** @var int $_items Total number of items */
	private $_items  = 0;

	/** @var int $_pages Total number of pages */
	private $_pages  = 1;

	/** @var array $_render Pagination rendering defaults */
	private $_render = NULL;


	/**
	 * Constructor
	 * 
	 * Sets the page, item limit, and number of items given provided
	 * values, or sets them via query params pulled from the request.
	 * Then sets the default rendering options.
	 *
	 * @param array $vals starting values for pagination
	 *
	 * @todo Document rendering options for PHPDoc
	 */
	public function __construct($vals)
	{
		$this->req  = Request::get_instance();

		$this->_page   = is_numeric($vals['page'])   ? $vals['page']   : $this->_page;
		$this->_limit  = is_numeric($vals['limit'])  ? $vals['limit']  : $this->_limit;
		$this->_items  = is_numeric($vals['items'])  ? $vals['items']  : $this->_items;

		if(!empty($this->req->vars['limit'])) { $this->_limit = $this->req->vars['limit']; }
		if(!empty($this->req->vars['pg']))    { $this->_page  = $this->req->vars['pg']; }

		// Set the rendering defaults
		$this->_render = array(
			'links'      => 9,            // Number of Page Links to show
			'container'  => 'ul',         // Container HTML Element
			'wrapper'    => 'li',         // Outer HTML Element (wraps inner)
			'element'    => 'a',          // Inner HTML Element
			'active'     => 'span',       // Inner HTML Element for Active Page
			'method'     => 'href',       // Interaction method (href, onclick, data-uri)
			'format'     => '?pg=#',      // Pagination Link Format (specific to method)
			'jump_show'  => TRUE,         // Show Jump link to prev/next hidden set
			'first_show' => TRUE,         // Show First Page Link
			'prev_show'  => TRUE,         // Show Previous Page Link
			'next_show'  => TRUE,         // Show Next Page Link
			'last_show'  => TRUE,         // Show Last Page Link
			'jump'       => '&hellip;',   // Jump Link String/Character/HTML
			'first'      => '&#8810;',    // First Page String/Character/HTML
			'prev'       => '&lt;',       // Previous Page String/Character/HTML
			'next'       => '&gt;',       // Next Page String/Character/HTML
			'last'       => '&#8811;',    // Last Page String/Character/HTML
			'c_class'    => 'pagination', // Classname for Container Element
			'w_class'    => '',           // Classname for Outer Element
			'e_class'    => '',           // Classname for Inner Element
			'a_class'    => 'active',     // Classname for active Inner Element (also receives element class)
			'd_class'    => 'disabled',   // Classname for disabled elements
			'f_class'    => 'first',      // Classname for First Page link
			'p_class'    => 'prev',       // Classname for Prev Page link
			'n_class'    => 'next',       // Classname for Next Page link
			'l_class'    => 'last',       // Classname for Last Page link
			's_class'    => 'text'        // Classname for String rendering
		);

		// Set the user preferred defaults
		if(isset($vals['render'])) { $this->set_opts($vals['render']); }

		$this->_calc_pages();
		$this->_calc_offset();
	}


	/**
	 * Set Rendering Options
	 *
	 * @param array $opts (optional) associative array of options to override
	 */
	public function set_opts($opts = FALSE)
	{
		if(is_array($opts))
		{
			foreach($opts as $opt => $val) { $this->_render[$opt] = $val; }
		}
	}


	/**
	 * Generate pagination links
	 *
	 * @param array $opts (optional) associative array of options to override
	 *
	 * @return string Generated pagination link html
	 */
	public function render_links($opts = FALSE)
	{
		// Short names for clarity
		$r   = $this->_render;
		$pg  = $this->_page;
		$pgs = $this->_pages;

		$str = FALSE;

		// Set the overrided options only for this run
		if(is_array($opts))	  { foreach($opts as $opt => $val) { $r[$opt] = $val; } }
		if(is_numeric($opts)) { $r['links'] = $opts; }
		if(is_string($opts))
		{
			$r['links'] = 0;
			$str = $opts;
		}

		$p_before = $pg-1;
		$p_after  = $pgs - $pg;

		$s = 1;          // Start Page
		$e = $pgs + 1;   // End Page

		if($p_before > $r['links']/2 ) { $s = min(  ceil( $pg - $r['links']/2 ), $e - $r['links'] ); }
		if($p_after  > $r['links']/2 ) { $e = max( floor( $pg + $r['links']/2 ), $s + $r['links'] ); }

		// Check disabled state of first, prev, next, and last links (booleans)
		$f_d = ($pg <= 1 + ceil( $r['links']/2 ) );
		$p_d = ($pg < 2);
		$n_d = ($pg > $pgs-1);
		$l_d = ($pg >= $pgs - floor( $r['links']/2 ) );

		// Render Output
		$html  = "<{$r['container']} class='{$r['c_class']}'>";

		$html .= ($r['first_show']) ? $this->wrap_link( 1, $r['first'], FALSE, $f_d, $r['f_class']) : '';
		$html .= ($r['prev_show'])  ? $this->wrap_link( max($pg-1,1), $r['prev'], FALSE, $p_d, $r['p_class']) : '';

		if($r['jump_show'] && $s > 2) { $html .= $this->wrap_link( $s-1, $r['jump'] ); }

		for($i = $s; $i < $e && $r['links'] > 0; $i++)
		{
			$active = ($i == $pg);
			$html  .= $this->wrap_link($i, $i, $active);
		}

		if($str)
		{
			$str = str_replace("[cStart]", $this->offset() + 1, $str);
			$str = str_replace("[cEnd]", min($this->count(), $this->offset() + $this->limit()), $str);
			$str = str_replace("[cTotal]", $this->count(), $str);
			$str = str_replace("[page]", $this->page(), $str);
			$str = str_replace("[pages]", $this->pages(), $str);

			$html .= $this->wrap_link($pg, $str, TRUE, TRUE, $r['s_class']);
		}

		if($r['jump_show'] && $e < $pgs - 2) { $html .= $this->wrap_link( $e, $r['jump'] ); }

		$html .= ($r['next_show']) ? $this->wrap_link( min($pg+1,$pgs), $r['next'], FALSE, $n_d, $r['n_class']) : '';
		$html .= ($r['last_show']) ? $this->wrap_link( $pgs, $r['last'], FALSE, $l_d, $r['l_class']) : '';

		$html .= "</{$r['container']}>";

		return $html;
	}


	/**
	 * Wrap pagination links
	 * 
	 * Builds a link with appropriate HTML based on the rendering options,
	 * page query, and necessary classes.
	 *
	 * @param int $page Page number
	 * @param string $val Link text
	 * @param boolean $active If this is the current page (default: FALSE)
	 * @param boolean $disables If this link is disabled (default: FALSE)
	 * @param string $classname HTML class names to include (default: "")
	 *
	 * @return string HTML encoded link
	 */
	private function wrap_link($page, $val, $active = FALSE, $disabled = FALSE, $classname = '')
	{
		$r = $this->_render;
		$w = $r['wrapper'];
		$e = ($active) ? $r['active'] : $r['element'];

		$f  = $r['format'];
		if(strpos($r['format'], '?') !== FALSE && !empty($_SERVER['QUERY_STRING']))
		{
			$q = preg_replace('/\??pg=[0-9]*\&?/', '', $_SERVER['QUERY_STRING']);
			$f = empty($q) ? $r['format'] : str_replace('?', '?'.$q.'&', $r['format']);
		}

		$m  = ($disabled || $active) ? '' : $r['method'] .'="'. str_replace("#", $page, $f) . '"';

		$wc = array();
		$ec = array();

		if(!empty($r['w_class'])) { array_push($wc, $r['w_class']); }
		if(!empty($r['e_class'])) { array_push($ec, $r['e_class']); }
		if(!empty($classname)) { array_push($wc, $classname); array_push($ec, $classname); }
		if($disabled) { array_push($wc, $r['d_class']); array_push($ec, $r['d_class']); }
		if($active)   { array_push($wc, $r['a_class']); array_push($ec, $r['a_class']); }

		$wc = !empty($wc) ? ' class="' . implode(' ', $wc) . '"' : '';
		$ec = !empty($ec) ? ' class="' . implode(' ', $ec) . '"' : '';

		$lnk  = '';
		$lnk .= !empty($w) ? "<{$w}{$wc}>" : '';
		$lnk .= "<{$e}{$ec} {$m}>{$val}</{$e}>";
		$lnk .= !empty($w) ? "</{$w}>" : '';

		return $lnk;
	}


	/**
	 * Set or retreive the current limit
	 *
	 * If $limit is numeric, this sets the per page item limit to the given
	 * value.  Otherwise, this returns the current per page item limit.
	 *
	 * @param int $limit (optional) the limit to set
	 *
	 * @return int Per page item limit
	 */
	public function limit($limit = FALSE)
	{
		if(is_numeric($limit))
		{
			$this->_limit = $limit;
			$this->_calc_offset();
			$this->_calc_pages();
		}

		return $this->_limit;
	}


	/**
	 * Set or retreive the total number of items
	 *
	 * If $items is numeric, this sets the total number of items to the given
	 * value.  Otherwise, this returns the current total number of items.
	 *
	 * @param int $items (optional) the number of items
	 *
	 * @return int Total number of items
	 */
	public function count($items = FALSE)
	{
		if(is_numeric($items))
		{
			$this->_items = $items;
			$this->_calc_pages();
		}

		return $this->_items;
	}


	/**
	 * Set or retreive the current page
	 *
	 * If $p is numeric, this sets the current page to the given value.
	 * Otherwise, this returns the current page number.
	 *
	 * @param int $p (optional) the page number to set
	 *
	 * @return int Current page number
	 */
	public function page($p = FALSE)
	{
		if(is_numeric($p))
		{
			if($p < 1)                   { $p =1; }
			if(!$this->_page_exists($p)) { $p = $this->_pages; }

			$this->_page = $p;
			$this->_calc_offset();
		}

		return $this->_page;
	}


	/**
	 * Return the total number of pages
	 *
	 * @return int Total number of pages
	 */
	public function pages()
	{
		$this->_calc_pages();
		return $this->_pages;
	}


	/**
	 * Retreive the current item offset
	 *
	 * @return int Item offset
	 */
	public function offset()
	{
		$this->_calc_offset();
		return $this->_offset;
	}


	/**
	 * Check if a given page number exists
	 *
	 * @param int $p Page number to check
	 *
	 * @return boolean If page exists
	 */
	private function _page_exists($p)
	{
		$this->_calc_pages(); 
		return ($p <= $this->_pages);
	}


	/**
	 * Calculate the total number of pages
	 */
	private function _calc_pages()
	{
		$this->_pages = ceil($this->_items/$this->_limit); 
	}


	/**
	 * Calculate the current item offset 
	 */
	private function _calc_offset() 
	{ 
		$this->_offset = ($this->_page-1) * $this->_limit; 
	}
}
