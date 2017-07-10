<?php

/**
 * Helper to test if tag name corresponds to a void element (HTML5 spec)
 */
function is_void_element ($tag) {
	return in_array($tag, [
		'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
	]);
}

/**
 * Helper function to output a syntax warning
 */
function warn($message, $c)
{
	list($line, $col, $pos) = $c->get_pos();
	echo "\e[93mNotice: \e[96m $message (at line $line offset $col)\e[90m \n";
}

/**
 * Syntax exception class thrown whenever a syntax error is encountered
 */
class syntax_exception extends Exception
{
	function __construct($message, $c)
	{
		list($line, $col, $pos) = $c->get_pos();

		parent::__construct(
			"\e[91mSyntax Error\e[96m at line $line offset $col:\e[90m \n"
				.str_replace("\r", '', $c->get_line())
				.str_repeat(' ', ($col - 1) - substr_count("\t", $c->get_line()))
				.str_repeat("\t", substr_count("\t", $c->get_line()))
				."\e[92m^~~~~~~~ $message\n"
		);

	}

	function __toString()
	{
		return $this->message;
	}
}

/**
 * Simple stack object
 */
class stack
{
	private $items = [];

	public function push($item)
	{
		array_push($this->items, $item);
	}

	public function pop()
	{
		return array_pop($this->items);
	}

	public function count()
	{
		return count($this->items);
	}

	public function empty()
	{
		return empty($this->items);
	}
}