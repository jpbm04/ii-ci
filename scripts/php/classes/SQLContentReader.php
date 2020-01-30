<?php
class SQLContentReader
{
	private $content;

	public function __construct($fileContents)
	{
		$this->content = $fileContents;
	}

	public function content()
	{
		$statements = preg_split("@[\r\n]{1,2}@", $this->content);

		foreach ($statements as $i => &$stmt)
		{
			$stmt = trim($stmt);
			$this->filterEmptyLines($statements, $stmt, $i);
			$this->filterComment($statements, $stmt, $i);
		}

		return $this->discardIndices($statements);
	}

	private function filterEmptyLines(array &$statements, $stmt, $i)
	{
		if (empty($stmt))
		{
			unset($statements[$i]);
		}
	}

	private function filterComment(array &$statements, $stmt, $i)
	{
		if (preg_match("@(^--)|(^#)@", $stmt))
		{
			unset($statements[$i]);
		}
	}

	private function discardIndices(array $statements)
	{
		return array_values($statements);
	}
}