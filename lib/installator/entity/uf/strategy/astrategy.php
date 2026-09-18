<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Bitrix\Main\Engine\Response\Converter;
use Shef\Options\Installator\Entity\UF;

/**
 * Абстракция для стратегии подготовки данных к установке
 */
abstract class AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	abstract public function getType(): UF\EType;
	
	/**
	 * @inheritDoc
	 */
	public function process(UF\AEntity $field): array
	{
		$converter = new Converter(Converter::TO_UPPER | Converter::KEYS | Converter::TO_SNAKE);
		
		return [
			'entityId' => $field->getEntityId(),
			'fieldName' => $field->getCode(),
			'userTypeId' => $this->getType()?->value,
			'xmlId' => $field->getCode(),
			'sort' => $field->getSort(),
			'multiple' => $field->isMultiple() ? 'Y' : 'N',
			'mandatory' => $field->isRequired() ? 'Y' : 'N',
			'showFilter' => $field->isShowFilter() ? 'Y' : 'N',
			'showInList' => $field->isShowInList() ? 'Y' : 'N',
			'editInList' => $field->isEditable() ? 'Y' : 'N',
			'isSearchable' => $field->isSearchable() ? 'Y' : 'N',
			'settings' => $converter->process(static::getSettings()),
			'editFormLabel' => $field->getEditFormInfo(),
		];
	}
	
	/**
	 * Возвращает расширенные настройки UF
	 *
	 * @return array
	 */
	public static function getSettings(): array
	{
		return [];
	}
}