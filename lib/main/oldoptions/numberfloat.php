<?php

namespace Shef\Options\Main\Options;

class NumberFloat
	extends Text
{
	protected float $min = 0;
	protected float $max = PHP_FLOAT_MAX;
	protected float $step = 0.1;

	// region Get/Set ////
	public function setMin(float $value): self
	{
		$this->min = $value;
		return $this;
	}

	public function getMin(): int
	{
		return $this->min;
	}

	public function setMax(float $value): self
	{
		$this->max = $value;
		return $this;
	}

	public function getMax(): float
	{
		return $this->max;
	}

	public function setStep(float $value): self
	{
		$this->step = $value;
		return $this;
	}

	public function getStep(): float
	{
		return $this->step;
	}
	// endregion ////
}