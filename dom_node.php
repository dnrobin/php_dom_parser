<?php

/**
 * Base node class used to build the DOM tree
 */
class dom_node
{
	/**
	 * The root node has no parent (=null)
	 *
	 * @var dom_node
	 */
	public $parent;

	/**
	 * @var dom_node[]
	 */
	public $children = [];

	function __construct(dom_node $parent = null, $children = [])
	{
		$this->parent = $parent;
		$this->children = $children;
	}

	/**
	 * Add a node child to the current node
	 */
	function add(dom_node $child)
	{
		$this->children[] = $child;
	}

	/**
	 * Get children count
	 */
	function countChildren()
	{
		return count($this->children);
	}

	/**
	 * Get first child
	 */
	function getFirstChild()
	{
		return @$this->children[0] ?: null;
	}

	/**
	 * Get last child
	 */
	function getLastChild()
	{
		return @end($this->children) ?: null;
	}

	/**
	 * Look for the first child matching the tag name
	 */
	function find(string $name)
	{
		foreach ($this->children as $child) {
			if ($child->name == $name) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * Get all children matching the tag name
	 */
	function getAll(string $name)
	{
		$matches = [];

		foreach ($this->children as $child) {
			if ($child->name == $name) {
				$matches[] = $child;
			}
		}

		return $matches;
	}

	/**
	 * Get all children (and sub children) matching the tag name
	 */
	function findAll(string $name)
	{
		$matches = [];

		foreach ($this->children as $child) {
			if ($child->name == $name) {
				$matches[] = $child;
			}
		}

		if (empty($matches)) {
			foreach ($this->children as $child) {
				$nodes = $child->findAll($name);
				if (!empty($nodes)) {
					$matches = array_merge($matches, $nodes);
				}
			}
		}

		return $matches;
	}

	/**
	 * Call func on each element of name
	 */
	function foreach(string $name, callable $func)
	{
		$items = $this->findAll($name);

		foreach ($items as $item) {
			$func($item);
		}
	}

	function __toString()
	{
		$str = '';
		foreach ($this->children as $child) {
			$str.= $child;
		}
		return $str;
	}

	function pretty_print()
	{
		$str = '';
		foreach ($this->children as $child) {
			$str.= $child->pretty_print()."\n";
		}
		return $str;
	}
}

/**
 * Base node containing HTML entity
 */
class dom_node_html extends dom_node
{
	/**
	 * @var dom_entity
	 */
	protected $entity;

	function __construct(dom_node $parent, dom_entity $entity, $children = [])
	{
		parent::__construct($parent, $children);
		$this->entity = $entity;
	}

	function __toString()
	{
		return (string)$this->entity;
	}

	function pretty_print($depth = 0)
	{
		return str_repeat("\t", $depth).$this->entity;
	}
}

/**
 * Base node for all elements (tags with/without body)
 */
class dom_node_element extends dom_node_html
{
	const ELEMENT_WITH_BODY = 2;
	const ELEMENT_VOID = 1;

	/**
	 * The tag name of the element
	 *
	 * @var string
	 */
	public $tagName;

	/**
	 * @var int
	 */
	private $type;

	// function setElement($tagName, $attr = [])
	// {
	// 	$this->tagName = $tagName;
	// 	$this->attributes = $attr;
	// }

	function __construct(dom_node $parent, dom_entity $entity, $type = self::ELEMENT_WITH_BODY, $children = [])
	{
		parent::__construct($parent, $entity, $children);
		$this->tagName = $entity->tagName;
		$this->type = $type;
	}

	function __toString()
	{
		$str = (string)$this->entity;
		if ($this->type == self::ELEMENT_WITH_BODY) {
			$str .= implode('', $this->children);
			$str .= '</'.$this->tagName.'>';
		}

		return $str;
	}

	function pretty_print($depth = 0)
	{
		$sp = str_repeat("\t", $depth);

		$str = $sp.$this->entity;
		if ($this->type == self::ELEMENT_WITH_BODY)
		{
			if (count($this->children) == 1 && @$this->children[0]->type != self::ELEMENT_WITH_BODY) {
				$str.= $this->children[0];
			}
			else {
				foreach ($this->children as $child) {
					$str.= "\n".$child->pretty_print($depth + 1);
				}
				$str.= "\n".$sp;
			}
			$str.= '</'.$this->tagName.'>';
		}

		return $str;
	}
}
