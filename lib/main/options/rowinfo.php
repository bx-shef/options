<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Shef\Options\Main\Utils;
	
/**
 * Вывод строки с текстом
 *
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
	
	public function __construct(string $code)
	{
		parent::__construct($code);

		\Bitrix\Main\UI\Extension::load(['ui.alerts']);
	}
	
	// region Get/Set ////
	public function getDescription(): string
	{
		return (string) Utils::getTextParserToHtml()
			?->convertText(
				parent::getDescription()
			)
		;
	}
	
	public function setType(TypeUIAlert $value): static
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

	public function setPadding(int $value): static
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
	
	// region Render /////
	protected function renderTitle(): string
	{
		return '';
	}
	
	protected function renderValue(string $moduleId): string
	{
		return sprintf(
			join('', [
				'<div class="ui-alert %s"><span class="ui-alert-message">',
					'%s',
				'</span></div>',
			]),
			$this->getType(),
			$this->getDescription()
		);
	}
	
	public function render(string $moduleId): string
	{
		return sprintf(
			'<td colspan="2" style="%s">%s</td>',
			$this->getPaddingStyle(),
			$this->renderValue($moduleId),
		);
	}
	// endregion ////
}