<?php

namespace Shef\Options\Main\Options;

class Tab
{
	protected int $index = 0;
	protected string $code = '';
	protected string $name = '';
	protected string $title = '';
	protected string $icon = 'sale_settings';
	protected string $description = '';

	/** @var array|AOption[] */
	protected array $options = [];

	public function __construct(string $code, int $index)
	{
		$this->code = $code;
		$this->index = $index;
	}

	// region Get/Set ////
	public function getIndex(): string
	{
		return $this->index;
	}

	public function getCode(): string
	{
		return $this->code;
	}

	public function setName(string $value): self
	{
		$this->name = $value;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setTitle(string $value): self
	{
		$this->title = $value;
		return $this;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setIcon(string $value): self
	{
		$this->icon = $value;
		return $this;
	}

	public function getIcon(): string
	{
		return $this->icon;
	}

	public function getBitrixAdminArray(): array
	{
		return [
			'DIV' => 'edit'.$this->getIndex(),
			'TAB' => $this->getName(),
			'ICON' => $this->getIcon(),
			'TITLE' => $this->getTitle()
		];
	}

	public function setDescription(string $value): self
	{
		$this->description = $value;
		return $this;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function isDescription(): bool
	{
		return mb_strlen($this->getDescription()) > 0;
	}

	public function addOption(AOption $value): self
	{
		$this->options[] = $value;
		return $this;
	}

	/**
	 * @return array|AOption[]
	 */
	public function getOptionList(): array
	{
		return $this->options;
	}
	// endregion ////

	// region Tools ////
	/**
	 * @param array|Tab[] $list
	 * @return array
	 */
	public static function prepareListForBitrixAdminArray(array $list): array
	{
		$result = [];

		/** @var Tab $tab */
		foreach($list as $tab)
		{
			if(!($tab instanceof Tab))
			{
				continue;
			}

			$result[] = $tab->getBitrixAdminArray();
		}
		return $result;
	}
	// endregion ////
}