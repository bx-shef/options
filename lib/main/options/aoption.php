<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\Config;

/**
 * Абстракция опции настроек
 */
abstract class AOption
{
	protected ?Tab $tab = null;
	protected string $code = '';
	protected string $title = '';
	protected string $description = '';
	protected string $defValue = '';

	public function __construct(string $code)
	{
		$this->code = $code;
	}

	// region Get/Set ////
	public function setTab(Tab $value): static
	{
		$this->tab = $value;
		return $this;
	}
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

	public function setTitle(string $value): static
	{
		$this->title = $value;
		return $this;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function setDescription(string $value): static
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

	public function setDefValue(string $value): static
	{
		$this->defValue = $value;
		return $this;
	}

	public function getDefValue(): string
	{
		return (string)$this->defValue;
	}
	// endregion ////
	
	// region Render ////
	protected function getInputName(): string
	{
		return htmlspecialcharsbx('param_'.$this->getComplexCode());
	}
	
	protected function getInputId(): string
	{
		return htmlspecialcharsbx('param_'.$this->getComplexCode());
	}
	
	protected function getInputValue(string $moduleId): string
	{
		return htmlspecialcharsbx(Config\Option::get(
			$moduleId,
			$this->getComplexCode(),
			$this->getDefValue()
		));
	}
	
	protected function renderTitle(): string
	{
		return sprintf(
			'<label for="%s">%s</label>%s',
			$this->getInputName(),
			$this->getTitle(),
			(
			$this->isDescription()
				? sprintf(
				'<br><small>%s</small>',
				$this->getDescription()
			)
				: ''
			),
		);
	}
	
	abstract protected function renderValue(string $moduleId): string;
	
	public function render(string $moduleId): string
	{
		return sprintf(
			join('', [
				'<td style="width: 40%%; vertical-align: top;">%s</td>',
				'<td style="width: 60%%; vertical-align: top;">%s</td>'
			]),
			$this->renderTitle(),
			$this->renderValue($moduleId)
		);
	}
	// endregion ////
}