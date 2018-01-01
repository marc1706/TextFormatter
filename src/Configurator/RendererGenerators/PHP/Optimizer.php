<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2018 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\RendererGenerators\PHP;

/**
* This class optimizes the code produced by the PHP renderer. It is not meant to be used on general
* purpose code
*/
class Optimizer
{
	/**
	* @var BranchOutputOptimizer
	*/
	public $branchOutputOptimizer;

	/**
	* @var integer Number of tokens in $this->tokens
	*/
	protected $cnt;

	/**
	* @var integer Current token index
	*/
	protected $i;

	/**
	* @var integer Maximum number iterations over the optimization passes
	*/
	public $maxLoops = 10;

	/**
	* @var array Array of tokens from token_get_all()
	*/
	protected $tokens;

	/**
	* Constructor
	*/
	public function __construct()
	{
		$this->branchOutputOptimizer = new BranchOutputOptimizer;
	}

	/**
	* Optimize the code generated by the PHP renderer generator
	*
	* @param  string $php Original code
	* @return string      Optimized code
	*/
	public function optimize($php)
	{
		$this->tokens = token_get_all('<?php ' . $php);
		$this->cnt    = count($this->tokens);
		$this->i      = 0;

		// Remove line numbers from tokens
		foreach ($this->tokens as &$token)
		{
			if (is_array($token))
			{
				unset($token[2]);
			}
		}
		unset($token);

		// Optimization passes, in order of execution
		$passes = [
			'optimizeOutConcatEqual',
			'optimizeConcatenations',
			'optimizeHtmlspecialchars'
		];

		// Limit the number of loops, in case something would make it loop indefinitely
		$remainingLoops = $this->maxLoops;
		do
		{
			$continue = false;

			foreach ($passes as $pass)
			{
				// Run the pass
				$this->$pass();

				// If the array was modified, reset the keys and keep going
				$cnt = count($this->tokens);
				if ($this->cnt !== $cnt)
				{
					$this->tokens = array_values($this->tokens);
					$this->cnt    = $cnt;
					$continue     = true;
				}
			}
		}
		while ($continue && --$remainingLoops);

		// Optimize common output expressions in if-else-elseif conditionals
		$php = $this->branchOutputOptimizer->optimize($this->tokens);

		// Reclaim some memory
		unset($this->tokens);

		return $php;
	}

	/**
	* Test whether current token is between two htmlspecialchars() calls
	*
	* @return bool
	*/
	protected function isBetweenHtmlspecialcharCalls()
	{
		return ($this->tokens[$this->i + 1]    === [T_STRING, 'htmlspecialchars']
		     && $this->tokens[$this->i + 2]    === '('
		     && $this->tokens[$this->i - 1]    === ')'
		     && $this->tokens[$this->i - 2][0] === T_LNUMBER
		     && $this->tokens[$this->i - 3]    === ',');
	}

	/**
	* Test whether current token is at the beginning of an htmlspecialchars()-safe var
	*
	* Tests whether current var is either $node->localName or $node->nodeName
	*
	* @return bool
	*/
	protected function isHtmlspecialcharSafeVar()
	{
		return ($this->tokens[$this->i    ]    === [T_VARIABLE,        '$node']
		     && $this->tokens[$this->i + 1]    === [T_OBJECT_OPERATOR, '->']
		     && ($this->tokens[$this->i + 2]   === [T_STRING,          'localName']
		      || $this->tokens[$this->i + 2]   === [T_STRING,          'nodeName'])
		     && $this->tokens[$this->i + 3]    === ','
		     && $this->tokens[$this->i + 4][0] === T_LNUMBER
		     && $this->tokens[$this->i + 5]    === ')');
	}

	/**
	* Test whether the cursor is at the beginning of an output assignment
	*
	* @return bool
	*/
	protected function isOutputAssignment()
	{
		return ($this->tokens[$this->i    ] === [T_VARIABLE,        '$this']
		     && $this->tokens[$this->i + 1] === [T_OBJECT_OPERATOR, '->']
		     && $this->tokens[$this->i + 2] === [T_STRING,          'out']
		     && $this->tokens[$this->i + 3] === [T_CONCAT_EQUAL,    '.=']);
	}

	/**
	* Test whether the cursor is immediately after the output variable
	*
	* @return bool
	*/
	protected function isPrecededByOutputVar()
	{
		return ($this->tokens[$this->i - 1] === [T_STRING,          'out']
		     && $this->tokens[$this->i - 2] === [T_OBJECT_OPERATOR, '->']
		     && $this->tokens[$this->i - 3] === [T_VARIABLE,        '$this']);
	}

	/**
	* Merge concatenated htmlspecialchars() calls together
	*
	* Must be called when the cursor is at the concatenation operator
	*
	* @return bool Whether calls were merged
	*/
	protected function mergeConcatenatedHtmlSpecialChars()
	{
		if (!$this->isBetweenHtmlspecialcharCalls())
		{
			 return false;
		}

		// Save the escape mode of the first call
		$escapeMode = $this->tokens[$this->i - 2][1];

		// Save the index of the comma that comes after the first argument of the first call
		$startIndex = $this->i - 3;

		// Save the index of the parenthesis that follows the second htmlspecialchars
		$endIndex = $this->i + 2;

		// Move the cursor to the first comma of the second call
		$this->i = $endIndex;
		$parens = 0;
		while (++$this->i < $this->cnt)
		{
			if ($this->tokens[$this->i] === ',' && !$parens)
			{
				break;
			}

			if ($this->tokens[$this->i] === '(')
			{
				++$parens;
			}
			elseif ($this->tokens[$this->i] === ')')
			{
				--$parens;
			}
		}

		if ($this->tokens[$this->i + 1] !== [T_LNUMBER, $escapeMode])
		{
			return false;
		}

		// Replace the first comma of the first call with a concatenator operator
		$this->tokens[$startIndex] = '.';

		// Move the cursor back to the first comma then advance it and delete everything up to the
		// parenthesis of the second call, included
		$this->i = $startIndex;
		while (++$this->i <= $endIndex)
		{
			unset($this->tokens[$this->i]);
		}

		return true;
	}

	/**
	* Merge concatenated strings together
	*
	* Must be called when the cursor is at the concatenation operator
	*
	* @return bool Whether strings were merged
	*/
	protected function mergeConcatenatedStrings()
	{
		if ($this->tokens[$this->i - 1][0]    !== T_CONSTANT_ENCAPSED_STRING
		 || $this->tokens[$this->i + 1][0]    !== T_CONSTANT_ENCAPSED_STRING
		 || $this->tokens[$this->i - 1][1][0] !== $this->tokens[$this->i + 1][1][0])
		{
			return false;
		}

		// Merge both strings into the right string
		$this->tokens[$this->i + 1][1] = substr($this->tokens[$this->i - 1][1], 0, -1)
		                               . substr($this->tokens[$this->i + 1][1], 1);

		// Unset the tokens that have been optimized away
		unset($this->tokens[$this->i - 1]);
		unset($this->tokens[$this->i]);

		// Advance the cursor
		++$this->i;

		return true;
	}

	/**
	* Optimize T_CONCAT_EQUAL assignments in an array of PHP tokens
	*
	* Will only optimize $this->out.= assignments
	*
	* @return void
	*/
	protected function optimizeOutConcatEqual()
	{
		// Start at offset 4 to skip the first four tokens: <?php $this->out.=
		$this->i = 3;

		while ($this->skipTo([T_CONCAT_EQUAL, '.=']))
		{
			// Test whether this T_CONCAT_EQUAL is preceded with $this->out
			if (!$this->isPrecededByOutputVar())
			{
				 continue;
			}

			while ($this->skipPast(';'))
			{
				// Test whether the assignment is followed by another $this->out.= assignment
				if (!$this->isOutputAssignment())
				{
					 break;
				}

				// Replace the semicolon between assignments with a concatenation operator
				$this->tokens[$this->i - 1] = '.';

				// Remove the following $this->out.= assignment and move the cursor past it
				unset($this->tokens[$this->i++]);
				unset($this->tokens[$this->i++]);
				unset($this->tokens[$this->i++]);
				unset($this->tokens[$this->i++]);
			}
		}
	}

	/**
	* Optimize concatenations in an array of PHP tokens
	*
	* - Will precompute the result of the concatenation of constant strings
	* - Will replace the concatenation of two compatible htmlspecialchars() calls with one call to
	*   htmlspecialchars() on the concatenation of their first arguments
	*
	* @return void
	*/
	protected function optimizeConcatenations()
	{
		$this->i = 1;
		while ($this->skipTo('.'))
		{
			$this->mergeConcatenatedStrings() || $this->mergeConcatenatedHtmlSpecialChars();
		}
	}

	/**
	* Optimize htmlspecialchars() calls
	*
	* - The result of htmlspecialchars() on literals is precomputed
	* - By default, the generator escapes all values, including variables that cannot contain
	*   special characters such as $node->localName. This pass removes those calls
	*
	* @return void
	*/
	protected function optimizeHtmlspecialchars()
	{
		$this->i = 0;

		while ($this->skipPast([T_STRING, 'htmlspecialchars']))
		{
			if ($this->tokens[$this->i] === '(')
			{
				++$this->i;
				$this->replaceHtmlspecialcharsLiteral() || $this->removeHtmlspecialcharsSafeVar();
			}
		}
	}

	/**
	* Remove htmlspecialchars() calls on variables that are known to be safe
	*
	* Must be called when the cursor is at the first argument of the call
	*
	* @return bool Whether the call was removed
	*/
	protected function removeHtmlspecialcharsSafeVar()
	{
		if (!$this->isHtmlspecialcharSafeVar())
		{
			 return false;
		}

		// Remove the htmlspecialchars() call, except for its first argument
		unset($this->tokens[$this->i - 2]);
		unset($this->tokens[$this->i - 1]);
		unset($this->tokens[$this->i + 3]);
		unset($this->tokens[$this->i + 4]);
		unset($this->tokens[$this->i + 5]);

		// Move the cursor past the call
		$this->i += 6;

		return true;
	}

	/**
	* Precompute the result of a htmlspecialchars() call on a string literal
	*
	* Must be called when the cursor is at the first argument of the call
	*
	* @return bool Whether the call was replaced
	*/
	protected function replaceHtmlspecialcharsLiteral()
	{
		// Test whether a constant string is being escaped
		if ($this->tokens[$this->i    ][0] !== T_CONSTANT_ENCAPSED_STRING
		 || $this->tokens[$this->i + 1]    !== ','
		 || $this->tokens[$this->i + 2][0] !== T_LNUMBER
		 || $this->tokens[$this->i + 3]    !== ')')
		{
			return false;
		}

		// Escape the content of the T_CONSTANT_ENCAPSED_STRING token
		$this->tokens[$this->i][1] = var_export(
			htmlspecialchars(
				stripslashes(substr($this->tokens[$this->i][1], 1, -1)),
				$this->tokens[$this->i + 2][1]
			),
			true
		);

		// Remove the htmlspecialchars() call, except for the T_CONSTANT_ENCAPSED_STRING token
		unset($this->tokens[$this->i - 2]);
		unset($this->tokens[$this->i - 1]);
		unset($this->tokens[++$this->i]);
		unset($this->tokens[++$this->i]);
		unset($this->tokens[++$this->i]);

		return true;
	}

	/**
	* Move the cursor past given token
	*
	* @param  array|string $token Target token
	* @return bool                Whether a matching token was found and the cursor is within bounds
	*/
	protected function skipPast($token)
	{
		return ($this->skipTo($token) && ++$this->i < $this->cnt);
	}

	/**
	* Move the cursor until it reaches given token
	*
	* @param  array|string $token Target token
	* @return bool                Whether a matching token was found
	*/
	protected function skipTo($token)
	{
		while (++$this->i < $this->cnt)
		{
			if ($this->tokens[$this->i] === $token)
			{
				return true;
			}
		}

		return false;
	}
}