<?php
declare(strict_types=1);

namespace Shef\Options\TraitList\UF;

use Bitrix\Main\Type\Dictionary;
use Bitrix\Main\Result;
use Shef\Options\Installator\UF;

/**
 * Interface IEntity
 * @package Shef\Options\TraitList\UF
 *
 * Интерфейс для получения UF
 */
interface IEntity
{
	/**
	 * Возвращает словарь Dictionary всех UF
	 *
	 * @return Dictionary[UF\Type\AUF]
	 */
	public function getUserFields(): Dictionary;

	/**
	 * Возвращает UF по названию из словаря Dictionary
	 *
	 * @param string $value
	 * @return UF\Type\AUF|null
	 */
	public function getUserFieldByName(string $value): ?UF\Type\AUF;
}