<?php

namespace Shef\Options\Main\Options;

class NumberInt
	extends Text
{
	protected int $min = 0;
	protected int $max = PHP_INT_MAX;
	protected int $step = 1;

	// region Get/Set ////
	public function setMin(int $value): self
	{
		$this->min = $value;
		return $this;
	}

	public function getMin(): int
	{
		return $this->min;
	}

	public function setMax(int $value): self
	{
		$this->max = $value;
		return $this;
	}

	public function getMax(): int
	{
		return $this->max;
	}

	public function setStep(int $value): self
	{
		$this->step = $value;
		return $this;
	}

	public function getStep(): int
	{
		return $this->step;
	}
	// endregion ////
}