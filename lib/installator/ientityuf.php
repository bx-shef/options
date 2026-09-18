<?php declare(strict_types=1);

namespace Shef\Options\Installator;

use Bitrix\Main\Type\Dictionary;

/**
 * Интерфейс UF сущности
 *
 * @see \Shef\Options\Installator\Trait\EntityUfTrait
 */
interface IEntityUf
{
	/**
	 * Возвращает словарь Dictionary всех UF
	 *
	 * @return Dictionary
	 */
	public function getUserFields(): Dictionary;

	/**
	 * Возвращает UF по названию из словаря Dictionary
	 *
	 * @param string $code
	 * @return null|Entity\UF\AEntity
	 */
	public function getUserFieldByCode(string $code): null|Entity\UF\AEntity;
}