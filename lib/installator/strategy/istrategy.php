<?php declare(strict_types=1);

namespace Shef\Options\Installator\Strategy;

use Bitrix\Main\Result;
use Shef\Options\Installator\IEntity;

/**
 * Интерфейс стратегии установки
 */
interface IStrategy
{
	public function process(IEntity $entity): Result;
}