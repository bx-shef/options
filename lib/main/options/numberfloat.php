<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод дробного числа
 */
class NumberFloat
	extends Text
{
	protected float $min = 0;
	protected float $max = PHP_FLOAT_MAX;
	protected float $step = 0.1;

	// region Get/Set ////
	public function setMin(float $value): static
	{
		$this->min = $value;
		return $this;
	}

	public function getMin(): float
	{
		return $this->min;
	}

	public function setMax(float $value): static
	{
		$this->max = $value;
		return $this;
	}

	public function getMax(): float
	{
		return $this->max;
	}

	public function setStep(float $value): static
	{
		$this->step = $value;
		return $this;
	}

	public function getStep(): float
	{
		return $this->step;
	}
	// endregion ////
	
	// region Render /////
	protected function renderValue(string $moduleId): string
	{
		return sprintf(
			'<input
				type="number"
				min="%s"
				max="%s"
				step="%s"
				size="%s"
				name="%s"
				id="%s"
				value="%s"
			>',
			$this->getMin(),
			$this->getMax(),
			$this->getStep(),
			$this->getSize(),
			$this->getInputName(),
			$this->getInputId(),
			$this->getInputValue($moduleId)
		);
	}
	// endregion ////
}