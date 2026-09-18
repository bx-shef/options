<?php

namespace Shef\Options\Main\Options;

abstract class AOption
{
	protected ?Tab $tab = null;
	protected string $code = '';
	protected string $title = '';
	protected string $description = '';

	public function __construct(Tab $tab, string $code)
	{
		$this->tab = $tab;
		$this->code = $code;
	}

	// region Get/Set ////
	public function getTab(): Tab
	{
		return $this->tab;
	}

	public function getCode(): string
	{
		return $this->code;
	}

	public function getComplexCode(): string
	{
		return implode('_', [
			$this->getTab()->getCode(),
			$this->getCode()
		]);
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

	public function setDefValue(string $value): self
	{
		return $this;
	}

	public function getDefValue(): string
	{
		return '';
	}
	// endregion ////
}