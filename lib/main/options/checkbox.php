<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Вывод чекбокса
 */
class Checkbox
	extends AOption
{
	// region Get/Set ////
	public function getInputValue(string $moduleId): string
	{
		return parent::getInputValue($moduleId);
	}
	
	public function getValueY(): string
	{
		return 'Y';
	}
	
	public function getValueN(): string
	{
		return 'N';
	}
	// endregion ////
	
	
	// region Render /////
	protected function renderValue(string $moduleId): string
	{
		return sprintf(
			'<input
				type="hidden"
				name="%s"
				value="%s"
			>
			<input
				type="checkbox"
				name="%s"
				id="%s"
				value="%s"
				%s
			>',
			$this->getInputName(),
			$this->getValueN(),
			$this->getInputName(),
			$this->getInputId(),
			$this->getValueY(),
			($this->getInputValue($moduleId) === $this->getValueY()
				? 'checked'
				: ''
			)
		);
	}
	// endregion ////
}