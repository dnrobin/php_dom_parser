<?php

/**
 * Wrapper class for the input string to be parsed.
 *
 * This class is extremely helpful for keep track of current position
 * in string as well as current line and column numbers for debugging
 * error messages. It is also very helpful in providing bookmarking
 * for backtracking when necessary.
 */
class dom_stream
{
	/**
	 * Current character at offset in stream
	 *
	 * @var char
	 */
	public $c;

	/**
	 * Single look ahead character
	 *
	 * @var char
	 */
	public $nc;

	function __construct(string $input)
	{
		$this->input = $input."\0";
		$this->len = strlen($this->input);

		$this->c = $this->input[0];
		$this->nc = @$this->input[1] ?: "\0";

		$this->last_nl = 0;
		$this->line_no = 1;
		$this->col_no = 1;
		$this->ofs = 0;
	}

	/**
	 * @var string
	 */
	private $input;

	/**
	 * @var int
	 */
	private $len;

	/**
	 * @var int
	 */
	private $ofs;

	/**
	 * @var int
	 */
	private $last_nl;

	/**
	 * @var string
	 */
	private $line_no;

	/**
	 * @var string
	 */
	private $col_no;

	function get_pos()
	{
		return [$this->line_no, $this->col_no, $this->ofs];
	}

	function seek(int $ofs)
	{
		if ($this->ofs + $ofs >= $this->len) {
			return "\0";
		}

		return $this->input[$ofs];
	}

	function next()
	{
		if ($this->ofs + 1 >= $this->len) {
			return $this->c;
		}

		$ofs = $this->ofs;

		$this->ofs++;
		$this->col_no++;

		// update coordinates
		if ($this->input[$this->ofs] == "\r" || $this->input[$this->ofs] == "\n") {
			if ($this->input[$this->ofs] == "\r" || $this->input[$this->ofs] == "\n") {
				$this->ofs++;
			}

			$this->last_nl = $this->ofs;
			$this->line_no++;
			$this->col_no = 1;
		}

		$this->c = $this->input[$this->ofs];
		$this->nc = @$this->input[$this->ofs + 1] ?: "\0";

		return substr($this->input, $ofs, $this->ofs - $ofs);
	}

	function skip(int $ofs)
	{
		if ($this->ofs + $ofs >= $this->len) {
			$ofs = $this->len - $this->ofs - 1;
		}

		for ($i=0; $i < $ofs; $i++) { 
			$this->next();
		}

		return $this->c;
	}

	function bookmark()
	{
		$this->bookmark = clone $this;
	}

	function restore()
	{
		$this->ofs = $this->bookmark->ofs;
		$this->last_nl = $this->bookmark->last_nl;
		$this->line_no = $this->bookmark->line_no;
		$this->col_no = $this->bookmark->col_no;
		$this->c = $this->bookmark->c;
		$this->nc = $this->bookmark->nc;
	}

	function test(string $seq)
	{
		return stripos(substr($this->input, $this->ofs), $seq) === 0;
	}

	function eof()
	{
		return $this->c === "\0";
	}

	function get_line()
	{
		$next_nl = stripos($this->input, "\n", $this->last_nl);
		return substr($this->input, $this->last_nl, ($next_nl !== false ? $next_nl - $this->last_nl : -1))."\n";
	}

	function __toString() {
		return $this->c;
	}
}