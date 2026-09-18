<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод строки
 */
class Text
	extends AOption
{
	protected int $size = 15;

	// region Get/Set ////
	public function setSize(int $value): static
	{
		$this->size = $value;
		return $this;
	}

	public function getSize(): int
	{
		return $this->size;
	}
	// endregion ////
	
	// region Render /////
	protected function renderValue(string $moduleId): string
	{
		return sprintf(
			'<input
				type="text"
				size="%s"
				name="%s"
				id="%s"
				value="%s"
			>',
			$this->getSize(),
			$this->getInputName(),
			$this->getInputId(),
			$this->getInputValue($moduleId)
		);
	}
	// endregion ////
}