<?php

namespace Shef\Options\Main\Options;

class Enum
	extends AOption
{
	protected int $showRows = 1;
	protected array $list = [];

	// region Get/Set ////
	public function setList(array $value): self
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

	public function setShowRows(int $value): self
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
}