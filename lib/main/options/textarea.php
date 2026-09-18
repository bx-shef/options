<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод textarea
 */
class TextArea
	extends AOption
{
	protected int $rows = 5;
	protected int $cols = 15;

	// region Get/Set ////
	public function setRows(int $value): static
	{
		$this->rows = $value;
		return $this;
	}

	public function getRows(): int
	{
		return $this->rows;
	}

	public function setCols(int $value): static
	{
		$this->cols = $value;
		return $this;
	}

	public function getCols(): int
	{
		return $this->cols;
	}
	// endregion ////
	// region Render /////
	protected function renderValue(string $moduleId): string
	{
		return sprintf(
			'<textarea
				rows="%s"
				cols="%s"
				name="%s"
				id="%s"
			>%s</textarea>',
			$this->getRows(),
			$this->getCols(),
			$this->getInputName(),
			$this->getInputId(),
			$this->getInputValue($moduleId)
		);
	}
	// endregion ////
}