<?php

namespace Shef\Options\Main\Options;

class TextArea
	extends AOption
{
	protected int $rows = 5;
	protected int $cols = 15;

	// region Get/Set ////
	public function setRows(int $value): self
	{
		$this->rows = $value;
		return $this;
	}

	public function getRows(): int
	{
		return $this->rows;
	}

	public function setCols(int $value): self
	{
		$this->cols = $value;
		return $this;
	}

	public function getCols(): int
	{
		return $this->cols;
	}
	// endregion ////
}