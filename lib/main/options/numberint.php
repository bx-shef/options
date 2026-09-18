<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод целого числа
 */
class NumberInt
	extends Text
{
	protected int $min = 0;
	protected int $max = PHP_INT_MAX;
	protected int $step = 1;

	// region Get/Set ////
	public function setMin(int $value): static
	{
		$this->min = $value;
		return $this;
	}

	public function getMin(): int
	{
		return $this->min;
	}

	public function setMax(int $value): static
	{
		$this->max = $value;
		return $this;
	}

	public function getMax(): int
	{
		return $this->max;
	}

	public function setStep(int $value): static
	{
		$this->step = $value;
		return $this;
	}

	public function getStep(): int
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