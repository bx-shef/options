<?php

namespace Shef\Options\Main\Options;

class Text
	extends AOption
{
	protected int $size = 15;

	// region Get/Set ////
	public function setSize(int $value): self
	{
		$this->size = $value;
		return $this;
	}

	public function getSize(): int
	{
		return $this->size;
	}
	// endregion ////
}