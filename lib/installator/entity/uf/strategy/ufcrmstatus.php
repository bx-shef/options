<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

class UFCrmStatus
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::CrmStatus;
	}
	
	/**
	 * @inheritDoc
	 *
	 * @throws \Exception
	 *
	 * @todo Реализовать
	 */
	public static function getSettings(): array
	{
		throw new \Exception('@todo');
	}
}