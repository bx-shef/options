<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

/**
 * Интерфейс стратегии подготовки данных к установке
 */
interface IStrategy
{
	/**
	 * Возвращает тип UF
	 *
	 * @return UF\EType
	 */
	public function getType(): UF\EType;
	
	/**
	 * Возвращает массив для внесения в БД
	 *
	 * @param UF\AEntity $field
	 * @return array
	 */
	public function process(UF\AEntity $field): array;
}