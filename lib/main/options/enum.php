<?php declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод перечисления
 */
class Enum
	extends AOption
{
	protected int $showRows = 1;
	protected array $list = [];

	// region Get/Set ////
	public function setList(array $value): static
	{
		$this->list = $value;
		return $this;
	}

	public function getList(): array
	{
		return $this->list;
	}
	
	public function getListForSelectBox(): array
	{
		$value = $this->getList();
		return [
			'REFERENCE' => array_values($value),
			'REFERENCE_ID' => array_keys($value)
		];
	}

	public function setShowRows(int $value): static
	{
		$this->showRows = $value;
		return $this;
	}

	public function getShowRows(): int
	{
		return $this->showRows;
	}

	public function isMultiple(): bool
	{
		return $this->showRows > 1;
	}
	// endregion ////
	
	// region Render /////
	protected function renderValue(string $moduleId): string
	{
		$value = $this->getInputValue($moduleId);
		if($this->isMultiple())
		{
			$value = htmlspecialcharsback($value);
			$value = unserialize($value);
			if(!is_array($value))
			{
				$value = [];
			}

			$result = SelectBoxMFromArray(
				$this->getInputName().'[]',
				$this->getListForSelectBox(),
				$value,
				'',
				false,
				$this->getShowRows(),
				'class="input-select input-select-multiple"'
			);
		}
		else
		{
			$result = SelectBoxFromArray(
				$this->getInputName(),
				$this->getListForSelectBox(),
				$value,
				'',
				'class="input-select"',
				false
			);
		}
		
		return $result;
	}
	// endregion ////
}