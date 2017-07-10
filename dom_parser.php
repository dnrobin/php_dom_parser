<?php
/**
 * Simple DOM parser capable of representing PHP and Ruby nodes in its tree.
 * 
 * @author Daniel Robin <daniel.robin.1@ulaval.ca>
 */

define('DIR', dirname(__FILE__).'/');

require_once DIR.'dom_stream.php';
require_once DIR.'dom_entity.php';
require_once DIR.'dom_node.php';
require_once DIR.'dom_util.php';

// ---------------------------------------------------------------------------------
// Lexical parsers (parsing terminal symbols)
// ---------------------------------------------------------------------------------

/**
 * whitespace: \s*
 */
function parse_whitespace (dom_stream $c)
{
	$out = '';
	while (ctype_space($c->c) && !$c->eof()) {
		$out .= $c;
		$c->next();
	}

	return $out;
}

/**
 * identifier: [a-z0-9][a-z0-9_-]*
 */
function parse_identifier (dom_stream $c)
{
	if (!ctype_alnum($c->c)) {
		throw new syntax_exception("Expected identifier name", $c);
	}

	$out = '';
	while ( (ctype_alnum($c->c) || $c == '_' || $c == '-') && !$c->eof() ) {
		$out .= $c;
		$c->next();
	}

	return $out;
}

/**
 * tag_name: [a-z][a-z0-9]*
 */
function parse_tag_name (dom_stream $c)
{
	if (!ctype_alpha($c->c)) {
		throw new syntax_exception("Tag name must begin with an alpha character", $c);
	}

	$out = '';
	while (ctype_alnum($c->c) && !$c->eof()) {
		$out .= $c;
		$c->next();
	} 

	return strtolower($out);
}

/**
 * string: " (.*?) " | ' (.*?) '
 */
function parse_string (dom_stream $c)
{
	if ($c != '"' && $c != '\'') {
		throw new syntax_exception("Expected string literal", $c);
	}

	$q = $c->c;
	$c->next();	// skip starting quote

	$out = '';
	while ($c != $q && !$c->eof()) {
		$out .= $c;
		$c->next();
	}

	$c->next();	// skip ending quote

	return $out;
}

/**
 * text: [^<]*
 */
function parse_text (dom_stream $c)
{
	$out = '';
	while ($c != '<' && !$c->eof()) {
		$out .= $c;
		$c->next();
	}

	return $out;
}

/**
 * cdata: (.*?) $ed
 */
function parse_cdata (dom_stream $c, string $ed)
{
	$out = '';
	while (!$c->eof()) {

		if ($c == $ed[0]) {
			if ($c->test($ed)) {
				return $out;
			}
		}

		$out .= $c->next();
	}

	throw new syntax_exception("EOF found while parsing CDATA, ending delimeter '$ed' not found", $c);

	return $out;
}

/**
 * literal: $symbol
 */
function parse_literal (dom_stream $c, string $symbol)
{
	for ($i = 0; $i < strlen($symbol); ++$i) {
		if ($c->c != $symbol[$i]) {
			throw new syntax_exception("Expecting '$symbol' found '$c'", $c);
		}

		$c->next();
	}

	return $symbol;
}

// ---------------------------------------------------------------------------------
// Entity parsers
// ---------------------------------------------------------------------------------

/**
 * attribute: identifier [ '=' (string | [^\s]+) ]
 */
function parse_attribute (dom_stream $c)
{
	parse_whitespace($c);

	$name = parse_identifier($c);

	parse_whitespace($c);

	if ($c != '=') {
		// empty attribute
		return new dom_tag_attribute($name);
	}

	$c->next(); // skip '='

	parse_whitespace($c);

	if ($c == '"' || $c == '\'') {

		$q = $c->c;
		$value = parse_string($c);
		
		return new dom_tag_attribute($name, $q.$value.$q);
	}

	// unquoted value attribute

	if ($c == '=' || $c == '>' || $c == '<' || $c == '`') {
		throw new syntax_exception("Expecting unquoted value for attribute '$name' found illegal character '$c'", $c);
	}

	$value = '';
	while (!ctype_space($c->c) && $c != '/' && $c != '=' && $c != '>' && $c != '<' && $c != '`' && !$c->eof()) {
		$value .= $c;
		$c->next();
	}

	if (strlen($value) == 0) {
		throw new syntax_exception("Illegal or missing value for attribute '$name'", $c);
	}

	return new dom_tag_attribute($name, $value);
}

/**
 * start_tag: '<' tag_name attribute* [ '/' ] '>'
 */
function parse_start_tag (dom_stream $c)
{
	parse_literal($c, '<');

	$tag = parse_tag_name($c);

	$attr = [];
	while($c != '<' && $c != '/' && $c != '>' && !$c->eof()) {
		$a = parse_attribute($c);
		if (in_array(strtolower($a->name), $attr)) {
			throw new syntax_exception("Attribute '{$a->name}' already defined", $c);
		}
		$attr[strtolower($a->name)] = $a;
		parse_whitespace($c);
	}

	// if ($c == '<') {	// might be PHP

	// }

	if ($c == '/') {
		if (!is_void_element($tag)) {
			throw new syntax_exception("Illegal '/' character for non-void element tag", $c);
		}
		$c->next();
	}
	parse_literal($c, '>');

	return new dom_entity_start_tag($tag, $attr);
}

/**
 * end_tag: '</' tag_name '>'
 */
function parse_end_tag (dom_stream $c)
{
	parse_literal($c, '</');

	$tag = parse_tag_name($c);

	parse_whitespace($c);
	parse_literal($c, '>');

	return new dom_entity_end_tag($tag);
}

/**
 * comment: '<!--' comment_text '-->'
 */
function parse_comment (dom_stream $c)
{
	parse_literal($c, '<!--');

	$cdata = parse_cdata($c, '-->');

	parse_literal($c, '-->');

	return new dom_entity_comment($cdata);
}

/**
 * php: '<?' [ 'php' ] cdata '?>'
 */
function parse_php (dom_stream $c)
{
	parse_literal($c, '<?');

	if ($c->test('php')) {
		$c->skip(3);
	}

	$cdata = parse_cdata($c, '?>');

	parse_literal($c, '?>');

	return new dom_entity_php($cdata);
}

/**
 *	<!DOCTYPE {{attr_list}} >
 */
function parse_doctype (dom_stream $c)
{
	parse_literal($c, '<!');

	$dt = parse_identifier($c);

	if (strtoupper($dt) != 'DOCTYPE') {
		throw new syntax_exception("Expecting doctype decleration", $c);
	}

	parse_whitespace($c);

	$attr = [];
	while($c != '/' && $c != '>' && !$c->eof()) {

		$a = '';
		while(!ctype_space($c->c) && $c != '/' && $c != '>' && !$c->eof()) {

			$a.= $c;
			$c->next();
		}

		parse_whitespace($c);

		if ($c == '=') {
			$c->next();

			parse_whitespace($c);

			$val = '';
			while(!ctype_space($c->c) && $c != '/' && $c != '>' && !$c->eof()) {

				$val.= $c;
				$c->next();
			}

			$a = new dom_tag_attribute($a, $val);
		}

		$attr[] = $a;
	}

	if ($c == '/') {
		$c->next();
	}
	parse_literal($c, '>');

	return new dom_entity_doctype($attr);
}

/**
 * entity: doctype | start_tag | end_tag | cdata | text | comment | php
 */
function parse_entity (dom_stream $c)
{
	parse_whitespace($c);

	$c->bookmark();	// keep track of start of last parsed entity!

	if ($c == '<') {
		if ($c->nc == '!') {
			if (strtoupper($c->seek(2)) == 'D') {
				return parse_doctype($c);
			}
			return parse_comment($c);
		}
		if ($c->nc == '/') {
			return parse_end_tag($c);
		}
		if ($c->nc == '?') {
			return parse_php($c);
		}
		return parse_start_tag($c);
	}

	$text = parse_text($c);
	return new dom_entity(dom_entity::TEXT, $text);
}

// ---------------------------------------------------------------------------------
// High level grammar parsing
// ---------------------------------------------------------------------------------

// nodes: element (void or not) | php | comment | text

function parse_node (dom_stream $c, dom_node $parent, &$stray = [])
{
	/**
	 * Note: the top-most parser should know about syntactic constraints
	 * 	like appropriate child nodes belonging to specific parent nodes,
	 *	ex. table -> tr -> td. This knowlege would help fix forgotten closing
	 *	tags better...
	 */

	do
	{
		$entity = parse_entity($c);

		// START TAG
		if ($entity->type == dom_entity::START_TAG) {

			if (is_void_element($entity->tagName)) {
				$parent->add ( new dom_node_element($parent, $entity, dom_node_element::ELEMENT_VOID) );
			}

			else {

				if ($entity->tagName == 'script' || $entity->tagName == 'style') {
					$content = parse_cdata($c, '</'.$entity->tagName.'>');
					parse_entity($c); // end tag

					$parent->add ( new dom_node_element($parent, $entity, dom_node_element::ELEMENT_WITH_BODY, [$content]) );
				}

				else {
					$node = new dom_node_element($parent, $entity);
					parse_node($c, $node, $stray);
					$parent->add( $node );
				}
			}
		}

		// END TAG
		else if ($entity->type == dom_entity::END_TAG) {

			// 1. Check for misplaced/stray end tag
			if (@end($stray) == $entity->tagName) {

				warn("Found misplaced end tag </".end($stray)."> and fixed...", $c->bookmark);
				array_pop($stray);
				continue;
			}

			// 2. Check if corresponds to parent tag
			else if ($entity->tagName == @$parent->tagName) {

				return;
			}

			// 3. Walk up tree to find matching parent
			$p = $parent; $d = 1;
			while ($p->parent !== null) {
				if ( $entity->tagName == @$p->parent->tagName ) {
					
					// parent found assume current misplaced or missing end tag
					warn("End tag </$parent->tagName> missing or misplaced, was added.", $c->bookmark);

					// add tag name to stray tags list
					array_push($stray, $parent->tagName);

					$c->restore();
					return;
				}
				$p = $p->parent;
			}

			// 4. Is a stray tag with no previous parent, simply remove it
			warn("End tag </$entity->tagName> does not match any parent... was ignored.", $c->bookmark);
		}

		// OTHER KIND (php, text, comment ...)
		else {
			$parent->add ( new dom_node_html($parent, $entity) );
		}

	} while (!$c->eof());

}

// ---------------------------------------------------------------------------------
// High level dom parsing
// ---------------------------------------------------------------------------------

function dom_parse(string $input)
{
	$dom = new dom_node();

	try {

		$c = new dom_stream($input);

		parse_node($c, $dom);

	} catch (Exception $e) {
		print $e;
		return null;
	}

	return $dom;
}

function dom_parse_file($filepath)
{
	if (!file_exists($filepath)) {
		return null;
	}

	$input = file_get_contents($filepath);

	return dom_parse($input);
}
