<?php

namespace Shef\Options\Main\Options;

/**
 * Class RowInfo
 * @package Shef\Options\Main\Options
 *
 * @url www\local\components\bitrix\system.show_message\component.php
 *
 */
class RowInfo
	extends AOption
{
	protected TypeUIAlert $type = TypeUIAlert::Note;
	protected int $padding = 0;
	
	public function __construct(Tab $tab, string $code)
	{
		parent::__construct($tab, $code);

		\Bitrix\Main\UI\Extension::load(['ui.alerts']);
	}
	
	// region Get/Set ////
	public function setType(TypeUIAlert $value): self
	{
		$this->type = $value;
		return $this;
	}

	public function getType(): string
	{
		return $this->type->value;
	}

	public function getTypeEnum(): TypeUIAlert
	{
		return $this->type;
	}

	public function setPadding(int $value): self
	{
		$this->padding = $value;
		return $this;
	}

	public function getPadding(): int
	{
		return $this->padding;
	}

	public function getPaddingStyle(): string
	{
		$value = $this->getPadding();

		return ($value > 0
			? 'padding-left: '.$value.'px'
			: ''
		);
	}
	// endregion ////
}