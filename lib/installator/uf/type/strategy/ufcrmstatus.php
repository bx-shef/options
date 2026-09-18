<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

class UFCrmStatus
	extends AStrategy
	implements IStrategy
{
	public const Type = 'crm_status';
	
	/**
	 * @todo Реализовать логику
	 * @return array
	 * @throws \Exception
	 */
	public static function getSettings(): array
	{
		throw new \Exception('@todo');
	}
}