<?php

/**
 * Base HTML entity element
 *
 * These classes help wrap entity data captured while parsing
 * in order to preserve data and make it easy to convert back
 * back to a string by implementing __toString().
 */
class dom_entity
{
	const DOCTYPE 	= 1;
	const START_TAG = 2;
	const END_TAG 	= 3;
	const COMMENT 	= 4;
	const PHP 		= 5;
	const RUBY 		= 6;
	const TEXT 		= 7;

	public $type;

	public $html;

	function __construct($type, $html)
	{
		$this->type = $type;
		$this->html = $html;
	}

	function __toString()
	{
		return $this->html;
	}
}

/**
 * Tag attribute: <name> ['=' <value>]
 */
class dom_tag_attribute
{
	public $name;

	public $value;

	function __construct(string $name, $value = false)
	{
		$this->name = $name;
		$this->value = $value;
	}

	function __toString()
	{
		return $this->name . ($this->value === false ? '' : '='.$this->value);
	}
}

/**
 *	Start tag: '<' <name> [<attributes>] '>'
 */
class dom_entity_start_tag extends dom_entity
{
	public $tagName;

	public $attributes;

	function __construct($tagName, $attributes = [])
	{
		$this->tagName = $tagName;
		$this->attributes = $attributes;

		parent::__construct(dom_entity::START_TAG, (string)$this);
	}

	function __toString()
	{
		$str = '<'.$this->tagName;
		if (!empty($this->attributes)) {
			$str .= ' '.implode(' ', $this->attributes);
		}
		$str.= '>';

		return $str;
	}
}

/**
 *	End tag: '</' <name> '>'
 */
class dom_entity_end_tag extends dom_entity
{
	public $tagName;

	function __construct($tagName)
	{
		$this->tagName = $tagName;

		parent::__construct(dom_entity::END_TAG, (string)$this);
	}

	function __toString()
	{
		return '</'.$this->tagName.'>';
	}
}

/**
 *	Doctype decl: '<!DOCTYPE ' <attributes> '>'
 */
class dom_entity_doctype extends dom_entity
{
	public $attributes;

	function __construct($attributes = [])
	{
		$this->attributes = $attributes;

		parent::__construct(dom_entity::DOCTYPE, (string)$this);
	}

	function __toString()
	{
		return '<!DOCTYPE '.implode(' ', $this->attributes).'>';
	}
}

/**
 *	Comment: '<!--' <cdata> '-->'
 */
class dom_entity_comment extends dom_entity
{
	function __construct($cdata)
	{
		parent::__construct(dom_entity::COMMENT, $cdata);
	}

	function __toString()
	{
		return '<!--'.parent::__toString().'-->';
	}
}

/**
 *	PHP: '<?[php]' <cdata> '?>'
 */
class dom_entity_php extends dom_entity
{
	function __construct($cdata)
	{
		parent::__construct(dom_entity::PHP, $cdata);
	}

	function __toString()
	{
		return '<?php'.parent::__toString().'?>';
	}
}

/**
 *	Ruby: '<%=' <cdata> '%>'
 */
class dom_entity_ruby extends dom_entity
{
	function __construct($cdata)
	{
		parent::__construct(dom_entity::RUBY, $cdata);
	}

	function __toString()
	{
		return '<%='.parent::__toString().'%>';
	}
}
